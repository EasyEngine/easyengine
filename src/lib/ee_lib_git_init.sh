# Initialise Git

function ee_lib_git_init()
{
	# Change directory
	cd $EE_GIT_DIR || ee_lib_error "Unable to change directory $EE_GIT_DIR, exit status = " $?

	# Check .git
	if [ ! -d .git ]; then
		ee_lib_echo "Initialise Git On $EE_GIT_DIR..."
		git init &>> $EE_COMMAND_LOG \
		|| ee_lib_error "Unable to initialize Git on $EE_GIT_DIR, exit status = " $?
	fi

	# Check for untracked files
	if [ $(git status -s | wc -l) -ne 0 ]; then
		# Add files in Git version control
		git add --all && git commit -am "Initialize Git On $EE_GIT_DIR"  &>> $EE_COMMAND_LOG \
		|| ee_lib_error "Unable to Git commit on $EE_GIT_DIR, exit status = " $?
	fi
}
