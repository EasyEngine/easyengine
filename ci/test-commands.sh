#!/bin/bash

# called by Travis CI

# Add certificates
./vendor/easyengine/site-command/ci/add-test-certs.sh > /dev/null

for repo in "$(find vendor/easyengine -type d -name 'features')"; do
	rsync -a --delete $repo/ features > /dev/null
	echo "Running tests for $repo"
	sudo ./vendor/bin/behat 
done