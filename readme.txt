=== Tillkeeper ===
Contributors: lukystile
Tags: ai, mcp, abilities api, woocommerce, security
Requires at least: 6.9
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Keeps AI agents in check in your WooCommerce store: every agent action is logged, and deleting products or finalising orders waits for your approval.

== Description ==

AI agents such as ChatGPT, Claude or your own bots can now work with a WooCommerce store directly, through the WordPress Abilities API and MCP. WooCommerce itself registers abilities that let an agent **delete products** and **change order statuses** - and they run immediately, with no confirmation.

Tillkeeper sits between the agent and your store:

* **Approval for dangerous actions.** When an agent tries to delete a product or move an order to Completed, Cancelled or Refunded, nothing happens. The request waits on the Tillkeeper page until a store manager clicks Approve or Reject. The agent gets "pending approval" back and has no way to approve it itself - so even a confused or manipulated agent cannot do damage on its own.
* **A log of everything agents do.** Every call to a WooCommerce ability - reads and changes - is recorded: which ability, which product or order, which user, and what happened. The log stores IDs only, never customer names, emails or addresses.
* **A clear picture of what agents can do.** The Tillkeeper page lists every store ability available to agents, which ones change data, and which ones are protected.
* **Your choice of strictness.** Require approval for every order status change, only for final statuses, or not at all; turn product deletion protection on or off.
* **Works with any agent.** Tillkeeper protects WooCommerce's own standard abilities, so any agent or MCP client that calls them is covered without learning anything new.

Tillkeeper also adds two read abilities WooCommerce does not have yet: `tillkeeper/list-customers` and `tillkeeper/get-customer`, available only to users who can list site users.

= Tillkeeper Pro =

[Tillkeeper Pro](https://cognitolab.net/products/tillkeeper) goes deeper on the same protection: a preview of every change before it is applied, limits for discounts and stock changes (anything over them goes to the approval queue), protection for product edits, an hourly limit on agent changes, a before/after history, and one-click rollback for price and stock changes.

== External services ==

This plugin does not connect to any external service. No data leaves your site. It only watches and guards abilities that software already running on your site (an MCP server, the REST API or an AI-agent integration) may call.

== Installation ==

1. Upload the `tillkeeper` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate it. WooCommerce must be active, on WordPress 6.9 or later. The guard covers the abilities WooCommerce registers for agents (tested with WooCommerce 11.0).
3. Open **Tillkeeper** in the admin menu. Protection is on from the start: product deletion and final order statuses need approval.

== Frequently Asked Questions ==

= Does an agent need to know about Tillkeeper? =

No. Tillkeeper wraps WooCommerce's own `product-delete` and `order-update-status` abilities. Any agent calling those standard abilities is protected automatically.

= Can an agent approve its own request? =

No. Approving happens only on the Tillkeeper page in wp-admin, by a logged-in user who can manage WooCommerce. The agent never receives anything it could use to approve.

= Who can see customer data through the customer abilities? =

Only users with the `list_users` capability (administrators and, by default, shop managers). An agent acting as a user without it gets a permission error. The activity log records the customer ID only.

= Does Tillkeeper slow my store down? =

No. It does nothing on the storefront. It only runs when an ability is called, and adds one small database write per call.

= What is not protected? =

Tillkeeper guards the abilities WooCommerce registers for deleting products and changing order status. Product edits (prices, stock) are logged; guarding them with limits and previews is part of Tillkeeper Pro. Abilities registered by other plugins are not touched.

== Screenshots ==

1. Requests waiting for approval, with Approve and Reject.
2. Protection settings and the list of store abilities agents can use.
3. The agent activity log.

== Changelog ==

= 1.0.0 =
* First public release.
* Approval queue for `woocommerce/product-delete` and `woocommerce/order-update-status` (final statuses by default).
* Activity log of every WooCommerce ability call, IDs only.
* Overview of store abilities and their protection.
* Read abilities for customers: `tillkeeper/list-customers`, `tillkeeper/get-customer`.
