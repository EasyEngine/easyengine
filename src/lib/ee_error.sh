# Capture errors

function ee_error()
{
	echo "[ `date` ] $(tput setaf 1)$@$(tput sgr0)" | tee -ai $ERROR_LOG
	exit $2
}
