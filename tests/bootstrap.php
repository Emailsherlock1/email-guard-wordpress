<?php
/**
 * PHPUnit bootstrap: composer autoload plus a minimal set of WordPress
 * function stubs. The decision engine is plain PHP; the adapters touch only
 * the narrow WP surface stubbed in wp-stubs.php, so the suite runs without
 * a WordPress checkout.
 */

// Every plugin file exits when loaded outside WordPress (direct-access guard).
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require dirname( __DIR__ ) . '/vendor/autoload.php';
require __DIR__ . '/Support/wp-stubs.php';
