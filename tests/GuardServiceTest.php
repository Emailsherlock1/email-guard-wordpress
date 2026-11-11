<?php
/**
 * The service glue: policy mapping, log + telemetry side effects, and
 * degrade-to-local when the API is down.
 */

namespace HostSherlock\EmailGuardWp\Tests;

use HostSherlock\EmailGuardWp\DecisionLog;
use HostSherlock\EmailGuardWp\Guard\DisposableSnapshot;
use HostSherlock\EmailGuardWp\Guard\Guard;
use HostSherlock\EmailGuardWp\Guard\Reporter;
use HostSherlock\EmailGuardWp\GuardService;
use HostSherlock\EmailGuardWp\Settings;
use HostSherlock\EmailGuardWp\Tests\Support\MockTransport;
use PHPUnit\Framework\TestCase;

final class GuardServiceTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
	}

	private function snapshot(): DisposableSnapshot {
		return new DisposableSnapshot( __DIR__ . '/vectors/fixtures/disposable-snapshot.json' );
	}

	/**
	 * @param array         $stored    Stored plugin settings.
	 * @param MockTransport $transport Transport for both guard and reporter.
	 */
	private function service( array $stored, MockTransport $transport ): GuardService {
		update_option( Settings::OPTION, $stored );
		$settings = new Settings();
		$config   = $settings->guard_config();
		$guard    = new Guard( $config, $transport, $this->snapshot() );
		$reporter = new Reporter( $settings->api_key(), $settings->site_key(), $config['base_url'], $config['timeout_ms'], $transport );

		return new GuardService( $settings, new DecisionLog( $settings ), $guard, $reporter );
	}

	public function testDisposableIsDeniedWithMessageAndLoggedAndReported(): void {
		$transport = MockTransport::responding_with( array(), 202 );
		$service   = $this->service( array( 'api_key' => 'k' ), $transport );

		$decision = $service->check( 'someone@mailinator.com', 'comments' );

		$this->assertTrue( $decision->is_denied() );
		$this->assertFalse( $decision->is_flagged() );
		$this->assertNotSame( '', $decision->message() );

		// Local short-circuit: the verify API was never called.
		$this->assertSame( 0, $transport->calls );

		// The decision landed in the local log, domain-only.
		$entries = ( new DecisionLog( new Settings() ) )->entries();
		$this->assertCount( 1, $entries );
		$this->assertSame( 'mailinator.com', $entries[0]['domain'] );
		$this->assertSame( 'comments', $entries[0]['form'] );

		// And in the telemetry queue; flush ships it to /v1/guard/events.
		$service->flush_telemetry();
		$this->assertSame( 1, $transport->calls );
		$this->assertStringContainsString( '/v1/guard/events', $transport->last_url );
		$body = json_decode( $transport->last_body, true );
		$this->assertSame( 'mailinator.com', $body['events'][0]['domain'] );
	}

	public function testDeniedMessageComesFromSettingsAndFilter(): void {
		$transport = MockTransport::responding_with( array(), 202 );
		$service   = $this->service( array( 'blocked_message' => 'No throwaway addresses, please.' ), $transport );

		$decision = $service->check( 'x@mailinator.com', 'comments' );
		$this->assertSame( 'No throwaway addresses, please.', $decision->message() );

		add_filter(
			'hostsherlock_email_guard_denied_message',
			static function ( $message, $result, $form_id ) {
				return $message . ' (' . $form_id . ')';
			},
			10,
			3
		);
		$decision = $service->check( 'x@mailinator.com', 'wpforms' );
		$this->assertSame( 'No throwaway addresses, please. (wpforms)', $decision->message() );
	}

	public function testFlagPolicyMapsToFlaggedDecision(): void {
		$transport = MockTransport::responding_with( array(), 202 );
		$service   = $this->service(
			array( 'verdict_actions' => array( 'disposable' => 'flag' ) ),
			$transport
		);

		$decision = $service->check( 'x@mailinator.com', 'comments' );

		$this->assertFalse( $decision->is_denied() );
		$this->assertTrue( $decision->is_flagged() );
		$this->assertTrue( $decision->is_allowed() );
		$this->assertSame( '', $decision->message() );
	}

	public function testEmptyEmailSkipsEverything(): void {
		$transport = MockTransport::responding_with( array(), 202 );
		$service   = $this->service( array( 'api_key' => 'k' ), $transport );

		$decision = $service->check( '   ', 'comments' );

		$this->assertTrue( $decision->is_allowed() );
		$this->assertNull( $decision->result );
		$this->assertSame( 0, $transport->calls );
		$this->assertSame( array(), ( new DecisionLog( new Settings() ) )->entries() );
	}

	public function testDegradeToLocalFailOpenAllowsCleanAddressOnApiOutage(): void {
		$transport = MockTransport::unavailable();
		$service   = $this->service( array( 'api_key' => 'k' ), $transport );

		// A disposable address still gets caught locally during the outage.
		$decision = $service->check( 'x@mailinator.com', 'comments' );
		$this->assertTrue( $decision->is_denied() );
		$this->assertSame( 0, $transport->calls );

		// A clean address degrades and passes (fail_open default).
		$decision = $service->check( 'jane@realcompany.com', 'comments' );
		$this->assertTrue( $decision->is_allowed() );
		$this->assertTrue( $decision->result->degraded );
		$this->assertContains( 'api_unavailable', $decision->result->reasons );
		$this->assertSame( 1, $transport->calls );
	}

	public function testDegradeFailClosedDeniesWhenConfigured(): void {
		$transport = MockTransport::unavailable();
		$service   = $this->service( array( 'api_key' => 'k', 'fail_open' => false ), $transport );

		$decision = $service->check( 'jane@realcompany.com', 'comments' );

		$this->assertTrue( $decision->is_denied() );
		$this->assertTrue( $decision->result->degraded );
		$this->assertNotSame( '', $decision->message() );
	}

	public function testRelayFlagDefaultMarksValidRelayAddress(): void {
		$transport = MockTransport::responding_with(
			array(
				'result'         => 'valid',
				'relay'          => true,
				'relay_provider' => 'SimpleLogin',
			)
		);
		$service   = $this->service( array( 'api_key' => 'k' ), $transport );

		$decision = $service->check( 'masked@simplelogin.example-relay.com', 'registration' );

		$this->assertTrue( $decision->is_flagged() );
		$this->assertSame( 'valid', $decision->result->verdict );
		$this->assertContains( 'relay', $decision->result->reasons );
		$this->assertSame( 'SimpleLogin', $decision->result->relay_provider );
	}

	public function testNoApiKeyMeansLocalOnlyAndNoTelemetry(): void {
		$transport = MockTransport::responding_with( array(), 202 );
		$service   = $this->service( array(), $transport );

		$decision = $service->check( 'jane@realcompany.com', 'comments' );

		$this->assertTrue( $decision->is_allowed() );
		$this->assertSame( 'unknown', $decision->result->verdict );
		$this->assertFalse( $decision->result->degraded );

		$service->flush_telemetry();
		// No key: neither verify nor telemetry touched the network.
		$this->assertSame( 0, $transport->calls );
	}
}
