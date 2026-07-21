<?php
/**
 * The Email-Guard decision engine, ported from email-guard-core-php for
 * WordPress (PHP 7.4, no composer at runtime).
 *
 * @package EmailSherlock\EmailGuardWp
 */

namespace EmailSherlock\EmailGuardWp\Guard;

/**
 * Implements email-guard-spec 1.3.0. Local checks run first and cost
 * nothing. Only addresses that pass them escalate to the Verify API, and
 * only when an api_key is configured. The guard fails open by default: an
 * API outage never blocks a signup.
 */
class Guard {

	const VERSION      = '0.1.0';
	const SPEC_VERSION = '1.3.0';

	const DEFAULTS = array(
		'api_key'    => null,
		'block_on'   => array( 'invalid', 'disposable' ),
		'review_on'  => array(),
		'fail_open'  => true,
		'timeout_ms' => 800,
		'base_url'   => 'https://api.emailsherlock.com',
	);

	/** @var array Merged configuration (spec section 6.3 plus base_url). */
	private $config;

	/** @var TransportInterface */
	private $transport;

	/** @var DisposableSnapshot */
	private $snapshot;

	/**
	 * @param array                   $config    Keys as in spec section 6.3, plus base_url.
	 * @param TransportInterface|null $transport HTTP transport; defaults to WpTransport.
	 * @param DisposableSnapshot|null $snapshot  Disposable snapshot; defaults to the bundled one.
	 * @throws \InvalidArgumentException On unknown config keys.
	 */
	public function __construct( $config = array(), $transport = null, $snapshot = null ) {
		$unknown = array_diff_key( $config, self::DEFAULTS );
		if ( array() !== $unknown ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- developer-facing construction guard; static message plus config key names, never rendered to a visitor.
			throw new \InvalidArgumentException( 'Unknown config keys: ' . implode( ', ', array_keys( $unknown ) ) );
		}
		if ( isset( $config['api_key'] ) && '' === $config['api_key'] ) {
			// An empty option reads as "", not null. Treating it as a
			// configured key would send a doomed API call per check.
			$config['api_key'] = null;
		}
		$this->config    = array_merge( self::DEFAULTS, $config );
		$this->transport = null !== $transport ? $transport : new \EmailSherlock\EmailGuardWp\WpTransport();
		$this->snapshot  = null !== $snapshot ? $snapshot : new DisposableSnapshot();
	}

	/**
	 * Check one address and decide what the form should do with it.
	 *
	 * @param string $email Raw form input.
	 * @return Result
	 */
	public function check( $email ) {
		$address = trim( $email, " \t\r\n" );

		// Domain for telemetry (spec section 11): null when there is no @
		// (syntax failed). Reused by the reserved-TLD / disposable checks.
		$at_position = strrpos( $address, '@' );
		$domain      = false !== $at_position ? strtolower( substr( $address, $at_position + 1 ) ) : null;
		if ( '' === $domain ) {
			$domain = null;
		}

		$verdict = null;
		$reasons = array();

		if ( ! SyntaxChecker::is_valid( $address ) ) {
			$verdict = Verdict::INVALID;
			$reasons = array( Reason::BAD_SYNTAX );
		} elseif ( null !== $domain ) {
			if ( ReservedTld::is_reserved( $domain ) ) {
				$verdict = Verdict::INVALID;
				$reasons = array( Reason::RESERVED_TLD );
			} elseif ( $this->snapshot_says_disposable( $domain ) ) {
				$verdict = Verdict::DISPOSABLE;
				$reasons = array( Reason::DISPOSABLE_PROVIDER );
			}
		}

		$degraded     = false;
		$api_called   = false;
		$api_response = null;
		// Signals (spec 6.5): boolean response properties that overlay the
		// verdict. Only the remote check can set them; a local verdict or a
		// degraded check leaves relay false.
		$relay          = false;
		$relay_provider = null;

		if ( null === $verdict && null !== $this->config['api_key'] ) {
			$api_called = true;
			try {
				$api_response = $this->call_api( $address );
				$verdict      = Verdict::from_api( isset( $api_response['result'] ) ? $api_response['result'] : null );
				$reason       = isset( $api_response['reason'] ) ? $api_response['reason'] : null;
				if ( is_string( $reason ) && '' !== $reason ) {
					$reasons[] = $reason;
				}
				$relay    = ( isset( $api_response['relay'] ) ? $api_response['relay'] : null ) === true;
				$provider = isset( $api_response['relay_provider'] ) ? $api_response['relay_provider'] : null;
				if ( is_string( $provider ) && '' !== $provider ) {
					$relay_provider = $provider;
				}
			} catch ( TransportException $exception ) {
				$verdict = Verdict::UNKNOWN;
				// api_unavailable is the stable umbrella for any degraded
				// check (spec 5.2); the attributed cause rides after it so
				// telemetry can separate a slow API from an errored one.
				$reasons  = array( Reason::API_UNAVAILABLE );
				$granular = self::degraded_reason( $exception );
				if ( null !== $granular ) {
					$reasons[] = $granular;
				}
				$degraded = true;
			}
		}

		if ( null === $verdict ) {
			$verdict = Verdict::UNKNOWN;
		}

		// An active signal (true AND listed in block_on/review_on) appends
		// its reason so telemetry can attribute the decision, even when a
		// verdict independently produced the same action (spec 6.5).
		if ( $relay && $this->signal_listed( 'relay' ) ) {
			$reasons[] = Reason::RELAY;
		}

		return new Result(
			$verdict,
			$this->resolve_action( $verdict, $degraded, $relay ),
			$reasons,
			$degraded,
			$api_called,
			$api_response,
			$relay,
			$relay_provider,
			$domain
		);
	}

	/**
	 * A broken bundled snapshot must not take the form down: the guard's
	 * whole promise is that it never blocks or breaks a signup because of
	 * its own infrastructure. Skip the check, warn, let syntax,
	 * reserved-TLD and the API keep working.
	 *
	 * @param string $domain Lowercased domain part.
	 * @return bool
	 */
	private function snapshot_says_disposable( $domain ) {
		try {
			return $this->snapshot->is_disposable( $domain );
		} catch ( SnapshotException $exception ) {
			trigger_error( 'email-guard: disposable check skipped: ' . esc_html( $exception->getMessage() ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error -- deliberate: a broken snapshot is a packaging defect worth surfacing.

			return false;
		}
	}

	/**
	 * @param string $address Trimmed address.
	 * @return array Decoded API response.
	 * @throws TransportException On any failure that makes the check degraded.
	 */
	private function call_api( $address ) {
		$response = $this->transport->post(
			rtrim( $this->config['base_url'], '/' ) . '/v1/verify/single',
			array(
				'X-API-Key'    => (string) $this->config['api_key'],
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			(string) wp_json_encode( array( 'email' => $address ) ),
			(int) $this->config['timeout_ms']
		);

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal TransportException, caught by the guard's fail-open path, never rendered to a visitor.
		if ( $response->status_code < 200 || $response->status_code >= 300 ) {
			throw new TransportException(
				'Verify API answered HTTP ' . $response->status_code,
				TransportException::KIND_HTTP,
				$response->status_code
			);
		}
		$data = json_decode( $response->body, true );
		if ( ! is_array( $data ) ) {
			throw new TransportException(
				'Verify API answered with a non-JSON body',
				TransportException::KIND_BAD_RESPONSE
			);
		}

		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		return $data;
	}

	/**
	 * Maps a transport failure to its granular degraded reason (spec 5.2),
	 * or null when the cause cannot be attributed.
	 *
	 * @param TransportException $exception The transport failure.
	 * @return string|null
	 */
	private static function degraded_reason( TransportException $exception ) {
		switch ( $exception->kind ) {
			case TransportException::KIND_TIMEOUT:
				return Reason::API_TIMEOUT;
			case TransportException::KIND_CONNECTION:
				return Reason::API_UNREACHABLE;
			case TransportException::KIND_BAD_RESPONSE:
				return Reason::API_BAD_RESPONSE;
			case TransportException::KIND_HTTP:
				if ( null === $exception->status_code ) {
					return null;
				}
				if ( $exception->status_code >= 500 ) {
					return Reason::API_HTTP_5XX;
				}
				if ( $exception->status_code >= 400 ) {
					return Reason::API_HTTP_4XX;
				}
				return null;
			default:
				return null;
		}
	}

	/**
	 * Action resolution per spec section 6.4.
	 *
	 * @param string $verdict  Verdict constant.
	 * @param bool   $degraded Whether the check degraded.
	 * @param bool   $relay    The relay signal.
	 * @return string Action constant.
	 */
	private function resolve_action( $verdict, $degraded, $relay ) {
		if ( $degraded ) {
			// Degradation bypasses block_on/review_on entirely (spec 6.4):
			// blocking "unknown" is a statement about answered unknowns, not
			// about outages. fail_open alone decides here.
			return $this->config['fail_open'] ? Action::ALLOW : Action::DENY;
		}
		// A verdict OR an active relay signal can deny; deny beats review.
		if ( in_array( $verdict, $this->config['block_on'], true )
			|| ( $relay && in_array( 'relay', $this->config['block_on'], true ) )
		) {
			return Action::DENY;
		}
		if ( in_array( $verdict, $this->config['review_on'], true )
			|| ( $relay && in_array( 'relay', $this->config['review_on'], true ) )
		) {
			return Action::REVIEW;
		}

		return Action::ALLOW;
	}

	/**
	 * True when the named signal (spec 6.5) appears in block_on or review_on.
	 *
	 * @param string $signal Signal name.
	 * @return bool
	 */
	private function signal_listed( $signal ) {
		return in_array( $signal, $this->config['block_on'], true )
			|| in_array( $signal, $this->config['review_on'], true );
	}
}
