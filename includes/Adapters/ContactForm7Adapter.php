<?php
/**
 * Guards Contact Form 7 email fields.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Adapters;

use HostSherlock\EmailGuardWp\GuardService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks the CF7 field validation filters for email and email* tags: a denied
 * address invalidates the field with the visitor-facing message, a flagged
 * one passes through untouched (the service already logs it).
 */
class ContactForm7Adapter implements AdapterInterface {

	/** @var GuardService */
	private $service;

	/**
	 * @return string
	 */
	public function id() {
		return 'contact_form_7';
	}

	/**
	 * @return string
	 */
	public function label() {
		return __( 'Contact Form 7', 'hostsherlock-email-guard' );
	}

	/**
	 * @return bool Whether Contact Form 7 is active.
	 */
	public function is_available() {
		return defined( 'WPCF7_VERSION' ) || class_exists( 'WPCF7_ContactForm' );
	}

	/**
	 * @param GuardService $service The shared guard service.
	 */
	public function register( GuardService $service ) {
		$this->service = $service;
		add_filter( 'wpcf7_validate_email', array( $this, 'validate' ), 20, 2 );
		add_filter( 'wpcf7_validate_email*', array( $this, 'validate' ), 20, 2 );
	}

	/**
	 * @param object $result A WPCF7_Validation-like result.
	 * @param object $tag    A WPCF7_FormTag-like tag; its name keys the POST value.
	 * @return object
	 */
	public function validate( $result, $tag ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- CF7 verifies its own nonce before invoking field validators; the value is sanitized below.
		$name = isset( $tag->name ) ? (string) $tag->name : '';
		if ( '' === $name || ! isset( $_POST[ $name ] ) || ! is_string( $_POST[ $name ] ) ) {
			return $result;
		}

		$email = sanitize_text_field( wp_unslash( $_POST[ $name ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( '' === $email ) {
			// Nothing to check. Required-field validation is CF7's job.
			return $result;
		}

		$decision = $this->service->check( $email, $this->id() );

		if ( $decision->is_denied() ) {
			$result->invalidate( $tag, $decision->message() );
		}

		return $result;
	}
}
