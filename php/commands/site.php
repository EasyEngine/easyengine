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
	public function create( $args, $assoc_args ) {
		
		list( $site_name ) = $args;
		
		if ( ! empty( $site_name) ) {
			if( ! empty( $assoc_args['wp'] ) ) {
				$last_line = system('apt-get update', $retval);
           // Printing additional info
				echo 'Return value: ' . $retval;

				EE::success( $site_name . ' WordPress site is created successfully!' );
			} else {
				EE::success( $site_name . ' site is created successfully!' );
			}
		} else {
			EE::error( 'Please give site name.' );
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
	 *	  # update site.
	 *	  $ ee site update example.com
	 *
	 */
	public function update( $args, $assoc_args ) {
		
		list( $site_name ) = $args;
		
		if ( ! empty( $site_name) ) {
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
	 *	  # Delete site.
	 *	  $ ee site delete example.com
	 *
	 */
	public function delete( $args, $assoc_args ) {
		
		list( $site_name ) = $args;
		
		if ( ! empty( $site_name) ) {
			EE::success( $site_name . ' site is deleted successfully!' );
		} else {
			EE::error( 'Please give site name.' );
		}
	}
}

EE::add_command( 'site', 'Site_Command' );