<?php
/**
 * Settings: defaults, policy mapping onto block_on / review_on, sanitize.
 */

namespace HostSherlock\EmailGuardWp\Tests;

use HostSherlock\EmailGuardWp\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		wp_stubs_reset();
	}

	public function testDefaultPolicyMatchesSpecDefaultsPlusRelayFlag(): void {
		$settings = new Settings();

		$this->assertSame( array( 'invalid', 'disposable' ), $settings->block_on() );
		$this->assertSame( array( 'relay' ), $settings->review_on() );
		$this->assertTrue( (bool) $settings->get( 'fail_open' ) );
		$this->assertNull( $settings->api_key() );
		$this->assertNull( $settings->site_key() );
	}

	public function testEveryFormEnabledByDefault(): void {
		$settings = new Settings();
		foreach ( array_keys( Settings::defaults()['forms'] ) as $form_id ) {
			$this->assertTrue( $settings->form_enabled( $form_id ), $form_id );
		}
		$this->assertFalse( $settings->form_enabled( 'nonexistent' ) );
	}

	public function testStoredSettingsMergeOverDefaults(): void {
		update_option(
			Settings::OPTION,
			array(
				'api_key'         => ' key-1 ',
				'site_key'        => 'site-1',
				'verdict_actions' => array( 'role' => 'block' ),
				'forms'           => array( 'comments' => false ),
			)
		);
		$settings = new Settings();

		$this->assertSame( 'key-1', $settings->api_key() );
		$this->assertSame( 'site-1', $settings->site_key() );
		// Partial verdict_actions keeps defaults for the untouched tokens.
		$this->assertSame( array( 'invalid', 'disposable', 'role' ), $settings->block_on() );
		$this->assertFalse( $settings->form_enabled( 'comments' ) );
		$this->assertTrue( $settings->form_enabled( 'registration' ) );
	}

	public function testGuardConfigShape(): void {
		update_option( Settings::OPTION, array( 'api_key' => 'k', 'fail_open' => false ) );
		$config = ( new Settings() )->guard_config();

		$this->assertSame( 'k', $config['api_key'] );
		$this->assertFalse( $config['fail_open'] );
		$this->assertSame( array( 'invalid', 'disposable' ), $config['block_on'] );
		$this->assertSame( array( 'relay' ), $config['review_on'] );
		$this->assertSame( 800, $config['timeout_ms'] );
		$this->assertSame( 'https://api.emailsherlock.com', $config['base_url'] );
	}

	public function testSanitizeCoercesAndClamps(): void {
		$settings = new Settings();
		$clean    = $settings->sanitize(
			array(
				'api_key'         => "  key\n",
				'verdict_actions' => array(
					'invalid' => 'allow',
					'role'    => 'bogus-action',
				),
				'forms'           => array( 'comments' => '1' ),
				'log_size'        => '9999',
				'blocked_message' => '<script>x</script>Hi',
			)
		);

		$this->assertSame( 'key', $clean['api_key'] );
		$this->assertSame( 'allow', $clean['verdict_actions']['invalid'] );
		// Unknown action falls back to the default for that token.
		$this->assertSame( 'allow', $clean['verdict_actions']['role'] );
		// Unchecked boxes are absent from POST and become false.
		$this->assertFalse( $clean['fail_open'] );
		$this->assertFalse( $clean['log_enabled'] );
		$this->assertTrue( $clean['forms']['comments'] );
		$this->assertFalse( $clean['forms']['registration'] );
		$this->assertSame( 200, $clean['log_size'] );
		$this->assertSame( 'xHi', $clean['blocked_message'] );
	}

	public function testSanitizeRejectsWrongShapes(): void {
		$settings = new Settings();
		$clean    = $settings->sanitize(
			array(
				'api_key'         => array( 'smuggled' ),
				'verdict_actions' => 'block',
				'forms'           => 'all',
				'log_size'        => '-5',
				'blocked_message' => array( '<b>x</b>' ),
			)
		);

		$this->assertSame( '', $clean['api_key'] );
		$this->assertSame( '', $clean['blocked_message'] );
		// A scalar where a map belongs: every token keeps its default.
		$this->assertSame( Settings::defaults()['verdict_actions'], $clean['verdict_actions'] );
		$this->assertSame( array_fill_keys( array_keys( Settings::defaults()['forms'] ), false ), $clean['forms'] );
		$this->assertSame( 5, $clean['log_size'] );
		// Unknown keys never reach the stored option.
		$this->assertEqualsCanonicalizing( array_keys( Settings::defaults() ), array_keys( $clean ) );
	}

	public function testSanitizeNormalisesActionCase(): void {
		$clean = ( new Settings() )->sanitize( array( 'verdict_actions' => array( 'role' => 'BLOCK' ) ) );

		$this->assertSame( 'block', $clean['verdict_actions']['role'] );
	}

	public function testBlockedMessageFallsBackToDefault(): void {
		$settings = new Settings();
		$this->assertNotSame( '', $settings->blocked_message() );

		update_option( Settings::OPTION, array( 'blocked_message' => 'Custom message.' ) );
		$this->assertSame( 'Custom message.', ( new Settings() )->blocked_message() );
	}
}
