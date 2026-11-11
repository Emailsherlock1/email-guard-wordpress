<?php
/**
 * Plugin bootstrap: adapter registry, availability gating, per-form toggles.
 */

namespace HostSherlock\EmailGuardWp\Tests;

use HostSherlock\EmailGuardWp\Adapters\AdapterInterface;
use HostSherlock\EmailGuardWp\Plugin;
use HostSherlock\EmailGuardWp\Settings;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
		$this->reset_singleton();
	}

	protected function tearDown(): void {
		$this->reset_singleton();
	}

	private function reset_singleton(): void {
		$property = new \ReflectionProperty( Plugin::class, 'instance' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}
		$property->setValue( null, null );
	}

	public function testBootRegistersLifecycleHooks(): void {
		Plugin::boot();

		$this->assertTrue( has_filter( 'plugins_loaded' ) );
		$this->assertTrue( has_filter( 'shutdown' ) );
	}

	public function testAdapterRegistryListsAllTenSurfaces(): void {
		$ids = array_map(
			static function ( AdapterInterface $adapter ) {
				return $adapter->id();
			},
			Plugin::boot()->adapters()
		);

		$this->assertSame(
			array( 'comments', 'registration', 'woocommerce', 'contact_form_7', 'wpforms', 'gravity_forms', 'elementor_forms', 'fluent_forms', 'ninja_forms', 'formidable_forms' ),
			$ids
		);
	}

	public function testRegisterAdaptersHooksOnlyAvailableSurfaces(): void {
		Plugin::boot()->register_adapters();

		// Core surfaces are always available.
		$this->assertTrue( has_filter( 'pre_comment_approved' ) );
		$this->assertTrue( has_filter( 'registration_errors' ) );

		// Host plugins are absent in the bare test env, so their adapters
		// never hook in. The WPForms and Ninja Forms surfaces are skipped
		// here: their test files define global wpforms() / Ninja_Forms()
		// fakes that make the adapter available for the rest of the process.
		if ( ! function_exists( 'wpforms' ) ) {
			$this->assertFalse( has_filter( 'wpforms_process' ) );
		}
		if ( ! function_exists( 'Ninja_Forms' ) ) {
			$this->assertFalse( has_filter( 'ninja_forms_submit_data' ) );
		}
		$this->assertFalse( has_filter( 'elementor_pro/forms/validation/email' ) );
		$this->assertFalse( has_filter( 'fluentform/validate_input_item_input_email' ) );
		$this->assertFalse( has_filter( 'frm_validate_email_field_entry' ) );
		$this->assertFalse( has_filter( 'gform_field_validation' ) );
		$this->assertFalse( has_filter( 'wpcf7_validate_email' ) );
		$this->assertFalse( has_filter( 'woocommerce_after_checkout_validation' ) );
	}

	public function testDisabledFormToggleSkipsAvailableAdapter(): void {
		update_option( Settings::OPTION, array( 'forms' => array( 'comments' => false ) ) );

		Plugin::boot()->register_adapters();

		$this->assertFalse( has_filter( 'pre_comment_approved' ) );
		$this->assertTrue( has_filter( 'registration_errors' ) );
	}

	public function testAdapterFilterCanExtendAndRejectsForeignObjects(): void {
		$extra = new class() implements AdapterInterface {
			public function id() {
				return 'custom';
			}
			public function label() {
				return 'Custom';
			}
			public function is_available() {
				return false;
			}
			public function register( \HostSherlock\EmailGuardWp\GuardService $service ) {
			}
		};
		add_filter(
			'hostsherlock_email_guard_adapters',
			static function ( $adapters ) use ( $extra ) {
				$adapters[] = $extra;
				$adapters[] = new \stdClass();

				return $adapters;
			}
		);

		$ids = array_map(
			static function ( AdapterInterface $adapter ) {
				return $adapter->id();
			},
			Plugin::boot()->adapters()
		);

		$this->assertContains( 'custom', $ids );
		$this->assertCount( 11, $ids );
	}
}
