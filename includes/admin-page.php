<?php
/**
 * Admin page: lists the abilities Tillkeeper has registered and the most
 * recent audit log entries. Read-only by design - there is nothing to
 * configure yet in this skeleton (no limits/policies exist until the Pro
 * write axis lands).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function tlkp_register_admin_page() {
	add_menu_page(
		__( 'Tillkeeper', 'tillkeeper' ),
		__( 'Tillkeeper', 'tillkeeper' ),
		'manage_woocommerce',
		'tillkeeper',
		'tlkp_render_admin_page',
		'dashicons-shield',
		56
	);
}
add_action( 'admin_menu', 'tlkp_register_admin_page' );

function tlkp_render_admin_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	if ( isset( $_POST['tlkp_clear_log'] ) && check_admin_referer( 'tlkp_clear_log' ) ) {
		tlkp_audit_log_clear();
		echo '<div class="notice notice-success"><p>' . esc_html__( 'Audit log cleared.', 'tillkeeper' ) . '</p></div>';
	}

	$abilities = wp_get_abilities( array( 'category' => TLKP_ABILITY_NAMESPACE ) );
	$log       = tlkp_audit_log_get( 50 );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Tillkeeper', 'tillkeeper' ); ?></h1>
		<p><?php esc_html_e( 'A trust layer between AI agents and your WooCommerce store. Below is every ability currently registered and what agents have read recently.', 'tillkeeper' ); ?></p>

		<h2><?php esc_html_e( 'Registered abilities', 'tillkeeper' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Label', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Description', 'tillkeeper' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $abilities ) ) : ?>
					<tr><td colspan="3"><?php esc_html_e( 'No abilities registered.', 'tillkeeper' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $abilities as $ability ) : ?>
						<tr>
							<td><code><?php echo esc_html( $ability->get_name() ); ?></code></td>
							<td><?php echo esc_html( $ability->get_label() ); ?></td>
							<td><?php echo esc_html( $ability->get_description() ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Recent activity', 'tillkeeper' ); ?></h2>
		<form method="post" style="margin-bottom: 1em;">
			<?php wp_nonce_field( 'tlkp_clear_log' ); ?>
			<button type="submit" name="tlkp_clear_log" value="1" class="button" onclick="return confirm('<?php echo esc_js( __( 'Clear the audit log?', 'tillkeeper' ) ); ?>');">
				<?php esc_html_e( 'Clear log', 'tillkeeper' ); ?>
			</button>
		</form>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'When', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Ability', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Object', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Count', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'User', 'tillkeeper' ); ?></th>
					<th><?php esc_html_e( 'Result', 'tillkeeper' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $log ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No activity yet.', 'tillkeeper' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $log as $entry ) : ?>
						<?php $user = $entry['actor'] ? get_userdata( $entry['actor'] ) : false; ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $entry['time'] ) ); ?></td>
							<td><code><?php echo esc_html( $entry['ability'] ); ?></code></td>
							<td><?php echo esc_html( $entry['object_type'] . ( $entry['object_id'] ? ' #' . $entry['object_id'] : '' ) ); ?></td>
							<td><?php echo esc_html( (string) $entry['count'] ); ?></td>
							<td><?php echo esc_html( $user ? $user->user_login : __( '(unknown)', 'tillkeeper' ) ); ?></td>
							<td><?php echo $entry['success'] ? esc_html__( 'OK', 'tillkeeper' ) : esc_html__( 'Error', 'tillkeeper' ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}
