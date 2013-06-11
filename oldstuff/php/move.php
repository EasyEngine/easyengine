<?php
/*
	MOVE A WORDPRESS SITE
	THIS SCRIPT IS REALLY BROKEN.
 */

include_once('config.pgp');


echo "\nEnter one or more domain names(s) which needs to be migrated...\n";
echo "You can separate domain names by comma (,)...\n";
echo "Example: google.com, yahoo.com,apple.com\n";

$usr_domain = (trim(fgets(STDIN)));

$domain_arr = explode(",", $usr_domain);

foreach($domain_arr as $domain){
    echo "****************************************************************************";
    echo "\n\nMoving :: $domain \n\n";
    move_domain(trim($domain));
}


/**
 * ALL SEENS WELL - Restart nginx
 */
echo "\n Relaoding nginx configuration...\n\n";
system('service nginx reload');		



/**
 * Function to move a single domain
 * @param <type> $domain 
 */
function move_domain($usr_domain) {
    global $local_env, $remote_env;

    if(trim($usr_domain) == ''){
        echo "\n CURRENT DOMAIN IS SKIPPED\n";
        return;
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
    if (file_exists($domain['conf'])) {
        echo "\nConfiguration files for domain '" . $domain['name'] . "'already exists :: " . $domain['conf'];
        echo "\nDo you want to overwrite previous configuration? [Y/N] (default=Y)";

        if (in_array(strtolower(fgets(STDIN)), array('n', 'no'))) {
	        die("\nYou choose to terminate this script! Bye-Bye!!! \n");
        }
    }

    /**
     * At this point - either domain config file doesn't exist or user showed interest in overwriting it
     * In either case...
     * Create nginx conf for $domain in /etc/nginx/sites-available/$domain
     * TODO :: Provide options to add supercache and/or w3total cache rules
     */
    /**
     * Create config file
     */
    $nginx_conf = file_get_contents($local_env['default_conf']);
    $nginx_conf = str_replace($local_env['default_domain'], $domain['name'], $nginx_conf);
    file_put_contents($domain['conf'], $nginx_conf);

//Error Check - if config file is created successfully or not
    if (!file_exists($domain['conf'])) {
        die("\nError encounterd while creating " . $domain['conf']);
    }

    /**
     * Linking config file
     */
    echo "\nCreating Symbolic Link..\n";
    $command = "sudo ln -s " . $domain['conf'] . " " . $local_env['nginx_dir_sites_enabled'];
    $result = system($command);

//Error check - if linking config file succeed
    if ($result != '') {
        die("\nError encountered while creating script. Please check if file '" . $domain['conf'] . "'is created or not!\n");
    }

//Go Ahead.
//    echo "Nginx configuration for '" . $domain['name'] . "' is created successfully";

    /**
     * Create webroot dirs for new domain
     */
//create dirs
    $result = system("mkdir " . $domain['rootdir']);
    $result = system("mkdir " . $domain['htdocs']);
    $result = system("mkdir " . $domain['logs']);

//create log files
    $result = system("touch " . $domain['logs'] . "/access.log");
    $result = system("touch " . $domain['logs'] . "/error.log");

//Error check
    if ($result != '') {
        die("\nError encountered while creating websites directories & files for " . $domain['name'] . "\n");
    }

    /**
     * moving wordpress in new webroot dir
     */
//export remote db
    $command = "rsync --exclude '*.iso'  -avz {$remote_env['ssh_user']}@{$remote_env['host']}:{$remote_env['webroot']}/{$domain['name']}/htdocs/* {$domain['htdocs']}/";
    $result = system($command);


    /**
     * MySQL Moving
     */
    $wp_config = get_defines($domain['htdocs'] . '/wp-config.php');

    $command = "mysqldump -u {$wp_config['DB_USER']} -p{$wp_config['DB_PASSWORD']} -h {$remote_env['host']}  --databases '{$wp_config['DB_NAME']}' > {$domain['rootdir']}/{$domain['name']}.sql";
//    echo "\n" . $command . "\n";
    $result = system($command);

    $command = "mysql -h {$local_env['mysql_host']} -u {$local_env['mysql_user']} -p{$local_env['mysql_pass']}  < {$domain['rootdir']}/{$domain['name']}.sql";
//    echo "\n\n" . $command . "\n";
    $result = system($command);

    /*
     * Create wp-config.php
     */
		//this may not be needed as we already have wp-config.php present for remote WordPress
		//@TODO we need to replace DB_HOST though
		
		
    /**
     * Chown
     */
    $command = "chown -R " . $local_env['nginx_user'] . ":" . $local_env['nginx_group'] . " " . $domain['rootdir'];
    echo "\n COMMAND :: $command \n";
    $result = system($command);

//Error check
    if ($result != '') {
        die("\nError encountered while chaging owner of " . $domain['rootdir'] . "\n" . $result);
    }
}
?>