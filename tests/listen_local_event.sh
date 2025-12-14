#!/bin/bash
set -eu

source ./tests/secrets.sh
source ./_cf-common/test/export_secrets.sh ${SECRETS[*]}

# Launch function
export FUNCTION_TARGET=main_event
export FUNCTION_SIGNATURE_TYPE=cloudevent
# php -S localhost:8081 vendor/bin/router.php
php -S localhost:8081 tests/bootstrap_local.php

source ./_cf-common/test/unset_secrets.sh ${SECRETS[*]}
