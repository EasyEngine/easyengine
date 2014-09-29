# EasyEngine Swap creation

function ee_lib_create_swap()
{
	if [ $EE_TOTAL_RAM -le 512 ]; then
		if [ $EE_TOTAL_SWAP -le $EE_SWAP ];then

			# Use dd command to create SWAP
			# Swap Parameters:
			# Location: /ee-swapfile
			# Block Size: 1024
			ee_lib_echo "Adding 1GB swapfile, please wait..."
			dd if=/dev/zero of=/ee-swapfile bs=1024 count=1024k &>> $EE_COMMAND_LOG \
			|| ee_lib_error "Unable to generate /ee-swapfile, exit status = " $?

			# Create it as a Swap
			mkswap /ee-swapfile &>> $EE_COMMAND_LOG \
			|| ee_lib_error "Unable to create swapfile, exit status = " $?

			# On the Swap
			swapon /ee-swapfile &>> $EE_COMMAND_LOG \
			|| ee_lib_error "Unable to on Swap, exit status = " $?

			# Add entry into /etc/fstab
			echo "/ee-swapfile		none		swap	sw	0	0" >> /etc/fstab \
			|| ee_lib_error "Unable to add entry into /etc/fstab, exit status = " $?
		fi
	fi
}
