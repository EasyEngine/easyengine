<?php
/*
	CREATE A SITE WITH WORDPRESS
 */

include_once('init.php');
 
/**
 * prompt user to eneter domain name
 */
 
echo "Enter a domain name which needs to be migrated...\n";
$usr_domain = (trim(fgets(STDIN)));
echo "You have entered :: $usr_domain \n";

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
    die("\nError encountered while creating " . $domain['conf']);
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
echo "Nginx configuration for '" . $domain['name'] . "' is created successfully";

/**
 * Create webroot dirs for new domain
 */
//create dirs
$result = system("mkdir " . $domain['rootdir']);
$result = system("mkdir " . $domain['htdocs']);
$result = system("mkdir " . $domain['logs']);

//create log files
//in nginx folder
$result = system("touch " . "/var/log/nginx/" . $domain['name'] . ".access.log");
$result = system("touch " . "/var/log/nginx/" . $domain['name'] . ".error.log");

//symlink
$result = system("ln -s " . "/var/log/nginx/" . $domain['name'] . ".access.log " . $domain['logs'] . "/access.log");
$result = system("ln -s " . "/var/log/nginx/" . $domain['name'] . ".error.log " . $domain['logs'] . "/error.log");


//Error check 
if ($result != '') {
    die("\nError encountered while creating websites directories & files for " . $domain['name'] . "\n");
}

/**
 * extract wordpress in new webroot dir
 */
$command = "unzip -q -o " . $local_env['wp_latest'] . " -d " . $domain['htdocs'] . " > /dev/null";
$result = system($command);

//Error check
if ($result != '') {
    die("\nError encountered while extracting latest wordpress in  directory " . $domain['rootdir'] . "\n");
}

$command = "mv " . $domain['htdocs'] . "/wordpress/* " . $domain['htdocs'] . "/";
echo "\n COMMAND :: $command \n";
$result = system($command);

//Error check
if ($result != '') {
    die("\nError encountered while moving latest wordpress in directory " . $domain['rootdir'] . "\n" . $result);
}

/**
 * MySQL Creation
 */
 
$command = "mysql -h " . $local_env['mysql_host'] . " -u " . $local_env['mysql_user'] . " -p" . $local_env['mysql_pass'] . " -e 'create database `'" . $domain['name'] . "'` '";
$result = system($command);

/*
 * Create wp-config.php
 */
$wp_config_sample = $domain['htdocs'] . "/wp-config-sample.php";

$command = "sed s/database_name_here/{$domain['name']}/ $wp_config_sample | sed s/username_here/{$local_env['mysql_user']}/ | sed s/password_here/{$local_env['mysql_pass']}/ > {$domain['htdocs']}/wp-config.php";

$result = system($command);

/**
 * Chown
 */
$command = "chown -R " . $local_env['nginx_user'] . ":" . $local_env['nginx_group'] . " " . $domain['rootdir'];
echo "\n COMMAND :: $command \n";
$result = system($command);

//Error check
if ($result != '') {
    die("\nError encountered while charging owner of " . $domain['rootdir'] . "\n" . $result);
}


/**
 * ALL SEENS WELL - Restart nginx
 */
echo "\n Relaoding nginx configuration...\n\n";
system('service nginx reload');		

/**
 * THE END
 */
//just echo URL for new domain like http://$domain
//user will click it and verify if its working fine! ;-)

echo $domain['name'] . " Successfully created\n\n";

?>
