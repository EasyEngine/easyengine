#!/bin/bash

# called by Travis CI

if [[ "$TRAVIS_BRANCH" == "develop-v4" ]]; then
    version=$(head -n 1 VERSION)
    version="$(echo $version | xargs)"
    version+="-nightly"
    echo $version > VERSION
fi

php -dphar.readonly=0 ./utils/make-phar.php easyengine.phar --quite  > /dev/null