#!/bin/bash

if [[ $TRAVIS_COMMIT_MESSAGE = *"[BREAKING CHANGES]"* ]]; then
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
	done

	popd
fi

find vendor/easyengine -type d -name 'features' | while read repo; do
	repo_name=$(echo $repo | awk -F  "/" '{print $3}');
	rsync -a --delete $repo/ features > /dev/null
	echo "Running tests for $repo_name"
	sudo ./vendor/bin/behat	
done

