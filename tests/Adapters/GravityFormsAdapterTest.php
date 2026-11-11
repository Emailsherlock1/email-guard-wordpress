<?php
/**
 * GravityFormsAdapter wiring tests.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Tests\Adapters;

use HostSherlock\EmailGuardWp\Adapters\GravityFormsAdapter;
use HostSherlock\EmailGuardWp\Tests\Support\FakeGuardService;
use PHPUnit\Framework\TestCase;

/**
 * A GF_Field-like fake exposing get_input_type(), the path Gravity Forms
 * itself takes for real field objects.
 */
class FakeGfField {

	/** @var string */
	public $type;

	/** @var string */
	private $input_type;

	public function __construct( string $type, ?string $input_type = null ) {
		$this->type       = $type;
		$this->input_type = null !== $input_type ? $input_type : $type;
	}

	public function get_input_type(): string {
		return $this->input_type;
	}
}

/**
 * Asserts the adapter's Gravity Forms wiring: hook registration, email-field
 * targeting, value extraction, and the deny / flag / allow mapping.
 */
final class GravityFormsAdapterTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
	}

	/**
	 * A GF_Field-like object without get_input_type(), only a $type property.
	 *
	 * @param string $type Field type.
	 * @return object
	 */
	private function plain_field( string $type ) {
		$field       = new \stdClass();
		$field->type = $type;

		return $field;
	}

	/**
	 * @return array A valid Gravity Forms validation result.
	 */
	private function valid_result(): array {
		return array(
			'is_valid' => true,
			'message'  => '',
		);
	}

	public function test_id_is_gravity_forms(): void {
		$this->assertSame( 'gravity_forms', ( new GravityFormsAdapter() )->id() );
	}

	public function test_register_hooks_gform_field_validation(): void {
		$adapter = new GravityFormsAdapter();
		$adapter->register( FakeGuardService::allowing() );

		$this->assertTrue( has_filter( 'gform_field_validation' ) );
	}

	public function test_denied_decision_fails_the_field_with_the_message(): void {
		$service = FakeGuardService::denying( 'This address is blocked.' );
		$adapter = new GravityFormsAdapter();
		$adapter->register( $service );

		$result = apply_filters(
			'gform_field_validation',
			$this->valid_result(),
			'spam@example.test',
			array( 'id' => 1 ),
			new FakeGfField( 'email' )
		);

		$this->assertFalse( $result['is_valid'] );
		$this->assertSame( 'This address is blocked.', $result['message'] );
		$this->assertCount( 1, $service->checks );
		$this->assertSame( 'spam@example.test', $service->checks[0]['email'] );
		$this->assertSame( 'gravity_forms', $service->checks[0]['form_id'] );
	}

	public function test_already_invalid_result_passes_through_without_a_check(): void {
		$service = FakeGuardService::denying();
		$adapter = new GravityFormsAdapter();
		$adapter->register( $service );

		$invalid = array(
			'is_valid' => false,
			'message'  => 'Earlier validator failed it.',
		);

		$result = $adapter->validate( $invalid, 'spam@example.test', array(), new FakeGfField( 'email' ) );

		$this->assertSame( $invalid, $result );
		$this->assertCount( 0, $service->checks );
	}

	public function test_non_array_result_passes_through_without_a_check(): void {
		$service = FakeGuardService::denying();
		$adapter = new GravityFormsAdapter();
		$adapter->register( $service );

		$result = $adapter->validate( 'not-an-array', 'spam@example.test', array(), new FakeGfField( 'email' ) );

		$this->assertSame( 'not-an-array', $result );
		$this->assertCount( 0, $service->checks );
	}

	public function test_non_email_field_types_never_call_the_service(): void {
		$service = FakeGuardService::denying();
		$adapter = new GravityFormsAdapter();
		$adapter->register( $service );

		$valid = $this->valid_result();

		$this->assertSame( $valid, $adapter->validate( $valid, 'anything', array(), new FakeGfField( 'text' ) ) );
		$this->assertSame( $valid, $adapter->validate( $valid, 'anything', array(), $this->plain_field( 'textarea' ) ) );
		$this->assertCount( 0, $service->checks );
	}

	public function test_plain_field_without_get_input_type_uses_the_type_property(): void {
		$service = FakeGuardService::denying( 'blocked' );
		$adapter = new GravityFormsAdapter();
		$adapter->register( $service );

		$result = $adapter->validate( $this->valid_result(), 'spam@example.test', array(), $this->plain_field( 'email' ) );

		$this->assertFalse( $result['is_valid'] );
		$this->assertCount( 1, $service->checks );
	}

	public function test_array_value_checks_the_first_element(): void {
		$service = FakeGuardService::allowing();
		$adapter = new GravityFormsAdapter();
		$adapter->register( $service );

		$adapter->validate(
			$this->valid_result(),
			array( 'first@example.test', 'first@example.test' ),
			array(),
			new FakeGfField( 'email' )
		);

		$this->assertCount( 1, $service->checks );
		$this->assertSame( 'first@example.test', $service->checks[0]['email'] );
	}

	public function test_empty_value_never_calls_the_service(): void {
		$service = FakeGuardService::denying();
		$adapter = new GravityFormsAdapter();
		$adapter->register( $service );

		$valid = $this->valid_result();

		$this->assertSame( $valid, $adapter->validate( $valid, '', array(), new FakeGfField( 'email' ) ) );
		$this->assertSame( $valid, $adapter->validate( $valid, '   ', array(), new FakeGfField( 'email' ) ) );
		$this->assertSame( $valid, $adapter->validate( $valid, array( '' ), array(), new FakeGfField( 'email' ) ) );
		$this->assertCount( 0, $service->checks );
	}

	public function test_allowed_decision_returns_the_result_unchanged(): void {
		$service = FakeGuardService::allowing();
		$adapter = new GravityFormsAdapter();
		$adapter->register( $service );

		$valid  = $this->valid_result();
		$result = $adapter->validate( $valid, 'good@example.test', array(), new FakeGfField( 'email' ) );

		$this->assertSame( $valid, $result );
		$this->assertCount( 1, $service->checks );
	}

	public function test_flagged_decision_returns_the_result_unchanged(): void {
		$service = FakeGuardService::flagging();
		$adapter = new GravityFormsAdapter();
		$adapter->register( $service );

		$valid  = $this->valid_result();
		$result = $adapter->validate( $valid, 'flagged@example.test', array(), new FakeGfField( 'email' ) );

		$this->assertSame( $valid, $result );
		$this->assertCount( 1, $service->checks );
	}
}
