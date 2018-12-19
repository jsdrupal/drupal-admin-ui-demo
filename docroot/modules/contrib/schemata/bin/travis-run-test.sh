#!/bin/bash

# Run either PHPUnit tests or CODE_QUALITY tests on Travis CI, depending
# on the passed in parameter.
#
# Adapted from https://github.com/Gizra/og/blob/8.x-1.x/scripts/travis-ci/run-test.sh

case "$1" in
    CODE_QUALITY)
        cd $MODULE_DIR
        echo "ENSURE DEVELOPMENT TOOLS"
        composer install
        echo "VALIDATE COMPOSER.JSON FILE"
        composer validate --no-check-lock --no-check-publish --no-interaction
        echo "RUN CODE QUALITY CHECKS"
        composer run-script quality
        exit $?
        ;;
    *)
        echo "RUN PHPUNIT TESTS"
        ln -sv $MODULE_DIR $DRUPAL_DIR/modules/schemata
        cd $DRUPAL_DIR
        ./vendor/bin/phpunit -c ./core/phpunit.xml.dist $MODULE_DIR/tests
        exit $?
esac
