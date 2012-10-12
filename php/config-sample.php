<?php

/**
 * local environment values
 */
$local_env['host'] = '191.168.1.199';			#ip of machine, this will be added to nginx config
$local_env['webroot'] = '/var/www';				#all websites will go hear
$local_env['htdocs'] = 'htdocs';						#name of directory which will act as document-root for a site
$local_env['logs'] = 'logs';			#name of directory which will store log files for a site

$local_env['nginx_dir'] = '/etc/nginx';			#nginx conf dir
$local_env['nginx_user'] = 'www-data';			#nginx user
$local_env['nginx_group'] = 'www-data';			#nginx group 

$local_env['nginx_dir_sites_avilable'] = '/etc/nginx/sites-available';	#sites-avaialble
$local_env['nginx_dir_sites_enabled'] = '/etc/nginx/sites-enabled';		#sites-enable
	
$local_env['default_conf'] = 'example.com';								#example configuration for wordpress
$local_env['default_domain'] = 'example.com';							#default domain in example.com

$local_env['mysql_host'] = 'localhost';									#mysql host
$local_env['mysql_user'] = 'USER';										#mysql user
$local_env['mysql_pass'] = 'PASS';										#mysql pass

$local_env['wp_latest'] = 'latest.zip';			#latest WordPress zip file 


/**
 * remote env - in scenario where you want to use move.php
 * assumes - (1) remote DB allows remote connection
 * 			 (2) nginx & sites directory-structure is same
 */
$remote_env['webroot'] = '/var/www';			//remote webroot - assuming similar directory sturcture
$remote_env['host'] = 'REMOTE_DBHOST';			//this is 
$remote_env['ssh_user'] = 'REMOTE_DBUSER';
$remote_env['ssh_pass'] = 'REMOTE_DBPASS';


?>
