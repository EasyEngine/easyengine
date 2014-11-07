# Install Required Packages while site create

function ee_mod_site_packages()
{
	# Install required packages

	if [ "$EE_SITE_CREATE_OPTION" = "--html" ] || [ "$EE_SITE_CREATE_OPTION" = "--php" ] || [ "$EE_SITE_CREATE_OPTION" = "--mysql" ] || [ "$EE_SITE_CREATE_OPTION" = "--wp" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ]; then
		# Check & Install NGINX Packages
		ee_lib_stack_packages nginx
	fi
	if [ "$EE_SITE_CREATE_OPTION" = "--php" ] || [ "$EE_SITE_CREATE_OPTION" = "--mysql" ] || [ "$EE_SITE_CREATE_OPTION" = "--wp" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ]; then
		# Check & Install PHP Packages
		ee_lib_stack_packages php
	fi
	if [ "$EE_SITE_CREATE_OPTION" = "--mysql" ] || [ "$EE_SITE_CREATE_OPTION" = "--wp" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ]; then
		# Check & Install Percona MySQL Packages
	ee_lib_stack_packages mysql
	fi

	# Check & Install Postfix Packages
	ee_lib_stack_packages postfix

	if [ "$EE_SITE_CREATE_OPTION" = "--wp" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ]; then
		# Install WP-CLI
		ee_ven_install_wpcli
	fi
}
