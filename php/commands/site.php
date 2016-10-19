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
class Site_Command extends EE_Command {

	/**
	 * Create site.
	 *
	 * ## OPTIONS
	 *
	 * [<name>]
	 * : Name of the site to create.
	 *
	 * [--type=<types>]
	 * : Type for create site.
	 *
	 * [--cache=<cache>]
	 * : Cache for site.
	 *
	 * [--user=<username>]
	 * : Username for WordPress admin.
	 *
	 * [--email=<email>]
	 * : Email id for WordPress admin.
	 *
	 * [--pass=<pass>]
	 * : Password for WordPress admin.
	 *
	 * [--ip=<ip>]
	 * : Proxy ip address for proxy site.
	 *
	 * [--port=<port>]
	 * : Port no for porxy site.
	 *
	 *
	 *
	 * ## EXAMPLES
	 *
	 *      # Create site.
	 *      $ ee site create example.com
	 *
	 */
	public function create( $args, $assoc_args ) {

		$site_name = empty( $args[0] ) ? '' : $args[0];

		if ( empty( $site_name ) ) {
			$value = EE::input_value( "Enter site name :" );
			if ( $value ) {
				$site_name = $value;
			}
		}
		$ee_www_domain = EE_Utils::validate_domain( $site_name, false );
		$site_name     = EE_Utils::validate_domain( $site_name );
		$ee_domain     = $site_name;

		if ( empty( $ee_domain ) ) {
			EE::error( 'Invalid domain name, Provide valid domain name' );
		}
		if ( is_site_exist( $ee_domain ) ) {
			EE::error( "Site {$ee_domain} already exists" );
		} else if ( ee_file_exists( EE_NGINX_SITE_AVAIL_DIR . $ee_domain ) ) {
			EE::error("Nginx configuration /etc/nginx/sites-available/{$ee_domain} already exists");
		}
		$ee_site_webroot = EE_Variables::get_ee_webroot() . $ee_domain;
		$registered_cmd  = array(
			'html',
			'php',
			'php7',
			'mysql',
			'wp',
			'wpsubdir',
			'wpsubdomain',
			'w3tc',
			'wpfc',
			'wpsc',
			'wpredis',
			'hhvm',
			'pagespeed',
			'le',
			'letsencrypt',
			'user',
			'email',
			'pass',
			'proxy',
			'experimental',
		);

		$data  = array();
		$stype = empty( $assoc_args['type'] ) ? 'html' : $assoc_args['type'];
		$cache = empty( $assoc_args['cache'] ) ? 'basic' : $assoc_args['cache'];

		if ( ! empty( $stype ) ) {
			if ( in_array( $stype, $registered_cmd ) ) {
				if ( 'proxy' == $stype ) {
					$proxyinfo = $assoc_args['ip'];
					if ( strpos( $proxyinfo, ':' ) !== false ) {
						$proxyinfo = explode( ':', $proxyinfo );
						$host      = $proxyinfo[0];
						$port      = ( strlen( $proxyinfo[1] ) < 2 ) ? '80' : $proxyinfo[1];
					} else {
						$host = $assoc_args['ip'];
						$port = $assoc_args['port'];
					}
				}
			} else {
				//TODO: we will add hook for other packages. i.e do_action('create_site',$stype);
			}
		}
	}

	/**
	 * Update site.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name of the site to update.
	 *
	 * ## EXAMPLES
	 *
	 *      # update site.
	 *      $ ee site update example.com
	 *
	 */
	public function update( $args, $assoc_args ) {

		list( $site_name ) = $args;

		if ( ! empty( $site_name ) ) {
			EE::success( $site_name . ' site is updated successfully!' );
		} else {
			EE::error( 'Please give site name.' );
		}
	}

	/**
	 * Delete site.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name of the site to delete.
	 *
	 * ## EXAMPLES
	 *
	 *      # Delete site.
	 *      $ ee site delete example.com
	 *
	 */
	public function delete( $args, $assoc_args ) {

		list( $site_name ) = $args;

		if ( ! empty( $site_name ) ) {
			EE::success( $site_name . ' site is deleted successfully!' );
		} else {
			EE::error( 'Please give site name.' );
		}
	}
}

EE::add_command( 'site', 'Site_Command' );