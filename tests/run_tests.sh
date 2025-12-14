#!/bin/bash
set -eu

source ./tests/secrets.sh
source ./_cf-common/test/export_secrets.sh ${SECRETS[*]}

# run tests
bash ./tests/run_linter.sh

echo "Running PHPUnit..."
php ./vendor/bin/phpunit

source ./_cf-common/test/unset_secrets.sh ${SECRETS[*]}
