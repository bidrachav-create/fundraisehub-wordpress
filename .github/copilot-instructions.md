# Copilot Instructions for fundraisehub-wordpress

## Project overview

This repository contains two WordPress plugins that embed FundRaiseHub fundraising campaigns into any WordPress site:

| Plugin | Directory | Namespace | Purpose |
|---|---|---|---|
| FundRaiseHub Core | `fundraisehub-core/` | `FundRaiseHub\Core` | Core plugin – API client, CPT, blocks, shortcodes, settings, cron sync |
| FundRaiseHub Elementor | `fundraisehub-elementor/` | `FundRaiseHub\Elementor` | Optional Elementor widget pack; requires Core + Elementor |

**WordPress compatibility target:** WordPress 6.4+, PHP 8.1+. The codebase is intentionally kept current with the latest stable WordPress release. Always use up-to-date WordPress APIs and avoid deprecated functions.

---

## Repository layout

```
fundraisehub-core/
  fundraisehub-core.php          # Plugin entry point; constants, activation/deactivation hooks, bootstrap
  uninstall.php                  # Cleanup on plugin uninstall
  includes/
    class-api-client.php         # ApiClient – wraps WP HTTP API calls to the FundRaiseHub REST API
    class-campaign-cpt.php       # CampaignCPT – registers `fundraisehub_campaign` custom post type
    class-campaign-sync.php      # CampaignSync – fetches/caches campaigns via transients + WP-Cron
    class-settings.php           # Settings – Settings API page (API key + site URL)
    class-block-registry.php     # BlockRegistry – auto-discovers and registers blocks from blocks/
    class-shortcode-registry.php # ShortcodeRegistry – shortcodes [fundraisehub_campaign] / [fundraisehub_campaign_list]
  blocks/                        # Gutenberg blocks; each block lives in blocks/{block-name}/ with block.json
  assets/
    css/                         # Compiled CSS (gitignored *.min.css)
    js/                          # Compiled JS (gitignored *.min.js)

fundraisehub-elementor/
  fundraisehub-elementor.php     # Plugin entry point; dependency checks, bootstrap
  includes/
    class-elementor-manager.php  # ElementorManager – auto-discovers widgets from widgets/
  widgets/                       # Elementor widgets; each widget lives in widgets/{slug}/class-{slug}-widget.php
                                 # Each widget class: FundRaiseHub\Elementor\Widget\{PascalCase} extends \Elementor\Widget_Base

tests/
  bootstrap.php                  # PHPUnit bootstrap – loads Composer autoloader; no full WP install required for unit tests

composer.json                    # PHP deps and scripts (phpcs, phpcbf, test)
package.json                     # JS deps and scripts (build, start, lint:js, lint:css)
webpack.config.js                # Extends @wordpress/scripts webpack config; output → fundraisehub-core/assets/
phpunit.xml                      # PHPUnit config – test suite discovers *Test.php in tests/
```

---

## Build and tooling

### PHP (Composer)

```bash
composer install              # Install PHP dev dependencies (phpunit, phpcs, wpcs)
composer run phpcs            # Lint PHP code with WordPress Coding Standards
composer run phpcbf           # Auto-fix PHP coding style issues
composer run test             # Run PHPUnit unit tests
```

> **Note:** `tests/bootstrap.php` only loads the Composer autoloader. Unit tests must not depend on a live WordPress install. For integration tests that need WordPress internals, set up `WP_TESTS_DIR` and extend the bootstrap accordingly.

### JavaScript / Blocks (npm)

```bash
npm install                   # Install JS dev dependencies (@wordpress/scripts)
npm run build                 # Production build (webpack via @wordpress/scripts)
npm run start                 # Development watch mode
npm run lint:js               # ESLint JS files
npm run lint:css              # Stylelint CSS files
```

Block entry points are declared in `webpack.config.js`. When adding a new block, add its entry point there and place the block source under `fundraisehub-core/blocks/{block-name}/`.

---

## Coding conventions

### PHP

- **Strict types:** Every file begins with `declare( strict_types=1 );`.
- **Namespaces:** `FundRaiseHub\Core` for core plugin classes; `FundRaiseHub\Elementor` for Elementor classes; `FundRaiseHub\Elementor\Widget` for individual widget classes.
- **File naming:** `class-{classname-kebab-case}.php` (e.g. `class-api-client.php` → `class ApiClient`).
- **Coding standard:** WordPress Coding Standards (WPCS 3.x) enforced by `phpcs --standard=WordPress`. Run `composer run phpcs` to check; `composer run phpcbf` to auto-fix.
- **WordPress APIs:** Always use WP HTTP API (`wp_remote_get`, `wp_remote_post`), transients, options, and hooks. Do not use raw `curl` or PDO.
- **Security:** Always escape output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`), sanitize input (`sanitize_text_field`, `esc_url_raw`), and verify nonces where applicable.
- **Internationalisation:** Wrap all user-facing strings in `__()`, `_x()`, `esc_html__()`, etc. with text domain `fundraisehub-core` or `fundraisehub-elementor`.
- **Docblocks:** All classes and public/protected methods must have a PHPDoc block with `@param` and `@return` tags.
- **ABSPATH guard:** Every PHP file starts with `if ( ! defined( 'ABSPATH' ) ) { exit; }` (after the `declare` and namespace statements).
- **WordPress version target:** Always use APIs available in WordPress 6.4+. Check `Requires at least:` headers in plugin files.

### JavaScript / Blocks

- Built with `@wordpress/scripts` (webpack). Uses the default WordPress ESLint and Stylelint configs.
- New Gutenberg blocks must include a `block.json` metadata file (block API v3) inside `fundraisehub-core/blocks/{block-name}/`.
- Add the new block's JS entry point to `webpack.config.js` before building.
- Output path for compiled assets: `fundraisehub-core/assets/`.

### Elementor widgets

- Each widget lives in `fundraisehub-elementor/widgets/{slug}/class-{slug}-widget.php`.
- Widget class: `FundRaiseHub\Elementor\Widget\{PascalCase}` (e.g. slug `campaign-card` → class `CampaignCard`).
- Must extend `\Elementor\Widget_Base`.
- `ElementorManager` auto-discovers and registers widgets; no manual registration required.

---

## Key architectural patterns

### Plugin bootstrap

Both plugins bootstrap on the `plugins_loaded` action to ensure dependencies are available. `fundraisehub-elementor` performs explicit dependency checks for both FundRaiseHub Core (`FUNDRAISEHUB_CORE_VERSION` constant) and Elementor (`elementor/loaded` action).

### API client

`ApiClient` wraps `wp_remote_get` / `wp_remote_post` with Bearer token authentication. Configuration (`fundraisehub_api_key`, `fundraisehub_site_url`) is stored as WordPress options. Returns `mixed[]|\WP_Error`.

### Campaign caching

`CampaignSync` caches API responses in WordPress transients with a 1-hour TTL (`HOUR_IN_SECONDS`). The WP-Cron event `fundraisehub_campaign_sync` fires hourly to refresh all campaigns. Cache keys:
- Individual campaign: `fundraisehub_campaign_{id}`
- List queries: `fundraisehub_campaign_list_{md5(args)}`

### Custom post type

`CampaignCPT` registers the `fundraisehub_campaign` CPT with REST API support (`show_in_rest: true`). The CPT mirrors remote campaign data and is flushed on activation/deactivation.

### WordPress options

| Option key | Purpose |
|---|---|
| `fundraisehub_api_key` | Bearer token for the FundRaiseHub REST API |
| `fundraisehub_site_url` | Base URL of the remote FundRaiseHub installation |
| `fundraisehub_needs_setup` | Flag shown as an admin notice prompting initial configuration |

---

## Testing

- Framework: PHPUnit 10.x
- Config: `phpunit.xml` (bootstrap: `tests/bootstrap.php`; discovers `*Test.php` under `tests/`)
- Test classes must live in `tests/` and end in `Test.php`
- Run: `composer run test`
- Unit tests mock WordPress functions as needed (no live WP install); for integration tests, load the WP test suite separately via `WP_TESTS_DIR`.

---

## Common errors and workarounds

- **"Composer autoloader not found"** during `composer run test`: Run `composer install` first.
- **phpcs "WordPress" standard not found**: Run `composer install` – the `dealerdirect/phpcodesniffer-composer-installer` plugin wires WPCS automatically.
- **Block not appearing**: Ensure its entry point is in `webpack.config.js`, run `npm run build`, and confirm `block.json` exists in the block directory.
- **Elementor widget not loading**: The widget file must match the pattern `widgets/{slug}/class-{slug}-widget.php` and the class must extend `\Elementor\Widget_Base` with the fully-qualified namespace `FundRaiseHub\Elementor\Widget\{PascalCase}`.
- **Stale campaign data**: Delete transients matching `fundraisehub_campaign_*` via WP-CLI (`wp transient delete --all`) or trigger a manual sync via `CampaignSync::sync_all()`.
- **Settings not saving**: Confirm `register_setting()` option group matches `settings_fields()` call (`fundraisehub_settings`).
