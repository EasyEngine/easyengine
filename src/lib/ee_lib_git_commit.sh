# Record changes to the repository

function ee_lib_git_commit()
{
	cd $EE_GIT_DIR \
	|| ee_lib_error "Unable to change directory $EE_GIT_DIR, exit status = " $?

	if [ $(git status -s | wc -l) -ne 0 ]; then
		ee_lib_echo "Commiting changes inside $EE_GIT_DIR, please wait..."

		# Add newly created files && commit it
		git add --all && git commit -am "$EE_GIT_MESSAGE" &>> $EE_COMMAND_LOG \
		|| ee_lib_error "Unable to Git commit on $EE_GIT_DIR, exit status = " $?
	fi
}
