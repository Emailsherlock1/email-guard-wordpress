<?php
/**
 * Guards the WordPress core registration form.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Adapters;

use HostSherlock\EmailGuardWp\GuardService;

/**
 * Hooks registration_errors: a denied address adds an error and the signup
 * never happens. A flagged address registers normally; the decision shows
 * up in the log and telemetry (WordPress has no pending-user state to park
 * it in).
 */
class RegistrationAdapter implements AdapterInterface {

	/** @var GuardService */
	private $service;

	/**
	 * @return string
	 */
	public function id() {
		return 'registration';
	}

	/**
	 * @return string
	 */
	public function label() {
		return __( 'User registration', 'hostsherlock-email-guard' );
	}

	/**
	 * @return bool Core surface, always present.
	 */
	public function is_available() {
		return true;
	}

	/**
	 * @param GuardService $service The shared guard service.
	 */
	public function register( GuardService $service ) {
		$this->service = $service;
		add_filter( 'registration_errors', array( $this, 'filter_errors' ), 20, 3 );
	}

	/**
	 * @param \WP_Error $errors               Registration errors so far.
	 * @param string    $sanitized_user_login The submitted login.
	 * @param string    $user_email           The submitted email.
	 * @return \WP_Error
	 */
	public function filter_errors( $errors, $sanitized_user_login, $user_email ) {
		// Core already complains about a missing/invalid email; do not stack
		// a second message on top of its own.
		if ( $errors->get_error_code() ) {
			return $errors;
		}

		$decision = $this->service->check( (string) $user_email, $this->id() );
		if ( $decision->is_denied() ) {
			$errors->add( 'hostsherlock_email_guard_denied', $decision->message() );
		}

		return $errors;
	}
}
