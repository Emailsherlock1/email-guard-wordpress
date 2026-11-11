<?php
/**
 * Guards Fluent Forms email fields.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Adapters;

use HostSherlock\EmailGuardWp\GuardService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks fluentform/validate_input_item_input_email (Fluent Forms 5.0 and
 * later): the filter returns the field's error, so a denied address returns
 * the visitor-facing message and Fluent Forms shows it at the field. A
 * flagged one passes through untouched (the service already logs it).
 */
class FluentFormsAdapter implements AdapterInterface {

	/** @var GuardService */
	private $service;

	/**
	 * @return string
	 */
	public function id() {
		return 'fluent_forms';
	}

	/**
	 * @return string
	 */
	public function label() {
		return __( 'Fluent Forms', 'hostsherlock-email-guard' );
	}

	/**
	 * @return bool Whether Fluent Forms is active.
	 */
	public function is_available() {
		return defined( 'FLUENTFORM' );
	}

	/**
	 * @param GuardService $service The shared guard service.
	 */
	public function register( GuardService $service ) {
		$this->service = $service;
		add_filter( 'fluentform/validate_input_item_input_email', array( $this, 'validate' ), 20, 6 );
	}

	/**
	 * @param string|array $error     The field's error so far; empty when it passed.
	 * @param array        $field     The field; 'name' keys the submitted value.
	 * @param array        $form_data Submitted values keyed by input name.
	 * @param array        $fields    All form fields (unused).
	 * @param object       $form      The form (unused).
	 * @param array        $errors    Errors collected so far, keyed by input name.
	 * @return string|array
	 */
	public function validate( $error, $field, $form_data, $fields = array(), $form = null, $errors = array() ) {
		// Fluent Forms already failed the field (malformed, not unique); one message is enough.
		if ( ! empty( $error ) || ! is_array( $field ) || ! is_array( $form_data ) ) {
			return $error;
		}

		$name = isset( $field['name'] ) && is_string( $field['name'] ) ? $field['name'] : '';
		if ( '' === $name || ! empty( $errors[ $name ] ) ) {
			return $error;
		}

		$email = isset( $form_data[ $name ] ) && is_string( $form_data[ $name ] ) ? trim( $form_data[ $name ] ) : '';
		if ( '' === $email ) {
			// Nothing to check. Required-field validation is Fluent Forms' job.
			return $error;
		}

		$decision = $this->service->check( $email, $this->id() );

		if ( $decision->is_denied() ) {
			return array( $decision->message() );
		}

		return $error;
	}
}
