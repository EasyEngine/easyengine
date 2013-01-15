#!/bin/bash
while true
do
	ping -c1 192.168.0.206 &> /dev/null
	if [ $? == 0 ]
	then
		echo "[+] Server Becomes Alive ......"
		rsync -avz --delete /var/www/ root@192.168.0.206:/var/www/
		exit 0;
	fi
done
