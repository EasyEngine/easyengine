#!/bin/bash

# called by Travis CI

# Add certificates
./vendor/easyengine/site-command/ci/add-test-certs.sh > /dev/null

if [[ "false" != "$TRAVIS_PULL_REQUEST" ]]; then
	commit_message="$(git --no-pager log -2 --pretty=%B)"
else
	commit_message=$TRAVIS_COMMIT_MESSAGE
fi

echo "Starting test suite for other commands"

if [[ $commit_message = *"[BREAKING CHANGES]"* ]]; then
	pattern='\<REPO\>.*\b'
	declare -a repos

	for word in $commit_message; do
		[[ $word =~ $pattern ]]
		if [[ ${BASH_REMATCH[0]} ]]; then
			repos+=(${BASH_REMATCH[0]})
		fi
	done

	pushd vendor/easyengine
		for repo in ${repos[@]}; do
			owner_name_unclean=${repo%%\/*}
			owner_name=${owner_name_unclean#*\=}
			repo_with_branch=${repo#*\/}
			repo_name=${repo_with_branch%\:*}
			repo_branch=${repo_with_branch#*\:}
			sudo rm -r "$repo_name"
			git clone https://github.com/"$owner_name"/"$repo_name".git -b "$repo_branch"
			pushd "$repo_name"
				echo "Updated to easyengine/$repo_name to $owner_name/$repo_name"
				git remote -v
				git branch
			popd
		done
	popd
	composer du
fi

find vendor/easyengine -type d -name 'features' | while read repo; do
	repo_name=$(echo $repo | awk -F  "/" '{print $3}');
	rsync -a --delete $repo/ features > /dev/null
	echo "Running tests for $repo_name"
	sudo ./vendor/bin/behat
done
