<?php
/**
 * Transport failure with an attributed cause (email-guard-spec, section 5.2).
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Guard;

/**
 * Thrown by a transport on any network-level failure. The guard turns it
 * into a degraded result decided by fail_open alone.
 */
class TransportException extends \RuntimeException {

	/** Cause could not be attributed; only `api_unavailable` is reported. */
	const KIND_UNKNOWN = 'unknown';

	/** Total or connect timeout budget exceeded. */
	const KIND_TIMEOUT = 'timeout';

	/** DNS, TLS, or connection failure before any HTTP status. */
	const KIND_CONNECTION = 'connection';

	/** The API answered a non-2xx status; $status_code carries the code. */
	const KIND_HTTP = 'http';

	/** A 2xx answer whose body was not the expected JSON. */
	const KIND_BAD_RESPONSE = 'bad_response';

	/** @var string One of the KIND_* constants. */
	public $kind;

	/** @var int|null HTTP status code for KIND_HTTP failures. */
	public $status_code;

	/**
	 * @param string          $message     Failure description.
	 * @param string          $kind        One of the KIND_* constants.
	 * @param int|null        $status_code HTTP status code, if any.
	 * @param \Throwable|null $previous    Underlying cause.
	 */
	public function __construct( $message, $kind = self::KIND_UNKNOWN, $status_code = null, $previous = null ) {
		parent::__construct( $message, 0, $previous );
		$this->kind        = $kind;
		$this->status_code = $status_code;
	}
}
