# Initialize Git

function ee_lib_git()
{
	for ee_git_dir in ${@:1:$(($#-1))}; do
		if [ -d $ee_git_dir ]; then
			# Change directory
	 		cd $ee_git_dir 

			# Check .git
			if [ ! -d .git ]; then
				# ee_lib_echo "Initialize Git on ${ee_git_dir}"
				git init &>> $EE_COMMAND_LOG \
				|| ee_lib_error "Unable to initialize Git on $ee_git_dir, exit status = " $?
			fi

			# Check for untracked files
			if [ $(git status -s | wc -l) -ne 0 ]; then
				# Add files in Git version control
				ee_lib_echo "Git commit on $ee_git_dir, please wait..."
				git add --all && git commit -am "${@: -1}" &>> $EE_COMMAND_LOG \
				|| ee_lib_error "Unable to Git commit on $ee_git_dir, exit status = " $?
			fi
		fi
	done
}
