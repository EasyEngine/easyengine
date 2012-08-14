<?php
/**
 * prompt user to eneter domain name
 */
echo "Enter a domain name which needs to be removed...\n";
$usr_domain = (trim(fgets(STDIN)));
echo "You have entered :: $usr_domain \n";

if(strlen($usr_domain) == 0 ){
        die("input cannot be empty!\n");
}

if(strpos($usr_domain,"..") !== false ){
	die("directory traversal is not allowed\n");
}

if(strpos($usr_domain,"\\") !== false ){
        die("domain cannot contain \\ !");
}
/*
 * Set domain environment values
 */
$domain['name'] = $usr_domain;
$domain['conf'] = $local_env['nginx_dir_sites_avilable'] . '/' . $domain['name'];
$domain['rootdir'] = $local_env['webroot'] . '/' . $domain['name'];
$domain['htdocs'] = $domain['rootdir'] . '/' . $local_env['htdocs'] ;
$domain['logs'] = $domain['rootdir'] . '/' . $local_env['logs'] ;

/**
 * Check if domain config file already exists
 */
echo "\n Domain Name - " .  $domain['name'] . 
     "\n Webroot Dir - " . realpath($domain['rootdir']) . 
     "\n Database Name - " . $domain['name'] ;
	  
echo "\nDo you want to remove this domain, related files and databases for sure? [Y/N] (default=N): ";

if ( strtolower(trim(fgets(STDIN))) != "y" ) {
        die("\nYou choose to terminate this script! The domain is NOT removed!  \n");
}


/**
 * At this point - user has confirmed domain removal
//Drop Database
/**
 * MySQL Database Deletion
 */

$command = "mysql -h " . $local_env['mysql_host'] . " -u " . $local_env['mysql_user'] . " -p" . $local_env['mysql_pass'] . " -e 'drop database `'" . $domain['name'] . "'` '";
$result = system($command);


//remove htdocs
if(file_exists(realpath($domain['rootdir']))){
	if(dirname(realpath($domain['rootdir']))=="/var/www")
		system("rm -rf ". realpath($domain['rootdir']));
	else
		die("Try something else!");
}else{
	echo "\n Directory " . $domain['rootdir']  . " doesn't exists\n";
}
//delete database 
$command = "mysql -h " . $local_env['mysql_host'] . " -u " . $local_env['mysql_user'] . " -p" . $local_env['mysql_pass'] . " -e 'create database `'" . $domain['name'] . "'` '";
$result = system($command);


/**
 * Remove config file
 */
if(file_exists($local_env['nginx_dir_sites_enabled']."/".$domain['name']) OR file_exists($local_env['nginx_dir_sites_avilable']."/".$domain['name'])){
	unlink($local_env['nginx_dir_sites_enabled']."/".$domain['name']);
 	unlink($local_env['nginx_dir_sites_avilable']."/".$domain['name']);

	/**
		 * ALL SEENS WELL - Restart nginx
	 */
	echo "\n Issuing nginx reboot command...\n\n";
	system('service nginx restart');
}else{
	echo "\nNginx config files for this domain do not exist\n";
}

?>
