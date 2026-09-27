<?php
/**
 * Logs every successful call to a store ability - WooCommerce's own
 * abilities included - using the core hook that fires after an ability
 * runs. Nothing is wrapped here, so reads stay exactly as fast and as
 * unchanged as they were.
 *
 * @package Tillkeeper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return string[] Ability name prefixes that are logged.
 */
function tlkp_logged_namespaces() {
	/**
	 * Filters which ability namespaces the activity log records.
	 *
	 * @param string[] $prefixes Default: WooCommerce and Tillkeeper abilities.
	 */
	return (array) apply_filters( 'tlkp_logged_namespaces', array( 'woocommerce/', TLKP_ABILITY_NAMESPACE . '/' ) );
}

/**
 * @param string $name Ability name.
 * @return bool
 */
function tlkp_is_logged_ability( $name ) {
	foreach ( tlkp_logged_namespaces() as $prefix ) {
		if ( 0 === strpos( (string) $name, (string) $prefix ) ) {
			return true;
		}
	}
	return false;
}

/**
 * @param string $name Ability name.
 * @return string 'read' or 'write', from the ability's own annotations.
 */
function tlkp_ability_kind( $name ) {
	$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( $name ) : null;
	if ( ! $ability ) {
		return 'write';
	}
	$annotations = (array) $ability->get_meta_item( 'annotations', array() );
	return ! empty( $annotations['readonly'] ) ? 'read' : 'write';
}

/**
 * @param string $name  Ability name.
 * @param mixed  $input Ability input.
 * @return array{0: string, 1: int} Object type and ID.
 */
function tlkp_ability_object( $name, $input ) {
	$input = is_array( $input ) ? $input : array();
	$map   = array(
		'product_id'  => 'product',
		'order_id'    => 'order',
		'customer_id' => 'customer',
	);
	foreach ( $map as $key => $type ) {
		if ( ! empty( $input[ $key ] ) ) {
			return array( $type, absint( $input[ $key ] ) );
		}
	}

	$type = '';
	if ( false !== strpos( $name, 'product' ) ) {
		$type = 'product';
	} elseif ( false !== strpos( $name, 'order' ) ) {
		$type = 'order';
	} elseif ( false !== strpos( $name, 'customer' ) ) {
		$type = 'customer';
	}
	return array( $type, ! empty( $input['id'] ) ? absint( $input['id'] ) : 0 );
}

/**
 * @param string $name   Ability name.
 * @param mixed  $input  Ability input.
 * @param mixed  $result Ability result.
 */
function tlkp_on_ability_executed( $name, $input, $result ) {
	if ( ! tlkp_is_logged_ability( $name ) ) {
		return;
	}

	list( $type, $id ) = tlkp_ability_object( $name, $input );

	$outcome = 'ok';
	$note    = '';
	if ( is_array( $result ) && isset( $result['tillkeeper']['status'] ) ) {
		$outcome = (string) $result['tillkeeper']['status'];
		if ( ! empty( $result['tillkeeper']['reasons'] ) && is_array( $result['tillkeeper']['reasons'] ) ) {
			$note = implode( ' ', array_map( 'strval', $result['tillkeeper']['reasons'] ) );
		}
	}

	tlkp_activity_log_record(
		array(
			'ability'     => $name,
			'kind'        => tlkp_ability_kind( $name ),
			'object_type' => $type,
			'object_id'   => $id,
			'outcome'     => $outcome,
			'note'        => $note,
		)
	);
}
add_action( 'wp_after_execute_ability', 'tlkp_on_ability_executed', 10, 3 );
