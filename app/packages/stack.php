<?php



/**
 * Manage sites.
 *
 * ## EXAMPLES
 *
 *     # Create site
 *     $ ee site create example.com
 *     Success: Created example.com site.
 *
 *     # Update site
 *     $ ee site update example.com
 *     Success: Updated example.com site.
 *
 *     # Delete site
 *     $ ee site delete example.com
 *     Success: Deleted example.com site.
 *
 * @package easyengine
 */
class Stack_Command extends EE_CLI_Command {

	/**
	 * Create site.
	 *
	 * ## OPTIONS
	 *
	 *
	 * [--web]
	 * : To install web.
	 *
	 * [--nginx]
	 * : To install nginx.
	 *
	 * [--php]
	 * : To install nginx.
	 *
	 * ## EXAMPLES
	 *
	 *	  # Create site.
	 *	  $ ee site create example.com
	 *
	 */
	public function install( $args, $assoc_args ) {

		if( ! empty( $assoc_args['php'] ) ) {
			$os_platform_codename = EE_OS::validate_domain("http://stackoverflow.com/questions/9364242/how-to-remove-http-www-and-slash-from-url-in-php");
			echo $os_platform_codename;

		}
		if( ! empty( $assoc_args['nginx'] ) ) {
			$check_nginx = EE_CLI::exec_cmd('nginx -t', 'Checking nginx..' );
			if ( 0 == $check_nginx ) {
				EE_CLI::success( 'Nginx is already available' );
			} else {
				EE_CLI::success( 'Please install nginx.' );
			}
		}
	}
}

EE_CLI::add_command( 'stack', 'Stack_Command' );