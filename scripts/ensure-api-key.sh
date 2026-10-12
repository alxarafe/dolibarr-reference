#!/usr/bin/env bash
# scripts/ensure-api-key.sh - Ensure API key exists in .env
# Single authoritative API key lifecycle helper
# Strict shell policy
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_ROOT/.env"
ENV_EXAMPLE="$PROJECT_ROOT/.env.example"

# Load existing .env if present (don't fail if missing)
if [[ -f "$ENV_FILE" ]]; then
    set -a
    # shellcheck source=/dev/null
    source "$ENV_FILE"
    set +a
fi

# Check if key exists and is non-empty
if [[ -n "${DOLIBARR_REFERENCE_API_KEY:-}" ]]; then
    # Key exists and is non-empty, export and return
    export DOLIBARR_REFERENCE_API_KEY
    return 0
fi

# Key is missing or empty - generate new one using Docker (no host openssl/xxd)
# Use the already-available Dolibarr image which has PHP with random_bytes
GENERATED_KEY=$(docker run --rm --entrypoint php "${DOLIBARR_IMAGE:-dolibarr/dolibarr:24.0.1@sha256:125a5b6838e1b0f5c1e85678967026b1f72775bd3a08252b440c06799d2c19ce}" -r 'echo bin2hex(random_bytes(32));' 2>/dev/null)

if [[ -z "$GENERATED_KEY" ]]; then
    # Fallback: use MariaDB image which also has PHP
    GENERATED_KEY=$(docker run --rm --entrypoint php mariadb:10.11.19-jammy -r 'echo bin2hex(random_bytes(32));' 2>/dev/null)
fi

if [[ -z "$GENERATED_KEY" ]]; then
    echo "ERROR: Failed to generate API key using Docker" >&2
    exit 1
fi

# Ensure .env exists (copy from example if needed)
if [[ ! -f "$ENV_FILE" ]]; then
    if [[ -f "$ENV_EXAMPLE" ]]; then
        cp "$ENV_EXAMPLE" "$ENV_FILE"
    else
        touch "$ENV_FILE"
    fi
fi

# Update or add the key in .env (preserve other content, no duplicates)
if grep -q "^DOLIBARR_REFERENCE_API_KEY=" "$ENV_FILE"; then
    sed -i "s/^DOLIBARR_REFERENCE_API_KEY=.*/DOLIBARR_REFERENCE_API_KEY=$GENERATED_KEY/" "$ENV_FILE"
else
    echo "DOLIBARR_REFERENCE_API_KEY=$GENERATED_KEY" >> "$ENV_FILE"
fi

# Ensure reasonable permissions
chmod 600 "$ENV_FILE" 2>/dev/null || true

# Export for current shell
export DOLIBARR_REFERENCE_API_KEY="$GENERATED_KEY"
return 0