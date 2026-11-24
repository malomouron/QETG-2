#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

export COMPOSE_DISABLE_ENV_FILE=1
project="qetg-ci-${GITHUB_RUN_ID:-local}-${GITHUB_RUN_ATTEMPT:-0}-$$-$RANDOM"
compose() {
    docker compose -p "$project" -f tests/compose.integration.yml "$@"
}
cleanup() {
    local status=$?
    if (( status != 0 )); then
        compose logs --no-color || true
    fi
    if ! compose down --volumes --remove-orphans --rmi local && (( status == 0 )); then
        status=1
    fi
    exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

compose config --quiet
compose up --build --wait --wait-timeout 180

compose exec -T app sh -eu -c '
    find . \( -name vendor -o -name node_modules \) -prune -o -name "*.php" -type f -print0 |
        xargs -0 -r -n 1 php -l >/dev/null
    for test in tests/*.php; do
        php "$test"
    done
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