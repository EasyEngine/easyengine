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
	 * <name>
	 * : Name of the site to create.
	 *
	 * [--wp]
	 * : To create WordPress site.
	 *
	 * ## EXAMPLES
	 *
	 *	  # Create site.
	 *	  $ ee site create example.com
	 *
	 */
	public function install( $args, $assoc_args ) {

		list( $site_name ) = $args;

		if( ! empty( $assoc_args['pagespeed'] ) ) {
			EE_CLI::error( $site_name . 'Pagespeed support has been dropped since EasyEngine v3.6.0' );
			EE_CLI::error( $site_name . 'Please run command again without `--pagespeed`' );
			EE_CLI::error( $site_name . 'For more details, read - https://easyengine.io/blog/disabling-pagespeed/' );
		}

		if ( ! empty( $site_name) ) {
			if( ! empty( $assoc_args['wp'] ) ) {
				$check_nginx = EE_CLI::exec_cmd('nginx -t', 'List The Directory', false);
				if ( 0 == $check_nginx ) {
					EE_CLI::success( 'Nginx is available' );
				} else {
					EE_CLI::success( 'Please install nginx' );
				}

			} else {
				EE_CLI::success( $site_name . ' site is created successfully!' );
			}
		} else {
			EE_CLI::error( 'Please give site name.' );
		}
	}

}

EE_CLI::add_command( 'stack', 'Stack_Command' );