<?php
/**
 * NinjaFormsAdapter wiring tests.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp\Tests\Adapters {

	use HostSherlock\EmailGuardWp\Adapters\NinjaFormsAdapter;
	use HostSherlock\EmailGuardWp\Tests\Support\FakeGuardService;
	use PHPUnit\Framework\TestCase;

	/**
	 * Fake of Ninja_Forms(): form( $id )->get_fields() returns field models
	 * from a per-test map of form id => [ field id => type ].
	 */
	class NinjaFormsFake {

		/** @var array<int|string, array<int|string, string>> */
		public $forms = array();

		public function form( $form_id ) {
			$types = isset( $this->forms[ $form_id ] ) ? $this->forms[ $form_id ] : array();

			return new class( $types ) {

				/** @var array<int|string, string> */
				private $types;

				public function __construct( array $types ) {
					$this->types = $types;
				}

				public function get_fields() {
					$fields = array();
					foreach ( $this->types as $id => $type ) {
						$fields[ $id ] = new class( $type ) {

							/** @var string */
							private $type;

							public function __construct( string $type ) {
								$this->type = $type;
							}

							public function get_setting( $key ) {
								return 'type' === $key ? $this->type : null;
							}
						};
					}

					return $fields;
				}
			};
		}
	}

	final class NinjaFormsAdapterTest extends TestCase {

		protected function setUp(): void {
			wp_stubs_reset();
			Ninja_Forms()->forms = array(
				3 => array(
					1 => 'textbox',
					2 => 'email',
					4 => 'email',
				),
			);
		}

		/**
		 * @param array $values Field id => submitted value.
		 * @return array Submit data as Ninja Forms passes it to the filter.
		 */
		private function submission( array $values ): array {
			$fields = array();
			foreach ( $values as $id => $value ) {
				$fields[ $id ] = array(
					'id'    => $id,
					'value' => $value,
				);
			}

			return array(
				'id'     => 3,
				'fields' => $fields,
			);
		}

		public function test_id_is_ninja_forms(): void {
			$this->assertSame( 'ninja_forms', ( new NinjaFormsAdapter() )->id() );
		}

		public function test_register_hooks_the_submit_data_filter(): void {
			( new NinjaFormsAdapter() )->register( FakeGuardService::allowing() );

			$this->assertTrue( has_filter( 'ninja_forms_submit_data' ) );
		}

		public function test_denied_decision_adds_the_message_under_the_field_id(): void {
			$service = FakeGuardService::denying( 'This address is blocked.' );
			( new NinjaFormsAdapter() )->register( $service );

			$data = apply_filters( 'ninja_forms_submit_data', $this->submission( array( 2 => ' bad@example.com ' ) ) );

			$this->assertSame( array( 2 => 'This address is blocked.' ), $data['errors']['fields'] );
			$this->assertSame(
				array(
					array(
						'email'   => 'bad@example.com',
						'form_id' => 'ninja_forms',
					),
				),
				$service->checks
			);
		}

		public function test_only_fields_the_saved_form_types_as_email_are_checked(): void {
			$service = FakeGuardService::denying( 'Blocked.' );
			$adapter = new NinjaFormsAdapter();
			$adapter->register( $service );

			// Field 1 holds an address but is a textbox in the saved form; field 9 is not in the form at all.
			$data = $adapter->validate(
				$this->submission(
					array(
						1 => 'text@example.com',
						2 => 'first@example.com',
						4 => 'second@example.com',
						9 => 'rogue@example.com',
					)
				)
			);

			$this->assertSame(
				array(
					2 => 'Blocked.',
					4 => 'Blocked.',
				),
				$data['errors']['fields']
			);
			$this->assertSame( array( 'first@example.com', 'second@example.com' ), array_column( $service->checks, 'email' ) );
		}

		public function test_allowed_and_flagged_decisions_leave_the_data_unchanged(): void {
			foreach ( array( FakeGuardService::allowing(), FakeGuardService::flagging() ) as $service ) {
				$adapter    = new NinjaFormsAdapter();
				$submission = $this->submission( array( 2 => 'fine@example.com' ) );
				$adapter->register( $service );

				$this->assertSame( $submission, $adapter->validate( $submission ) );
				$this->assertCount( 1, $service->checks );
			}
		}

		public function test_empty_non_string_or_already_failed_fields_never_reach_the_service(): void {
			$service = FakeGuardService::denying( 'Blocked.' );
			$adapter = new NinjaFormsAdapter();
			$adapter->register( $service );

			$this->assertSame( array(), $adapter->validate( $this->submission( array( 2 => '  ', 4 => array( 'x@example.com' ) ) ) )['errors']['fields'] ?? array() );

			$failed                        = $this->submission( array( 2 => 'bad@example.com' ) );
			$failed['errors']['fields'][2] = 'Earlier error.';
			$this->assertSame( array( 2 => 'Earlier error.' ), $adapter->validate( $failed )['errors']['fields'] );

			$this->assertSame( array(), $service->checks );
		}

		public function test_malformed_submission_is_returned_untouched(): void {
			$service = FakeGuardService::denying();
			$adapter = new NinjaFormsAdapter();
			$adapter->register( $service );

			$this->assertSame( 'nope', $adapter->validate( 'nope' ) );
			$this->assertSame( array( 'fields' => array() ), $adapter->validate( array( 'fields' => array() ) ) );
			$this->assertSame( array(), $service->checks );
		}
	}
}

namespace {

	if ( ! function_exists( 'Ninja_Forms' ) ) {
		/**
		 * Shared Ninja Forms fake, mirroring the real global accessor.
		 *
		 * @return \HostSherlock\EmailGuardWp\Tests\Adapters\NinjaFormsFake
		 */
		function Ninja_Forms() {
			static $instance = null;

			if ( null === $instance ) {
				$instance = new \HostSherlock\EmailGuardWp\Tests\Adapters\NinjaFormsFake();
			}

			return $instance;
		}
	}
}
