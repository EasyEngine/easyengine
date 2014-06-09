# Initialize Git

function ee_lib_git_init()
{
	for ee_git_dir in $@; do
		# Change directory
		cd $ee_git_dir || ee_lib_error "Unable to change directory $ee_git_dir, exit status = " $?

		# Check .git
		if [ ! -d .git ]; then
			ee_lib_echo "Initialize Git on ${ee_git_dir}..."
			git init &>> $EE_COMMAND_LOG \
			|| ee_lib_error "Unable to initialize Git on $ee_git_dir, exit status = " $?
		fi

		# Check for untracked files
		if [ $(git status -s | wc -l) -ne 0 ]; then
			# Add files in Git version control
			git add --all && git commit -am "Initialize Git on $ee_git_dir"  &>> $EE_COMMAND_LOG \
			|| ee_lib_error "Unable to Git commit on $ee_git_dir, exit status = " $?
		fi
	done
}
