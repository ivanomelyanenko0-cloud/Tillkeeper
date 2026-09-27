<?php
/**
 * Uninstall handler.
 *
 * Settings, the activity log and the approval queue are Tillkeeper's own
 * operational data, not store content, so they are always removed.
 *
 * @package Tillkeeper
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function tlkp_uninstall_site() {
	delete_option( 'tlkp_settings' );
	delete_option( 'tlkp_activity_log' );
	delete_option( 'tlkp_approval_queue' );
	delete_option( 'tlkp_audit_log' ); // Left by pre-release builds.
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $tlkp_site_id ) {
		switch_to_blog( $tlkp_site_id );
		tlkp_uninstall_site();
		restore_current_blog();
	}
} else {
	tlkp_uninstall_site();
}
