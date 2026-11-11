<?php
/**
 * WpFormsAdapter wiring tests.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Tests\Adapters {

	use HostSherlock\EmailGuardWp\Adapters\WpFormsAdapter;
	use HostSherlock\EmailGuardWp\Tests\Support\FakeGuardService;
	use PHPUnit\Framework\TestCase;

	/**
	 * Fake of the WPForms plugin container: only the process error bag,
	 * which is all the adapter touches.
	 */
	class WpFormsPluginFake {

		/** @var WpFormsProcessFake */
		public $process;

		public function __construct() {
			$this->process = new WpFormsProcessFake();
		}
	}

	/**
	 * Fake of WPForms' process object with its public error bag.
	 */
	class WpFormsProcessFake {

		/** @var array<int|string, array<int|string, string>> */
		public $errors = array();
	}

	class WpFormsAdapterTest extends TestCase {

		protected function setUp(): void {
			parent::setUp();
			wp_stubs_reset();
			wpforms()->process->errors = array();
		}

		public function test_id_is_wpforms() {
			$adapter = new WpFormsAdapter();

			$this->assertSame( 'wpforms', $adapter->id() );
		}

		public function test_is_available_when_wpforms_function_exists() {
			$adapter = new WpFormsAdapter();

			$this->assertTrue( $adapter->is_available() );
		}

		public function test_register_hooks_wpforms_process() {
			$adapter = new WpFormsAdapter();
			$adapter->register( FakeGuardService::allowing() );

			$this->assertTrue( has_filter( 'wpforms_process' ) );
		}

		public function test_denied_decision_writes_error_into_wpforms_error_bag() {
			$service = FakeGuardService::denying( 'This address is blocked.' );
			$adapter = new WpFormsAdapter();
			$adapter->register( $service );

			do_action(
				'wpforms_process',
				array(
					3 => array(
						'type'  => 'email',
						'value' => 'bad@example.com',
					),
				),
				array(),
				array( 'id' => 42 )
			);

			$this->assertSame( 'This address is blocked.', wpforms()->process->errors[42][3] );
			$this->assertSame(
				array(
					array(
						'email'   => 'bad@example.com',
						'form_id' => 'wpforms',
					),
				),
				$service->checks
			);
		}

		public function test_non_email_and_empty_email_fields_never_reach_the_service() {
			$service = FakeGuardService::denying();
			$adapter = new WpFormsAdapter();
			$adapter->register( $service );

			do_action(
				'wpforms_process',
				array(
					1 => array(
						'type'  => 'text',
						'value' => 'someone@example.com',
					),
					2 => array(
						'type'  => 'email',
						'value' => '',
					),
					3 => array(
						'type'  => 'email',
						'value' => '   ',
					),
					4 => array(
						'type'  => 'email',
						'value' => array( 'not', 'a', 'string' ),
					),
					5 => 'not-an-array-field',
				),
				array(),
				array( 'id' => 42 )
			);

			$this->assertSame( array(), $service->checks );
			$this->assertSame( array(), wpforms()->process->errors );
		}

		public function test_allowed_decision_leaves_error_bag_empty() {
			$service = FakeGuardService::allowing();
			$adapter = new WpFormsAdapter();
			$adapter->register( $service );

			do_action(
				'wpforms_process',
				array(
					3 => array(
						'type'  => 'email',
						'value' => 'fine@example.com',
					),
				),
				array(),
				array( 'id' => 42 )
			);

			$this->assertCount( 1, $service->checks );
			$this->assertSame( array(), wpforms()->process->errors );
		}

		public function test_flagged_decision_passes_through_untouched() {
			$service = FakeGuardService::flagging();
			$adapter = new WpFormsAdapter();
			$adapter->register( $service );

			do_action(
				'wpforms_process',
				array(
					3 => array(
						'type'  => 'email',
						'value' => 'suspicious@example.com',
					),
				),
				array(),
				array( 'id' => 42 )
			);

			$this->assertCount( 1, $service->checks );
			$this->assertSame( array(), wpforms()->process->errors );
		}

		public function test_multiple_email_fields_each_get_checked_and_their_own_error() {
			$service = FakeGuardService::denying( 'Blocked.' );
			$adapter = new WpFormsAdapter();
			$adapter->register( $service );

			do_action(
				'wpforms_process',
				array(
					1 => array(
						'type'  => 'email',
						'value' => 'first@example.com',
					),
					2 => array(
						'type'  => 'text',
						'value' => 'hello',
					),
					5 => array(
						'type'  => 'email',
						'value' => 'second@example.com',
					),
				),
				array(),
				array( 'id' => 7 )
			);

			$this->assertSame(
				array(
					array(
						'email'   => 'first@example.com',
						'form_id' => 'wpforms',
					),
					array(
						'email'   => 'second@example.com',
						'form_id' => 'wpforms',
					),
				),
				$service->checks
			);
			$this->assertSame( 'Blocked.', wpforms()->process->errors[7][1] );
			$this->assertSame( 'Blocked.', wpforms()->process->errors[7][5] );
		}

		public function test_missing_form_id_returns_early() {
			$service = FakeGuardService::denying();
			$adapter = new WpFormsAdapter();
			$adapter->register( $service );

			$adapter->validate(
				array(
					3 => array(
						'type'  => 'email',
						'value' => 'bad@example.com',
					),
				),
				array(),
				array()
			);

			$this->assertSame( array(), $service->checks );
			$this->assertSame( array(), wpforms()->process->errors );
		}

		public function test_non_array_fields_returns_early() {
			$service = FakeGuardService::denying();
			$adapter = new WpFormsAdapter();
			$adapter->register( $service );

			$adapter->validate( null, array(), array( 'id' => 42 ) );

			$this->assertSame( array(), $service->checks );
			$this->assertSame( array(), wpforms()->process->errors );
		}
	}
}

namespace {

	if ( ! function_exists( 'wpforms' ) ) {
		/**
		 * Shared WPForms plugin fake, mirroring the real global accessor. The
		 * adapter under test and the assertions both resolve to this instance.
		 *
		 * @return \HostSherlock\EmailGuardWp\Tests\Adapters\WpFormsPluginFake
		 */
		function wpforms() {
			static $instance = null;

			if ( null === $instance ) {
				$instance = new \HostSherlock\EmailGuardWp\Tests\Adapters\WpFormsPluginFake();
			}

			return $instance;
		}
	}
}
