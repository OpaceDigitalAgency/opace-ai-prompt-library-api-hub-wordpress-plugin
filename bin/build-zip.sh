#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="opace-ai-prompt-library-api-hub"
MAIN_FILE="${PLUGIN_SLUG}.php"
SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT_DIR="${SRC_DIR}/dist"

if [[ "${1:-}" == "--output" ]]; then
    [[ -n "${2:-}" ]] || { echo "--output requires a directory" >&2; exit 2; }
    OUTPUT_DIR="$2"
elif [[ $# -gt 0 ]]; then
    echo "Usage: bin/build-zip.sh [--output DIR]" >&2
    exit 2
fi

VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "${SRC_DIR}/${MAIN_FILE}" | head -n 1 | tr -d '[:space:]')"
CONST_VERSION="$(sed -n "s/.*define('AI_CORE_VERSION', '\([^']*\)').*/\1/p" "${SRC_DIR}/${MAIN_FILE}" | head -n 1)"
LIBRARY_VERSION="$(sed -n "s/^[[:space:]]*const VERSION = '\([^']*\)';/\1/p" "${SRC_DIR}/lib/src/AICore.php" | head -n 1)"
AUTOLOAD_VERSION="$(sed -n "s/.*define('AI_CORE_VERSION', '\([^']*\)').*/\1/p" "${SRC_DIR}/lib/autoload.php" | head -n 1)"
HTTP_VERSION="$(sed -n "s/.*AI_CORE_VERSION.*: '\([^']*\)';/\1/p" "${SRC_DIR}/lib/src/Http/HttpClient.php" | head -n 1)"
JSON_VERSION="$(sed -n 's/^[[:space:]]*"version":[[:space:]]*"\([^"]*\)".*/\1/p' "${SRC_DIR}/lib/version.json" | head -n 1)"
STABLE_TAG="$(sed -n 's/^Stable tag:[[:space:]]*//p' "${SRC_DIR}/readme.txt" | head -n 1 | tr -d '[:space:]')"

[[ -n "${VERSION}" ]] || { echo "Could not read the plugin version." >&2; exit 1; }
[[ "${CONST_VERSION}" == "${VERSION}" ]] || { echo "AI_CORE_VERSION does not match ${VERSION}." >&2; exit 1; }
[[ "${LIBRARY_VERSION}" == "${VERSION}" ]] || { echo "AICore::VERSION does not match ${VERSION}." >&2; exit 1; }
[[ "${AUTOLOAD_VERSION}" == "${VERSION}" ]] || { echo "Autoload fallback version does not match ${VERSION}." >&2; exit 1; }
[[ "${HTTP_VERSION}" == "${VERSION}" ]] || { echo "HTTP client fallback version does not match ${VERSION}." >&2; exit 1; }
[[ "${JSON_VERSION}" == "${VERSION}" ]] || { echo "lib/version.json does not match ${VERSION}." >&2; exit 1; }
[[ "${STABLE_TAG}" == "${VERSION}" ]] || { echo "Stable tag does not match ${VERSION}." >&2; exit 1; }

STAGE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/ai-core-build.XXXXXXXX")"
trap 'rm -rf "${STAGE_DIR}"' EXIT
mkdir -p "${STAGE_DIR}/${PLUGIN_SLUG}" "${OUTPUT_DIR}"

rsync -a --exclude-from="${SRC_DIR}/.distignore" "${SRC_DIR}/" "${STAGE_DIR}/${PLUGIN_SLUG}/"
find "${STAGE_DIR}/${PLUGIN_SLUG}" -name '.DS_Store' -delete

# ZIP records timestamps and Unix permissions. Normalise both so rebuilding
# unchanged source produces the same reviewable package hash.
find "${STAGE_DIR}/${PLUGIN_SLUG}" -type d -exec chmod 0755 {} +
find "${STAGE_DIR}/${PLUGIN_SLUG}" -type f -exec chmod 0644 {} +
find "${STAGE_DIR}/${PLUGIN_SLUG}" -exec touch -t 198001010000 {} +

ZIP_PATH="${OUTPUT_DIR}/${PLUGIN_SLUG}-${VERSION}.zip"
rm -f "${ZIP_PATH}"
(
    cd "${STAGE_DIR}"
    find "${PLUGIN_SLUG}" -print | LC_ALL=C sort | zip -X -q "${ZIP_PATH}" -@
)

for forbidden in '/.git/' '/.github/' '/.wordpress-org/' '/bin/' '/docs/' '/research/' '/recovery/' '/backups/' '.DS_Store'; do
    if unzip -Z1 "${ZIP_PATH}" | grep -Fq -- "${forbidden}"; then
        echo "Distribution leak detected: ${forbidden}" >&2
        exit 1
    fi
done

echo "Built ${ZIP_PATH}"
echo "SHA256 $(shasum -a 256 "${ZIP_PATH}" | awk '{print $1}')"
unzip -Z1 "${ZIP_PATH}" | tail -n +1 | wc -l | awk '{print $1 " archive entries"}'
