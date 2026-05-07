# FundRaiseHub for WordPress

Bring your FundRaiseHub fundraising campaigns directly into your WordPress site. The suite consists of two plugins:

| Plugin | Directory | Purpose |
|---|---|---|
| **FundRaiseHub Core** | `fundraisehub-core/` | API client, campaign sync, Gutenberg blocks, shortcodes, settings |
| **FundRaiseHub Elementor** | `fundraisehub-elementor/` | Optional Elementor widget pack (requires Core + Elementor) |

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Installation](#installation)
3. [First-Run Setup Wizard](#first-run-setup-wizard)
4. [Manual Configuration](#manual-configuration)
   - [Generating an API Key in FundRaiseHub](#generating-an-api-key-in-fundraisehub)
   - [Configuring the Plugin Settings](#configuring-the-plugin-settings)
   - [Verifying the Connection](#verifying-the-connection)
5. [Embedding Campaigns](#embedding-campaigns)
   - [Campaign Wrapper Block (Gutenberg)](#campaign-wrapper-block-gutenberg)
   - [Shortcodes](#shortcodes)
   - [Elementor Widgets](#elementor-widgets)
6. [Configuring Allowed Origins](#configuring-allowed-origins)
7. [Syncing Campaigns](#syncing-campaigns)
8. [Troubleshooting](#troubleshooting)
9. [Security Notes](#security-notes)
10. [Development](#development)

---

## Prerequisites

| Requirement | Minimum version |
|---|---|
| WordPress | **6.4** |
| PHP | **8.1** |
| Elementor *(optional, for the Elementor plugin only)* | **3.5** |

Both plugins require an active FundRaiseHub account and an API key scoped to your organisation or programme.

---

## Installation

### Install from a ZIP package

If you want an installable plugin ZIP instead of copying the plugin folders manually, build it from the repo root:

```bash
bash bin/build-zip.sh
```

When the script finishes, upload one of these files in **WordPress → Plugins → Add New → Upload Plugin**:

- `dist/fundraisehub-core-{version}.zip`
- `dist/fundraisehub-elementor-{version}.zip`

### FundRaiseHub Core (required)

1. Download or clone this repository.
2. Copy (or symlink) the `fundraisehub-core/` directory into your WordPress `wp-content/plugins/` folder.
3. In the WordPress admin, go to **Plugins → Installed Plugins** and activate **FundRaiseHub Core**.
4. On activation you will be automatically redirected to the **Setup Wizard**. Follow the steps to complete configuration.

### FundRaiseHub Elementor (optional)

> Requires FundRaiseHub Core **and** Elementor 3.5+ to be active before activation.

1. Copy the `fundraisehub-elementor/` directory into `wp-content/plugins/`.
2. Activate **FundRaiseHub Elementor** from the Plugins screen.

If either dependency is missing, an admin notice will appear explaining what is required.

---

## First-Run Setup Wizard

After activating FundRaiseHub Core you will be redirected to a guided three-step wizard at  
**Dashboard → (admin.php?page=fundraisehub-setup)**.

You can also reach the wizard any time the "needs-setup" flag is active by clicking the admin notice banner at the top of the WordPress admin.

### Step 1 — API Connection

1. Enter the **API URL** (the base URL of your FundRaiseHub installation, e.g. `https://app.fundraisehub.com`).
2. Paste your **API Key** (see [Generating an API Key](#generating-an-api-key-in-fundraisehub) below).
3. Click **Test & Continue**.

The wizard tests the connection before saving. If the test fails, an error message describes the problem (wrong URL, invalid key, network issue, etc.).

### Step 2 — Display Settings

Choose the **Campaign URL Slug** that will be used as the URL prefix for campaign pages and the campaign archive. The default is `campaigns`, giving URLs such as:

```
https://example.org/campaigns/
https://example.org/campaigns/my-campaign/
```

Click **Continue** when you are happy with the slug.

### Step 3 — Initial Sync

Click **Run Initial Sync** to fetch all published campaigns from FundRaiseHub and create corresponding WordPress posts. When the sync completes you will see a table of imported campaigns with **Edit** and **View** links.

Click **Finish Setup** to clear the setup flag and go to the main settings page. Your site is ready.

---

## Manual Configuration

### Generating an API Key in FundRaiseHub

1. Log in to your FundRaiseHub dashboard.
2. Go to **Settings → WordPress Connections**.
3. Click **Create API Key** (or **Generate New Key** if one already exists).
4. Copy the key — it will only be shown once.
5. Set the **Allowed Origin** to your WordPress site's exact origin (see [Configuring Allowed Origins](#configuring-allowed-origins)).

### Configuring the Plugin Settings

Navigate to **Settings → FundRaiseHub** in the WordPress admin.

| Field | Description |
|---|---|
| **API URL** | The base URL of your FundRaiseHub installation (`https://app.fundraisehub.com` for the hosted version). No trailing slash. |
| **API Key** | The Bearer token generated above. Click **Show** to reveal the stored value. |
| **Campaign Archive Slug** | URL prefix for campaign pages (default: `campaigns`). Changing this flushes rewrite rules automatically. |

Click **Save Settings** to save. The plugin will test the connection and display a success or failure notice.

### Verifying the Connection

After saving, the **Connection Status** row in the settings page displays one of:

- **Connected – *Organisation Name*** — the key is valid and the API is reachable.
- **Connection failed: *message*** — check the API URL, key, and network connectivity.

You can also use the **Sync Now** button at the bottom of the settings page to perform a manual sync and confirm data is flowing correctly.

---

## Embedding Campaigns

### Campaign Wrapper Block (Gutenberg)

The **FundRaiseHub Campaign** block (`fundraisehub/campaign-wrapper`) is available in the **Embed** category of the block inserter.

> **How it works:** The wrapper block is **post-meta-driven**. It reads campaign data from the `_fundraisehub_campaign_data` post meta field of the current `fundraisehub_campaign` post. This means the block is designed to be placed on the single-campaign page template generated by the plugin (i.e. on a post of type `fundraisehub_campaign` that was created by the sync). It does not offer a free-standing campaign picker — use the shortcode if you need to embed a specific campaign on an arbitrary page.

1. Open a `fundraisehub_campaign` post in the block editor (created automatically by the sync).
2. Click **+** → search for *"FundRaiseHub Campaign"* → insert the block.
3. The wrapper block can contain any combination of the built-in inner blocks:

   | Inner block | What it renders |
   |---|---|
   | Campaign Banner | Hero image/header |
   | Campaign Description | Long-form description text |
   | Campaign Stats Bar | Raised / Goal / Donors counters |
   | Campaign Thermometer | Progress bar towards goal |
   | Campaign Donate Button | Donation form trigger (opens in overlay) |
   | Campaign Donation Tiles | Pre-set donation amount tiles |
   | Campaign Video | Embedded campaign video |
   | Campaign Photo Gallery | Gallery of campaign images |
   | Campaign Teams | Fundraising team leaderboard |
   | Campaign Comments | Donor comments / messages |
   | Campaign Honor Scroll | Donor recognition scroll |

5. Save or publish the post.

> **Tip:** The Campaign Wrapper block is designed for use on the single-campaign post template generated by the plugin. For embedding a specific campaign on any arbitrary page, use the `[fundraisehub_campaign id="CAMPAIGN_ID"]` shortcode instead.

### Shortcodes

Shortcodes are provided as a fallback for Classic Editor users or page builders that do not support blocks.

#### Single campaign

```
[fundraisehub_campaign id="CAMPAIGN_ID"]
```

| Attribute | Default | Description |
|---|---|---|
| `id` | *(required)* | The remote campaign ID from FundRaiseHub |
| `class` | `""` | Extra CSS class(es) applied to the wrapper `<div>` |

#### Campaign list

```
[fundraisehub_campaign_list limit="10" category="education"]
```

| Attribute | Default | Description |
|---|---|---|
| `limit` | `10` | Number of campaigns to display |
| `category` | `""` | Filter by campaign category slug |
| `class` | `""` | Extra CSS class(es) applied to the list wrapper |

### Elementor Widgets

All FundRaiseHub widgets appear under the **FundRaiseHub Campaigns** category in the Elementor panel.

1. Open a page in the Elementor editor.
2. In the left panel, search for *"FundRaiseHub"* or scroll to the **FundRaiseHub Campaigns** section.
3. Drag any widget onto the canvas.
4. In the widget controls, select a **Campaign** from the dropdown (populated from synced campaigns).

Available widgets mirror the Gutenberg inner blocks:

| Widget | Description |
|---|---|
| Campaign Banner | Hero image/header |
| Campaign Description | Long-form description |
| Campaign Stats Bar | Raised / Goal / Donors counters |
| Campaign Thermometer | Progress bar |
| Campaign Donate Button | Donation form trigger |
| Campaign Donation Tiles | Pre-set donation amount tiles |
| Campaign Video | Embedded video |
| Campaign Photo Gallery | Image gallery |
| Campaign Teams | Team leaderboard |
| Campaign Comments | Donor comments |
| Campaign Honor Scroll | Donor recognition scroll |

---

## Configuring Allowed Origins

The donation form is embedded as an iframe served from FundRaiseHub. To enable secure cross-origin communication (postMessage) between the donation iframe and your WordPress site, you must configure the allowed origin in FundRaiseHub.

1. In FundRaiseHub, go to **Settings → WordPress Connections**.
2. In the **Allowed Origin** field, enter your WordPress site's **exact** origin — scheme, host, and optional port — with **no trailing slash**.

   ```
   ✅  https://example.org
   ✅  https://www.example.org
   ✅  https://example.org:8443
   ❌  https://example.org/          ← trailing slash not allowed
   ❌  example.org                   ← scheme required
   ```

3. Save. The origin must match exactly what appears in the `Origin` header sent by the browser when a donor visits your WordPress site.

> **Note:** If your site is accessible on multiple domains (e.g. `www.example.org` and `example.org`), you may need to configure a separate API key / connection for each origin, or ensure canonical redirects are in place so only one origin is used.

---

## Syncing Campaigns

Campaign data is kept up-to-date in two ways:

| Method | How it works |
|---|---|
| **Automatic (WP-Cron)** | A daily WP-Cron event (`fundraisehub_campaign_sync`) runs `CampaignSync::sync_all()` and refreshes all campaigns. |
| **Manual** | Go to **Settings → FundRaiseHub** → click **Sync Now**. You will see a success notice when complete. |

The sync is **idempotent**: campaigns whose remote data has not changed (same content hash) are skipped, so repeated syncs are inexpensive.

Current backend contract support:

- Campaign list sync calls `/api/wp/v1/campaigns`, then fetches each campaign detail from `/api/wp/v1/campaigns/{campaignId}`.
- Detail responses using nested payloads (for example `data.campaign`, `data.teams`, `data.ambassadors`, `data.comments`, `data.media`, `data.recentDonations`) are normalized and stored in `_fundraisehub_campaign_data`.
- Renderers and shortcodes consume normalized fields such as `id`, `name/title`, `description`, `amount_raised`, `goal_amount`, `donor_count`, `donation_amounts`, `teams`, `comments`, `media`, and `recentDonations`.

After updating to a version that includes this contract support, run **Sync Now** once so existing campaign posts are refreshed with the latest detail payload.

To delete all cached data and force a fresh fetch:

```bash
wp transient delete --all  # deletes all transients including API response cache
```

---

## Troubleshooting

### CORS errors in the browser console

**Symptom:** Browser shows errors like `Blocked by CORS policy` or `Access-Control-Allow-Origin` is missing when the donation form tries to communicate with your site.

**Cause:** The **Allowed Origin** in FundRaiseHub does not match your site's origin.

**Fix:**
1. Check the exact origin in the browser's Network tab (look for the `Origin` request header on a failing request).
2. In FundRaiseHub (**Settings → WordPress Connections**), update the **Allowed Origin** to match exactly (no trailing slash, correct scheme).
3. Save. Changes typically take effect within a minute.

### API key rotation (changing to a new key)

1. Generate a new key in FundRaiseHub (**Settings → WordPress Connections → Generate New Key**).
2. Copy the new key immediately — it is only shown once.
3. In WordPress, go to **Settings → FundRaiseHub**, paste the new key, and click **Save Settings**.
4. The plugin automatically busts the API cache and tests the new key on save.
5. Revoke the old key in FundRaiseHub once you have confirmed the new key works.

> **Note:** The API key is stored as a WordPress option (`fundraisehub_api_key`). It is not stored in any log file or exposed to the browser.

### Stale or missing campaign data (re-sync)

If campaigns are out of date or missing after a sync, try:

1. Go to **Settings → FundRaiseHub** and click **Sync Now**.
2. If that does not help, delete the API cache transients and retry:

   ```bash
   wp transient delete --all
   ```

3. Check that the API key and URL are correct under **Settings → FundRaiseHub → Connection Status**.
4. If WP-Cron is not running (common on low-traffic sites), configure a real cron job to call `wp cron event run fundraisehub_campaign_sync` on a schedule, or install a plugin like WP Crontrol to verify cron events are firing.

### "FundRaiseHub Elementor requires FundRaiseHub Core"

Activate **FundRaiseHub Core** before (or alongside) FundRaiseHub Elementor.

### "FundRaiseHub Elementor requires Elementor version 3.5 or higher"

Update the Elementor plugin to version 3.5 or later.

### Campaign archive shows a 404

After changing the **Campaign Archive Slug**, WordPress needs its rewrite rules flushed:

1. Go to **Settings → Permalinks** and click **Save Changes** (no actual change needed).

The plugin flushes rewrite rules automatically when the slug is changed via the settings page or the wizard, but a manual flush is sometimes needed after a direct database update or import.

---

## Security Notes

- **Scope:** The API key is scoped to a specific organisation or programme within FundRaiseHub. It grants access **only** to public campaign display data (titles, descriptions, goals, images, donation form URLs). It cannot access financial transaction records, donor personally identifiable information (PII), or any administrative functions within FundRaiseHub.

- **Storage:** The API key is stored in the WordPress `wp_options` table as a plain string. Protect your WordPress database accordingly. The key is never written to log files or rendered in HTML visible to unauthenticated visitors.

- **Transport:** All API requests use HTTPS. The key is sent as a `Bearer` token in the `Authorization` HTTP header.
- **Origin hardening:** Server-to-server requests also include deterministic `Origin` and `X-FundraiseHub-Site-Origin` headers derived from `home_url()` as defense in depth for backend origin validation.

- **Output escaping:** All data fetched from the FundRaiseHub API is escaped before being output to HTML (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).

- **Nonces:** All admin forms (settings, sync, setup wizard) use WordPress nonces to prevent CSRF.

- **Key rotation:** Rotate your API key regularly using the procedure described in [Troubleshooting → API key rotation](#api-key-rotation-changing-to-a-new-key).

---

## Development

### Prerequisites

```bash
composer install   # PHP dev dependencies (phpunit, phpcs, WPCS)
npm ci             # JavaScript dev dependencies (@wordpress/scripts)
```

### PHP

```bash
composer run phpcs   # Lint with WordPress Coding Standards
composer run phpcbf  # Auto-fix coding style
composer run test    # Run PHPUnit unit tests
```

### JavaScript / Blocks

```bash
npm run build      # Production webpack build
npm run start      # Development watch mode
npm run lint:js    # ESLint
npm run lint:css   # Stylelint
```

Compiled assets are written to `fundraisehub-core/assets/`. When adding a new Gutenberg block, register its webpack entry point in `webpack.config.js` and place the block source under `fundraisehub-core/blocks/{block-name}/` with a `block.json` metadata file.

### Adding an Elementor widget

1. Create `fundraisehub-elementor/widgets/{slug}/class-{slug}-widget.php`.
2. Define class `FundRaiseHub\Elementor\Widget\{PascalCase}` extending `\Elementor\Widget_Base`.
3. `ElementorManager` auto-discovers and registers widgets — no manual registration required.

### Plugin file structure

```
fundraisehub-core/
  fundraisehub-core.php           # Entry point, constants, activation/deactivation hooks
  includes/
    class-api-client.php          # Authenticated HTTP client (WP HTTP API)
    class-campaign-cpt.php        # Registers the fundraisehub_campaign CPT
    class-campaign-sync.php       # Fetch, cache, and mirror campaigns into the CPT
    class-settings.php            # Settings API page (API key + slug)
    class-setup-wizard.php        # First-run setup wizard (3 steps)
    class-block-registry.php      # Auto-discovers and registers Gutenberg blocks
    class-shortcode-registry.php  # [fundraisehub_campaign] / [fundraisehub_campaign_list]
    class-campaign-renderer.php   # Shared rendering helpers
  blocks/                         # Gutenberg block source (block.json + JS + render.php)
  assets/                         # Compiled CSS and JS (generated by npm run build)

fundraisehub-elementor/
  fundraisehub-elementor.php      # Entry point, dependency checks
  includes/
    class-elementor-manager.php   # Auto-discovers and registers Elementor widgets
  widgets/                        # One sub-directory per widget
```
