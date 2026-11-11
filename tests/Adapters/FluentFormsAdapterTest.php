<?php
/**
 * FluentFormsAdapter wiring tests.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Tests\Adapters;

use HostSherlock\EmailGuardWp\Adapters\FluentFormsAdapter;
use HostSherlock\EmailGuardWp\Tests\Support\FakeGuardService;
use PHPUnit\Framework\TestCase;

final class FluentFormsAdapterTest extends TestCase {

	/** @var array A Fluent Forms input_email field as the validator passes it. */
	private const FIELD = array(
		'element' => 'input_email',
		'name'    => 'email',
	);

	protected function setUp(): void {
		wp_stubs_reset();
	}

	public function test_id_is_fluent_forms(): void {
		$this->assertSame( 'fluent_forms', ( new FluentFormsAdapter() )->id() );
	}

	public function test_unavailable_without_fluent_forms(): void {
		$this->assertFalse( ( new FluentFormsAdapter() )->is_available() );
	}

	public function test_register_hooks_the_email_input_filter(): void {
		( new FluentFormsAdapter() )->register( FakeGuardService::allowing() );

		$this->assertTrue( has_filter( 'fluentform/validate_input_item_input_email' ) );
	}

	public function test_denied_decision_returns_the_message_as_the_field_error(): void {
		$service = FakeGuardService::denying( 'This address is blocked.' );
		( new FluentFormsAdapter() )->register( $service );

		$error = apply_filters( 'fluentform/validate_input_item_input_email', '', self::FIELD, array( 'email' => ' bad@example.com ' ), array(), null, array() );

		$this->assertSame( array( 'This address is blocked.' ), $error );
		$this->assertSame(
			array(
				array(
					'email'   => 'bad@example.com',
					'form_id' => 'fluent_forms',
				),
			),
			$service->checks
		);
	}

	public function test_allowed_and_flagged_decisions_return_no_error(): void {
		foreach ( array( FakeGuardService::allowing(), FakeGuardService::flagging() ) as $service ) {
			$adapter = new FluentFormsAdapter();
			$adapter->register( $service );

			$this->assertSame( '', $adapter->validate( '', self::FIELD, array( 'email' => 'fine@example.com' ) ) );
			$this->assertCount( 1, $service->checks );
		}
	}

	public function test_missing_empty_or_non_string_value_never_reaches_the_service(): void {
		$service = FakeGuardService::denying();
		$adapter = new FluentFormsAdapter();
		$adapter->register( $service );

		foreach ( array( array(), array( 'email' => '' ), array( 'email' => '  ' ), array( 'email' => array( 'x@example.com' ) ) ) as $form_data ) {
			$this->assertSame( '', $adapter->validate( '', self::FIELD, $form_data ) );
		}
		$this->assertSame( '', $adapter->validate( '', array( 'element' => 'input_email' ), array( 'email' => 'x@example.com' ) ) );

		$this->assertSame( array(), $service->checks );
	}

	public function test_field_fluent_forms_already_failed_is_not_checked_again(): void {
		$service = FakeGuardService::denying( 'Blocked.' );
		$adapter = new FluentFormsAdapter();
		$adapter->register( $service );

		// An earlier filter (the unique-email check) returned its own error.
		$this->assertSame( 'Already used.', $adapter->validate( 'Already used.', self::FIELD, array( 'email' => 'bad@example.com' ) ) );
		// The format check failed the field before the filter ran.
		$this->assertSame( '', $adapter->validate( '', self::FIELD, array( 'email' => 'bad@example.com' ), array(), null, array( 'email' => array( 'email' => 'Invalid.' ) ) ) );

		$this->assertSame( array(), $service->checks );
	}
}
