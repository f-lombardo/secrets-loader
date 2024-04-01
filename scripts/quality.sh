#!/bin/bash

########################################################################################################################
# This script runs quality checks
########################################################################################################################

echo "Project: ${COMPOSE_PROJECT_NAME}"

vendor/bin/phpcbf && vendor/bin/phpcs && vendor/bin/phpstan && vendor/bin/phpunit
