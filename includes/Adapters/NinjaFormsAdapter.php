<?php
/**
 * Guards Ninja Forms email fields.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Adapters;

use HostSherlock\EmailGuardWp\GuardService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks ninja_forms_submit_data. The submitted fields carry an id and a
 * value but no type, so the adapter reads the saved form to find its email
 * fields. A denied address goes into $form_data['errors']['fields'] under
 * the field id, which Ninja Forms reports at the field and which stops the
 * submission. A flagged one passes through untouched (the service already
 * logs it).
 */
class NinjaFormsAdapter implements AdapterInterface {

	/** @var GuardService */
	private $service;

	/**
	 * @return string
	 */
	public function id() {
		return 'ninja_forms';
	}

	/**
	 * @return string
	 */
	public function label() {
		return __( 'Ninja Forms', 'hostsherlock-email-guard' );
	}

	/**
	 * @return bool Whether Ninja Forms is active.
	 */
	public function is_available() {
		return function_exists( 'Ninja_Forms' );
	}

	/**
	 * @param GuardService $service The shared guard service.
	 */
	public function register( GuardService $service ) {
		$this->service = $service;
		add_filter( 'ninja_forms_submit_data', array( $this, 'validate' ), 20 );
	}

	/**
	 * @param array $form_data Submission: form id, fields keyed by field id, errors.
	 * @return array
	 */
	public function validate( $form_data ) {
		if ( ! is_array( $form_data ) || ! isset( $form_data['id'], $form_data['fields'] ) || ! is_array( $form_data['fields'] ) ) {
			return $form_data;
		}

		foreach ( $this->email_field_ids( $form_data['id'] ) as $field_id ) {
			if ( ! empty( $form_data['errors']['fields'][ $field_id ] ) ) {
				continue;
			}

			$field = isset( $form_data['fields'][ $field_id ] ) ? $form_data['fields'][ $field_id ] : null;
			$email = is_array( $field ) && isset( $field['value'] ) && is_string( $field['value'] ) ? trim( $field['value'] ) : '';
			if ( '' === $email ) {
				// Nothing to check. Required-field validation is Ninja Forms' job.
				continue;
			}

			$decision = $this->service->check( $email, $this->id() );

			if ( $decision->is_denied() ) {
				$form_data['errors']['fields'][ $field_id ] = $decision->message();
			}
		}

		return $form_data;
	}

	/**
	 * Ids of the form's email fields, read from the saved form rather than
	 * from the request, so a visitor cannot relabel a field to skip the check.
	 *
	 * @param int|string $form_id Ninja Forms form id.
	 * @return array<int, int|string>
	 */
	private function email_field_ids( $form_id ) {
		$ids = array();
		foreach ( Ninja_Forms()->form( $form_id )->get_fields() as $field_id => $field ) {
			if ( is_object( $field ) && method_exists( $field, 'get_setting' ) && 'email' === $field->get_setting( 'type' ) ) {
				$ids[] = $field_id;
			}
		}

		return $ids;
	}
}
