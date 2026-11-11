<?php
/**
 * Admin-ajax connection test for the settings screen.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Admin;

use HostSherlock\EmailGuardWp\Guard\Guard;
use HostSherlock\EmailGuardWp\Guard\TransportException;
use HostSherlock\EmailGuardWp\Settings;
use HostSherlock\EmailGuardWp\WpTransport;

/**
 * Sends one telemetry event to POST /v1/guard/events with the key the admin
 * typed (falling back to the saved one). The endpoint is free and exercises
 * exactly the path the plugin uses in production, including the site key.
 * The event carries no address, only the reason tag "connection_test".
 */
class ConnectionTest {

	const ACTION = 'hostsherlock_email_guard_test';

	/** @var Settings */
	private $settings;

	/**
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Attach the ajax handler.
	 */
	public function register() {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Handle the ajax request.
	 */
	public function handle() {
		check_ajax_referer( self::ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'hostsherlock-email-guard' ) ), 403 );
		}

		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		if ( '' === $api_key ) {
			$api_key = (string) $this->settings->api_key();
		}
		$site_key = isset( $_POST['site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['site_key'] ) ) : '';
		if ( '' === $site_key ) {
			$site_key = (string) $this->settings->site_key();
		}

		if ( '' === $api_key ) {
			wp_send_json_error(
				array( 'message' => __( 'Enter an API key first. Local checks run without one, but the connection test needs a key.', 'hostsherlock-email-guard' ) )
			);
		}

		$event = array(
			'domain'       => null,
			'verdict'      => 'unknown',
			'action'       => 'allow',
			'reasons'      => array( 'connection_test' ),
			'degraded'     => false,
			'source'       => 'local',
			'integration'  => 'wordpress-plugin',
			'lib_version'  => Guard::VERSION,
			'spec_version' => Guard::SPEC_VERSION,
		);
		if ( '' !== $site_key ) {
			$event['property_key'] = $site_key;
		}

		$base_url  = (string) apply_filters( 'hostsherlock_email_guard_base_url', 'https://api.emailsherlock.com' );
		$transport = new WpTransport();

		try {
			$response = $transport->post(
				rtrim( $base_url, '/' ) . '/v1/guard/events',
				array(
					'X-API-Key'    => $api_key,
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				(string) wp_json_encode( array( 'events' => array( $event ) ) ),
				5000
			);
		} catch ( TransportException $exception ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: transport error detail. */
						__( 'Could not reach the API: %s', 'hostsherlock-email-guard' ),
						$exception->getMessage()
					),
				)
			);

			return;
		}

		if ( 202 === $response->status_code ) {
			wp_send_json_success( array( 'message' => __( 'Connected. The key works and a test event reached your dashboard.', 'hostsherlock-email-guard' ) ) );
		} elseif ( 401 === $response->status_code ) {
			wp_send_json_error( array( 'message' => __( 'The API rejected this key. Check it in your HostSherlock dashboard.', 'hostsherlock-email-guard' ) ) );
		} elseif ( 403 === $response->status_code ) {
			wp_send_json_error( array( 'message' => __( 'The key is valid but is not granted the guard:events scope.', 'hostsherlock-email-guard' ) ) );
		} else {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: HTTP status code. */
						__( 'Unexpected answer from the API: HTTP %d.', 'hostsherlock-email-guard' ),
						$response->status_code
					),
				)
			);
		}
	}
}
