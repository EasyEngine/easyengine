# Create symbolic link

function ee_lib_symbolic_link()
{
	# Creating symbolic link
	ln -sf $1 $2 \
	|| ee_lib_error "Unable to create symbolic link for $1 -> $2, exit status = " $?
}
