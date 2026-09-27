<?php
/**
 * Plugin Name:       Tillkeeper
 * Plugin URI:        https://cognitolab.net/products/tillkeeper
 * Description:       Keeps AI agents honest in your WooCommerce store: every agent action is logged, and deleting products or finalising orders waits for a person to approve it.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            CognitoLab
 * Author URI:        https://cognitolab.net
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       tillkeeper
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 *
 * Internal identifiers use the TLKP_/tlkp_ prefix.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TLKP_VERSION', '1.0.0' );
define( 'TLKP_PLUGIN_FILE', __FILE__ );
define( 'TLKP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TLKP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Namespace for this plugin's own abilities and their category
 * (`tillkeeper/list-customers`), as the Abilities API requires.
 */
define( 'TLKP_ABILITY_NAMESPACE', 'tillkeeper' );

require_once TLKP_PLUGIN_DIR . 'includes/settings.php';
require_once TLKP_PLUGIN_DIR . 'includes/activity-log.php';
require_once TLKP_PLUGIN_DIR . 'includes/approval-queue.php';
require_once TLKP_PLUGIN_DIR . 'includes/guard.php';
require_once TLKP_PLUGIN_DIR . 'includes/observe.php';
require_once TLKP_PLUGIN_DIR . 'includes/ability-category.php';
require_once TLKP_PLUGIN_DIR . 'includes/abilities/read-customers.php';
require_once TLKP_PLUGIN_DIR . 'includes/admin-page.php';

/**
 * No load_plugin_textdomain() call: discouraged since WP 4.6 for plugins
 * hosted on wordpress.org - core auto-loads translations for wp.org-hosted
 * plugins using the plugin slug, no manual loading needed.
 */
