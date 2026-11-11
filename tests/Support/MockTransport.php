<?php
/**
 * Transport mock for the conformance harness and unit tests.
 */

namespace HostSherlock\EmailGuardWp\Tests\Support;

use HostSherlock\EmailGuardWp\Guard\TransportException;
use HostSherlock\EmailGuardWp\Guard\TransportInterface;
use HostSherlock\EmailGuardWp\Guard\TransportResponse;

/**
 * Either unavailable (throws, optionally with an attributed failure kind)
 * or answers a fixed body and status. Records every call. HTTP-status and
 * non-JSON failures are simulated by returning the response and letting the
 * guard classify it, exactly as a real transport would.
 */
final class MockTransport implements TransportInterface {

	/** @var int */
	public $calls = 0;

	/** @var string|null */
	public $last_url = null;

	/** @var array|null */
	public $last_headers = null;

	/** @var string|null */
	public $last_body = null;

	/** @var int|null */
	public $last_timeout_ms = null;

	/** @var array|null */
	private $response_body;

	/** @var int */
	private $status_code;

	/** @var bool */
	private $unavailable;

	/** @var string|null */
	private $raw_body;

	/** @var string */
	private $throw_kind;

	private function __construct( $response_body, $status_code, $unavailable, $raw_body = null, $throw_kind = TransportException::KIND_UNKNOWN ) {
		$this->response_body = $response_body;
		$this->status_code   = $status_code;
		$this->unavailable   = $unavailable;
		$this->raw_body      = $raw_body;
		$this->throw_kind    = $throw_kind;
	}

	public static function unavailable(): self {
		return new self( null, 0, true );
	}

	/** Throws a TransportException with the given attributed kind (timeout / connection). */
	public static function throwing( string $kind ): self {
		return new self( null, 0, true, null, $kind );
	}

	public static function responding_with( array $body, int $status_code = 200 ): self {
		return new self( $body, $status_code, false );
	}

	public static function responding_raw( string $body, int $status_code = 200 ): self {
		return new self( null, $status_code, false, $body );
	}

	public function post( $url, $headers, $body, $timeout_ms ) {
		++$this->calls;
		$this->last_url        = $url;
		$this->last_headers    = $headers;
		$this->last_body       = $body;
		$this->last_timeout_ms = $timeout_ms;

		if ( $this->unavailable ) {
			throw new TransportException( 'mock: API unavailable', $this->throw_kind );
		}

		$payload = null !== $this->raw_body ? $this->raw_body : json_encode( $this->response_body );

		return new TransportResponse( $this->status_code, $payload );
	}
}
