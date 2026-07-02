<?php
/**
 * Guards WooCommerce checkout and account registration.
 *
 * @package EmailSherlock\EmailGuardWp
 */

namespace EmailSherlock\EmailGuardWp\Adapters;

use EmailSherlock\EmailGuardWp\GuardService;

/**
 * Two surfaces, one adapter: the billing email at checkout
 * (woocommerce_after_checkout_validation) and the email on the account
 * registration form (woocommerce_registration_errors). Flagged addresses
 * pass; the decision lands in the log and telemetry.
 */
class WooCommerceAdapter implements AdapterInterface {

	/** @var GuardService */
	private $service;

	/**
	 * @return string
	 */
	public function id() {
		return 'woocommerce';
	}

	/**
	 * @return string
	 */
	public function label() {
		return __( 'WooCommerce checkout and registration', 'emailsherlock-email-guard' );
	}

	/**
	 * @return bool
	 */
	public function is_available() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * @param GuardService $service The shared guard service.
	 */
	public function register( GuardService $service ) {
		$this->service = $service;
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 20, 2 );
		add_filter( 'woocommerce_registration_errors', array( $this, 'filter_registration_errors' ), 20, 3 );
	}

	/**
	 * @param array     $data   Posted checkout data.
	 * @param \WP_Error $errors Checkout validation errors so far.
	 */
	public function validate_checkout( $data, $errors ) {
		$email    = isset( $data['billing_email'] ) ? (string) $data['billing_email'] : '';
		$decision = $this->service->check( $email, $this->id() );
		if ( $decision->is_denied() ) {
			$errors->add( 'emailsherlock_email_guard_denied', $decision->message(), array( 'id' => 'billing_email' ) );
		}
	}

	/**
	 * @param \WP_Error $errors   Registration errors so far.
	 * @param string    $username The submitted username.
	 * @param string    $email    The submitted email.
	 * @return \WP_Error
	 */
	public function filter_registration_errors( $errors, $username, $email ) {
		if ( $errors->get_error_code() ) {
			return $errors;
		}

		$decision = $this->service->check( (string) $email, $this->id() );
		if ( $decision->is_denied() ) {
			$errors->add( 'emailsherlock_email_guard_denied', $decision->message() );
		}

		return $errors;
	}
}
