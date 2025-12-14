#!/bin/bash
set -eu

# echo "Running ESLint..."
# npm run lint public/js/

echo "Running PHPStan..."
./vendor/bin/phpstan analyze -c phpstan.neon
