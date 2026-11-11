<?php
/**
 * Registration adapter: deny adds an error to the registration error bag.
 */

namespace HostSherlock\EmailGuardWp\Tests\Adapters;

use HostSherlock\EmailGuardWp\Adapters\RegistrationAdapter;
use HostSherlock\EmailGuardWp\Tests\Support\FakeGuardService;
use PHPUnit\Framework\TestCase;

final class RegistrationAdapterTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
	}

	public function testIdAndAvailability(): void {
		$adapter = new RegistrationAdapter();
		$this->assertSame( 'registration', $adapter->id() );
		$this->assertTrue( $adapter->is_available() );
	}

	public function testDeniedDecisionAddsError(): void {
		$service = FakeGuardService::denying( 'Blocked address.' );
		$adapter = new RegistrationAdapter();
		$adapter->register( $service );

		$errors = apply_filters( 'registration_errors', new \WP_Error(), 'jane', 'jane@mailinator.com' );

		$this->assertSame( 'hostsherlock_email_guard_denied', $errors->get_error_code() );
		$this->assertSame( 'Blocked address.', $errors->get_error_message() );
		$this->assertSame( 'jane@mailinator.com', $service->checks[0]['email'] );
		$this->assertSame( 'registration', $service->checks[0]['form_id'] );
	}

	public function testAllowedAndFlaggedDecisionsAddNoError(): void {
		foreach ( array( FakeGuardService::allowing(), FakeGuardService::flagging() ) as $service ) {
			wp_stubs_reset();
			$adapter = new RegistrationAdapter();
			$adapter->register( $service );

			$errors = apply_filters( 'registration_errors', new \WP_Error(), 'jane', 'jane@gmail.com' );
			$this->assertSame( '', $errors->get_error_code() );
			$this->assertCount( 1, $service->checks );
		}
	}

	public function testExistingErrorShortCircuits(): void {
		$service = FakeGuardService::denying();
		$adapter = new RegistrationAdapter();
		$adapter->register( $service );

		$errors = apply_filters( 'registration_errors', new \WP_Error( 'empty_email', 'Missing email.' ), 'jane', '' );

		$this->assertSame( 'empty_email', $errors->get_error_code() );
		$this->assertSame( array(), $service->checks );
	}
}
