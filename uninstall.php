<?php
/**
 * Uninstall: remove every option the plugin created. No custom tables, no
 * transients, no user meta.
 *
 * @package HostSherlock\EmailGuardWp
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$hostsherlock_email_guard_options = array(
	'hostsherlock_email_guard_settings',
	'hostsherlock_email_guard_log',
);

if ( is_multisite() && function_exists( 'get_sites' ) ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $hostsherlock_email_guard_site_id ) {
		switch_to_blog( $hostsherlock_email_guard_site_id );
		foreach ( $hostsherlock_email_guard_options as $hostsherlock_email_guard_option ) {
			delete_option( $hostsherlock_email_guard_option );
		}
		restore_current_blog();
	}
} else {
	foreach ( $hostsherlock_email_guard_options as $hostsherlock_email_guard_option ) {
		delete_option( $hostsherlock_email_guard_option );
	}
}
