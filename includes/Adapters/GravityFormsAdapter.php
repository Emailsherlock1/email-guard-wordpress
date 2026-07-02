<?php
/**
 * Guards Gravity Forms email fields.
 *
 * @package EmailSherlock\EmailGuardWp
 */

namespace EmailSherlock\EmailGuardWp\Adapters;

use EmailSherlock\EmailGuardWp\GuardService;

/**
 * Hooks gform_field_validation for email fields: a denied address fails the
 * field with the visitor-facing message, a flagged one passes through
 * untouched (the service already logs it).
 */
class GravityFormsAdapter implements AdapterInterface {

	/** @var GuardService */
	private $service;

	/**
	 * @return string
	 */
	public function id() {
		return 'gravity_forms';
	}

	/**
	 * @return string
	 */
	public function label() {
		return __( 'Gravity Forms', 'emailsherlock-email-guard' );
	}

	/**
	 * @return bool Whether Gravity Forms is active.
	 */
	public function is_available() {
		return class_exists( 'GFForms' );
	}

	/**
	 * @param GuardService $service The shared guard service.
	 */
	public function register( GuardService $service ) {
		$this->service = $service;
		add_filter( 'gform_field_validation', array( $this, 'validate' ), 20, 4 );
	}

	/**
	 * @param array        $result The validation result so far: is_valid plus message.
	 * @param string|array $value  The submitted value; an array when confirmation is enabled.
	 * @param array        $form   The Gravity Forms form meta.
	 * @param object       $field  A GF_Field-like field object.
	 * @return array
	 */
	public function validate( $result, $value, $form, $field ) {
		// Someone earlier already failed the field; nothing to add.
		if ( ! is_array( $result ) || empty( $result['is_valid'] ) ) {
			return $result;
		}

		// Only email fields carry an address worth checking.
		$type = method_exists( $field, 'get_input_type' ) ? $field->get_input_type() : $field->type;
		if ( 'email' !== $type ) {
			return $result;
		}

		// With confirmation enabled the value arrives as [email, confirmation].
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		$email = trim( (string) $value );
		if ( '' === $email ) {
			// Nothing to check. Required-field validation is Gravity Forms' job.
			return $result;
		}

		$decision = $this->service->check( $email, $this->id() );

		if ( $decision->is_denied() ) {
			return array(
				'is_valid' => false,
				'message'  => $decision->message(),
			);
		}

		return $result;
	}
}
