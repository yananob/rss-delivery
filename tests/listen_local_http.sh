#!/bin/bash
set -eu

source ./tests/secrets.sh
source ./_cf-common/test/export_secrets.sh ${SECRETS[*]}

# Launch function
export FUNCTION_TARGET=main_http
APP_ENV=local php -S localhost:${PORT:-8080} vendor/bin/router.php


source ./_cf-common/test/unset_secrets.sh ${SECRETS[*]}
