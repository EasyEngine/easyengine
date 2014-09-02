# Check Server hostname is FQDN or not

function ee_lib_check_fqdn()
{
	case $1 in
		*.*)
			if [ "$EE_FQDN" != "" ];then
				echo $EE_FQDN > /etc/hostname
				if [ "$EE_DEBIAN_VERSION" == "squeeze" ];then
					/etc/init.d/hostname.sh start &>> $EE_COMMAND_LOG
				else
					service hostname restart &>> $EE_COMMAND_LOG
				fi
				hostname -f &>> $EE_COMMAND_LOG
			fi
			;;
		*)
			read -p "Enter hostname [FQDN]: " EE_FQDN
			ee_lib_check_fqdn $EE_FQDN
			;;
	esac
}
