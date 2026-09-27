<?php
/**
 * Uninstall handler.
 *
 * The audit log is Tillkeeper's own operational data (what agents read),
 * not merchant content, so it is always removed - there is no store data to
 * decide about yet in this skeleton (no settings, no options beyond the log).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function tlkp_uninstall_site() {
	delete_option( 'tlkp_audit_log' );
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
