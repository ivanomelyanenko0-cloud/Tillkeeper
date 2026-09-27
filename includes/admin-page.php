<?php
/**
 * Admin page: approval queue, protection settings and the agent activity log.
 *
 * @package Tillkeeper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the menu, with a count of waiting approvals.
 */
function tlkp_register_admin_page() {
	$pending = tlkp_pending_count();
	$label   = __( 'Tillkeeper', 'tillkeeper' );
	if ( $pending ) {
		$label .= sprintf( ' <span class="awaiting-mod">%d</span>', $pending );
	}

	add_menu_page(
		__( 'Tillkeeper', 'tillkeeper' ),
		$label,
		'manage_woocommerce',
		'tillkeeper',
		'tlkp_render_admin_page',
		'dashicons-shield',
		56
	);

	// Same page, but the first submenu item gets a plain label instead of the count.
	add_submenu_page( 'tillkeeper', __( 'Tillkeeper', 'tillkeeper' ), __( 'Overview', 'tillkeeper' ), 'manage_woocommerce', 'tillkeeper', 'tlkp_render_admin_page' );
}
add_action( 'admin_menu', 'tlkp_register_admin_page' );

/**
 * @param string $hook Admin page hook.
 */
function tlkp_admin_assets( $hook ) {
	if ( 'toplevel_page_tillkeeper' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'tlkp-admin', TLKP_PLUGIN_URL . 'assets/css/admin.css', array(), TLKP_VERSION );
	wp_enqueue_script( 'tlkp-admin', TLKP_PLUGIN_URL . 'assets/js/admin.js', array(), TLKP_VERSION, array( 'in_footer' => true ) );
}
add_action( 'admin_enqueue_scripts', 'tlkp_admin_assets' );

/**
 * Saves settings and clears the log (both nonce-checked form posts).
 */
function tlkp_handle_admin_forms() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'tillkeeper' ) );
	}
	check_admin_referer( 'tlkp_admin_form' );

	$form = isset( $_POST['tlkp_form'] ) ? sanitize_key( wp_unslash( $_POST['tlkp_form'] ) ) : '';
	if ( 'settings' === $form ) {
		tlkp_update_settings(
			array(
				'guard_product_delete' => ! empty( $_POST['guard_product_delete'] ),
				'order_status_mode'    => isset( $_POST['order_status_mode'] ) ? sanitize_key( wp_unslash( $_POST['order_status_mode'] ) ) : '',
			)
		);
		$notice = 'settings_saved';
	} else {
		tlkp_activity_log_clear();
		$notice = 'log_cleared';
	}

	wp_safe_redirect( add_query_arg( 'tlkp_notice', $notice, admin_url( 'admin.php?page=tillkeeper' ) ) );
	exit;
}
add_action( 'admin_post_tlkp_admin_form', 'tlkp_handle_admin_forms' );

/**
 * Notice after a redirect. The actions that set it checked their own nonce.
 */
function tlkp_render_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set after a nonce-checked redirect.
	$key = isset( $_GET['tlkp_notice'] ) ? sanitize_key( wp_unslash( $_GET['tlkp_notice'] ) ) : '';

	$messages = array(
		'approve_ok'     => array( 'success', __( 'Approved and applied.', 'tillkeeper' ) ),
		'approve_failed' => array( 'error', __( 'Could not run that request. See the activity log for the error.', 'tillkeeper' ) ),
		'reject_ok'      => array( 'success', __( 'Rejected. Nothing was changed.', 'tillkeeper' ) ),
		'reject_failed'  => array( 'error', __( 'Could not reject that request.', 'tillkeeper' ) ),
		'settings_saved' => array( 'success', __( 'Settings saved.', 'tillkeeper' ) ),
		'log_cleared'    => array( 'success', __( 'Activity log cleared.', 'tillkeeper' ) ),
	);
	if ( isset( $messages[ $key ] ) ) {
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $key ][0] ), esc_html( $messages[ $key ][1] ) );
	}
}

/**
 * @param int $user_id User ID.
 * @return string Login or a placeholder.
 */
function tlkp_user_label( $user_id ) {
	$user = $user_id ? get_userdata( (int) $user_id ) : false;
	return $user ? $user->user_login : __( '(unknown)', 'tillkeeper' );
}

/**
 * @param string $type Object type.
 * @param int    $id   Object ID.
 * @return string Plain text.
 */
function tlkp_object_label( $type, $id ) {
	if ( ! $id ) {
		return '' === $type ? '—' : $type;
	}
	if ( 'product' === $type && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( $id );
		if ( $product ) {
			return sprintf( '%s #%d', $product->get_name(), $id );
		}
	}
	/* translators: 1: object type, 2: object ID. */
	return sprintf( __( '%1$s #%2$d', 'tillkeeper' ), $type, $id );
}

/**
 * Renders the page.
 */
function tlkp_render_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
	$kind     = isset( $_GET['kind'] ) && 'write' === $_GET['kind'] ? 'write' : '';
	$pending  = tlkp_approval_queue_get_pending();
	$resolved = tlkp_approval_queue_get_resolved( 10 );
	$settings = tlkp_get_settings();
	$log      = tlkp_activity_log_get( 100, $kind );
	?>
	<div class="wrap tlkp-wrap">
		<h1><?php esc_html_e( 'Tillkeeper', 'tillkeeper' ); ?></h1>
		<p class="description"><?php esc_html_e( 'AI agents can read and change your store through the WordPress Abilities API. Tillkeeper records everything they do and holds the dangerous actions until a person approves them.', 'tillkeeper' ); ?></p>
		<?php tlkp_render_notice(); ?>

		<h2><?php esc_html_e( 'Waiting for approval', 'tillkeeper' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Requested action', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Object', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Requested by', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Why it waits', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Decision', 'tillkeeper' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $pending ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'Nothing is waiting.', 'tillkeeper' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $pending as $entry ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'Y-m-d H:i', $entry['time'] ) ); ?></td>
						<td><code><?php echo esc_html( $entry['ability'] ); ?></code><?php echo esc_html( tlkp_describe_input( $entry ) ); ?></td>
						<td><?php echo esc_html( tlkp_object_label( $entry['object_type'], $entry['object_id'] ) ); ?></td>
						<td><?php echo esc_html( tlkp_user_label( $entry['actor'] ) ); ?></td>
						<td><?php echo esc_html( implode( ' ', $entry['reasons'] ) ); ?></td>
						<td class="tlkp-decision">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'tlkp_queue_decision' ); ?>
								<input type="hidden" name="action" value="tlkp_queue_decision" />
								<input type="hidden" name="approval_id" value="<?php echo esc_attr( $entry['id'] ); ?>" />
								<button type="submit" name="decision" value="approve" class="button button-primary" data-tlkp-confirm="<?php esc_attr_e( 'Approve and run this action now?', 'tillkeeper' ); ?>"><?php esc_html_e( 'Approve', 'tillkeeper' ); ?></button>
								<button type="submit" name="decision" value="reject" class="button"><?php esc_html_e( 'Reject', 'tillkeeper' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $resolved ) : ?>
			<details class="tlkp-resolved">
				<summary><?php esc_html_e( 'Recently decided', 'tillkeeper' ); ?></summary>
				<ul>
					<?php foreach ( $resolved as $entry ) : ?>
						<li>
							<?php
							/* translators: 1: date, 2: ability, 3: object, 4: status, 5: user. */
							echo esc_html( sprintf( __( '%1$s — %2$s on %3$s: %4$s by %5$s', 'tillkeeper' ), wp_date( 'Y-m-d H:i', $entry['resolved_at'] ), $entry['ability'], tlkp_object_label( $entry['object_type'], $entry['object_id'] ), $entry['status'], tlkp_user_label( $entry['resolved_by'] ) ) );
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			</details>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Protection', 'tillkeeper' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'tlkp_admin_form' ); ?>
			<input type="hidden" name="action" value="tlkp_admin_form" />
			<input type="hidden" name="tlkp_form" value="settings" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Deleting products', 'tillkeeper' ); ?></th>
					<td>
						<label><input type="checkbox" name="guard_product_delete" value="1" <?php checked( $settings['guard_product_delete'] ); ?> /> <?php esc_html_e( 'An agent can only delete a product after a person approves it', 'tillkeeper' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Changing order status', 'tillkeeper' ); ?></th>
					<td>
						<fieldset>
							<label><input type="radio" name="order_status_mode" value="final" <?php checked( $settings['order_status_mode'], 'final' ); ?> /> <?php echo esc_html( sprintf( /* translators: %s: list of statuses. */ __( 'Approval for final statuses only (%s)', 'tillkeeper' ), implode( ', ', tlkp_final_order_statuses() ) ) ); ?></label><br />
							<label><input type="radio" name="order_status_mode" value="all" <?php checked( $settings['order_status_mode'], 'all' ); ?> /> <?php esc_html_e( 'Approval for every status change', 'tillkeeper' ); ?></label><br />
							<label><input type="radio" name="order_status_mode" value="off" <?php checked( $settings['order_status_mode'], 'off' ); ?> /> <?php esc_html_e( 'No approval (not recommended)', 'tillkeeper' ); ?></label>
						</fieldset>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save protection settings', 'tillkeeper' ) ); ?>
		</form>

		<h3><?php esc_html_e( 'Store abilities agents can use', 'tillkeeper' ); ?></h3>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Ability', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'What it does', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Type', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Protection', 'tillkeeper' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( tlkp_store_abilities_overview() as $row ) : ?>
					<tr>
						<td><code><?php echo esc_html( $row['name'] ); ?></code></td>
						<td><?php echo esc_html( $row['label'] ); ?></td>
						<td><?php echo esc_html( $row['type'] ); ?></td>
						<td class="tlkp-protection-<?php echo esc_attr( $row['level'] ); ?>"><?php echo esc_html( $row['protection'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( ! defined( 'TLKP_PRO_VERSION' ) ) : ?>
			<p class="description"><?php esc_html_e( 'Tillkeeper Pro adds previews before changes, limits for prices and stock, product edits under protection, and one-click rollback.', 'tillkeeper' ); ?></p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Agent activity', 'tillkeeper' ); ?></h2>
		<p class="tlkp-log-tools">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tillkeeper' ) ); ?>" class="<?php echo '' === $kind ? 'current' : ''; ?>"><?php esc_html_e( 'Everything', 'tillkeeper' ); ?></a> |
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=tillkeeper&kind=write' ) ); ?>" class="<?php echo 'write' === $kind ? 'current' : ''; ?>"><?php esc_html_e( 'Changes only', 'tillkeeper' ); ?></a>
		</p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Ability', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Object', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'User', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Result', 'tillkeeper' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $log ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No agent activity yet.', 'tillkeeper' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $log as $entry ) : ?>
					<tr class="tlkp-kind-<?php echo esc_attr( $entry['kind'] ); ?>">
						<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $entry['time'] ) ); ?></td>
						<td><code><?php echo esc_html( $entry['ability'] ); ?></code></td>
						<td><?php echo esc_html( tlkp_object_label( $entry['object_type'], $entry['object_id'] ) ); ?></td>
						<td><?php echo esc_html( tlkp_user_label( $entry['actor'] ) ); ?></td>
						<td><?php echo esc_html( tlkp_outcome_label( $entry['outcome'] ) . ( '' !== $entry['note'] ? ' — ' . $entry['note'] : '' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tlkp-clear">
			<?php wp_nonce_field( 'tlkp_admin_form' ); ?>
			<input type="hidden" name="action" value="tlkp_admin_form" />
			<input type="hidden" name="tlkp_form" value="clear_log" />
			<button type="submit" class="button" data-tlkp-confirm="<?php esc_attr_e( 'Clear the activity log?', 'tillkeeper' ); ?>"><?php esc_html_e( 'Clear activity log', 'tillkeeper' ); ?></button>
		</form>
	</div>
	<?php
}

/**
 * @param string $outcome Outcome key.
 * @return string Label.
 */
function tlkp_outcome_label( $outcome ) {
	$labels = array(
		'ok'               => __( 'Done', 'tillkeeper' ),
		'applied'          => __( 'Applied', 'tillkeeper' ),
		'pending_approval' => __( 'Held for approval', 'tillkeeper' ),
		'preview'          => __( 'Preview only', 'tillkeeper' ),
		'approved'         => __( 'Approved and applied', 'tillkeeper' ),
		'rejected'         => __( 'Rejected', 'tillkeeper' ),
		'error'            => __( 'Failed', 'tillkeeper' ),
	);
	return isset( $labels[ $outcome ] ) ? $labels[ $outcome ] : $outcome;
}

/**
 * A short, PII-free summary of what a queued call would do.
 *
 * @param array $entry Queue entry.
 * @return string Leading space + summary, or empty.
 */
function tlkp_describe_input( array $entry ) {
	$input = isset( $entry['input'] ) && is_array( $entry['input'] ) ? $entry['input'] : array();
	if ( 'woocommerce/order-update-status' === $entry['ability'] && isset( $input['status'] ) ) {
		/* translators: %s: order status. */
		return ' ' . sprintf( __( '→ %s', 'tillkeeper' ), sanitize_key( $input['status'] ) );
	}
	if ( 'woocommerce/product-delete' === $entry['ability'] ) {
		return ! empty( $input['force'] ) ? ' ' . __( '(permanently)', 'tillkeeper' ) : ' ' . __( '(to trash)', 'tillkeeper' );
	}
	return '';
}

/**
 * @return array[] One row per WooCommerce / Tillkeeper ability.
 */
function tlkp_store_abilities_overview() {
	if ( ! function_exists( 'wp_get_abilities' ) ) {
		return array();
	}

	$guarded = tlkp_guarded_abilities();
	$rows    = array();

	foreach ( wp_get_abilities() as $ability ) {
		$name = $ability->get_name();
		if ( ! tlkp_is_logged_ability( $name ) ) {
			continue;
		}

		$annotations = (array) $ability->get_meta_item( 'annotations', array() );
		$read        = ! empty( $annotations['readonly'] );
		$destructive = ! empty( $annotations['destructive'] );

		if ( $read ) {
			$type = __( 'Read', 'tillkeeper' );
		} elseif ( $destructive ) {
			$type = __( 'Change (destructive)', 'tillkeeper' );
		} else {
			$type = __( 'Change', 'tillkeeper' );
		}

		if ( isset( $guarded[ $name ] ) ) {
			$level      = 'guarded';
			$protection = __( 'Guarded', 'tillkeeper' );
		} elseif ( $read ) {
			$level      = 'logged';
			$protection = __( 'Logged', 'tillkeeper' );
		} else {
			$level      = $destructive ? 'open' : 'logged';
			$protection = $destructive ? __( 'Logged only — runs immediately', 'tillkeeper' ) : __( 'Logged', 'tillkeeper' );
		}

		$rows[] = array(
			'name'       => $name,
			'label'      => $ability->get_label(),
			'type'       => $type,
			'level'      => $level,
			'protection' => $protection,
		);
	}

	usort(
		$rows,
		static function ( $a, $b ) {
			return strcmp( $a['name'], $b['name'] );
		}
	);
	return $rows;
}
