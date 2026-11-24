#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

export COMPOSE_DISABLE_ENV_FILE=1
export PHP_VERSION=8.2
project="qetg-coverage-${GITHUB_RUN_ID:-local}-${GITHUB_RUN_ATTEMPT:-0}-$$-$RANDOM"
compose() {
    docker compose -p "$project" -f tests/compose.integration.yml -f tests/compose.coverage.yml "$@"
}
cleanup() {
    local status=$?
    if (( status != 0 )); then compose logs --no-color || true; fi
    compose down --volumes --remove-orphans --rmi local || true
    exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

compose config --quiet
compose up --build --wait --wait-timeout 180

compose exec -T app sh -eu -c '
    mkdir -p /tmp/qetg-coverage/conf.d
    printf "%s\n" \
        "xdebug.mode=coverage" \
        "auto_prepend_file=/var/www/html/tests/.coverage-prepend.php" \
        "auto_append_file=/var/www/html/tests/.coverage-append.php" \
        > /tmp/qetg-coverage/conf.d/99-qetg-coverage.ini
    export PHP_INI_SCAN_DIR="/usr/local/etc/php/conf.d:/tmp/qetg-coverage/conf.d"
    php -r '\''if (!extension_loaded("xdebug") || ini_get("auto_prepend_file") !== "/var/www/html/tests/.coverage-prepend.php" || ini_get("auto_append_file") !== "/var/www/html/tests/.coverage-append.php") { fwrite(STDERR, "Coverage instrumentation is not active.\\n"); exit(2); }'\''
    find . \( -name vendor -o -name node_modules \) -prune -o -name "*.php" -type f -print0 |
        xargs -0 -r -n 1 php -l >/dev/null
    for test in tests/*.php; do php "$test"; done
    php tests/integration/member-registration.php
    php tests/integration/repositories.php
    php tests/integration/quiz-repository.php
    php tests/integration/account-http.php
    php tests/integration/pages-http.php
    php tests/integration/traffic.php
    php tests/integration/administration.php
    php tests/integration/official-quiz-http.php
    php tests/integration/member-quiz-http.php
    php tests/integration/community-http.php
    php tests/integration/administration-http.php
'

mysql() {
    compose exec -T db sh -c 'export MYSQL_PWD="$MYSQL_ROOT_PASSWORD"; exec mysql -u root --default-character-set=utf8mb4 "$@"' sh "$@"
}
source tests/integration/migrations.sh

compose exec -T -e "QETG_MIN_LINE_COVERAGE=${QETG_MIN_LINE_COVERAGE:-0}" app php tests/.coverage-report.php
