<?php
/**
 * Plugin Name: HostSherlock Email Guard
 * Plugin URI: https://www.hostsherlock.com/integrations/wordpress-email-guard
 * Description: Blocks disposable and undeliverable email addresses at form submit: comments, registration, WooCommerce, Contact Form 7, WPForms, Gravity Forms.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: HostSherlock
 * Author URI: https://www.hostsherlock.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hostsherlock-email-guard
 *
 * @package HostSherlock\EmailGuardWp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HOSTSHERLOCK_EMAIL_GUARD_VERSION', '0.1.0' );
define( 'HOSTSHERLOCK_EMAIL_GUARD_FILE', __FILE__ );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'HostSherlock\\EmailGuardWp\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$path = __DIR__ . '/includes/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_file( $path ) ) {
			require $path;
		}
	}
);

\HostSherlock\EmailGuardWp\Plugin::boot();
