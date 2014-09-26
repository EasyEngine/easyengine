# EasyEngine Swap creation

function ee_lib_create_swap()
{
	# Use dd command to create SWAP
	# Swap Parameters:
	# Location: /swapfile
	# Block Size: 1024
	dd if=/dev/zero of=/swapfile bs=1024 count=1024k \
	|| ee_lib_error "Unable to generate /swapfile, exit status = " $?

	# Create it as a Swap
	mkswap /swapfile \
	|| ee_lib_error "Unable to create swapfile, exit status = " $?

	# On the Swap
	swapon /swapfile \
	|| ee_lib_error "Unable to on Swap, exit status = " $?

	# Add entry into /etc/fstab
	echo "/swapfile		none		swap	sw	0	0" >> /etc/fstab \
	|| ee_lib_error "Unable to add entry into /etc/fstab, exit status = " $?"
}
