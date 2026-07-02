<?php
/**
 * Plugin bootstrap: adapter registry, availability gating, per-form toggles.
 */

namespace EmailSherlock\EmailGuardWp\Tests;

use EmailSherlock\EmailGuardWp\Adapters\AdapterInterface;
use EmailSherlock\EmailGuardWp\Plugin;
use EmailSherlock\EmailGuardWp\Settings;
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

	public function testAdapterRegistryListsAllSixSurfaces(): void {
		$ids = array_map(
			static function ( AdapterInterface $adapter ) {
				return $adapter->id();
			},
			Plugin::boot()->adapters()
		);

		$this->assertSame(
			array( 'comments', 'registration', 'woocommerce', 'contact_form_7', 'wpforms', 'gravity_forms' ),
			$ids
		);
	}

	public function testRegisterAdaptersHooksOnlyAvailableSurfaces(): void {
		Plugin::boot()->register_adapters();

		// Core surfaces are always available.
		$this->assertTrue( has_filter( 'pre_comment_approved' ) );
		$this->assertTrue( has_filter( 'registration_errors' ) );

		// Host plugins are absent in the bare test env, so their adapters
		// never hook in. The WPForms surface is skipped here: its test file
		// defines a global wpforms() fake that makes the adapter available
		// for the rest of the process.
		if ( ! function_exists( 'wpforms' ) ) {
			$this->assertFalse( has_filter( 'wpforms_process' ) );
		}
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
			public function register( \EmailSherlock\EmailGuardWp\GuardService $service ) {
			}
		};
		add_filter(
			'emailsherlock_email_guard_adapters',
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
		$this->assertCount( 7, $ids );
	}
}
