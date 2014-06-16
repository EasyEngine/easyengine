# Create symbolic link for site name

function ee_lib_symbolic_link()
{
	# Creating symbolic link
	ee_lib_echo "Creating symbolic link, please wait..."
	ln -sf $1 $2 \
	|| ee_lib_error "Unable to create symbolic link for $1 -> $2, exit status = " $?
}
