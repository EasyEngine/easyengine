<?php

use \EE\Utils;
use \EE\Dispatcher;

class debug_Command extends EE_Command {


	public function debug_nginx($_argc){
//	"""Start/Stop Nginx debug"""

		$debug = $_argc;
		$debug_address = '0.0.0.0/0';

		if('on'=== $debug['nginx'] && empty($debug['sitename'])){
			if(!grep_string('/etc/nginx/nginx.conf','debug_connection')){
				EE::success("Setting up Nginx debug connection for 0.0.0.0/0");
				EE::exec_cmd("sed -i \"/events {{/a\\ \\ \\ \\ $(echo debug_connection ".$debug_address.";)\" /etc/nginx/nginx.conf");
			}else{
				EE::success("Nginx debug connection already enabled");
			}

		}elseif('off'=== $debug['nginx'] && empty($debug['sitename'])){
			if(grep_string('/etc/nginx/nginx.conf','debug_connection')){
				EE::success("Disabling Nginx debug connections");
				EE::exec_cmd("sed -i \"/debug_connection.*/d\" /etc/nginx/nginx.conf");
			}else{
				EE::success("Nginx debug connection already disabled");
			}
		}elseif('on'=== $debug['nginx'] && !empty($debug['sitename'])){

			//todo:

		}elseif('off'=== $debug['nginx'] && !empty($debug['sitename'])){

				//todo:
		}

	}

	public function debug_php(){
		// """Start/Stop PHP debug"""

	}

}

EE::add_command( 'debug', 'Debug_Command' );

