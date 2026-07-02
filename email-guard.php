<?php
/**
 * Plugin Name: EmailSherlock Email Guard
 * Plugin URI: https://emailsherlock.com/integrations/wordpress-email-guard
 * Description: Blocks disposable and undeliverable email addresses at form submit: comments, registration, WooCommerce, Contact Form 7, WPForms, Gravity Forms.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: EmailSherlock
 * Author URI: https://emailsherlock.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: emailsherlock-email-guard
 *
 * @package EmailSherlock\EmailGuardWp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EMAILSHERLOCK_EMAIL_GUARD_VERSION', '0.1.0' );
define( 'EMAILSHERLOCK_EMAIL_GUARD_FILE', __FILE__ );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'EmailSherlock\\EmailGuardWp\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$path = __DIR__ . '/includes/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_file( $path ) ) {
			require $path;
		}
	}
);

\EmailSherlock\EmailGuardWp\Plugin::boot();
