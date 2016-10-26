<?php
/**
 * This command will show you the information related to Nginx and PHP. You need to log in as root user and run this command.
 *
 * @package EasyEngine
 * @subpackage EasyEngine/Commands
 */
class Info_Command extends EE_Command {
	
	/**
	 * This command will show you the information related to Nginx and PHP. You need to log in as root user and run this command.
	 * 
	 * ## OPTIONS
	 * 
	 * [--nginx]
	 * : Get Nginx configuration information.
	 * 
	 * [--php]
	 * : Get PHP configuration information.
	 * ---
	 * default: 5.6
	 * options:
	 *   - 5.6
	 *   - 7
	 * 
	 * [--mysql]
	 * : Get MySql configuration information.
	 * 
	 * 
	 * ## EXAMPLES
	 *
	 *     # All Info
	 *     $ ee info
	 *     Success: Display configuration information related to Nginx, PHP and MySQL.
	 * 
	 *     # NGINX Info
	 *     $ ee info --nginx
	 *     Success: Get Nginx configuration information.
	 *
	 *     # PHP Info
	 *     $ ee info --php
	 *     Success: Get PHP configuration information.
	 * 
	 *	   # PHP7 Info
	 *     $ ee info --php=7
	 *     Success: Get PHP 7.0 configuration information.
	 * 
	 *	   # MySQL Info
	 *     $ ee info --mysql
	 *     Success: Get MySQL configuration information.
	 * 
	 * @since 4.0.0
	 * 
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __invoke( $args, $assoc_args ) {
		
		if ( empty( $assoc_args ) ) {
			$assoc_args['nginx'] = $assoc_args['php'] = $assoc_args['mysql'] = $assoc_args['php7'] = true;
		}
		
		if ( ! empty( $assoc_args['nginx'] ) ) {
            if ( EE_Apt_Get::is_installed( 'nginx-custom' ) || EE_Apt_Get::is_installed( 'nginx-common' ) ) {
                $this->info_nginx();
			} else {
                EE::error( 'Nginx is not installed' );
			}
		}
		
        if ( ! empty( $assoc_args['php'] ) && '7' !== $assoc_args['php'] ) {
            if ( EE_OS::ee_platform_distro() === 'debian' || EE_OS::ee_platform_codename() === 'precise' ) {
                if ( EE_Apt_Get::is_installed( 'php5-fpm' ) ) {
                    $this->info_php();
				}
                else {
                    EE::error( 'PHP5 is not installed' );
				}
			} else {
                if ( EE_Apt_Get::is_installed( 'php5.6-fpm' ) ) {
                    $this->info_php();
				} else {
                    EE::error( 'PHP5.6 is not installed' );
				}
			}
		}
		
        if ( ! empty( $assoc_args['php'] ) && '7' === $assoc_args['php'] ) {
            if ( EE_Apt_Get::is_installed( 'php7.0-fpm' ) ) {
                $this->info_php7();
			} else {
                EE::error( 'PHP 7.0 is not installed' );
			}
		}
		
        if ( ! empty( $assoc_args['mysql'] ) ) {
             if ( EE::exec_cmd( 'mysqladmin ping' ) ) {
                $this->info_mysql();
			} else {
                EE::error( 'MySQL is not installed' );
			}
		}
	}
	
	/**
	 * Get Nginx configuration information.
	 * 
	 * @since 4.0.0
	 */
	public function info_nginx() {
		
		$version	= EE::exec_cmd_output( "nginx -v 2>&1 | cut -d':' -f2 | cut -d' ' -f2 | cut -d'/' -f2 | tr -d '\n'" );
        $allow		= EE::exec_cmd_output( "grep ^allow /etc/nginx/common/acl.conf | cut -d' ' -f2 | cut -d';' -f1 | tr '\n' ' '" );
		
        $nc			= new NginxConfig();
        
		$nc.loadf( '/etc/nginx/nginx.conf' );
        $user		= $nc.get( 'user' )[1];
		
		/**
		 * @todo Need to check about Nginx Config parser.
		 */
        $worker_processes		= $nc.get( 'worker_processes' )[1];
        $worker_connections		= $nc.get( array( 'events', 'worker_connections' ) )[1];
        $keepalive_timeout		= $nc.get( array( 'http', 'keepalive_timeout' ) )[1];
        $fastcgi_read_timeout	= $nc.get( array( 'http', 'fastcgi_read_timeout' ) )[1];
        $client_max_body_size	= $nc.get( array( 'http', 'client_max_body_size' ) )[1];
        
		$data = array(
			'version'				 => $version,
			'allow'					 => $allow,
			'user'					 => $user,
			'worker_processes'		 => $worker_processes,
			'keepalive_timeout'		 => $keepalive_timeout,
			'worker_connections'	 => $worker_connections,
			'fastcgi_read_timeout'	 => $fastcgi_read_timeout,
			'client_max_body_size'	 => $client_max_body_size,
		);

		echo \EE\Utils\mustache_render( 'info_nginx.mustache', $data );
	}
	
	/**
	 * Get PHP configuration information.
	 * 
	 * @since 4.0.0
	 */
	public function info_php() {
		
		$php_version = ( EE_OS::ee_platform_codename() === 'trusty' or EE_OS::ee_platform_codename() === 'xenial' ) ? 'php5.6' : 'php';
		
		$version = EE::exec_cmd_output( "{$php_version} -v 2>/dev/null | head -n1 | cut -d' ' -f2 | cut -d'+' -f1 | tr -d '\n'" );
        
		/**
		 * @todo Need to check about PHP Config parser.
		 */
		$config = $configparser.ConfigParser();
        
		$php_version_folder = ( EE_OS::ee_platform_codename() === 'trusty' or EE_OS::ee_platform_codename() === 'xenial' ) ? 'php/5.6' : 'php5';
		
		$config.read( '/etc/' . $php_version_folder . '/fpm/php.ini' );
		
        $expose_php				= $config['PHP']['expose_php'];
        $memory_limit			= $config['PHP']['memory_limit'];
        $post_max_size			= $config['PHP']['post_max_size'];
        $upload_max_filesize	= $config['PHP']['upload_max_filesize'];
        $max_execution_time		= $config['PHP']['max_execution_time'];

        $config.read( '/etc/' . $php_version_folder . '/fpm/pool.d/www.conf' );
		
        $www_listen					= $config['www']['listen'];
        $www_ping_path				= $config['www']['ping.path'];
        $www_pm_status_path			= $config['www']['pm.status_path'];
        $www_pm						= $config['www']['pm'];
        $www_pm_max_requests		= $config['www']['pm.max_requests'];
        $www_pm_max_children		= $config['www']['pm.max_children'];
        $www_pm_start_servers		= $config['www']['pm.start_servers'];
        $www_pm_min_spare_servers	= $config['www']['pm.min_spare_servers'];
        $www_pm_max_spare_servers	= $config['www']['pm.max_spare_servers'];
        $www_request_terminate_time = $config['www']['request_terminate_timeout'];
		
        try {
            $www_xdebug = $config['www']['php_admin_flag[xdebug.profiler_enable_trigger]'];
		} catch ( Exception $e ) {
            $www_xdebug = 'off';
		}

        $config.read( '/etc/' . $php_version_folder . '/fpm/pool.d/debug.conf' );
        
		$debug_listen					= $config['debug']['listen'];
        $debug_ping_path				= $config['debug']['ping.path'];
        $debug_pm_status_path			= $config['debug']['pm.status_path'];
        $debug_pm						= $config['debug']['pm'];
        $debug_pm_max_requests			= $config['debug']['pm.max_requests'];
        $debug_pm_max_children			= $config['debug']['pm.max_children'];
        $debug_pm_start_servers			= $config['debug']['pm.start_servers'];
        $debug_pm_min_spare_servers		= $config['debug']['pm.min_spare_servers'];
        $debug_pm_max_spare_servers		= $config['debug']['pm.max_spare_servers'];
        $debug_request_terminate		= $config['debug']['request_terminate_timeout'];
		
        try {
            $debug_xdebug = $config['debug']['php_admin_flag[xdebug.profiler_enable_trigger]'];
		} catch ( Exception $e ) {
            $debug_xdebug = 'off';
		}

        $data = array(
			'version'								 => $version,
			'expose_php'							 => $expose_php,
			'memory_limit'							 => $memory_limit,
			'post_max_size'							 => $post_max_size,
			'upload_max_filesize'					 => $upload_max_filesize,
			'max_execution_time'					 => $max_execution_time,
			'www_listen'							 => $www_listen,
			'www_ping_path'							 => $www_ping_path,
			'www_pm_status_path'					 => $www_pm_status_path,
			'www_pm'								 => $www_pm,
			'www_pm_max_requests'					 => $www_pm_max_requests,
			'www_pm_max_children'					 => $www_pm_max_children,
			'www_pm_start_servers'					 => $www_pm_start_servers,
			'www_pm_min_spare_servers'				 => $www_pm_min_spare_servers,
			'www_pm_max_spare_servers'				 => $www_pm_max_spare_servers,
			'www_request_terminate_timeout'			 => $www_request_terminate_time,
			'www_xdebug_profiler_enable_trigger'	 => $www_xdebug,
			'debug_listen'							 => $debug_listen,
			'debug_ping_path'						 => $debug_ping_path,
			'debug_pm_status_path'					 => $debug_pm_status_path,
			'debug_pm'								 => $debug_pm,
			'debug_pm_max_requests'					 => $debug_pm_max_requests,
			'debug_pm_max_children'					 => $debug_pm_max_children,
			'debug_pm_start_servers'				 => $debug_pm_start_servers,
			'debug_pm_min_spare_servers'			 => $debug_pm_min_spare_servers,
			'debug_pm_max_spare_servers'			 => $debug_pm_max_spare_servers,
			'debug_request_terminate_timeout'		 => $debug_request_terminate,
			'debug_xdebug_profiler_enable_trigger'	 => $debug_xdebug,
		);

		echo \EE\Utils\mustache_render( 'info_php.mustache', $data );
	}
	
	/**
	 * Get PHP 7.0 configuration information.
	 * 
	 * @since 4.0.0
	 */
	public function info_php7() {
		$version = EE::exec_cmd_output( "php7.0 -v 2>/dev/null | head -n1 | cut -d' ' -f2 | cut -d'+' -f1 | tr -d '\n'" );
        
		/**
		 * @todo Need to check about PHP Config parser.
		 */
		$config = $configparser.ConfigParser();
		
        $config.read( '/etc/php/7.0/fpm/php.ini' );
		
        $expose_php					= $config['PHP']['expose_php'];
        $memory_limit				= $config['PHP']['memory_limit'];
        $post_max_size				= $config['PHP']['post_max_size'];
        $upload_max_filesize		= $config['PHP']['upload_max_filesize'];
        $max_execution_time			= $config['PHP']['max_execution_time'];

        $config.read( '/etc/php/7.0/fpm/pool.d/www.conf' );
        
		$www_listen					= $config['www']['listen'];
        $www_ping_path				= $config['www']['ping.path'];
        $www_pm_status_path			= $config['www']['pm.status_path'];
        $www_pm						= $config['www']['pm'];
        $www_pm_max_requests		= $config['www']['pm.max_requests'];
        $www_pm_max_children		= $config['www']['pm.max_children'];
        $www_pm_start_servers		= $config['www']['pm.start_servers'];
        $www_pm_min_spare_servers	= $config['www']['pm.min_spare_servers'];
        $www_pm_max_spare_servers	= $config['www']['pm.max_spare_servers'];
        $www_request_terminate_time = $config['www']['request_terminate_timeout'];
		
        try {
            $www_xdebug = $config['www']['php_admin_flag[xdebug.profiler_enable_trigger]'];
		} catch( Exception $e ) {
            $www_xdebug = 'off';
		}

        $config.read( '/etc/php/7.0/fpm/pool.d/debug.conf' );
        $debug_listen				= $config['debug']['listen'];
        $debug_ping_path			= $config['debug']['ping.path'];
        $debug_pm_status_path		= $config['debug']['pm.status_path'];
        $debug_pm					= $config['debug']['pm'];
        $debug_pm_max_requests		= $config['debug']['pm.max_requests'];
        $debug_pm_max_children		= $config['debug']['pm.max_children'];
        $debug_pm_start_servers		= $config['debug']['pm.start_servers'];
        $debug_pm_min_spare_servers = $config['debug']['pm.min_spare_servers'];
        $debug_pm_max_spare_servers = $config['debug']['pm.max_spare_servers'];
        $debug_request_terminate	= $config['debug']['request_terminate_timeout'];
		
        try {
            $debug_xdebug = config['debug']['php_admin_flag[xdebug.profiler_enable_trigger]'];
        } catch( Exception $e ) {
            $debug_xdebug = 'off';
		}

        $data = array(
			'version'								 => $version,
			'expose_php'							 => $expose_php,
			'memory_limit'							 => $memory_limit,
			'post_max_size'							 => $post_max_size,
			'upload_max_filesize'					 => $upload_max_filesize,
			'max_execution_time'					 => $max_execution_time,
			'www_listen'							 => $www_listen,
			'www_ping_path'							 => $www_ping_path,
			'www_pm_status_path'					 => $www_pm_status_path,
			'www_pm'								 => $www_pm,
			'www_pm_max_requests'					 => $www_pm_max_requests,
			'www_pm_max_children'					 => $www_pm_max_children,
			'www_pm_start_servers'					 => $www_pm_start_servers,
			'www_pm_min_spare_servers'				 => $www_pm_min_spare_servers,
			'www_pm_max_spare_servers'				 => $www_pm_max_spare_servers,
			'www_request_terminate_timeout'			 => $www_request_terminate_time,
			'www_xdebug_profiler_enable_trigger'	 => $www_xdebug,
			'debug_listen'							 => $debug_listen,
			'debug_ping_path'						 => $debug_ping_path,
			'debug_pm_status_path'					 => $debug_pm_status_path,
			'debug_pm'								 => $debug_pm,
			'debug_pm_max_requests'					 => $debug_pm_max_requests,
			'debug_pm_max_children'					 => $debug_pm_max_children,
			'debug_pm_start_servers'				 => $debug_pm_start_servers,
			'debug_pm_min_spare_servers'			 => $debug_pm_min_spare_servers,
			'debug_pm_max_spare_servers'			 => $debug_pm_max_spare_servers,
			'debug_request_terminate_timeout'		 => $debug_request_terminate,
			'debug_xdebug_profiler_enable_trigger'	 => $debug_xdebug,
		);
		
		echo \EE\Utils\mustache_render( 'info_php.mustache', $data );
	}
	
	/**
	 * Get MySql configuration information.
	 * 
	 * @since 4.0.0
	 */
	public function info_mysql() {
		$version		= EE::exec_cmd_output( "mysql -V | awk '{print($5)}' | cut -d ',' -f1 | tr -d '\n'" );
        $host			= 'localhost';
        $port			= EE::exec_cmd_output( "mysql -e \"show variables\" | grep ^port | awk '{print($2)}' | tr -d '\n'" );
        $wait_timeout	= EE::exec_cmd_output( "mysql -e \"show variables\" | grep ^wait_timeout | awk '{print($2)}' | tr -d '\n'" );
        
		$interactive_timeout	= EE::exec_cmd_output( "mysql -e \"show variables\" | grep ^interactive_timeout | awk '{print($2)}' | tr -d '\n'" );
        $max_used_connections	= EE::exec_cmd_output( "mysql -e \"show global status\" | grep Max_used_connections | awk '{print($2)}' | tr -d '\n'" );
        $datadir				= EE::exec_cmd_output( "mysql -e \"show variables\" | grep datadir | awk '{print($2)}' | tr -d '\n'" );
        $socket					= EE::exec_cmd_output( "mysql -e \"show variables\" | grep \"^socket\" | awk '{print($2)}' | tr -d '\n'" );
        
		$data = array(
			'version'				 => $version,
			'host'					 => $host,
			'port'					 => $port,
			'wait_timeout'			 => $wait_timeout,
			'interactive_timeout'	 => $interactive_timeout,
			'max_used_connections'	 => $max_used_connections,
			'datadir'				 => $datadir,
			'socket'				 => $socket,
		);

		echo \EE\Utils\mustache_render( 'info_mysql.mustache', $data );
	}
}

EE::add_command( 'info', 'Info_Command' );