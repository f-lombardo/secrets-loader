#!/bin/bash

########################################################################################################################
# This script runs quality checks
########################################################################################################################

echo "AWS_REGION: ${AWS_REGION}"

vendor/bin/phpcbf && vendor/bin/phpcs && vendor/bin/phpstan && vendor/bin/phpunit
