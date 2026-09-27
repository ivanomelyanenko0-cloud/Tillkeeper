<?php
/**
 * Protection settings: which destructive WooCommerce abilities need a
 * human to approve them before they run.
 *
 * @package Tillkeeper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TLKP_SETTINGS_OPTION', 'tlkp_settings' );

/**
 * Safe by default: deleting a product and moving an order to a final state
 * always wait for a person.
 *
 * @return array{guard_product_delete: bool, order_status_mode: string}
 */
function tlkp_default_settings() {
	return array(
		'guard_product_delete' => true,
		'order_status_mode'    => 'final',
	);
}

/**
 * @return array{guard_product_delete: bool, order_status_mode: string}
 */
function tlkp_get_settings() {
	$stored = get_option( TLKP_SETTINGS_OPTION, array() );
	$stored = is_array( $stored ) ? $stored : array();

	return array_merge( tlkp_default_settings(), array_intersect_key( $stored, tlkp_default_settings() ) );
}

/**
 * @param array $input Raw form data; only known keys are read.
 * @return array Saved settings.
 */
function tlkp_update_settings( array $input ) {
	$settings = array(
		'guard_product_delete' => ! empty( $input['guard_product_delete'] ),
		'order_status_mode'    => isset( $input['order_status_mode'] ) && in_array( $input['order_status_mode'], array( 'all', 'final', 'off' ), true ) ? $input['order_status_mode'] : 'final',
	);
	update_option( TLKP_SETTINGS_OPTION, $settings, false );

	return $settings;
}

/**
 * Order statuses an agent should not reach without a person: the ones
 * after which money moves, customers get emails, or stock is released.
 *
 * @return string[] Status slugs without the wc- prefix.
 */
function tlkp_final_order_statuses() {
	/**
	 * Filters which order statuses count as final.
	 *
	 * @param string[] $statuses Default: completed, cancelled, refunded.
	 */
	return (array) apply_filters( 'tlkp_final_order_statuses', array( 'completed', 'cancelled', 'refunded' ) );
}
