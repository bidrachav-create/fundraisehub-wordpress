=== FundRaiseHub Core ===
Contributors: fundraisehub
Tags: fundraising, campaigns, donations, nonprofit, blocks
Requires at least: 6.4
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bring your FundRaiseHub fundraising campaigns directly into your WordPress site with Gutenberg blocks and shortcodes.

== Description ==

FundRaiseHub Core connects your WordPress site to your FundRaiseHub account, letting you embed live fundraising campaigns anywhere on your site using the block editor, shortcodes, or Elementor (with the companion FundRaiseHub Elementor plugin).

**Features**

* **Setup Wizard** — a guided three-step wizard walks you through API connection, URL slug, and initial campaign sync on first activation.
* **Gutenberg blocks** — a full set of campaign blocks that compose inside the Campaign Wrapper block:
  * Campaign Banner (hero image)
  * Campaign Description
  * Campaign Stats Bar (raised / goal / donors)
  * Campaign Thermometer (progress bar)
  * Campaign Donate Button (opens donation overlay)
  * Campaign Donation Tiles (preset amounts)
  * Campaign Video
  * Campaign Photo Gallery
  * Campaign Teams leaderboard
  * Campaign Comments
  * Campaign Honor Scroll
* **Shortcodes** — `[fundraisehub_campaign id="…"]` and `[fundraisehub_campaign_list]` for classic editor compatibility.
* **Automatic sync** — a daily WP-Cron event keeps campaign data fresh; a manual **Sync Now** button is available on the settings page.
* **Custom post type** — campaigns are stored as the `fundraisehub_campaign` CPT with full REST API support, making them available for custom themes and queries.

**Requirements**

* An active [FundRaiseHub](https://fundraisehub.com/) account.
* An API key generated in FundRaiseHub under **Settings → WordPress Connections**.

= External Services =

This plugin connects to the FundRaiseHub service to retrieve campaign data. Requests are made to the API URL that you configure in the plugin settings (e.g. `https://app.fundraisehub.com`). No data is sent to any external service without your explicit configuration.

* [FundRaiseHub Terms of Service](https://fundraisehub.com/terms)
* [FundRaiseHub Privacy Policy](https://fundraisehub.com/privacy)

The donation form is embedded as an iframe served from your configured FundRaiseHub installation. The `donate-bridge.js` script handles secure cross-origin `postMessage` communication between the iframe and your site.

== Installation ==

= Automatic installation (recommended) =

1. In the WordPress admin go to **Plugins → Add New**.
2. Search for **FundRaiseHub**.
3. Click **Install Now**, then **Activate**.
4. You will be redirected to the **Setup Wizard** — follow the three steps to connect your FundRaiseHub account.

= Manual installation =

1. Download the plugin zip file.
2. In the WordPress admin go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip file and click **Install Now**, then **Activate**.
4. Follow the Setup Wizard to complete configuration.

= After activation =

1. The Setup Wizard opens automatically. Enter your **API URL** (e.g. `https://app.fundraisehub.com`) and **API Key**.
2. Choose a **Campaign Archive Slug** (default: `campaigns`).
3. Click **Run Initial Sync** to import all published campaigns.

You can also configure the plugin manually at **Settings → FundRaiseHub**.

== Frequently Asked Questions ==

= Where do I get an API key? =

Log in to your FundRaiseHub dashboard, go to **Settings → WordPress Connections**, and click **Create API Key**. Copy the key immediately — it is shown only once.

= What is the API URL? =

For the hosted FundRaiseHub service the URL is `https://app.fundraisehub.com`. If you are running a self-hosted FundRaiseHub instance, use that instance's base URL with no trailing slash.

= How do I embed a campaign on a page? =

In the block editor, click **+** and search for *FundRaiseHub Campaign*. Insert the Campaign Wrapper block, then add inner blocks (Banner, Stats Bar, Donate Button, etc.) inside it. Save the page.

For the Classic Editor, use `[fundraisehub_campaign id="CAMPAIGN_ID"]`.

= How often is campaign data refreshed? =

Automatically once per day via WP-Cron. You can also trigger an immediate refresh by clicking **Sync Now** on the **Settings → FundRaiseHub** page.

= The donation form does not load or shows a CORS error. =

Make sure the **Allowed Origin** in FundRaiseHub (**Settings → WordPress Connections**) is set to your WordPress site's exact origin (`scheme://host[:port]`, no path or trailing slash), e.g. `https://example.org` or `https://example.org:8443`. The plugin normalizes `home_url()` to this origin format when sending `Origin` and `X-FundraiseHub-Site-Origin` headers.

= Does the plugin store donor data? =

No. FundRaiseHub Core only stores campaign display data (titles, descriptions, goals, images, donation form URLs). All financial transactions and donor PII are handled entirely within FundRaiseHub.

= Is the API key secure? =

The key is stored in the WordPress `wp_options` table and is never exposed in page HTML or log files. It is transmitted over HTTPS as a `Bearer` token. Rotate your key regularly in FundRaiseHub (**Settings → WordPress Connections → Generate New Key**).

= Is this plugin compatible with Elementor? =

Yes — install the companion **FundRaiseHub Elementor** plugin (requires this plugin and Elementor 3.5+) for a full set of Elementor widgets.

== Screenshots ==

1. Setup Wizard — Step 1: API Connection.
2. Setup Wizard — Step 3: Initial campaign sync results.
3. Settings page showing Connection Status and Sync Now button.
4. Block editor with Campaign Wrapper block and inner blocks.
5. Front-end campaign embed with Stats Bar and Donate Button.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps required.
