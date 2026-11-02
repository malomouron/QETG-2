#!/bin/sh
set -eu

php tests/characterization.php
find . -type f -name '*.php' ! -path './vendor/*' ! -path './node_modules/*' -print0 |
    xargs -0 -n 1 php -l >/dev/null
QETG_ENV_CONFIG=1 php -r 'require "db.php"; new db($dbhost, $dbuser, $dbpass, $dbname);'
php tests/integration.php
