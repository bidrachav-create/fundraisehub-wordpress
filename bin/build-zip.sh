#!/usr/bin/env bash
# build-zip.sh – Build distributable ZIP files for the FundRaiseHub WordPress plugins.
#
# Usage:
#   bash bin/build-zip.sh [--core-only] [--elementor-only] [--skip-build]
#
# Output:
#   dist/fundraisehub-core-{version}.zip
#   dist/fundraisehub-elementor-{version}.zip
#
# The script:
#   1. Runs `npm run build` to compile JS assets (skip with --skip-build).
#   2. Copies each plugin directory to a temp staging area, excluding all
#      development files (node_modules, vendor, source JS, tests, dev config).
#   3. Packages each staging area into a zip under dist/.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="${REPO_ROOT}/dist"

BUILD_CORE=true
BUILD_ELEMENTOR=true
SKIP_BUILD=false

# Parse flags.
for arg in "$@"; do
  case "$arg" in
    --core-only)      BUILD_ELEMENTOR=false ;;
    --elementor-only) BUILD_CORE=false ;;
    --skip-build)     SKIP_BUILD=true ;;
    *)
      echo "Unknown option: $arg"
      echo "Usage: $0 [--core-only] [--elementor-only] [--skip-build]"
      exit 1
      ;;
  esac
done

# ── 1. Build JS assets ────────────────────────────────────────────────────────
if [ "$SKIP_BUILD" = false ]; then
  echo "==> Building JavaScript assets…"
  cd "${REPO_ROOT}"
  npm run build
  echo "==> Build complete."
fi

# ── 2. Helper: read Version from plugin header ────────────────────────────────
get_version() {
  local file="$1"
  grep -m1 'Version:' "$file" | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]'
}

# ── 3. Helper: package a plugin directory ─────────────────────────────────────
# Usage: package_plugin <slug> <src_dir> <version>
package_plugin() {
  local slug="$1"
  local src="$2"
  local version="$3"
  local zip_name="${slug}-${version}.zip"
  local staging
  staging="$(mktemp -d)"
  local plugin_staging="${staging}/${slug}"

  echo "==> Staging ${slug} ${version}…"

  # Copy the plugin directory into staging/<slug>/ so the zip extracts cleanly.
  cp -r "${src}" "${plugin_staging}"

  # ── Remove development artefacts ──────────────────────────────────────────
  # Source JS files inside blocks/ (compiled versions live in assets/blocks/).
  find "${plugin_staging}/blocks" \
    \( -name "*.js" -o -name "*.jsx" -o -name "*.ts" -o -name "*.tsx" \) \
    -delete 2>/dev/null || true

  # Source JS entry point directory (compiled into assets/js/).
  rm -rf "${plugin_staging}/js" 2>/dev/null || true

  # Git, CI, and development config files.
  rm -f \
    "${plugin_staging}/.gitignore" \
    "${plugin_staging}/.gitkeep" 2>/dev/null || true

  # Remove any stray .gitkeep files inside assets subdirectories.
  find "${plugin_staging}" -name ".gitkeep" -delete 2>/dev/null || true

  echo "==> Creating ${zip_name}…"
  mkdir -p "${DIST_DIR}"
  (cd "${staging}" && zip -r -q "${DIST_DIR}/${zip_name}" "${slug}/")

  rm -rf "${staging}"
  echo "==> Created: dist/${zip_name}"
}

# ── 4. Package core ───────────────────────────────────────────────────────────
if [ "$BUILD_CORE" = true ]; then
  CORE_DIR="${REPO_ROOT}/fundraisehub-core"
  CORE_VERSION="$(get_version "${CORE_DIR}/fundraisehub-core.php")"
  package_plugin "fundraisehub-core" "${CORE_DIR}" "${CORE_VERSION}"
fi

# ── 5. Package Elementor add-on ───────────────────────────────────────────────
if [ "$BUILD_ELEMENTOR" = true ]; then
  ELEMENTOR_DIR="${REPO_ROOT}/fundraisehub-elementor"
  ELEMENTOR_VERSION="$(get_version "${ELEMENTOR_DIR}/fundraisehub-elementor.php")"
  package_plugin "fundraisehub-elementor" "${ELEMENTOR_DIR}" "${ELEMENTOR_VERSION}"
fi

echo ""
echo "Done. ZIP files are in: ${DIST_DIR}/"
ls -lh "${DIST_DIR}/"
