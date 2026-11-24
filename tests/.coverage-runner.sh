#!/bin/sh
set -eu
PHP=/usr/bin/php8.2
mkdir -p /tmp/qetg-coverage
export QETG_COVERAGE_DIR=/tmp/qetg-coverage
cat >/etc/php/8.2/cli/conf.d/99-qetg-coverage.ini <<'INI'
auto_prepend_file=/tmp/qetg-coverage/prepend.php
auto_append_file=/tmp/qetg-coverage/append.php
INI
cat >/tmp/qetg-coverage/prepend.php <<'PHP'
<?php
xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, array('/workspace/src/'));
xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
PHP
cat >/tmp/qetg-coverage/append.php <<'PHP'
<?php
$name = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? 'inline')));
file_put_contents('/tmp/qetg-coverage/' . $name . '-' . getmypid() . '.ser', serialize(xdebug_get_code_coverage()), LOCK_EX);
PHP
for test in /workspace/tests/*.php /workspace/tests/integration/traffic.php /workspace/tests/integration/administration.php /workspace/tests/integration/repositories.php /workspace/tests/integration/quiz-repository.php; do
  printf 'Running %s\n' "${test#/workspace/}"
  "$PHP" "$test"
done
"$PHP" /workspace/tests/.coverage-report.php /tmp/qetg-coverage
