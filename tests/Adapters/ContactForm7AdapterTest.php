<?php
/**
 * ContactForm7Adapter wiring tests.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Tests\Adapters;

use HostSherlock\EmailGuardWp\Adapters\ContactForm7Adapter;
use HostSherlock\EmailGuardWp\Tests\Support\FakeGuardService;
use PHPUnit\Framework\TestCase;

/**
 * Duck-typed stand-in for WPCF7_Validation: records invalidate() calls.
 */
final class FakeCf7Validation {

	/** @var array<int, array{tag: object, message: string}> */
	public $invalidations = array();

	/**
	 * @param object $tag     The offending form tag.
	 * @param string $message Visitor-facing message.
	 */
	public function invalidate( $tag, $message ) {
		$this->invalidations[] = array(
			'tag'     => $tag,
			'message' => $message,
		);
	}
}

/**
 * Covers hook registration, deny/flag/allow handling, empty-input skips,
 * and the availability probe in a bare (no CF7) environment.
 */
final class ContactForm7AdapterTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
	}

	protected function tearDown(): void {
		unset( $_POST['your-email'] );
	}

	/**
	 * @return object A WPCF7_FormTag-like fake with a public name.
	 */
	private function tag( $name = 'your-email' ) {
		$tag       = new \stdClass();
		$tag->name = $name;

		return $tag;
	}

	public function test_id_and_availability_in_bare_env() {
		$adapter = new ContactForm7Adapter();

		$this->assertSame( 'contact_form_7', $adapter->id() );
		$this->assertFalse( $adapter->is_available() );
	}

	public function test_register_adds_both_email_validation_filters() {
		$adapter = new ContactForm7Adapter();
		$adapter->register( FakeGuardService::allowing() );

		$this->assertTrue( has_filter( 'wpcf7_validate_email' ) );
		$this->assertTrue( has_filter( 'wpcf7_validate_email*' ) );
	}

	public function test_denied_decision_invalidates_tag_with_message() {
		$service = FakeGuardService::denying( 'This address is blocked.' );
		$adapter = new ContactForm7Adapter();
		$adapter->register( $service );

		$_POST['your-email'] = "  spam@example.com\\'s  ";

		$tag      = $this->tag();
		$result   = new FakeCf7Validation();
		$returned = apply_filters( 'wpcf7_validate_email', $result, $tag );

		$this->assertSame( $result, $returned );
		$this->assertCount( 1, $result->invalidations );
		$this->assertSame( $tag, $result->invalidations[0]['tag'] );
		$this->assertSame( 'This address is blocked.', $result->invalidations[0]['message'] );

		$this->assertCount( 1, $service->checks );
		$this->assertSame( "spam@example.com's", $service->checks[0]['email'] );
		$this->assertSame( 'contact_form_7', $service->checks[0]['form_id'] );
	}

	public function test_allowed_decision_does_not_invalidate() {
		$service = FakeGuardService::allowing();
		$adapter = new ContactForm7Adapter();
		$adapter->register( $service );

		$_POST['your-email'] = 'fine@example.com';

		$result   = new FakeCf7Validation();
		$returned = apply_filters( 'wpcf7_validate_email*', $result, $this->tag() );

		$this->assertSame( $result, $returned );
		$this->assertSame( array(), $result->invalidations );
		$this->assertCount( 1, $service->checks );
	}

	public function test_flagged_decision_passes_through_untouched() {
		$service = FakeGuardService::flagging();
		$adapter = new ContactForm7Adapter();
		$adapter->register( $service );

		$_POST['your-email'] = 'suspicious@example.com';

		$result   = new FakeCf7Validation();
		$returned = apply_filters( 'wpcf7_validate_email', $result, $this->tag() );

		$this->assertSame( $result, $returned );
		$this->assertSame( array(), $result->invalidations );
		$this->assertCount( 1, $service->checks );
	}

	public function test_missing_post_value_never_calls_the_service() {
		$service = FakeGuardService::denying();
		$adapter = new ContactForm7Adapter();
		$adapter->register( $service );

		$result   = new FakeCf7Validation();
		$returned = apply_filters( 'wpcf7_validate_email', $result, $this->tag( 'absent-field' ) );

		$this->assertSame( $result, $returned );
		$this->assertSame( array(), $result->invalidations );
		$this->assertSame( array(), $service->checks );
	}

	public function test_empty_post_value_never_calls_the_service() {
		$service = FakeGuardService::denying();
		$adapter = new ContactForm7Adapter();
		$adapter->register( $service );

		$_POST['your-email'] = '   ';

		$result = new FakeCf7Validation();
		apply_filters( 'wpcf7_validate_email', $result, $this->tag() );

		$this->assertSame( array(), $result->invalidations );
		$this->assertSame( array(), $service->checks );
	}

	public function test_tag_without_name_returns_result_untouched() {
		$service = FakeGuardService::denying();
		$adapter = new ContactForm7Adapter();
		$adapter->register( $service );

		$result   = new FakeCf7Validation();
		$returned = apply_filters( 'wpcf7_validate_email', $result, new \stdClass() );

		$this->assertSame( $result, $returned );
		$this->assertSame( array(), $service->checks );
	}
}
