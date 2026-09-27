<?php
/**
 * Read-only order abilities: `tillkeeper/list-orders` and
 * `tillkeeper/get-order`. HPOS-safe (uses `wc_get_orders()`/`wc_get_order()`,
 * never queries the `shop_order` post type directly, per WooCommerce's
 * High-Performance Order Storage).
 *
 * Deliberately excludes customer PII (name, email, address, phone) from both
 * the ability output and the audit log. Only the customer's numeric user
 * ID is exposed, same as a WooCommerce
 * REST API response scoped without the `read_private_orders`-level detail.
 * A dedicated customer-read ability now exists (includes/abilities/read-customers.php)
 * for when an agent actually needs contact details, kept separate so it can be
 * called deliberately rather than leaking through every order read.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tlkp_can_read_orders( $input = null ) {
	return current_user_can( 'edit_shop_orders' );
}

/**
 * @param WC_Order $order
 * @return array
 */
function tlkp_format_order_summary( $order ) {
	return array(
		'id'          => $order->get_id(),
		'status'      => $order->get_status(),
		'total'       => $order->get_total(),
		'currency'    => $order->get_currency(),
		'date_created'=> $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
		'item_count'  => $order->get_item_count(),
		'customer_id' => $order->get_customer_id(),
	);
}

function tlkp_ability_list_orders( $input ) {
	$input = is_array( $input ) ? $input : array();

	$args = array(
		'limit'   => min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) ),
		'page'    => max( 1, (int) ( $input['page'] ?? 1 ) ),
		'orderby' => 'date',
		'order'   => 'DESC',
		'return'  => 'objects',
	);
	if ( ! empty( $input['status'] ) ) {
		$args['status'] = sanitize_key( $input['status'] );
	}

	$orders = array_map( 'tlkp_format_order_summary', wc_get_orders( $args ) );

	tlkp_audit_log_record( TLKP_ABILITY_NAMESPACE . '/list-orders', 'order', 0, count( $orders ), true );

	return array(
		'orders' => $orders,
		'page'   => $args['page'],
	);
}

function tlkp_ability_get_order( $input ) {
	$order_id = isset( $input['order_id'] ) ? (int) $input['order_id'] : 0;
	$order    = $order_id ? wc_get_order( $order_id ) : false;

	if ( ! $order ) {
		tlkp_audit_log_record( TLKP_ABILITY_NAMESPACE . '/get-order', 'order', $order_id, 0, false );

		return new WP_Error(
			'tlkp_order_not_found',
			__( 'No order exists with that ID.', 'tillkeeper' ),
			array( 'status' => 404 )
		);
	}

	$summary          = tlkp_format_order_summary( $order );
	$summary['items'] = array();
	foreach ( $order->get_items() as $item ) {
		$summary['items'][] = array(
			'product_id' => $item->get_product_id(),
			'name'       => $item->get_name(),
			'quantity'   => $item->get_quantity(),
			'subtotal'   => $item->get_subtotal(),
			'total'      => $item->get_total(),
		);
	}

	tlkp_audit_log_record( TLKP_ABILITY_NAMESPACE . '/get-order', 'order', $order_id, 1, true );

	return $summary;
}

function tlkp_register_order_abilities() {
	wp_register_ability(
		TLKP_ABILITY_NAMESPACE . '/list-orders',
		array(
			'label'               => __( 'List orders', 'tillkeeper' ),
			'description'         => __( 'Lists WooCommerce orders (status, total, item count) without customer personal data, optionally filtered by status.', 'tillkeeper' ),
			'category'            => TLKP_ABILITY_NAMESPACE,
			'execute_callback'    => 'tlkp_ability_list_orders',
			'permission_callback' => 'tlkp_can_read_orders',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'status'   => array(
						'type'        => 'string',
						'description' => __( 'Optional order status to filter by, e.g. "processing" or "completed".', 'tillkeeper' ),
					),
					'per_page' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
					),
					'page'     => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			),
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'public'      => true,
			),
		)
	);

	wp_register_ability(
		TLKP_ABILITY_NAMESPACE . '/get-order',
		array(
			'label'               => __( 'Get order', 'tillkeeper' ),
			'description'         => __( 'Fetches one WooCommerce order by ID, with its line items, but no customer personal data.', 'tillkeeper' ),
			'category'            => TLKP_ABILITY_NAMESPACE,
			'execute_callback'    => 'tlkp_ability_get_order',
			'permission_callback' => 'tlkp_can_read_orders',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'order_id' => array(
						'type'        => 'integer',
						'description' => __( 'The order ID to look up.', 'tillkeeper' ),
					),
				),
				'required'   => array( 'order_id' ),
			),
			'meta'                => array(
				'annotations' => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'public'      => true,
			),
		)
	);
}
add_action( 'wp_abilities_api_init', 'tlkp_register_order_abilities' );
