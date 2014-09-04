# Check Server hostname is FQDN or not

function ee_lib_check_fqdn()
{
	case $1 in
		*.*)
			if [ "$EE_FQDN" != "" ];then
				echo $EE_FQDN > /etc/hostname
				if [ "$EE_LINUX_DISTRO" == "Debian" ];then
					grep $EE_FQDN /etc/hosts &>> $EE_COMMAND_LOG
					if [ $? -ne 0 ]; then
						sed -i "1i\127.0.0.1	$EE_FQDN" /etc/hosts \
						|| ee_lib_error "Unable setup hostname = " $?
					fi
					/etc/init.d/hostname.sh start &>> $EE_COMMAND_LOG
				else
					service hostname restart &>> $EE_COMMAND_LOG
				fi
				echo "hostname = $(hostname -f)" &>> $EE_COMMAND_LOG
			fi
			;;
		*)
			read -p "Enter hostname [FQDN]: " EE_FQDN
			ee_lib_check_fqdn $EE_FQDN
			;;
	esac
}
