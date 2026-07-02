<?php
/**
 * Uninstall: remove every option the plugin created. No custom tables, no
 * transients, no user meta.
 *
 * @package EmailSherlock\EmailGuardWp
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$emailsherlock_email_guard_options = array(
	'emailsherlock_email_guard_settings',
	'emailsherlock_email_guard_log',
);

if ( is_multisite() && function_exists( 'get_sites' ) ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $emailsherlock_email_guard_site_id ) {
		switch_to_blog( $emailsherlock_email_guard_site_id );
		foreach ( $emailsherlock_email_guard_options as $emailsherlock_email_guard_option ) {
			delete_option( $emailsherlock_email_guard_option );
		}
		restore_current_blog();
	}
} else {
	foreach ( $emailsherlock_email_guard_options as $emailsherlock_email_guard_option ) {
		delete_option( $emailsherlock_email_guard_option );
	}
}
