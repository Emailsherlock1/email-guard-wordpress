<?php
/**
 * Minimal HTTP response value object.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Guard;

/**
 * Status + body, nothing else. The guard classifies everything itself.
 */
class TransportResponse {

	/** @var int */
	public $status_code;

	/** @var string */
	public $body;

	/**
	 * @param int    $status_code HTTP status code.
	 * @param string $body        Raw response body.
	 */
	public function __construct( $status_code, $body ) {
		$this->status_code = $status_code;
		$this->body        = $body;
	}
}
