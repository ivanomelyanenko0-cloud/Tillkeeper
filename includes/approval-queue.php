<?php
/**
 * Human-approval queue. A queued action never hands the agent a way to
 * finish it: only a store manager clicking Approve in wp-admin runs it.
 *
 * Non-autoloaded, capped option, same as the activity log.
 *
 * @package Tillkeeper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TLKP_APPROVAL_QUEUE_OPTION', 'tlkp_approval_queue' );
define( 'TLKP_APPROVAL_QUEUE_LIMIT', 200 );

/**
 * @param string $ability Ability name.
 * @param array  $input   Input.
 * @return string Stable hash of (ability, actor, input).
 */
function tlkp_fingerprint_input( $ability, array $input ) {
	ksort( $input );
	return hash( 'sha256', $ability . '|' . get_current_user_id() . '|' . wp_json_encode( $input ) );
}

/**
 * @param string $ability  Ability name.
 * @param array  $input    Input.
 * @param array  $decision Guard decision (reasons, object_id).
 * @param array  $config   Guard config.
 * @return array The entry; an identical pending request is reused, not duplicated.
 */
function tlkp_approval_queue_add( $ability, array $input, array $decision, array $config ) {
	$fingerprint = tlkp_fingerprint_input( $ability, $input );

	foreach ( tlkp_approval_queue_get_pending() as $existing ) {
		if ( $existing['fingerprint'] === $fingerprint ) {
			return $existing;
		}
	}

	$queue = get_option( TLKP_APPROVAL_QUEUE_OPTION, array() );
	$queue = is_array( $queue ) ? $queue : array();

	$entry = array(
		'id'          => wp_generate_uuid4(),
		'time'        => time(),
		'actor'       => get_current_user_id(),
		'ability'     => (string) $ability,
		'object_type' => (string) $config['object_type'],
		'object_id'   => isset( $decision['object_id'] ) ? absint( $decision['object_id'] ) : 0,
		'input'       => $input,
		'fingerprint' => $fingerprint,
		'reasons'     => isset( $decision['reasons'] ) ? array_map( 'strval', (array) $decision['reasons'] ) : array(),
		'status'      => 'pending',
		'resolved_by' => 0,
		'resolved_at' => 0,
	);

	$queue[] = $entry;
	if ( count( $queue ) > TLKP_APPROVAL_QUEUE_LIMIT ) {
		$queue = array_slice( $queue, -1 * TLKP_APPROVAL_QUEUE_LIMIT );
	}
	update_option( TLKP_APPROVAL_QUEUE_OPTION, $queue, false );

	return $entry;
}

/**
 * @return array Pending entries, oldest first.
 */
function tlkp_approval_queue_get_pending() {
	$queue = get_option( TLKP_APPROVAL_QUEUE_OPTION, array() );
	if ( ! is_array( $queue ) ) {
		return array();
	}

	return array_values(
		array_filter(
			$queue,
			static function ( $entry ) {
				return isset( $entry['status'] ) && 'pending' === $entry['status'];
			}
		)
	);
}

/**
 * @param int $limit Max entries.
 * @return array Resolved entries, most recent first.
 */
function tlkp_approval_queue_get_resolved( $limit = 20 ) {
	$queue = get_option( TLKP_APPROVAL_QUEUE_OPTION, array() );
	if ( ! is_array( $queue ) ) {
		return array();
	}

	$resolved = array_filter(
		$queue,
		static function ( $entry ) {
			return isset( $entry['status'] ) && 'pending' !== $entry['status'];
		}
	);
	return array_slice( array_reverse( $resolved ), 0, max( 0, (int) $limit ) );
}

/**
 * @param string $id Entry ID.
 * @return array|null
 */
function tlkp_approval_queue_get( $id ) {
	foreach ( (array) get_option( TLKP_APPROVAL_QUEUE_OPTION, array() ) as $entry ) {
		if ( isset( $entry['id'] ) && $entry['id'] === $id ) {
			return $entry;
		}
	}
	return null;
}

/**
 * @param array     $entry   Entry.
 * @param string    $status  'approved', 'rejected' or 'failed'.
 * @param int       $user_id Resolver.
 */
function tlkp_approval_queue_set_resolved( array $entry, $status, $user_id ) {
	$queue = get_option( TLKP_APPROVAL_QUEUE_OPTION, array() );
	if ( ! is_array( $queue ) ) {
		return;
	}

	foreach ( $queue as $i => $existing ) {
		if ( isset( $existing['id'] ) && $existing['id'] === $entry['id'] ) {
			$queue[ $i ]['status']      = $status;
			$queue[ $i ]['resolved_by'] = (int) $user_id;
			$queue[ $i ]['resolved_at'] = time();
			break;
		}
	}
	update_option( TLKP_APPROVAL_QUEUE_OPTION, $queue, false );
}

/**
 * Runs an approved entry - the only place a queued action ever executes.
 *
 * @param string $id      Entry ID.
 * @param int    $user_id Approver.
 * @return mixed|WP_Error
 */
function tlkp_approval_queue_approve( $id, $user_id ) {
	$entry = tlkp_approval_queue_get( $id );
	if ( ! $entry || 'pending' !== $entry['status'] ) {
		return new WP_Error( 'tlkp_approval_not_pending', __( 'This request is no longer pending.', 'tillkeeper' ) );
	}

	$guarded = tlkp_guarded_abilities();
	if ( ! isset( $guarded[ $entry['ability'] ] ) ) {
		return new WP_Error( 'tlkp_ability_unavailable', __( 'This ability is no longer guarded by Tillkeeper.', 'tillkeeper' ) );
	}

	$result = tlkp_guard_run(
		$entry['ability'],
		$guarded[ $entry['ability'] ],
		(array) $entry['input'],
		array(
			'via'  => 'approval',
			'risk' => 'high',
		)
	);

	tlkp_approval_queue_set_resolved( $entry, is_wp_error( $result ) ? 'failed' : 'approved', $user_id );
	return $result;
}

/**
 * @param string $id      Entry ID.
 * @param int    $user_id Rejecter.
 * @return true|WP_Error
 */
function tlkp_approval_queue_reject( $id, $user_id ) {
	$entry = tlkp_approval_queue_get( $id );
	if ( ! $entry || 'pending' !== $entry['status'] ) {
		return new WP_Error( 'tlkp_approval_not_pending', __( 'This request is no longer pending.', 'tillkeeper' ) );
	}

	tlkp_approval_queue_set_resolved( $entry, 'rejected', $user_id );
	tlkp_activity_log_record(
		array(
			'ability'     => $entry['ability'],
			'kind'        => 'write',
			'object_type' => $entry['object_type'],
			'object_id'   => $entry['object_id'],
			'outcome'     => 'rejected',
		)
	);
	return true;
}

/**
 * admin-post handler for Approve and Reject. Needs the same capability the
 * guarded abilities require, plus a nonce.
 */
function tlkp_handle_queue_decision() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'tillkeeper' ) );
	}
	check_admin_referer( 'tlkp_queue_decision' );

	$id       = isset( $_POST['approval_id'] ) ? sanitize_text_field( wp_unslash( $_POST['approval_id'] ) ) : '';
	$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';

	if ( 'approve' === $decision ) {
		$notice = is_wp_error( tlkp_approval_queue_approve( $id, get_current_user_id() ) ) ? 'approve_failed' : 'approve_ok';
	} else {
		$notice = is_wp_error( tlkp_approval_queue_reject( $id, get_current_user_id() ) ) ? 'reject_failed' : 'reject_ok';
	}

	wp_safe_redirect( add_query_arg( 'tlkp_notice', $notice, admin_url( 'admin.php?page=tillkeeper' ) ) );
	exit;
}
add_action( 'admin_post_tlkp_queue_decision', 'tlkp_handle_queue_decision' );

/**
 * Shows the number of waiting requests on the menu item.
 *
 * @return int
 */
function tlkp_pending_count() {
	return count( tlkp_approval_queue_get_pending() );
}
