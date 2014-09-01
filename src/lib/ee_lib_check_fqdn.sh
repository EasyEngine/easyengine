# Check Server hostname is FQDN or not

function ee_lib_check_fqdn()
{
	case $1 in
		*.*)
			if [ $EE_FQDN != "" ];then
				echo $EE_FQDN > /etc/hostname
				service hostname restart &>> $EE_COMMAND_LOG
				hostname -f &>> $EE_COMMAND_LOG
			fi
			;;
		*)
			read -p "Enter FQDN to set for Hostname: " EE_FQDN
			ee_lib_check_fqdn $EE_FQDN
			;;
	esac
}
