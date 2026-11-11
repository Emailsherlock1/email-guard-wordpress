<?php
/**
 * Decision telemetry emitter (email-guard-spec, section 11).
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Guard;

/**
 * Collects one event per guard decision and ships them, batched, to
 * POST /v1/guard/events on shutdown.
 *
 * The contract is the spec's: key-gated (no key, no telemetry), fail-silent
 * (a reporting failure never surfaces), domain only (never the address).
 * The optional property key (site key from the HostSherlock dashboard)
 * rides along as `property_key` so events attribute to the right property.
 */
class Reporter {

	const INTEGRATION = 'wordpress-plugin';

	/** @var string|null */
	private $api_key;

	/** @var string|null */
	private $property_key;

	/** @var string */
	private $base_url;

	/** @var int */
	private $timeout_ms;

	/** @var TransportInterface */
	private $transport;

	/** @var array[] */
	private $pending = array();

	/**
	 * @param string|null             $api_key      Account API key; null disables telemetry.
	 * @param string|null             $property_key Site key of the Guard property, optional.
	 * @param string                  $base_url     API base URL.
	 * @param int                     $timeout_ms   Time budget for the flush call.
	 * @param TransportInterface|null $transport    HTTP transport; defaults to a non-blocking WpTransport.
	 */
	public function __construct( $api_key, $property_key = null, $base_url = 'https://api.emailsherlock.com', $timeout_ms = 800, $transport = null ) {
		$this->api_key      = ( is_string( $api_key ) && '' !== $api_key ) ? $api_key : null;
		$this->property_key = ( is_string( $property_key ) && '' !== $property_key ) ? $property_key : null;
		$this->base_url     = $base_url;
		$this->timeout_ms   = $timeout_ms;
		$this->transport    = null !== $transport ? $transport : new \HostSherlock\EmailGuardWp\WpTransport( false );
	}

	/** @return bool */
	public function is_enabled() {
		return null !== $this->api_key;
	}

	/**
	 * Queue one decision. No-op without a key, so callers can record
	 * unconditionally. Stores only the domain, never the address.
	 *
	 * @param Result $result The guard decision.
	 */
	public function record( Result $result ) {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$event = array(
			'domain'       => $result->domain,
			'verdict'      => $result->verdict,
			'action'       => $result->action,
			'reasons'      => $result->reasons,
			'degraded'     => $result->degraded,
			'source'       => $result->api_called ? 'remote' : 'local',
			'integration'  => self::INTEGRATION,
			'lib_version'  => Guard::VERSION,
			'spec_version' => Guard::SPEC_VERSION,
		);
		if ( null !== $this->property_key ) {
			$event['property_key'] = $this->property_key;
		}

		$this->pending[] = $event;
	}

	/**
	 * Ship the queued events and clear the queue. Fire-and-forget: any
	 * transport failure is swallowed, the queue is cleared either way.
	 */
	public function flush() {
		if ( ! $this->is_enabled() || array() === $this->pending ) {
			return;
		}

		$events        = $this->pending;
		$this->pending = array();

		try {
			$this->transport->post(
				rtrim( $this->base_url, '/' ) . '/v1/guard/events',
				array(
					'X-API-Key'    => (string) $this->api_key,
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				(string) wp_json_encode( array( 'events' => $events ) ),
				$this->timeout_ms
			);
		} catch ( \Exception $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Fail-silent by contract: telemetry must never break or slow
			// the host. The events are already dropped from the queue.
		}
	}

	/**
	 * Events queued but not yet flushed (testing).
	 *
	 * @return array[]
	 */
	public function pending() {
		return $this->pending;
	}
}
