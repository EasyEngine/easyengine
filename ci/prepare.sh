#!/bin/bash

# called by Travis CI

if [[ "$TRAVIS_BRANCH" != $DEPLOY_BRANCH ]]; then
    version=$(head -n 1 VERSION)
    version="$(echo $version | xargs)"
    version+="-nightly-$(git rev-parse --short HEAD)"
    echo $version > VERSION
fi

php -dphar.readonly=0 ./utils/make-phar.php easyengine.phar --quiet

# Checking the phar is working.
sudo ./easyengine.phar cli info
docker ps -a