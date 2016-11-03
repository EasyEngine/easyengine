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
		//todo:

	}

	public function debug_mysql($_argc){
		//"""Start/Stop MySQL debug"""
		$debug = $_argc;
		if('on'=== $debug['mysql'] && empty($debug['sitename'])){
			if(!EE::exec_cmd("mysql -e \"show variables like 'slow_query_log';\" | grep ON")){
				EE::success("Setting up MySQL slow log");
				EE_MySql::execute("set global slow_query_log = 'ON';");
				EE_MySql::execute("set global slow_query_log_file = '/var/log/mysql/mysql-slow.log';");
				EE_MySql::execute("set global long_query_time = 2;");
				EE_MySql::execute("set global log_queries_not_using_indexes = 'ON';");
			}else{
				EE::success("MySQL slow log is already enabled");
			}

		}elseif('off'=== $debug['mysql'] && empty($debug['sitename'])){
			if(EE::exec_cmd("mysql -e \"show variables like 'slow_query_log';\" | grep ON")){
				EE::success("Disabling MySQL slow log");
				EE_MySql::execute("set global slow_query_log = 'OFF';");
				EE_MySql::execute("set global slow_query_log_file = '/var/log/mysql/mysql-slow.log';");
				EE_MySql::execute("set global long_query_time = 10;");
				EE_MySql::execute("set global log_queries_not_using_indexes = 'OFF';");
			}else{
				EE::success("MySQL slow log already disabled");
			}
		}

	}

}

EE::add_command( 'debug', 'Debug_Command' );

