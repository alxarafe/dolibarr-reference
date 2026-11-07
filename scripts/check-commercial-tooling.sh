#!/usr/bin/env bash
# Verify commercial profile isolation and cleanup failure handling without Docker.
set -euo pipefail
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
sandbox=$(mktemp -d)
cleanup_test() {
    rm -f -- "$sandbox/cleanup-function.sh" "$sandbox/cleanup-success.log" \
        "$sandbox/cleanup-down-failure.log" "$sandbox/cleanup-list-failure.log" \
        "$sandbox/cleanup-leak.log" "$sandbox/bin/up-commercial" \
        "$sandbox/bin/down-commercial" "$sandbox/fake-bin/docker" \
        "$sandbox/.env.commercial" "$sandbox/calls" "$sandbox/up.log" "$sandbox/down.log"
    rmdir -- "$sandbox/bin" "$sandbox/fake-bin" "$sandbox"
}
mkdir -p "$sandbox/bin" "$sandbox/fake-bin"
trap cleanup_test EXIT
# Source only the real cleanup function; never start the check's Docker project.
sed -n '/^cleanup() {/,/^}/p' "$PROJECT_ROOT/bin/check-commercial" > "$sandbox/cleanup-function.sh"
for mode in success down-failure list-failure leak; do
    result=0
    MODE="$mode" PROFILE_TEST_ROOT="$sandbox" bash -c '
        set -euo pipefail
        source "$PROFILE_TEST_ROOT/cleanup-function.sh"
        COMPOSE_FILE=/unused
        ENV_FILE=/unused
        COMPOSE_PROJECT_NAME=isolated-test
        docker() {
            case "$MODE:$1" in
                down-failure:compose) return 7 ;;
                list-failure:volume) return 8 ;;
                leak:container) echo stopped-container ;;
            esac
            return 0
        }
        true
        cleanup
    ' > "$sandbox/cleanup-$mode.log" 2>&1 || result=$?
    if [[ "$mode" == success ]]; then
        [[ "$result" == 0 ]]
        grep -q '^VALIDATION PASSED$' "$sandbox/cleanup-$mode.log"
    else
        [[ "$result" != 0 ]]
        ! grep -q '^VALIDATION PASSED$' "$sandbox/cleanup-$mode.log"
    fi
    echo "PASS cleanup scenario: $mode"
done
cp "$PROJECT_ROOT/bin/up-commercial" "$PROJECT_ROOT/bin/down-commercial" "$sandbox/bin/"
cp "$PROJECT_ROOT/.env.commercial.example" "$sandbox/.env.commercial"
printf '\nDOLIBARR_REFERENCE_API_KEY=test_fixture_key_not_a_credential\n' >> "$sandbox/.env.commercial"
chmod 644 "$sandbox/.env.commercial"
cat > "$sandbox/fake-bin/docker" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
if [[ "$1" == inspect ]]; then echo healthy; exit; fi
if [[ "$1" == compose ]]; then
    [[ "$2" == --env-file && "$3" == "$PROFILE_TEST_ROOT/.env.commercial" ]]
    [[ "$DB_PASSWORD" == change_me_lab_only ]]
    [[ "$*" != *'down -v'* ]]
    echo "$*" >> "$PROFILE_TEST_ROOT/calls"
    if [[ "$*" == *'ps -q'* ]]; then echo test-container; fi
    exit
fi
exit 9
MOCK
chmod +x "$sandbox/fake-bin/docker"
export PROFILE_TEST_ROOT="$sandbox"
PATH="$sandbox/fake-bin:$PATH" DB_PASSWORD=baseline_sentinel bash "$sandbox/bin/up-commercial" > "$sandbox/up.log"
[[ "$(stat -c %a "$sandbox/.env.commercial")" == 600 ]]
PATH="$sandbox/fake-bin:$PATH" DB_PASSWORD=baseline_sentinel bash "$sandbox/bin/down-commercial" > "$sandbox/down.log"
echo 'PASS up/down: explicit commercial env, baseline override, existing-key file mode 0600, volume-preserving down'
