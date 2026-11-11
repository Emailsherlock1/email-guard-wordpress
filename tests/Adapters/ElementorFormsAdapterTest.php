<?php
/**
 * ElementorFormsAdapter wiring tests.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Tests\Adapters;

use HostSherlock\EmailGuardWp\Adapters\ElementorFormsAdapter;
use HostSherlock\EmailGuardWp\Tests\Support\FakeGuardService;
use PHPUnit\Framework\TestCase;

/**
 * Fake of Elementor Pro's Ajax_Handler: the public errors bag and add_error(),
 * which is all the adapter touches.
 */
class FakeElementorAjaxHandler {

	/** @var array<string, string> */
	public $errors = array();

	public function add_error( $field, $message = '' ) {
		$this->errors[ $field ] = $message;

		return $this;
	}
}

final class ElementorFormsAdapterTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
	}

	/**
	 * @param mixed $value Submitted value.
	 * @return array An Elementor form field as Form_Record builds it.
	 */
	private function email_field( $value ): array {
		return array(
			'id'       => 'email',
			'type'     => 'email',
			'title'    => 'Email',
			'value'    => $value,
			'required' => true,
		);
	}

	public function test_id_is_elementor_forms(): void {
		$this->assertSame( 'elementor_forms', ( new ElementorFormsAdapter() )->id() );
	}

	public function test_unavailable_without_elementor_pro(): void {
		$this->assertFalse( ( new ElementorFormsAdapter() )->is_available() );
	}

	public function test_register_hooks_the_email_field_validation(): void {
		( new ElementorFormsAdapter() )->register( FakeGuardService::allowing() );

		$this->assertTrue( has_filter( 'elementor_pro/forms/validation/email' ) );
	}

	public function test_denied_decision_adds_the_message_under_the_field_id(): void {
		$service = FakeGuardService::denying( 'This address is blocked.' );
		$handler = new FakeElementorAjaxHandler();
		( new ElementorFormsAdapter() )->register( $service );

		do_action( 'elementor_pro/forms/validation/email', $this->email_field( ' bad@example.com ' ), null, $handler );

		$this->assertSame( array( 'email' => 'This address is blocked.' ), $handler->errors );
		$this->assertSame(
			array(
				array(
					'email'   => 'bad@example.com',
					'form_id' => 'elementor_forms',
				),
			),
			$service->checks
		);
	}

	public function test_allowed_and_flagged_decisions_add_no_error(): void {
		foreach ( array( FakeGuardService::allowing(), FakeGuardService::flagging() ) as $service ) {
			$handler = new FakeElementorAjaxHandler();
			$adapter = new ElementorFormsAdapter();
			$adapter->register( $service );
			$adapter->validate( $this->email_field( 'fine@example.com' ), null, $handler );

			$this->assertSame( array(), $handler->errors );
			$this->assertCount( 1, $service->checks );
		}
	}

	public function test_empty_or_non_string_value_never_reaches_the_service(): void {
		$service = FakeGuardService::denying();
		$adapter = new ElementorFormsAdapter();
		$adapter->register( $service );
		$handler = new FakeElementorAjaxHandler();

		foreach ( array( '', '   ', array( 'a@example.com' ), null ) as $value ) {
			$adapter->validate( $this->email_field( $value ), null, $handler );
		}

		$this->assertSame( array(), $service->checks );
		$this->assertSame( array(), $handler->errors );
	}

	public function test_field_elementor_already_failed_is_not_checked_again(): void {
		$service         = FakeGuardService::denying( 'Blocked.' );
		$handler         = new FakeElementorAjaxHandler();
		$handler->errors = array( 'email' => 'Invalid email.' );
		$adapter         = new ElementorFormsAdapter();
		$adapter->register( $service );

		$adapter->validate( $this->email_field( 'bad@example.com' ), null, $handler );

		$this->assertSame( array(), $service->checks );
		$this->assertSame( array( 'email' => 'Invalid email.' ), $handler->errors );
	}
}
