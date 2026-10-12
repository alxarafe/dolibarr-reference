#!/usr/bin/env bash
# scripts/capture-technical.sh - Single authoritative technical reference capture engine
# Strict shell policy
set -euo pipefail

# Required environment variables:
# BASE_URL          - Dolibarr base URL (e.g., http://127.0.0.1:48240)
# API_KEY           - REST API key for authentication
# DB_CONTAINER      - MariaDB container name/ID
# DB_USER           - Database username
# DB_PASSWORD       - Database password
# DB_NAME           - Database name
# WEB_CONTAINER     - Dolibarr web container name/ID
# OUTPUT_DIR        - Directory to write output artifacts
#
# Optional environment variables (with defaults):
# SOURCE_COMMIT     - Dolibarr source commit (default: b7958385f00a92219f76a4dfea67dae93df97a25)
# DOLIBARR_IMAGE    - Dolibarr Docker image (default: dolibarr/dolibarr:24.0.1@sha256:125a5b6838e1b0f5c1e85678967026b1f72775bd3a08252b440c06799d2c19ce)
# DB_IMAGE          - MariaDB Docker image (default: mariadb:10.11.19-jammy@sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c)

# Validate required environment
if [[ -z "${BASE_URL:-}" ]]; then echo "ERROR: BASE_URL not set" >&2; exit 1; fi
if [[ -z "${API_KEY:-}" ]]; then echo "ERROR: API_KEY not set" >&2; exit 1; fi
if [[ -z "${DB_CONTAINER:-}" ]]; then echo "ERROR: DB_CONTAINER not set" >&2; exit 1; fi
if [[ -z "${DB_USER:-}" ]]; then echo "ERROR: DB_USER not set" >&2; exit 1; fi
if [[ -z "${DB_PASSWORD:-}" ]]; then echo "ERROR: DB_PASSWORD not set" >&2; exit 1; fi
if [[ -z "${DB_NAME:-}" ]]; then echo "ERROR: DB_NAME not set" >&2; exit 1; fi
if [[ -z "${WEB_CONTAINER:-}" ]]; then echo "ERROR: WEB_CONTAINER not set" >&2; exit 1; fi
if [[ -z "${OUTPUT_DIR:-}" ]]; then echo "ERROR: OUTPUT_DIR not set" >&2; exit 1; fi

# Defaults
SOURCE_COMMIT="${SOURCE_COMMIT:-b7958385f00a92219f76a4dfea67dae93df97a25}"
DOLIBARR_IMAGE="${DOLIBARR_IMAGE:-dolibarr/dolibarr:24.0.1@sha256:125a5b6838e1b0f5c1e85678967026b1f72775bd3a08252b440c06799d2c19ce}"
DB_IMAGE="${DB_IMAGE:-mariadb:10.11.19-jammy@sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c}"

# 1. Get database prefix from Dolibarr configuration
DB_PREFIX=$(docker exec "$WEB_CONTAINER" php -r "
require '/var/www/html/conf/conf.php';
echo \$dolibarr_main_db_prefix ?? 'llx_';
" 2>/dev/null | tr -d '\r\n')
if [[ -z "$DB_PREFIX" ]]; then
    DB_PREFIX="llx_"
fi

# 2. Get Dolibarr version
DOLIBARR_VERSION=$(docker exec "$WEB_CONTAINER" grep "define('DOL_MINOR_VERSION'" /var/www/html/version.inc.php 2>/dev/null | sed "s/.*'DOL_MINOR_VERSION', '\([^']*\)'.*/\1/" | tr -d '\r\n' || echo "unknown")
DOLIBARR_VERSION="24.${DOLIBARR_VERSION}"

# 3. Get MariaDB version (observed from runtime) and derive semantic version
MARIADB_VERSION_RAW=$(docker exec "$DB_CONTAINER" mariadb --version 2>/dev/null | head -1 || echo "unknown")
# Derive semantic version from raw output (e.g., "10.11.19" from "10.11.19-MariaDB-...")
MARIADB_VERSION=$(echo "$MARIADB_VERSION_RAW" | sed -E 's/.*Distrib ([0-9]+\.[0-9]+\.[0-9]+).*/\1/' | head -1)
if [[ -z "$MARIADB_VERSION" || "$MARIADB_VERSION" == "$MARIADB_VERSION_RAW" ]]; then
    # Fallback: try to extract first X.Y.Z pattern
    MARIADB_VERSION=$(echo "$MARIADB_VERSION_RAW" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1 || echo "unknown")
fi

# 4. Get observed database charset/collation and table count
CHARSET=$(docker exec "$DB_CONTAINER" mariadb -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "SHOW VARIABLES LIKE 'character_set_database';" 2>/dev/null | awk '{print $2}' || echo "unknown")
COLLATION=$(docker exec "$DB_CONTAINER" mariadb -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "SHOW VARIABLES LIKE 'collation_database';" 2>/dev/null | awk '{print $2}' || echo "unknown")
TABLE_COUNT=$(docker exec "$DB_CONTAINER" mariadb -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';" 2>/dev/null | tr -d '\r\n' || echo "0")

# 5. Get Dolibarr configured database charset/collation from conf.php
CONFIG_CHARSET=$(docker exec "$WEB_CONTAINER" php -r "
require '/var/www/html/conf/conf.php';
echo \$dolibarr_main_db_character_set ?? 'not_configured';
" 2>/dev/null | tr -d '\r\n')
CONFIG_COLLATION=$(docker exec "$WEB_CONTAINER" php -r "
require '/var/www/html/conf/conf.php';
echo \$dolibarr_main_db_collation ?? 'not_configured';
" 2>/dev/null | tr -d '\r\n')

# 6. Get enabled modules
MODULES=$(docker exec "$DB_CONTAINER" mariadb -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "SELECT name FROM ${DB_PREFIX}const WHERE name LIKE 'MAIN_MODULE_%' AND value='1' ORDER BY name;" 2>/dev/null | sed 's/^MAIN_MODULE_//' | tr '\n' ',' | sed 's/,$//' || echo "")

# 7. Get company info
COMPANY_NAME=$(docker exec "$DB_CONTAINER" mariadb -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "SELECT value FROM ${DB_PREFIX}const WHERE name='MAIN_INFO_SOCIETE_NOM' LIMIT 1;" 2>/dev/null | tr -d '\r\n' || echo "")
COMPANY_COUNTRY_RAW=$(docker exec "$DB_CONTAINER" mariadb -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "SELECT value FROM ${DB_PREFIX}const WHERE name='MAIN_INFO_SOCIETE_COUNTRY' LIMIT 1;" 2>/dev/null | tr -d '\r\n' || echo "")

# 8. Get relevant API configuration (whitelist only) - use JSON_OBJECTAGG for safe transport
API_CONFIG_JSON=$(docker exec "$DB_CONTAINER" mariadb -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -sN -e "
SELECT JSON_OBJECTAGG(name, value)
FROM ${DB_PREFIX}const
WHERE name IN (
    'API_EXPLORER_DISABLED', 'API_ENDPOINT_RULES', 'API_RESTRICT_ON_IP',
    'API_DISABLE_COMPRESSION', 'API_IN_TOKEN_TABLE', 'MAIN_LANG_DEFAULT'
);
" 2>/dev/null || echo "{}")
# Ensure valid JSON object even if no rows
if [[ -z "$API_CONFIG_JSON" || "$API_CONFIG_JSON" == "NULL" ]]; then
    API_CONFIG_JSON="{}"
fi

# 9. Fetch Swagger descriptor
SWAGGER_URL="${BASE_URL}/api/index.php/explorer/swagger.json"
curl -fsS -H "DOLAPIKEY: ${API_KEY}" -H "Accept: application/json" "$SWAGGER_URL" > "$OUTPUT_DIR/swagger_raw.json"

if [[ ! -s "$OUTPUT_DIR/swagger_raw.json" ]]; then
    echo "ERROR: Failed to fetch Swagger descriptor" >&2
    exit 1
fi

# 10. Normalize Swagger descriptor using PHP in container (no host jq dependency)
# Only removes host (environment-dependent), sorts keys deterministically
# Preserves all contractual data: info.*, schemes, paths, definitions, securityDefinitions
cat "$OUTPUT_DIR/swagger_raw.json" | docker exec -i "$WEB_CONTAINER" php /var/www/scripts/process-swagger.php normalize > "$OUTPUT_DIR/swagger_normalized.json"

# Validate JSON
if ! docker exec -i "$WEB_CONTAINER" php -r 'json_decode(file_get_contents("php://stdin"), true);' < "$OUTPUT_DIR/swagger_normalized.json" 2>/dev/null; then
    echo "ERROR: Normalized Swagger descriptor is not valid JSON" >&2
    exit 1
fi

# 11. Verify no secrets in normalized swagger
if grep -q "$API_KEY" "$OUTPUT_DIR/swagger_normalized.json" 2>/dev/null; then
    echo "ERROR: API key found in normalized Swagger - ABORTING" >&2
    exit 1
fi

# 12. Extract basePath from normalized Swagger descriptor
SWAGGER_BASE_PATH=$(cat "$OUTPUT_DIR/swagger_normalized.json" | docker exec -i "$WEB_CONTAINER" php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["basePath"] ?? "";')

# 13. Generate API inventory using PHP in container
cat "$OUTPUT_DIR/swagger_normalized.json" | docker exec -i "$WEB_CONTAINER" php /var/www/scripts/process-swagger.php inventory > "$OUTPUT_DIR/api_inventory.tsv"

# 14. Compute metadata using PHP in container
METADATA=$(cat "$OUTPUT_DIR/swagger_normalized.json" | docker exec -i "$WEB_CONTAINER" php /var/www/scripts/process-swagger.php metadata)
SWAGGER_TYPE=$(echo "$METADATA" | docker exec -i "$WEB_CONTAINER" php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["type"];')
PATH_COUNT=$(echo "$METADATA" | docker exec -i "$WEB_CONTAINER" php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["path_count"];')
OPERATION_COUNT=$(echo "$METADATA" | docker exec -i "$WEB_CONTAINER" php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["operation_count"];')
TAG_COUNT=$(echo "$METADATA" | docker exec -i "$WEB_CONTAINER" php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["tag_count"];')
DEFINITION_COUNT=$(echo "$METADATA" | docker exec -i "$WEB_CONTAINER" php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["definition_count"];')
SWAGGER_SHA256=$(echo "$METADATA" | docker exec -i "$WEB_CONTAINER" php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo $d["swagger_sha256"];')

# 15. Prepare modules JSON (as JSON array for proper parsing)
MODULES_JSON=$(echo "$MODULES" | sed 's/,/","/g' | sed 's/^/["/' | sed 's/$/"]/')
if [[ "$MODULES" == "" ]]; then
    MODULES_JSON="[]"
fi

# 16. Parse country raw value into components
# Format: "id:code:label" where label may contain colons
COUNTRY_ID=""
COUNTRY_CODE=""
COUNTRY_LABEL=""
if [[ -n "$COMPANY_COUNTRY_RAW" ]]; then
    COUNTRY_ID=$(echo "$COMPANY_COUNTRY_RAW" | cut -d: -f1)
    COUNTRY_CODE=$(echo "$COMPANY_COUNTRY_RAW" | cut -d: -f2)
    # Label is everything after the second colon
    COUNTRY_LABEL=$(echo "$COMPANY_COUNTRY_RAW" | cut -d: -f3-)
fi

# 17. Generate technical-reference.json using the "generate-tr" command
cat "$OUTPUT_DIR/swagger_normalized.json" | docker exec -i \
    -e DOLIBARR_VERSION="$DOLIBARR_VERSION" \
    -e SOURCE_COMMIT="$SOURCE_COMMIT" \
    -e DOLIBARR_IMAGE="$DOLIBARR_IMAGE" \
    -e DB_ENGINE="MariaDB" \
    -e DB_VERSION="$MARIADB_VERSION" \
    -e DB_IMAGE="$DB_IMAGE" \
    -e DB_CHARSET="$CHARSET" \
    -e DB_COLLATION="$COLLATION" \
    -e DB_TABLE_COUNT="$TABLE_COUNT" \
    -e CONFIG_CHARSET="$CONFIG_CHARSET" \
    -e CONFIG_COLLATION="$CONFIG_COLLATION" \
    -e CONFIG_PREFIX="$DB_PREFIX" \
    -e COMPANY_NAME="$COMPANY_NAME" \
    -e COMPANY_COUNTRY_RAW="$COMPANY_COUNTRY_RAW" \
    -e COUNTRY_ID="$COUNTRY_ID" \
    -e COUNTRY_CODE="$COUNTRY_CODE" \
    -e COUNTRY_LABEL="$COUNTRY_LABEL" \
    -e MODULES_JSON="$MODULES_JSON" \
    -e API_CONFIG_JSON="$API_CONFIG_JSON" \
    -e SWAGGER_TYPE="$SWAGGER_TYPE" \
    -e SWAGGER_BASE_PATH="$SWAGGER_BASE_PATH" \
    -e PATH_COUNT="$PATH_COUNT" \
    -e OPERATION_COUNT="$OPERATION_COUNT" \
    -e TAG_COUNT="$TAG_COUNT" \
    -e DEFINITION_COUNT="$DEFINITION_COUNT" \
    -e SWAGGER_SHA256="$SWAGGER_SHA256" \
    "$WEB_CONTAINER" php /var/www/scripts/process-swagger.php generate-tr > "$OUTPUT_DIR/technical-reference.json"

# 18. Copy final artifacts to output directory (swagger.json and api-inventory.tsv)
cp "$OUTPUT_DIR/swagger_normalized.json" "$OUTPUT_DIR/swagger.json"
# api-inventory.tsv already in place
# technical-reference.json already in place