# Define echo function for each color

function echo_red()
{
	echo $(tput setaf 1)$@$(tput sgr0)
}

function echo_blue()
{
	echo $(tput setaf 4)$@$(tput sgr0)
}

function echo_white()
{
	echo $(tput setaf 7)$@$(tput sgr0)
}
