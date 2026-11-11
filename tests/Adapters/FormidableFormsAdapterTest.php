<?php
/**
 * FormidableFormsAdapter wiring tests.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Tests\Adapters;

use HostSherlock\EmailGuardWp\Adapters\FormidableFormsAdapter;
use HostSherlock\EmailGuardWp\Tests\Support\FakeGuardService;
use PHPUnit\Framework\TestCase;

final class FormidableFormsAdapterTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
	}

	/**
	 * @return object A Formidable field row.
	 */
	private function email_field() {
		$field       = new \stdClass();
		$field->id   = 25;
		$field->type = 'email';

		return $field;
	}

	public function test_id_is_formidable_forms(): void {
		$this->assertSame( 'formidable_forms', ( new FormidableFormsAdapter() )->id() );
	}

	public function test_unavailable_without_formidable(): void {
		$this->assertFalse( ( new FormidableFormsAdapter() )->is_available() );
	}

	public function test_register_hooks_the_email_field_entry_filter(): void {
		( new FormidableFormsAdapter() )->register( FakeGuardService::allowing() );

		$this->assertTrue( has_filter( 'frm_validate_email_field_entry' ) );
	}

	public function test_denied_decision_adds_the_message_under_the_field_key(): void {
		$service = FakeGuardService::denying( 'This address is blocked.' );
		( new FormidableFormsAdapter() )->register( $service );

		$errors = apply_filters( 'frm_validate_email_field_entry', array(), $this->email_field(), 'bad@example.com', array( 'id' => 25 ) );

		$this->assertSame( array( 'field25' => 'This address is blocked.' ), $errors );
		$this->assertSame(
			array(
				array(
					'email'   => 'bad@example.com',
					'form_id' => 'formidable_forms',
				),
			),
			$service->checks
		);
	}

	public function test_repeater_rows_use_the_error_key_from_args(): void {
		$adapter = new FormidableFormsAdapter();
		$adapter->register( FakeGuardService::denying( 'Blocked.' ) );

		$errors = $adapter->validate( array(), $this->email_field(), 'bad@example.com', array( 'id' => '25-0' ) );

		$this->assertSame( array( 'field25-0' => 'Blocked.' ), $errors );
	}

	public function test_allowed_and_flagged_decisions_add_no_error(): void {
		foreach ( array( FakeGuardService::allowing(), FakeGuardService::flagging() ) as $service ) {
			$adapter = new FormidableFormsAdapter();
			$adapter->register( $service );

			$this->assertSame( array(), $adapter->validate( array(), $this->email_field(), 'fine@example.com', array( 'id' => 25 ) ) );
			$this->assertCount( 1, $service->checks );
		}
	}

	public function test_empty_or_non_string_value_never_reaches_the_service(): void {
		$service = FakeGuardService::denying();
		$adapter = new FormidableFormsAdapter();
		$adapter->register( $service );

		foreach ( array( '', '  ', array( 'x@example.com' ) ) as $value ) {
			$this->assertSame( array(), $adapter->validate( array(), $this->email_field(), $value, array( 'id' => 25 ) ) );
		}

		$this->assertSame( array(), $service->checks );
	}

	public function test_field_formidable_already_failed_is_not_checked_again(): void {
		$service = FakeGuardService::denying( 'Blocked.' );
		$adapter = new FormidableFormsAdapter();
		$adapter->register( $service );

		$errors = $adapter->validate( array( 'field25' => 'This field cannot be blank.' ), $this->email_field(), 'bad@example.com', array( 'id' => 25 ) );

		$this->assertSame( array( 'field25' => 'This field cannot be blank.' ), $errors );
		$this->assertSame( array(), $service->checks );
	}
}
