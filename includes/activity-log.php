<?php
/**
 * Activity log: every call an AI agent makes to a store ability - reads,
 * writes, and what the guard decided. PII-free by design: entries hold
 * ability names, object IDs and outcomes, never names, emails or
 * addresses, even when the ability's own answer to the agent includes them.
 *
 * Stored as a single non-autoloaded option, capped at TLKP_ACTIVITY_LOG_LIMIT
 * entries (oldest dropped first).
 *
 * @package Tillkeeper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TLKP_ACTIVITY_LOG_OPTION', 'tlkp_activity_log' );
define( 'TLKP_ACTIVITY_LOG_LIMIT', 500 );

/**
 * @param array $entry {
 *     @type string $ability     Full ability name.
 *     @type string $kind        'read' or 'write'.
 *     @type string $object_type 'product', 'order', 'customer', ... or ''.
 *     @type int    $object_id   Object ID, 0 for list calls.
 *     @type string $outcome     'ok', 'error', 'pending_approval', 'preview', 'applied',
 *                               'approved', 'rejected'.
 *     @type string $note        Short, PII-free detail (reason, error code).
 *     @type int    $actor       User ID; defaults to the current user.
 * }
 */
function tlkp_activity_log_record( array $entry ) {
	$log = get_option( TLKP_ACTIVITY_LOG_OPTION, array() );
	$log = is_array( $log ) ? $log : array();

	$log[] = array(
		'id'          => wp_generate_uuid4(),
		'time'        => time(),
		'actor'       => isset( $entry['actor'] ) ? (int) $entry['actor'] : get_current_user_id(),
		'ability'     => isset( $entry['ability'] ) ? (string) $entry['ability'] : '',
		'kind'        => isset( $entry['kind'] ) && 'write' === $entry['kind'] ? 'write' : 'read',
		'object_type' => isset( $entry['object_type'] ) ? (string) $entry['object_type'] : '',
		'object_id'   => isset( $entry['object_id'] ) ? absint( $entry['object_id'] ) : 0,
		'outcome'     => isset( $entry['outcome'] ) ? sanitize_key( $entry['outcome'] ) : 'ok',
		'note'        => isset( $entry['note'] ) ? substr( sanitize_text_field( (string) $entry['note'] ), 0, 200 ) : '',
	);

	if ( count( $log ) > TLKP_ACTIVITY_LOG_LIMIT ) {
		$log = array_slice( $log, -1 * TLKP_ACTIVITY_LOG_LIMIT );
	}

	update_option( TLKP_ACTIVITY_LOG_OPTION, $log, false );
}

/**
 * @param int    $limit Max entries.
 * @param string $kind  '' for all, 'read' or 'write'.
 * @return array Most recent first.
 */
function tlkp_activity_log_get( $limit = 50, $kind = '' ) {
	$log = get_option( TLKP_ACTIVITY_LOG_OPTION, array() );
	if ( ! is_array( $log ) ) {
		return array();
	}

	$log = array_reverse( $log );
	if ( '' !== $kind ) {
		$log = array_values(
			array_filter(
				$log,
				static function ( $entry ) use ( $kind ) {
					return isset( $entry['kind'] ) && $entry['kind'] === $kind;
				}
			)
		);
	}
	return array_slice( $log, 0, max( 0, (int) $limit ) );
}

/**
 * Empties the log.
 */
function tlkp_activity_log_clear() {
	delete_option( TLKP_ACTIVITY_LOG_OPTION );
}
