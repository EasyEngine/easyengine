# Install Postfix package

function ee_mod_install_postfix()
{
	# Setup Postfix
	debconf-set-selections <<< "postfix postfix/main_mailer_type string 'Internet Site'"
	debconf-set-selections <<< "postfix postfix/mailname string $(hostname -f)"

	# Install Postfix
	ee_lib_echo "Installing Postfix, please wait..."
	$EE_APT_GET install postfix | tee -ai EE_COMMAND_LOG || ee_lib_error "Unable to install Postfix, exit status = " $?
}
