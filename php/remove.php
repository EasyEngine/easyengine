<?php
include_once('init.php');

/* choice */
$choice = "";

/**
 * prompt user to eneter domain name
 */
echo "Enter a domain name which needs to be removed...\n";
$usr_domain = (trim(fgets(STDIN)));

$domain_arr = explode(" ", $usr_domain);

foreach($domain_arr as $domain){
    echo "****************************************************************************";
    echo "\n\nMoving :: $domain \n\n";
    remove_domain(trim($domain));
}

function remove_domain($usr_domain){
		global $local_env, $choice;
		
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
		$domain['conf'] = $local_env['nginx_dir_sites_avilable'] . '/' . $domain['name'] ;
		$domain['rootdir'] = $local_env['webroot'] . '/' . $domain['name'] ;
		$domain['htdocs'] = $domain['rootdir'] . '/' . $local_env['htdocs'] ;
		$domain['logs'] = $domain['rootdir'] . '/' . $local_env['logs'] ;
		
		
		/**
		 * Check if domain config file already exists
		 */
		echo "\n Domain Name - " .  $domain['name'] . 
		     "\n Webroot Dir - " . realpath($domain['rootdir']) . 
		     "\n Database Name - " . $domain['name'] ;
			  
		if($choice != "a"){ 
				//ask for confirmation
				echo "\nDo you want to remove this domain, related files and databases for sure? [Y(es)/N(o)/A(lways)] (default=N): ";
				
				switch(strtolower(trim(fgets(STDIN)))){
					case 'y' :
						die("\nYou choose to terminate this script! The domain is NOT removed!  \n");
						
					case 'a' : $choice = "a";	
				} 				
		}
		
		//remove htdocs
		if(file_exists(realpath($domain['rootdir']))){
			if(dirname(realpath($domain['rootdir']))=="/var/www"){
				echo "/nremoving webroot \n";
				system("rm -rf ". $domain['rootdir']);
			}				
			else
				die("Try something else!");
		}else{
			echo "\n Directory " . $domain['rootdir']  . " doesn't exists\n";
		}
		//delete database 
		$command = "mysql -h " . $local_env['mysql_host'] . " -u " . $local_env['mysql_user'] . " -p" . $local_env['mysql_pass'] . " -e 'drop database `'" . $domain['name'] . "'` '";		
		$result = system($command);
		
		
		/**
		 * Remove config file
		 */
		if(file_exists($local_env['nginx_dir_sites_enabled']."/".$domain['name']) OR file_exists($local_env['nginx_dir_sites_avilable']."/".$domain['name'])){
			unlink($local_env['nginx_dir_sites_enabled']."/".$domain['name']);
		 	unlink($local_env['nginx_dir_sites_avilable']."/".$domain['name']);
		
		}else{
			echo "\nNginx config files for $usr_domain domain do not exist\n";
		}
		

	
}//end func

	/**
		 * ALL SEENS WELL - Restart nginx
	 */
	echo "\n Issuing nginx reboot command...\n\n";
	system('service nginx restart');


?>