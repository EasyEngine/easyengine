# Define echo function for each color
function ECHO_RED()
{
	echo $(tput setaf 1)$@$(tput sgr0)
}

function ECHO_BLUE()
{
	echo $(tput setaf 4)$@$(tput sgr0)
}

function ECHO_WHITE()
{
	echo $(tput setaf 7)$@$(tput sgr0)
}
