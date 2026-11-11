<?php
/**
 * Guards Formidable Forms email fields.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Adapters;

use HostSherlock\EmailGuardWp\GuardService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks frm_validate_email_field_entry, which Formidable fires for each
 * email field: a denied address adds the visitor-facing message under the
 * field's error key, a flagged one passes through untouched (the service
 * already logs it).
 */
class FormidableFormsAdapter implements AdapterInterface {

	/** @var GuardService */
	private $service;

	/**
	 * @return string
	 */
	public function id() {
		return 'formidable_forms';
	}

	/**
	 * @return string
	 */
	public function label() {
		return __( 'Formidable Forms', 'hostsherlock-email-guard' );
	}

	/**
	 * @return bool Whether Formidable Forms is active.
	 */
	public function is_available() {
		return function_exists( 'load_formidable_forms' );
	}

	/**
	 * @param GuardService $service The shared guard service.
	 */
	public function register( GuardService $service ) {
		$this->service = $service;
		add_filter( 'frm_validate_email_field_entry', array( $this, 'validate' ), 20, 4 );
	}

	/**
	 * @param array  $errors       Errors so far, keyed 'field' . field id.
	 * @param object $posted_field The Formidable field row.
	 * @param mixed  $value        The submitted value, already trimmed.
	 * @param array  $args         Validation context; 'id' is the error key's id (repeaters differ from the field id).
	 * @return array
	 */
	public function validate( $errors, $posted_field, $value, $args = array() ) {
		if ( ! is_array( $errors ) || ! is_object( $posted_field ) ) {
			return $errors;
		}

		$id  = isset( $args['id'] ) ? $args['id'] : ( isset( $posted_field->id ) ? $posted_field->id : '' );
		$key = 'field' . $id;

		// Formidable already failed the field (required, malformed); one message is enough.
		if ( '' === (string) $id || isset( $errors[ $key ] ) ) {
			return $errors;
		}

		$email = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $email ) {
			// Nothing to check. Required-field validation is Formidable's job.
			return $errors;
		}

		$decision = $this->service->check( $email, $this->id() );

		if ( $decision->is_denied() ) {
			$errors[ $key ] = $decision->message();
		}

		return $errors;
	}
}
