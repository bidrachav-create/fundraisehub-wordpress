=== FundRaiseHub Elementor ===
Contributors: fundraisehub
Tags: fundraising, campaigns, donations, elementor, nonprofit
Requires at least: 6.4
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.1
Requires Plugins: fundraisehub-core
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Elementor widget pack for FundRaiseHub campaigns. Requires FundRaiseHub Core and Elementor 3.5+.

== Description ==

FundRaiseHub Elementor adds a full set of drag-and-drop Elementor widgets for embedding FundRaiseHub fundraising campaigns in your pages. All widgets appear under the **FundRaiseHub Campaigns** category in the Elementor panel.

**Requires:**

* [FundRaiseHub Core](https://wordpress.org/plugins/fundraisehub-core/) — installed and active.
* [Elementor](https://wordpress.org/plugins/elementor/) 3.5 or higher.

**Available widgets**

* Campaign Banner — hero image/header
* Campaign Description — long-form description
* Campaign Stats Bar — raised amount, donor count, and goal
* Campaign Thermometer — progress bar towards goal
* Campaign Donate Button — opens the donation overlay
* Campaign Donation Tiles — preset donation amount tiles
* Campaign Video — embedded campaign video
* Campaign Photo Gallery — gallery of campaign images
* Campaign Teams — fundraising team leaderboard
* Campaign Comments — donor comments and messages
* Campaign Honor Scroll — donor recognition scroll

= External Services =

This plugin relies on FundRaiseHub Core to fetch campaign data from the FundRaiseHub service. See the [FundRaiseHub Core readme](https://wordpress.org/plugins/fundraisehub-core/) for full details on the external service connection.

== Installation ==

1. Make sure **FundRaiseHub Core** and **Elementor** (3.5+) are installed and active.
2. In the WordPress admin go to **Plugins → Add New**, search for **FundRaiseHub Elementor**, and click **Install Now → Activate**.
3. Open a page in Elementor, search for *FundRaiseHub* in the widget panel, and drag any widget onto the canvas.

If a dependency is missing, an admin notice will appear with instructions.

== Frequently Asked Questions ==

= Do I need FundRaiseHub Core? =

Yes. FundRaiseHub Elementor is a companion plugin — it requires FundRaiseHub Core to be installed and active.

= Which version of Elementor is required? =

Elementor 3.5 or higher.

= How do I select a campaign in a widget? =

Each widget has a **Campaign** dropdown in its Elementor controls panel. The list is populated from campaigns synced by FundRaiseHub Core.

== Screenshots ==

1. Elementor panel showing FundRaiseHub Campaigns widget category.
2. Campaign Donate Button widget on the canvas.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.
