<?php
/**
 * Read-only product abilities: `tillkeeper/list-products` and
 * `tillkeeper/get-product` - store data an agent can safely look at,
 * gated behind the same capability a shop manager needs in wp-admin.
 *
 * Deliberately excludes anything resembling a write path (no `set_*` calls
 * on WC_Product anywhere in this file) - that split is what makes the Free
 * vs Pro axis boundary enforceable by review, not just by convention.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Same read gate for both abilities: whoever can edit products in wp-admin
 * can have an agent read them. An agent acts as an authenticated WP user
 * (e.g. an Application Password on a Shop Manager account) - Tillkeeper
 * never exposes store data to anonymous or unauthenticated requests.
 */
function tlkp_can_read_products( $input = null ) {
	return current_user_can( 'edit_products' );
}

/**
 * @param WC_Product $product
 * @return array
 */
function tlkp_format_product_summary( $product ) {
	return array(
		'id'             => $product->get_id(),
		'name'           => $product->get_name(),
		'sku'            => $product->get_sku(),
		'status'         => $product->get_status(),
		'price'          => $product->get_price(),
		'regular_price'  => $product->get_regular_price(),
		'sale_price'     => $product->get_sale_price(),
		'currency'       => get_woocommerce_currency(),
		'stock_status'   => $product->get_stock_status(),
		'stock_quantity' => $product->managing_stock() ? $product->get_stock_quantity() : null,
		'permalink'      => $product->get_permalink(),
	);
}

function tlkp_ability_list_products( $input ) {
	$input = is_array( $input ) ? $input : array();

	$args = array(
		'status'   => 'publish',
		'limit'    => min( 100, max( 1, (int) ( $input['per_page'] ?? 20 ) ) ),
		'page'     => max( 1, (int) ( $input['page'] ?? 1 ) ),
		'orderby'  => 'title',
		'order'    => 'ASC',
		'paginate' => true,
	);
	if ( ! empty( $input['search'] ) ) {
		$args['s'] = sanitize_text_field( $input['search'] );
	}

	$result = wc_get_products( $args );

	$products = array_map( 'tlkp_format_product_summary', $result->products );

	tlkp_audit_log_record( TLKP_ABILITY_NAMESPACE . '/list-products', 'product', 0, count( $products ), true );

	return array(
		'products' => $products,
		'total'    => $result->total,
		'page'     => $args['page'],
	);
}

function tlkp_ability_get_product( $input ) {
	$product_id = isset( $input['product_id'] ) ? (int) $input['product_id'] : 0;
	$product    = $product_id ? wc_get_product( $product_id ) : false;

	if ( ! $product ) {
		tlkp_audit_log_record( TLKP_ABILITY_NAMESPACE . '/get-product', 'product', $product_id, 0, false );

		return new WP_Error(
			'tlkp_product_not_found',
			__( 'No product exists with that ID.', 'tillkeeper' ),
			array( 'status' => 404 )
		);
	}

	tlkp_audit_log_record( TLKP_ABILITY_NAMESPACE . '/get-product', 'product', $product_id, 1, true );

	return tlkp_format_product_summary( $product );
}

function tlkp_register_product_abilities() {
	wp_register_ability(
		TLKP_ABILITY_NAMESPACE . '/list-products',
		array(
			'label'               => __( 'List products', 'tillkeeper' ),
			'description'         => __( 'Lists published WooCommerce products with price and stock, optionally filtered by a search term.', 'tillkeeper' ),
			'category'            => TLKP_ABILITY_NAMESPACE,
			'execute_callback'    => 'tlkp_ability_list_products',
			'permission_callback' => 'tlkp_can_read_products',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'search'   => array(
						'type'        => 'string',
						'description' => __( 'Optional search term matched against the product title.', 'tillkeeper' ),
					),
					'per_page' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 100,
						'description' => __( 'Results per page, capped at 100.', 'tillkeeper' ),
					),
					'page'     => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Page number, starting at 1.', 'tillkeeper' ),
					),
				),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'products' => array( 'type' => 'array' ),
					'total'    => array( 'type' => 'integer' ),
					'page'     => array( 'type' => 'integer' ),
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
		TLKP_ABILITY_NAMESPACE . '/get-product',
		array(
			'label'               => __( 'Get product', 'tillkeeper' ),
			'description'         => __( 'Fetches one WooCommerce product by ID, with price and stock.', 'tillkeeper' ),
			'category'            => TLKP_ABILITY_NAMESPACE,
			'execute_callback'    => 'tlkp_ability_get_product',
			'permission_callback' => 'tlkp_can_read_products',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'product_id' => array(
						'type'        => 'integer',
						'description' => __( 'The product ID to look up.', 'tillkeeper' ),
					),
				),
				'required'   => array( 'product_id' ),
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
add_action( 'wp_abilities_api_init', 'tlkp_register_product_abilities' );
