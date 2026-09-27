<?php
/**
 * Plugin Name:       Tillkeeper
 * Plugin URI:        https://cognitolab.net/products/tillkeeper
 * Description:       A trust layer between AI agents and your WooCommerce store: safe, audited read access via the WordPress Abilities API.
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
 * "Tillkeeper" is a working name and may still change before the first
 * public release - every internal identifier lives behind the TLKP_/tlkp_
 * prefix below so a rename stays a mechanical find/replace instead of an
 * architecture change.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TLKP_VERSION', '1.0.0' );
define( 'TLKP_PLUGIN_FILE', __FILE__ );
define( 'TLKP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TLKP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Namespace prefix used for every ability name and the ability category
 * (`{namespace}/{ability-slug}`, e.g. `tillkeeper/list-products`), per the
 * Abilities API's namespaced-slug requirement. Centralized here so the
 * working-name rename above only has to happen once.
 */
define( 'TLKP_ABILITY_NAMESPACE', 'tillkeeper' );

require_once TLKP_PLUGIN_DIR . 'includes/audit-log.php';
require_once TLKP_PLUGIN_DIR . 'includes/ability-category.php';
require_once TLKP_PLUGIN_DIR . 'includes/abilities/read-products.php';
require_once TLKP_PLUGIN_DIR . 'includes/abilities/read-orders.php';
require_once TLKP_PLUGIN_DIR . 'includes/abilities/read-customers.php';
require_once TLKP_PLUGIN_DIR . 'includes/admin-page.php';

/**
 * No load_plugin_textdomain() call: discouraged since WP 4.6 for plugins
 * hosted on wordpress.org - core auto-loads translations for wp.org-hosted
 * plugins using the plugin slug, no manual loading needed.
 */
