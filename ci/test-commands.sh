#!/bin/bash

# called by Travis CI

# Add certificates
./vendor/easyengine/site-command/ci/add-test-certs.sh

for repo in "$(find vendor/easyengine -type d -name 'features')"; do
	rsync -a --delete $repo/ features
	sudo ./vendor/bin/behat 
done