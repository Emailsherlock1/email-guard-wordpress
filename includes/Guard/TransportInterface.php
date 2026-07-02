<?php
/**
 * Minimal HTTP contract the guard needs.
 *
 * @package EmailSherlock\EmailGuardWp
 */

namespace EmailSherlock\EmailGuardWp\Guard;

/**
 * One JSON POST with a hard time budget. Implementations throw
 * TransportException on any network-level failure.
 */
interface TransportInterface {

	/**
	 * @param string $url        Absolute endpoint URL.
	 * @param array  $headers    Header name => value map.
	 * @param string $body       JSON request body.
	 * @param int    $timeout_ms Total time budget in milliseconds.
	 * @return TransportResponse
	 * @throws TransportException On connection failure or timeout.
	 */
	public function post( $url, $headers, $body, $timeout_ms );
}
