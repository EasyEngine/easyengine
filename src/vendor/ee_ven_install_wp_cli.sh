# Install wp-cli

function ee_ven_install_wp_cli()
{
	if [ ! -d /usr/share/wp-cli ]; then

		ee_lib_echo "Installing WP-CLI, please wait..."
		curl -sL https://raw.github.com/wp-cli/wp-cli.github.com/master/installer.sh \
		| INSTALL_DIR='/usr/share/wp-cli' VERSION=$EE_WP_CLI_VERSION bash &>> $EE_COMMAND_LOG \
		|| ee_lib_error "Unable to install WP-CLI, exit status = " $?

		# Add WP-CLI command in $PATH variable
		if [ ! -L /usr/bin/wp ]; then
			ln -s /usr/share/wp-cli/bin/wp /usr/bin/wp \ 
			|| ee_lib_error "Unable to create symbolic link for WP-CLI command, exit status = " $?
		fi

		# Auto completion for WP-CLI
		cp /usr/share/wp-cli/vendor/wp-cli/wp-cli/utils/wp-completion.bash /etc/bash_completion.d/
		source /etc/bash_completion.d/wp-completion.bash
	fi
}
