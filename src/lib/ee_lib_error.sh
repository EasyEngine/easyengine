# Capture errors

function ee_lib_error()
{
	echo -e "[ `date` ] $(tput setaf 1)$@$(tput sgr0)" | tee -ai $EE_ERROR_LOG
	exit $2
}
