<?php
/**
 * TransportInterface backed by the WordPress HTTP API.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp;

use HostSherlock\EmailGuardWp\Guard\TransportException;
use HostSherlock\EmailGuardWp\Guard\TransportInterface;
use HostSherlock\EmailGuardWp\Guard\TransportResponse;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * wp_remote_post with a hard time budget. The non-blocking mode is for
 * telemetry: the request is dispatched and the response ignored, so a form
 * submit never waits on the report.
 */
class WpTransport implements TransportInterface {

	/** @var bool */
	private $blocking;

	/**
	 * @param bool $blocking False dispatches fire-and-forget requests (telemetry).
	 */
	public function __construct( $blocking = true ) {
		$this->blocking = $blocking;
	}

	/**
	 * @param string $url        Absolute endpoint URL.
	 * @param array  $headers    Header name => value map.
	 * @param string $body       JSON request body.
	 * @param int    $timeout_ms Total time budget in milliseconds.
	 * @return TransportResponse
	 * @throws TransportException On connection failure or timeout.
	 */
	public function post( $url, $headers, $body, $timeout_ms ) {
		$response = wp_remote_post(
			$url,
			array(
				'headers'    => $headers,
				'body'       => $body,
				'timeout'    => max( 1, $timeout_ms ) / 1000,
				'blocking'   => $this->blocking,
				'user-agent' => 'hostsherlock-email-guard/' . Guard\Guard::VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( ! $this->blocking ) {
			// Fire-and-forget: the caller never inspects this.
			return new TransportResponse( 202, '' );
		}

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			$kind    = false !== stripos( $message, 'timed out' ) || false !== stripos( $message, 'timeout' )
				? TransportException::KIND_TIMEOUT
				: TransportException::KIND_CONNECTION;

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal exception, caught by the guard's fail-open path, never rendered to a visitor.
			throw new TransportException( 'Verify API request failed: ' . $message, $kind );
		}

		return new TransportResponse(
			(int) wp_remote_retrieve_response_code( $response ),
			(string) wp_remote_retrieve_body( $response )
		);
	}
}
