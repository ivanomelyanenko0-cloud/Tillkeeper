<?php
/**
 * Read-only audit log: records every ability execution so a store owner can
 * see what an agent looked at. Deliberately PII-free by design - entries
 * store object IDs and counts, never customer names, emails, addresses, or
 * any other personal data, even though the ability's own return value to
 * the agent may legitimately include it.
 *
 * Stored as a single non-autoloaded option, capped at TLKP_AUDIT_LOG_LIMIT
 * entries (oldest dropped first) - the same "aggregate in one option, prune
 * on write" approach IntelliDesc uses for usage stats, sized for a log of
 * discrete events instead of daily buckets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TLKP_AUDIT_LOG_OPTION', 'tlkp_audit_log' );
define( 'TLKP_AUDIT_LOG_LIMIT', 200 );

/**
 * @param string     $ability     Full ability name, e.g. 'tillkeeper/list-products'.
 * @param string     $object_type Short label for what was read, e.g. 'product', 'order'.
 * @param int|string $object_id   The object's ID, or 0/'' for a list-type ability.
 * @param int        $count       Number of objects returned (1 for a single-object read).
 * @param bool       $success     Whether the ability's execute_callback succeeded.
 */
function tlkp_audit_log_record( $ability, $object_type, $object_id, $count, $success ) {
	$log = get_option( TLKP_AUDIT_LOG_OPTION, array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}

	$log[] = array(
		'time'        => time(),
		'actor'       => get_current_user_id(),
		'ability'     => (string) $ability,
		'object_type' => (string) $object_type,
		'object_id'   => is_int( $object_id ) ? $object_id : (string) $object_id,
		'count'       => max( 0, (int) $count ),
		'success'     => (bool) $success,
	);

	if ( count( $log ) > TLKP_AUDIT_LOG_LIMIT ) {
		$log = array_slice( $log, -1 * TLKP_AUDIT_LOG_LIMIT );
	}

	update_option( TLKP_AUDIT_LOG_OPTION, $log, false );
}

/**
 * @param int $limit Max entries to return.
 * @return array Most recent entries first.
 */
function tlkp_audit_log_get( $limit = 50 ) {
	$log = get_option( TLKP_AUDIT_LOG_OPTION, array() );
	if ( ! is_array( $log ) ) {
		return array();
	}

	$log = array_reverse( $log );

	return array_slice( $log, 0, max( 0, (int) $limit ) );
}

function tlkp_audit_log_clear() {
	delete_option( TLKP_AUDIT_LOG_OPTION );
}
