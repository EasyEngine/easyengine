<?php

/* This will have configuration */
// Report all PHP errors (see changelog)
error_reporting(E_ALL);

if (file_exists('config.php')){
	include 'config.php'}
else{
	die("Create a config.php to start with...");
}

/*** YOU CAN IGNORE ANYTHING BELOW THIS LINE **/

$hello = <<<EOF
 .----------------.  .----------------.  .----------------.  .----------------.  .----------------.  .----------------.
| .--------------. || .--------------. || .--------------. || .--------------. || .--------------. || .--------------. |
| |  _______     | || |  _________   | || |     ______   | || |      __      | || | ____    ____ | || |   ______     | |
| | |_   __ \    | || | |  _   _  |  | || |   .' ___  |  | || |     /  \     | || ||_   \  /   _|| || |  |_   __ \   | |
| |   | |__) |   | || | |_/ | | \_|  | || |  / .'   \_|  | || |    / /\ \    | || |  |   \/   |  | || |    | |__) |  | |
| |   |  __ /    | || |     | |      | || |  | |         | || |   / ____ \   | || |  | |\  /| |  | || |    |  ___/   | |
| |  _| |  \ \_  | || |    _| |_     | || |  \ `.___.'\  | || | _/ /    \ \_ | || | _| |_\/_| |_ | || |   _| |_      | |
| | |____| |___| | || |   |_____|    | || |   `._____.'  | || ||____|  |____|| || ||_____||_____|| || |  |_____|     | |
| |              | || |              | || |              | || |              | || |              | || |              | |
| '--------------' || '--------------' || '--------------' || '--------------' || '--------------' || '--------------' |
 '----------------'  '----------------'  '----------------'  '----------------'  '----------------'  '----------------'
EOF;

echo $hello . "\n";

/*** OK enough now. This is second & last time I am reminding YOU CAN IGNORE ANYTHING BELOW THIS LINE **/

chdir(dirname(__FILE__));

/* Useful test to avoid time-wastage */
if($local_env['mysql_user'] == 'USER' || $local_env['mysql_pass'] == 'PASS'){
	die("Please enter mysql username & password before running this script.");
}

if(!file_exists($local_env['wp_latest'])){
	echo "Latest WordPress is not present at " . $local_env['wp_latest'] ;
	echo "Let me try downloading it…\n";
	$command = "wget http://wordpress.org/latest.zip -O " . $local_env['wp_latest'] ;	
	$result = system($command);
	if(!file_exists($local_env['wp_latest'])){
		die ("this is second time I'm checking for WordPress but its missing at path " . $local_env['wp_latest'] . 
			"\n Please fix it first and then try running scripts here");
	}else{

		echo "wordpress found at ". $local_env['wp_latest'] ; 
	}	
}

echo "\nconfig is correct.... go ahead my boy! \n";
?>
