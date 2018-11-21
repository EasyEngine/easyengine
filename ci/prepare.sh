#!/bin/bash

# called by Travis CI

if [[ "$TRAVIS_BRANCH" != $DEPLOY_BRANCH ]]; then
    version=$(head -n 1 VERSION)
    version="$(echo $version | xargs)"
    version+="-nightly-$(git rev-parse --short HEAD)"
    echo $version > VERSION
    sed -i 's/\:\ \"\(.*\)\"/\:\ \"\1-nightly\"/g' img-versions.json
fi

php -dphar.readonly=0 ./utils/make-phar.php easyengine.phar --quiet