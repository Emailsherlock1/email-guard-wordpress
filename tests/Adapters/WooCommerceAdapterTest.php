<?php
/**
 * WooCommerce adapter: checkout billing email and account registration.
 */

namespace HostSherlock\EmailGuardWp\Tests\Adapters;

use HostSherlock\EmailGuardWp\Adapters\WooCommerceAdapter;
use HostSherlock\EmailGuardWp\Tests\Support\FakeGuardService;
use PHPUnit\Framework\TestCase;

final class WooCommerceAdapterTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
	}

	public function testIdAndAvailabilityWithoutWooCommerce(): void {
		$adapter = new WooCommerceAdapter();
		$this->assertSame( 'woocommerce', $adapter->id() );
		$this->assertFalse( $adapter->is_available() );
	}

	public function testCheckoutDenialAddsErrorOnBillingEmail(): void {
		$service = FakeGuardService::denying( 'Blocked address.' );
		$adapter = new WooCommerceAdapter();
		$adapter->register( $service );

		$errors = new \WP_Error();
		do_action( 'woocommerce_after_checkout_validation', array( 'billing_email' => 'x@mailinator.com' ), $errors );

		$this->assertSame( 'hostsherlock_email_guard_denied', $errors->get_error_code() );
		$this->assertSame( array( 'id' => 'billing_email' ), $errors->get_error_data() );
		$this->assertSame( 'x@mailinator.com', $service->checks[0]['email'] );
		$this->assertSame( 'woocommerce', $service->checks[0]['form_id'] );
	}

	public function testCheckoutAllowedAddsNoError(): void {
		$adapter = new WooCommerceAdapter();
		$adapter->register( FakeGuardService::allowing() );

		$errors = new \WP_Error();
		do_action( 'woocommerce_after_checkout_validation', array( 'billing_email' => 'x@gmail.com' ), $errors );

		$this->assertSame( '', $errors->get_error_code() );
	}

	public function testRegistrationDenialAddsError(): void {
		$service = FakeGuardService::denying( 'Blocked address.' );
		$adapter = new WooCommerceAdapter();
		$adapter->register( $service );

		$errors = apply_filters( 'woocommerce_registration_errors', new \WP_Error(), 'jane', 'x@mailinator.com' );

		$this->assertSame( 'hostsherlock_email_guard_denied', $errors->get_error_code() );
	}

	public function testRegistrationExistingErrorShortCircuits(): void {
		$service = FakeGuardService::denying();
		$adapter = new WooCommerceAdapter();
		$adapter->register( $service );

		$errors = apply_filters( 'woocommerce_registration_errors', new \WP_Error( 'other', 'x' ), 'jane', 'x@mailinator.com' );

		$this->assertSame( 'other', $errors->get_error_code() );
		$this->assertSame( array(), $service->checks );
	}
}
