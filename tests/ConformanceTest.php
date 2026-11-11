<?php
/**
 * Runs every conformance vector from email-guard-spec (vendored under
 * tests/vectors/). All vectors must pass; api_called is part of the
 * assertion so local short-circuits provably never burn an API call.
 */

namespace HostSherlock\EmailGuardWp\Tests;

use HostSherlock\EmailGuardWp\Guard\DisposableSnapshot;
use HostSherlock\EmailGuardWp\Guard\Guard;
use HostSherlock\EmailGuardWp\Guard\TransportException;
use HostSherlock\EmailGuardWp\Tests\Support\MockTransport;
use PHPUnit\Framework\TestCase;

final class ConformanceTest extends TestCase {

	private const VECTOR_DIR = __DIR__ . '/vectors';

	/** @return iterable<string, array{array<string, mixed>}> */
	public static function vectors(): iterable {
		foreach ( glob( self::VECTOR_DIR . '/*.json' ) as $file ) {
			if ( 'schema.json' === basename( $file ) ) {
				continue;
			}
			$doc = json_decode( (string) file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
			assert( 'email-guard-vectors/1' === $doc['format'] );
			foreach ( $doc['vectors'] as $vector ) {
				yield $vector['id'] => array( $vector );
			}
		}
	}

	/**
	 * @dataProvider vectors
	 * @param array<string, mixed> $vector One conformance vector.
	 */
	public function testVector( array $vector ): void {
		$config = $vector['input']['config'] ?? array();
		$api    = $vector['input']['api'] ?? null;

		if ( 'unavailable' === $api ) {
			// Generic degraded check, cause not attributed.
			$transport = MockTransport::unavailable();
		} elseif ( is_array( $api ) && isset( $api['fail'] ) ) {
			// A specific transport failure to simulate (spec 5.2). timeout and
			// connection throw from the transport; http and bad_response return
			// a response and let the guard classify it, as a real call would.
			switch ( $api['fail'] ) {
				case 'timeout':
					$transport = MockTransport::throwing( TransportException::KIND_TIMEOUT );
					break;
				case 'connection':
					$transport = MockTransport::throwing( TransportException::KIND_CONNECTION );
					break;
				case 'http':
					$transport = MockTransport::responding_raw( '{}', (int) $api['status'] );
					break;
				case 'bad_response':
				default:
					$transport = MockTransport::responding_raw( 'not-json', 200 );
					break;
			}
		} else {
			$transport = MockTransport::responding_with( is_array( $api ) ? $api : array() );
		}

		$guard = new Guard(
			$config,
			$transport,
			new DisposableSnapshot( self::VECTOR_DIR . '/fixtures/disposable-snapshot.json' )
		);

		$result   = $guard->check( $vector['input']['email'] );
		$expected = $vector['expected'];

		$this->assertSame( $expected['verdict'], $result->verdict, 'verdict' );
		$this->assertSame( $expected['action'], $result->action, 'action' );
		$this->assertSame( $expected['reasons'], $result->reasons, 'reasons' );
		$this->assertSame( $expected['degraded'], $result->degraded, 'degraded' );
		$this->assertSame( $expected['api_called'], $result->api_called, 'api_called' );
		$this->assertSame( $expected['api_called'] ? 1 : 0, $transport->calls, 'transport call count' );
	}
}
