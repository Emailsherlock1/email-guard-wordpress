<?php
/**
 * Guards WPForms submissions.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Adapters;

use HostSherlock\EmailGuardWp\GuardService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks wpforms_process: every non-empty email field runs through the guard,
 * and a denied address writes its message into WPForms' own error bag, which
 * makes WPForms render the error inline and abort the entry. Flagged
 * decisions pass through untouched; the service already logged them.
 */
class WpFormsAdapter implements AdapterInterface {

	/** @var GuardService */
	private $service;

	/**
	 * @return string
	 */
	public function id() {
		return 'wpforms';
	}

	/**
	 * @return string
	 */
	public function label() {
		return __( 'WPForms', 'hostsherlock-email-guard' );
	}

	/**
	 * @return bool Whether WPForms is active.
	 */
	public function is_available() {
		return function_exists( 'wpforms' );
	}

	/**
	 * @param GuardService $service The shared guard service.
	 */
	public function register( GuardService $service ) {
		$this->service = $service;
		add_action( 'wpforms_process', array( $this, 'validate' ), 20, 3 );
	}

	/**
	 * Check every submitted email field and reject denied addresses.
	 *
	 * @param array $fields    Submitted fields, keyed by field id, each with
	 *                         at least 'type' and 'value'.
	 * @param array $entry     Raw entry data (unused).
	 * @param array $form_data Form settings; carries the form id.
	 */
	public function validate( $fields, $entry, $form_data ) {
		if ( ! is_array( $fields ) ) {
			return;
		}

		// Without a form id there is no slot in the error bag to write to.
		if ( ! isset( $form_data['id'] ) ) {
			return;
		}

		foreach ( $fields as $field_id => $field ) {
			if ( ! is_array( $field ) || ! isset( $field['type'] ) || 'email' !== $field['type'] ) {
				continue;
			}

			$value = isset( $field['value'] ) && is_string( $field['value'] ) ? trim( $field['value'] ) : '';
			if ( '' === $value ) {
				// Nothing to check. Required-field validation is WPForms' job.
				continue;
			}

			$decision = $this->service->check( $value, $this->id() );

			if ( $decision->is_denied() ) {
				wpforms()->process->errors[ $form_data['id'] ][ $field_id ] = $decision->message();
			}
		}
	}
}
