<?php
/**
 * Guards Elementor Pro form email fields.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Adapters;

use HostSherlock\EmailGuardWp\GuardService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks elementor_pro/forms/validation/email, which Elementor Pro fires once
 * per email field: a denied address goes into the AJAX handler's error bag
 * under the field id, which fails the submission and shows the message at
 * the field. A flagged one passes through untouched (the service already
 * logs it).
 */
class ElementorFormsAdapter implements AdapterInterface {

	/** @var GuardService */
	private $service;

	/**
	 * @return string
	 */
	public function id() {
		return 'elementor_forms';
	}

	/**
	 * @return string
	 */
	public function label() {
		return __( 'Elementor Forms', 'hostsherlock-email-guard' );
	}

	/**
	 * The form widget ships with Elementor Pro, not the free Elementor.
	 *
	 * @return bool Whether Elementor Pro is active.
	 */
	public function is_available() {
		return defined( 'ELEMENTOR_PRO_VERSION' );
	}

	/**
	 * @param GuardService $service The shared guard service.
	 */
	public function register( GuardService $service ) {
		$this->service = $service;
		add_action( 'elementor_pro/forms/validation/email', array( $this, 'validate' ), 20, 3 );
	}

	/**
	 * @param array  $field        The field: id, type, value, required.
	 * @param object $record       The form record (unused).
	 * @param object $ajax_handler Elementor's AJAX handler with its errors bag and add_error().
	 */
	public function validate( $field, $record, $ajax_handler ) {
		if ( ! is_array( $field ) || ! isset( $field['id'] ) || ! is_object( $ajax_handler ) ) {
			return;
		}

		// Elementor already failed the field (required, malformed); one message is enough.
		if ( isset( $ajax_handler->errors ) && is_array( $ajax_handler->errors ) && isset( $ajax_handler->errors[ $field['id'] ] ) ) {
			return;
		}

		$email = isset( $field['value'] ) && is_string( $field['value'] ) ? trim( $field['value'] ) : '';
		if ( '' === $email ) {
			// Nothing to check. Required-field validation is Elementor's job.
			return;
		}

		$decision = $this->service->check( $email, $this->id() );

		if ( $decision->is_denied() ) {
			$ajax_handler->add_error( $field['id'], $decision->message() );
		}
	}
}
