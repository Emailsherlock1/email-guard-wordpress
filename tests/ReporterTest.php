<?php
/**
 * Telemetry contract: key-gated, domain-only, batched, fail-silent, and the
 * site key rides along as property_key.
 */

namespace HostSherlock\EmailGuardWp\Tests;

use HostSherlock\EmailGuardWp\Guard\Action;
use HostSherlock\EmailGuardWp\Guard\Guard;
use HostSherlock\EmailGuardWp\Guard\Reporter;
use HostSherlock\EmailGuardWp\Guard\Result;
use HostSherlock\EmailGuardWp\Guard\Verdict;
use HostSherlock\EmailGuardWp\Tests\Support\MockTransport;
use PHPUnit\Framework\TestCase;

final class ReporterTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
	}

	private function denied_result(): Result {
		return new Result(
			Verdict::DISPOSABLE,
			Action::DENY,
			array( 'disposable_provider' ),
			false,
			false,
			null,
			false,
			null,
			'mailinator.com'
		);
	}

	public function testEventShapeAndPropertyKey(): void {
		$transport = MockTransport::responding_with( array( 'accepted' => 1, 'rejected' => 0 ), 202 );
		$reporter  = new Reporter( 'key-123', 'site-abc', 'https://api.example.test', 800, $transport );

		$reporter->record( $this->denied_result() );
		$this->assertCount( 1, $reporter->pending() );

		$reporter->flush();

		$this->assertSame( 1, $transport->calls );
		$this->assertSame( 'https://api.example.test/v1/guard/events', $transport->last_url );
		$this->assertSame( 'key-123', $transport->last_headers['X-API-Key'] );

		$body = json_decode( $transport->last_body, true );
		$this->assertCount( 1, $body['events'] );
		$event = $body['events'][0];

		$this->assertSame( 'mailinator.com', $event['domain'] );
		$this->assertSame( 'disposable', $event['verdict'] );
		$this->assertSame( 'deny', $event['action'] );
		$this->assertSame( array( 'disposable_provider' ), $event['reasons'] );
		$this->assertFalse( $event['degraded'] );
		$this->assertSame( 'local', $event['source'] );
		$this->assertSame( 'wordpress-plugin', $event['integration'] );
		$this->assertSame( Guard::VERSION, $event['lib_version'] );
		$this->assertSame( Guard::SPEC_VERSION, $event['spec_version'] );
		$this->assertSame( 'site-abc', $event['property_key'] );

		// The queue is cleared after a flush.
		$this->assertSame( array(), $reporter->pending() );
	}

	public function testNoPropertyKeyWhenSiteKeyMissing(): void {
		$transport = MockTransport::responding_with( array(), 202 );
		$reporter  = new Reporter( 'key-123', null, 'https://api.example.test', 800, $transport );

		$reporter->record( $this->denied_result() );
		$reporter->flush();

		$event = json_decode( $transport->last_body, true )['events'][0];
		$this->assertArrayNotHasKey( 'property_key', $event );
	}

	public function testRemoteSourceForApiDecisions(): void {
		$transport = MockTransport::responding_with( array(), 202 );
		$reporter  = new Reporter( 'key-123', null, 'https://api.example.test', 800, $transport );

		$reporter->record( new Result( Verdict::VALID, Action::ALLOW, array(), false, true, array( 'result' => 'valid' ), false, null, 'gmail.com' ) );
		$reporter->flush();

		$event = json_decode( $transport->last_body, true )['events'][0];
		$this->assertSame( 'remote', $event['source'] );
	}

	public function testKeyGatedNoKeyNoTelemetry(): void {
		$transport = MockTransport::responding_with( array(), 202 );
		$reporter  = new Reporter( null, 'site-abc', 'https://api.example.test', 800, $transport );

		$this->assertFalse( $reporter->is_enabled() );
		$reporter->record( $this->denied_result() );
		$this->assertSame( array(), $reporter->pending() );

		$reporter->flush();
		$this->assertSame( 0, $transport->calls );
	}

	public function testFlushIsFailSilentAndDropsEvents(): void {
		$transport = MockTransport::unavailable();
		$reporter  = new Reporter( 'key-123', null, 'https://api.example.test', 800, $transport );

		$reporter->record( $this->denied_result() );
		$reporter->flush();

		$this->assertSame( 1, $transport->calls );
		$this->assertSame( array(), $reporter->pending() );

		// A second flush does not resend anything.
		$reporter->flush();
		$this->assertSame( 1, $transport->calls );
	}

	public function testBatchesMultipleEvents(): void {
		$transport = MockTransport::responding_with( array(), 202 );
		$reporter  = new Reporter( 'key-123', null, 'https://api.example.test', 800, $transport );

		$reporter->record( $this->denied_result() );
		$reporter->record( $this->denied_result() );
		$reporter->flush();

		$this->assertSame( 1, $transport->calls );
		$this->assertCount( 2, json_decode( $transport->last_body, true )['events'] );
	}
}
