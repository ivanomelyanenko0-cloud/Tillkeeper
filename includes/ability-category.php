<?php
/**
 * Registers the ability category every Tillkeeper ability belongs to.
 * Categories must be registered before any ability references them, on the
 * dedicated `wp_abilities_api_categories_init` hook (core Abilities API,
 * WP 6.9+) - a separate hook from ability registration itself.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tlkp_register_ability_category() {
	wp_register_ability_category(
		TLKP_ABILITY_NAMESPACE,
		array(
			'label'       => __( 'Tillkeeper', 'tillkeeper' ),
			'description' => __( 'Customer lookups for AI agents, with every call logged by Tillkeeper.', 'tillkeeper' ),
		)
	);
}
add_action( 'wp_abilities_api_categories_init', 'tlkp_register_ability_category' );
