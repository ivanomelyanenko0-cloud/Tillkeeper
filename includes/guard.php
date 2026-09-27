<?php
/**
 * The guard: wraps destructive WooCommerce abilities so an AI agent cannot
 * run them without a person's approval.
 *
 * WooCommerce registers `product-delete` and `order-update-status` with
 * `destructive: true` but no protection: any agent with the capability runs
 * them immediately. `wp_register_ability_args` fires for every ability
 * registration, including ones this plugin does not own, so wrapping here
 * protects every agent that calls the standard WooCommerce ability - no
 * plugin-specific namespace for the agent to learn.
 *
 * The native permission_callback is left untouched: an agent still needs the
 * same WooCommerce capability as before just to reach the guard.
 *
 * Extension points (used by Tillkeeper Pro):
 * - 'tlkp_guarded_abilities'   add abilities to guard.
 * - 'tlkp_guard_ability_args'  adjust a guarded ability's registration args.
 * - 'tlkp_guard_decision'      change what happens to a call.
 * - 'tlkp_guard_pre_run'       veto a run with a WP_Error (limits).
 * - 'tlkp_guard_before_run' / 'tlkp_guard_after_run'  observe a run.
 * - 'tlkp_guard_applied_meta'  add fields to the 'applied' response.
 *
 * @package Tillkeeper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<string, array{object_type: string, reversible: bool}>
 */
function tlkp_guarded_abilities() {
	$abilities = array(
		'woocommerce/product-delete'      => array(
			'object_type' => 'product',
			'reversible'  => false,
		),
		'woocommerce/order-update-status' => array(
			'object_type' => 'order',
			'reversible'  => false, // Emails and hooked integrations are not undoable.
		),
	);

	/**
	 * Filters which abilities go through the guard.
	 *
	 * @param array $abilities Ability name => array( object_type, reversible ).
	 */
	return (array) apply_filters( 'tlkp_guarded_abilities', $abilities );
}

/**
 * Original execute callbacks, captured as guarded abilities register.
 *
 * @param string        $ability  Ability name.
 * @param callable|null $callback Callback to store, or null to read.
 * @return callable|null
 */
function tlkp_original_callback( $ability, $callback = null ) {
	static $callbacks = array();
	if ( null !== $callback ) {
		$callbacks[ $ability ] = $callback;
	}
	return isset( $callbacks[ $ability ] ) ? $callbacks[ $ability ] : null;
}

/**
 * @param array  $args Registration args.
 * @param string $name Ability name.
 * @return array
 */
function tlkp_guard_ability_args( $args, $name ) {
	$guarded = tlkp_guarded_abilities();
	if ( ! isset( $guarded[ $name ] ) || empty( $args['execute_callback'] ) || ! is_callable( $args['execute_callback'] ) ) {
		return $args;
	}

	tlkp_original_callback( $name, $args['execute_callback'] );
	$config = $guarded[ $name ];

	$args['execute_callback'] = static function ( $input ) use ( $name, $config ) {
		return tlkp_guarded_execute( $name, $config, $input );
	};

	if ( isset( $args['output_schema'] ) && is_array( $args['output_schema'] ) ) {
		$args['output_schema'] = tlkp_inject_guard_output_property( $args['output_schema'] );
	}

	/**
	 * Filters a guarded ability's registration args after wrapping.
	 *
	 * @param array  $args Registration args.
	 * @param string $name Ability name.
	 */
	return apply_filters( 'tlkp_guard_ability_args', $args, $name );
}
add_filter( 'wp_register_ability_args', 'tlkp_guard_ability_args', 10, 2 );

/**
 * Guard responses carry a `tillkeeper` object instead of (or next to) the
 * native result. WooCommerce's output schemas list no `required` fields, so
 * a loose extra property is all output validation needs.
 *
 * @param array $schema Output schema.
 * @return array
 */
function tlkp_inject_guard_output_property( array $schema ) {
	if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
		$schema['properties']['tillkeeper'] = array(
			'type'                 => 'object',
			'description'          => __( 'What Tillkeeper did with this call: pending approval, preview, or applied.', 'tillkeeper' ),
			'additionalProperties' => true,
		);
	}
	return $schema;
}

/**
 * The free policy for a call, before Pro or other plugins adjust it.
 *
 * @param string $ability Ability name.
 * @param array  $input   Input.
 * @return array{action: string, reasons: string[], object_id: int}
 */
function tlkp_default_decision( $ability, array $input ) {
	$settings  = tlkp_get_settings();
	$object_id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;

	if ( 'woocommerce/product-delete' === $ability && $settings['guard_product_delete'] ) {
		return array(
			'action'    => 'queue',
			'reasons'   => array( __( 'Deleting a product needs a person to approve it.', 'tillkeeper' ) ),
			'object_id' => $object_id,
		);
	}

	if ( 'woocommerce/order-update-status' === $ability ) {
		$status = isset( $input['status'] ) ? preg_replace( '/^wc-/', '', sanitize_key( $input['status'] ) ) : '';
		$final  = in_array( $status, tlkp_final_order_statuses(), true );

		if ( 'all' === $settings['order_status_mode'] || ( 'final' === $settings['order_status_mode'] && $final ) ) {
			return array(
				'action'    => 'queue',
				/* translators: %s: order status slug, e.g. "completed". */
				'reasons'   => array( sprintf( __( 'Changing an order to "%s" needs a person to approve it.', 'tillkeeper' ), $status ) ),
				'object_id' => $object_id,
			);
		}
	}

	return array(
		'action'    => 'run',
		'reasons'   => array(),
		'object_id' => $object_id,
	);
}

/**
 * Every call to a guarded ability runs through here.
 *
 * @param string $ability Ability name.
 * @param array  $config  Entry from tlkp_guarded_abilities().
 * @param mixed  $input   Input.
 * @return mixed|WP_Error
 */
function tlkp_guarded_execute( $ability, array $config, $input ) {
	$input   = is_array( $input ) ? $input : array();
	$context = array(
		'via'           => 'agent',
		'confirm_token' => isset( $input['confirm_token'] ) ? (string) $input['confirm_token'] : '',
	);
	unset( $input['confirm_token'] );

	/**
	 * Filters what happens to a guarded call.
	 *
	 * Return an array with 'action' => 'run' | 'queue', or with 'response' set
	 * to answer the agent directly (e.g. a preview), or a WP_Error to refuse.
	 *
	 * @param array  $decision From tlkp_default_decision().
	 * @param string $ability  Ability name.
	 * @param array  $input    Input without confirm_token.
	 * @param array  $config   Guard config for this ability.
	 * @param array  $context  'via' and 'confirm_token'.
	 */
	$decision = apply_filters( 'tlkp_guard_decision', tlkp_default_decision( $ability, $input ), $ability, $input, $config, $context );

	if ( is_wp_error( $decision ) ) {
		return $decision;
	}
	if ( is_array( $decision ) && array_key_exists( 'response', $decision ) ) {
		return $decision['response'];
	}

	if ( is_array( $decision ) && isset( $decision['action'] ) && 'queue' === $decision['action'] ) {
		$entry = tlkp_approval_queue_add( $ability, $input, $decision, $config );

		return array(
			'tillkeeper' => array(
				'status'      => 'pending_approval',
				'reasons'     => $decision['reasons'],
				'approval_id' => $entry['id'],
				'message'     => __( 'This action needs a store administrator to approve it in Tillkeeper before it runs. Nothing has been changed.', 'tillkeeper' ),
			),
		);
	}

	$context['risk'] = isset( $decision['risk'] ) ? (string) $decision['risk'] : '';
	return tlkp_guard_run( $ability, $config, $input, $context );
}

/**
 * Runs the original WooCommerce callback. Used for calls the policy allows
 * and for approved queue entries.
 *
 * @param string $ability Ability name.
 * @param array  $config  Guard config.
 * @param array  $input   Input.
 * @param array  $context 'via' => 'agent' | 'approval', plus 'risk'.
 * @return mixed|WP_Error
 */
function tlkp_guard_run( $ability, array $config, array $input, array $context ) {
	$original = tlkp_original_callback( $ability );
	if ( ! is_callable( $original ) ) {
		return new WP_Error( 'tlkp_ability_unavailable', __( 'The underlying WooCommerce ability is not registered.', 'tillkeeper' ) );
	}

	/**
	 * Lets a limit refuse the run. Return a WP_Error to stop it.
	 *
	 * @param null|WP_Error $pre     Null to continue.
	 * @param string        $ability Ability name.
	 * @param array         $input   Input.
	 * @param array         $context Run context.
	 */
	$pre = apply_filters( 'tlkp_guard_pre_run', null, $ability, $input, $context );
	if ( is_wp_error( $pre ) ) {
		tlkp_guard_log_failure( $ability, $config, $input, $pre, $context );
		return $pre;
	}

	do_action( 'tlkp_guard_before_run', $ability, $input, $context );
	$result = call_user_func( $original, $input );
	do_action( 'tlkp_guard_after_run', $ability, $input, $result, $config, $context );

	if ( is_wp_error( $result ) ) {
		tlkp_guard_log_failure( $ability, $config, $input, $result, $context );
		return $result;
	}

	if ( 'approval' === $context['via'] ) {
		// Agent calls are logged by the observer; approvals run outside the Abilities API.
		tlkp_activity_log_record(
			array(
				'ability'     => $ability,
				'kind'        => 'write',
				'object_type' => $config['object_type'],
				'object_id'   => isset( $input['id'] ) ? $input['id'] : 0,
				'outcome'     => 'approved',
			)
		);
	}

	if ( is_array( $result ) ) {
		/**
		 * Filters the 'tillkeeper' object added to an applied result.
		 *
		 * @param array  $meta    Default array( 'status' => 'applied' ).
		 * @param string $ability Ability name.
		 * @param array  $input   Input.
		 * @param array  $config  Guard config.
		 */
		$result['tillkeeper'] = apply_filters( 'tlkp_guard_applied_meta', array( 'status' => 'applied' ), $ability, $input, $config );
	}
	return $result;
}

/**
 * @param string   $ability Ability name.
 * @param array    $config  Guard config.
 * @param array    $input   Input.
 * @param WP_Error $error   Error.
 * @param array    $context Run context.
 */
function tlkp_guard_log_failure( $ability, array $config, array $input, WP_Error $error, array $context ) {
	tlkp_activity_log_record(
		array(
			'ability'     => $ability,
			'kind'        => 'write',
			'object_type' => $config['object_type'],
			'object_id'   => isset( $input['id'] ) ? $input['id'] : 0,
			'outcome'     => 'error',
			'note'        => $error->get_error_code(),
		)
	);
}
