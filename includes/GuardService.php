<?php
/**
 * The glue between form adapters and the guard engine.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp;

use HostSherlock\EmailGuardWp\Guard\Guard;
use HostSherlock\EmailGuardWp\Guard\Reporter;

/**
 * One entry point for every adapter: check() runs the guard, writes the
 * local decision log, queues the telemetry event, and maps the spec action
 * (allow / deny / review) onto what a WordPress form can do (pass / reject
 * with message / pass but flag).
 */
class GuardService {

	/** @var Settings */
	private $settings;

	/** @var DecisionLog */
	private $log;

	/** @var Guard|null Lazily built from settings. */
	private $guard;

	/** @var Reporter|null Lazily built from settings. */
	private $reporter;

	/**
	 * @param Settings         $settings Plugin settings.
	 * @param DecisionLog|null $log      Decision log; built from settings when omitted.
	 * @param Guard|null       $guard    Guard engine override (tests).
	 * @param Reporter|null    $reporter Telemetry reporter override (tests).
	 */
	public function __construct( Settings $settings, $log = null, $guard = null, $reporter = null ) {
		$this->settings = $settings;
		$this->log      = null !== $log ? $log : new DecisionLog( $settings );
		$this->guard    = $guard;
		$this->reporter = $reporter;
	}

	/**
	 * Check one address on behalf of a form adapter.
	 *
	 * @param string $email   Raw form input.
	 * @param string $form_id Adapter id, for the log and per-form policy.
	 * @return Decision
	 */
	public function check( $email, $form_id ) {
		$email = is_string( $email ) ? trim( $email ) : '';
		if ( '' === $email ) {
			// Nothing to check. Required-field validation is the form's job.
			return new Decision( null, false, false );
		}

		$result = $this->guard()->check( $email );

		$this->log->add( $form_id, $result );
		$this->reporter()->record( $result );

		$denied  = $result->is_denied();
		$flagged = $result->needs_review();

		$message = '';
		if ( $denied ) {
			/**
			 * Filter the visitor-facing message for a denied address.
			 *
			 * @param string                                $message Message from settings.
			 * @param \HostSherlock\EmailGuardWp\Guard\Result $result  The guard decision.
			 * @param string                                $form_id Adapter id.
			 */
			$message = apply_filters(
				'hostsherlock_email_guard_denied_message',
				$this->settings->blocked_message(),
				$result,
				$form_id
			);
		}

		return new Decision( $result, $denied, $flagged, $message );
	}

	/**
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * @return DecisionLog
	 */
	public function decision_log() {
		return $this->log;
	}

	/**
	 * Ship queued telemetry. Hooked on shutdown; safe to call any time.
	 */
	public function flush_telemetry() {
		$this->reporter()->flush();
	}

	/**
	 * @return Guard
	 */
	private function guard() {
		if ( null === $this->guard ) {
			$this->guard = new Guard( $this->settings->guard_config() );
		}

		return $this->guard;
	}

	/**
	 * @return Reporter
	 */
	private function reporter() {
		if ( null === $this->reporter ) {
			$config         = $this->settings->guard_config();
			$this->reporter = new Reporter(
				$this->settings->api_key(),
				$this->settings->site_key(),
				$config['base_url'],
				$config['timeout_ms']
			);
		}

		return $this->reporter;
	}
}
