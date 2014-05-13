# Capture errors
function EE_ERROR()
{
	echo "[ `date` ] $(tput setaf 1)$@$(tput sgr0)" | tee -ai $ERROR_LOG
	exit 102
}