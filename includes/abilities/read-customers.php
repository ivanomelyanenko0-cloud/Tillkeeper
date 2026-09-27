<?php
/**
 * Read-only customer abilities: `tillkeeper/list-customers` and
 * `tillkeeper/get-customer`. Deliberately left out of the first skeleton
 * pass pending a PII decision.
 *
 * Unlike products/orders, WooCommerce does not register a native customer
 * read ability (checked 2026-09-23, WooCommerce 11.0.1), so this one does
 * not duplicate core.
 *
 * PII policy: a customer's name/email is the entire point of this ability
 * (an agent asking "who is customer #42" needs an answer), so the ability's
 * *output* includes it - same as WooCommerce's own REST API would. The
 * activity log never does: only the customer's numeric ID is recorded.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customers are WP users, not a WooCommerce-specific object, so the gate is
 * the WP capability for reading user accounts rather than a WooCommerce one.
 * `shop_manager` has `list_users` by default alongside `manage_woocommerce`.
 */
function tlkp_can_read_customers( $input = null ) {
	return current_user_can( 'list_users' );
}

/**
 * @param WC_Customer $customer
 * @return array
 */
function tlkp_format_customer_summary( $customer ) {
	return array(
		'id'                 => $customer->get_id(),
		'email'              => $customer->get_email(),
		'first_name'         => $customer->get_first_name(),
		'last_name'          => $customer->get_last_name(),
		'username'           => $customer->get_username(),
		'date_created'       => $customer->get_date_created() ? $customer->get_date_created()->date( 'c' ) : null,
		'is_paying_customer' => $customer->get_is_paying_customer(),
		'order_count'        => wc_get_customer_order_count( $customer->get_id() ),
		'total_spent'        => wc_get_customer_total_spent( $customer->get_id() ),
	);
}

function tlkp_ability_list_customers( $input ) {
	$input = is_array( $input ) ? $input : array();

	$args = array(
		'role'    => 'customer',
		'number'  => min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) ),
		'paged'   => max( 1, (int) ( $input['page'] ?? 1 ) ),
		'orderby' => 'registered',
		'order'   => 'DESC',
	);
	if ( ! empty( $input['search'] ) ) {
		$args['search']         = '*' . sanitize_text_field( $input['search'] ) . '*';
		$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
	}

	$user_query = new WP_User_Query( $args );
	$customers  = array_map(
		function ( $user ) {
			return tlkp_format_customer_summary( new WC_Customer( $user->ID ) );
		},
		$user_query->get_results()
	);

	return array(
		'customers' => $customers,
		'total'     => (int) $user_query->get_total(),
		'page'      => $args['paged'],
	);
}

function tlkp_ability_get_customer( $input ) {
	$customer_id = isset( $input['customer_id'] ) ? (int) $input['customer_id'] : 0;
	$user        = $customer_id ? get_userdata( $customer_id ) : false;

	if ( ! $user ) {
		return new WP_Error(
			'tlkp_customer_not_found',
			__( 'No customer exists with that ID.', 'tillkeeper' ),
			array( 'status' => 404 )
		);
	}

	$summary = tlkp_format_customer_summary( new WC_Customer( $customer_id ) );

	return $summary;
}

function tlkp_register_customer_abilities() {
	wp_register_ability(
		TLKP_ABILITY_NAMESPACE . '/list-customers',
		array(
			'label'               => __( 'List customers', 'tillkeeper' ),
			'description'         => __( 'Lists WooCommerce customer accounts with contact details and lifetime order stats, optionally filtered by a search term.', 'tillkeeper' ),
			'category'            => TLKP_ABILITY_NAMESPACE,
			'execute_callback'    => 'tlkp_ability_list_customers',
			'permission_callback' => 'tlkp_can_read_customers',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'search'   => array(
						'type'        => 'string',
						'description' => __( 'Optional search term matched against username, email or display name.', 'tillkeeper' ),
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
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'customers' => array( 'type' => 'array' ),
					'total'     => array( 'type' => 'integer' ),
					'page'      => array( 'type' => 'integer' ),
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
		TLKP_ABILITY_NAMESPACE . '/get-customer',
		array(
			'label'               => __( 'Get customer', 'tillkeeper' ),
			'description'         => __( 'Fetches one customer account by ID, with contact details and lifetime order stats.', 'tillkeeper' ),
			'category'            => TLKP_ABILITY_NAMESPACE,
			'execute_callback'    => 'tlkp_ability_get_customer',
			'permission_callback' => 'tlkp_can_read_customers',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'customer_id' => array(
						'type'        => 'integer',
						'description' => __( 'The customer\'s user ID.', 'tillkeeper' ),
					),
				),
				'required'   => array( 'customer_id' ),
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
add_action( 'wp_abilities_api_init', 'tlkp_register_customer_abilities' );
