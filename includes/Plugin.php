<?php
/**
 * Plugin bootstrap: wires settings, service, adapters, and admin.
 *
 * @package HostSherlock\EmailGuardWp
 */

namespace HostSherlock\EmailGuardWp;

use HostSherlock\EmailGuardWp\Adapters\AdapterInterface;
use HostSherlock\EmailGuardWp\Adapters\CommentsAdapter;
use HostSherlock\EmailGuardWp\Adapters\ContactForm7Adapter;
use HostSherlock\EmailGuardWp\Adapters\ElementorFormsAdapter;
use HostSherlock\EmailGuardWp\Adapters\FluentFormsAdapter;
use HostSherlock\EmailGuardWp\Adapters\FormidableFormsAdapter;
use HostSherlock\EmailGuardWp\Adapters\GravityFormsAdapter;
use HostSherlock\EmailGuardWp\Adapters\NinjaFormsAdapter;
use HostSherlock\EmailGuardWp\Adapters\RegistrationAdapter;
use HostSherlock\EmailGuardWp\Adapters\WooCommerceAdapter;
use HostSherlock\EmailGuardWp\Adapters\WpFormsAdapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One instance per request. Adapters self-report availability, so a form
 * plugin that is not installed costs one class_exists call and nothing else.
 */
class Plugin {

	/** @var Plugin|null */
	private static $instance = null;

	/** @var Settings */
	private $settings;

	/** @var GuardService */
	private $service;

	/** @var AdapterInterface[]|null */
	private $adapters = null;

	private function __construct() {
		$this->settings = new Settings();
		$this->service  = new GuardService( $this->settings );
	}

	/**
	 * Build the singleton and attach all hooks. Called once from the main
	 * plugin file.
	 *
	 * @return Plugin
	 */
	public static function boot() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * @return Plugin
	 */
	public static function instance() {
		return self::boot();
	}

	/**
	 * All adapters the plugin knows, available or not. The settings screen
	 * lists them all; register_adapters() only hooks the live ones.
	 *
	 * @return AdapterInterface[]
	 */
	public function adapters() {
		if ( null === $this->adapters ) {
			$adapters = array(
				new CommentsAdapter(),
				new RegistrationAdapter(),
				new WooCommerceAdapter(),
				new ContactForm7Adapter(),
				new WpFormsAdapter(),
				new GravityFormsAdapter(),
				new ElementorFormsAdapter(),
				new FluentFormsAdapter(),
				new NinjaFormsAdapter(),
				new FormidableFormsAdapter(),
			);

			/**
			 * Filter the adapter list. Add an object implementing
			 * AdapterInterface to guard another form plugin.
			 *
			 * @param AdapterInterface[] $adapters Adapter instances.
			 */
			$adapters = apply_filters( 'hostsherlock_email_guard_adapters', $adapters );

			$this->adapters = array_values(
				array_filter(
					is_array( $adapters ) ? $adapters : array(),
					static function ( $adapter ) {
						return $adapter instanceof AdapterInterface;
					}
				)
			);
		}

		return $this->adapters;
	}

	/**
	 * Hook every adapter whose host plugin is present and whose per-form
	 * toggle is on. Runs late on plugins_loaded so class_exists probes see
	 * every other plugin.
	 */
	public function register_adapters() {
		foreach ( $this->adapters() as $adapter ) {
			if ( $adapter->is_available() && $this->settings->form_enabled( $adapter->id() ) ) {
				$adapter->register( $this->service );
			}
		}
	}

	/**
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}

	/**
	 * @return GuardService
	 */
	public function service() {
		return $this->service;
	}

	private function init() {
		add_action( 'plugins_loaded', array( $this, 'register_adapters' ), 20 );

		// Telemetry ships after the response, never in the visitor's path.
		add_action( 'shutdown', array( $this->service, 'flush_telemetry' ), 20 );

		if ( is_admin() ) {
			$page = new Admin\SettingsPage( $this );
			$page->register();

			$test = new Admin\ConnectionTest( $this->settings );
			$test->register();
		}
	}
}
