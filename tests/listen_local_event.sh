#!/bin/bash
set -eu

source ./tests/secrets.sh
source ./_cf-common/test/export_secrets.sh ${SECRETS[*]}

# Launch function
export FUNCTION_TARGET=main_event
export FUNCTION_SIGNATURE_TYPE=cloudevent
APP_ENV=local php -S localhost:8081 vendor/bin/router.php

source ./_cf-common/test/unset_secrets.sh ${SECRETS[*]}
