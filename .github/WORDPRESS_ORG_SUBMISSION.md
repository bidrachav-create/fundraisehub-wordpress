# WordPress.org Plugin Submission Guide

This document covers how to build distributable ZIPs and publish both FundRaiseHub plugins to the WordPress.org plugin directory.

---

## Generating the ZIP packages

Run the build script from the repo root. It compiles JS assets, then packages each plugin into a clean ZIP under `dist/`, excluding all dev artefacts (`node_modules`, `vendor`, block source JS, `tests/`, dev config files). After it finishes, installable ZIPs will be in:

```text
dist/fundraisehub-core-{version}.zip
dist/fundraisehub-elementor-{version}.zip
```

```bash
# Full build (compiles JS then packages both plugins):
bash bin/build-zip.sh

# Skip the JS rebuild if assets are already compiled:
bash bin/build-zip.sh --skip-build

# Package only one plugin:
bash bin/build-zip.sh --core-only
bash bin/build-zip.sh --elementor-only
```

Output:

```
dist/fundraisehub-core-{version}.zip
dist/fundraisehub-elementor-{version}.zip
```

---

## Before you submit

1. **Create a WordPress.org account** at <https://login.wordpress.org/register> if you don't already have one.

2. **Choose a plugin slug.** The slug becomes the permanent URL (`wordpress.org/plugins/YOUR-SLUG`). Suggested slugs:
   - `fundraisehub-core` — the main plugin
   - `fundraisehub-elementor` — the Elementor add-on (submit separately, after core is approved)

3. **Add screenshots.** Prepare PNG/JPG screenshots labelled `screenshot-1.png`, `screenshot-2.png`, etc. matching the `== Screenshots ==` section in each `readme.txt`. These are uploaded to the SVN `assets/` folder after approval (see below).

4. **Verify `Tested up to`** in each `readme.txt` matches the current WordPress stable release before every submission or update. Current value: `6.7`.

---

## Submitting a plugin (first time)

1. Go to **<https://wordpress.org/plugins/developers/add/>**.
2. Fill in the form: plugin name, upload the ZIP, and confirm the slug.
3. The review team inspects your code (typically within a few weeks). They check:
   - GPL 2.0+ license compliance
   - Security practices (escaping, nonces, sanitisation)
   - No tracking or data collection without user consent
   - General code quality
4. Once approved you will receive an email with SVN repository access.

---

## After approval — SVN setup

WordPress.org uses SVN (not Git) for distribution.

```bash
# Check out your SVN repo:
svn co https://plugins.svn.wordpress.org/fundraisehub-core svn-fundraisehub-core
cd svn-fundraisehub-core

# Copy plugin files into trunk/:
cp -r /path/to/fundraisehub-core/* trunk/

# Add optional assets (screenshots, banner, icon):
cp screenshot-1.png assets/
cp plugin-banner-1544x500.png assets/   # optional – shown on the directory page
cp plugin-icon-256x256.png assets/      # optional – shown in search results

# Stage and commit trunk:
svn add --force trunk/ assets/
svn commit -m "Initial release 1.0.0"

# Tag the release (WordPress.org serves the tag matching Stable tag in readme.txt):
svn cp trunk tags/1.0.0
svn commit -m "Tag 1.0.0"
```

> The `Stable tag: 1.0.0` value in `readme.txt` **must** match the tag you create in SVN.

---

## Releasing future versions

1. Update `Version:` in the plugin's main PHP file and `Stable tag:` in `readme.txt`.
2. Add a new entry to the `== Changelog ==` section in `readme.txt`.
3. Run `bash bin/build-zip.sh --skip-build` to verify the ZIP builds cleanly.
4. Commit the updated files to `trunk/` in SVN, then create a new tag:

```bash
svn cp trunk tags/1.1.0
svn commit -m "Tag 1.1.0"
```

---

## Submitting the Elementor add-on separately

Repeat the same process using the `fundraisehub-elementor` slug. Because `fundraisehub-elementor.php` already contains `Requires Plugins: fundraisehub-core`, WordPress.org will automatically prompt users to install Core first and surface the dependency in the directory UI.

---

## Key files

| File | Purpose |
|---|---|
| `fundraisehub-core/readme.txt` | WordPress.org readme for the core plugin |
| `fundraisehub-elementor/readme.txt` | WordPress.org readme for the Elementor add-on |
| `bin/build-zip.sh` | Script that builds and packages both plugins |
| `dist/` | Output directory for generated ZIPs (gitignored) |
