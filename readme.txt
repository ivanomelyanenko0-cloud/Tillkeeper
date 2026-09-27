=== Tillkeeper ===
Contributors: lukystile
Tags: ai, mcp, abilities api, woocommerce, agent
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A trust layer between AI agents and your WooCommerce store: safe, audited read access via the WordPress Abilities API.

== Description ==

**Tillkeeper** registers a set of read-only [WordPress Abilities API](https://developer.wordpress.org/plugins/abilities-api/) abilities that let an AI agent (ChatGPT, Claude, or any MCP-compatible client) look up your WooCommerce products and orders — safely, and with every read logged.

* **Products** — list and look up products, including price and stock.
* **Orders** — list and look up orders and their line items, without exposing customer personal data.
* **Customers** — list and look up customer accounts (no WooCommerce core ability covers this today).
* **Audit log** — every ability call is recorded (what was read, when, by whom), visible from the Tillkeeper admin page. IDs only, never names, emails, or addresses - even for the Customers abilities.

Everything an agent can do here is *read-only*. Letting an agent safely *change* your store — prices, discounts, refunds, order status — is a separate, deliberately harder problem: dry-run previews, limits, anomaly detection, and rollback. That's the planned scope of Tillkeeper Pro, not yet built.

This is an early, evolving plugin — the name itself may still change before a public release.

== External services ==

This plugin does not connect to any external service. No data leaves your site. It only registers abilities that other software already running on your site (an MCP server, the REST API, or a compatible AI-agent integration) may call.

== Installation ==

1. Upload the `tillkeeper` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** screen. WooCommerce must be installed and active.
3. Open **Tillkeeper** in the admin menu to see the registered abilities and recent activity.

== Frequently Asked Questions ==

= Does this let an AI agent change my store? =

No. Every ability this plugin registers is read-only. It does not modify products, orders, or any other store data.

= Does this send my store data anywhere? =

No. Tillkeeper itself makes no external requests. It only exposes read access to whatever already has permission to call WordPress abilities on your own site.

== Changelog ==

= 1.0.0 =
* First public release.
* Read-only abilities: `list-products`/`get-product`, `list-orders`/`get-order`, `list-customers`/`get-customer`.
* PII-free audit log (IDs only) and an admin page listing registered abilities and recent activity.
