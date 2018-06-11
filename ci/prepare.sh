#!/bin/bash

# called by Travis CI

php -dphar.readonly=0 ./utils/make-phar.php easyengine.phar --quite  > /dev/null