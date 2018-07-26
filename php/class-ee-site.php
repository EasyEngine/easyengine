<?php

use \Symfony\Component\Filesystem\Filesystem;

/**
 * Base class for Site command
 *
 * @package ee
 */
abstract class EE_Site_Command {
	private $fs;
	private $le;
	private $le_mail;
	private $site_name;
	private $site_root;
	private $site_type;

	public function __construct() {}

	/**
	 * Lists the created websites.
	 * abstract list
	 *
	 * [--enabled]
	 * : List only enabled sites.
	 *
	 * [--disabled]
	 * : List only disabled sites.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - yaml
	 *   - json
	 *   - count
	 *   - text
	 * ---
	 *
	 * @subcommand list
	 */
	public function _list( $args, $assoc_args ) {
		\EE\Utils\delem_log( 'site list start' );
		$format   = \EE\Utils\get_flag_value( $assoc_args, 'format' );
		$enabled  = \EE\Utils\get_flag_value( $assoc_args, 'enabled' );
		$disabled = \EE\Utils\get_flag_value( $assoc_args, 'disabled' );

		$where = array();

		if ( $enabled && ! $disabled ) {
			$where['is_enabled'] = 1;
		} elseif ( $disabled && ! $enabled ) {
			$where['is_enabled'] = 0;
		}

		$sites = EE::db()::select( array( 'sitename', 'is_enabled' ), $where );

		if ( ! $sites ) {
			EE::error( 'No sites found!' );
		}

		if ( 'text' === $format ) {
			foreach ( $sites as $site ) {
				EE::log( $site['sitename'] );
			}
		} else {
			$result = array_map(
				function ( $site ) {
					$site['site']   = $site['sitename'];
					$site['status'] = $site['is_enabled'] ? 'enabled' : 'disabled';

					return $site;
				}, $sites
			);

			$formatter = new \EE\Formatter( $assoc_args, [ 'site', 'status' ] );

			$formatter->display_items( $result );
		}

		\EE\Utils\delem_log( 'site list end' );
	}


	/**
	 * Deletes a website.
	 *
	 * ## OPTIONS
	 *
	 * <site-name>
	 * : Name of website to be deleted.
	 *
	 * [--yes]
	 * : Do not prompt for confirmation.
	 */
	public function delete( $args, $assoc_args ) {
		\EE\Utils\delem_log( 'site delete start' );
		$this->populate_site_info( $args );
		EE::confirm( "Are you sure you want to delete $this->site_name?", $assoc_args );
		$this->delete_site( 5, $this->site_name, $this->site_root );
		\EE\Utils\delem_log( 'site delete end' );
	}

	/**
	 * Function to delete the given site.
	 *
	 * @param int $level
	 *  Level of deletion.
	 *  Level - 0: No need of clean-up.
	 *  Level - 1: Clean-up only the site-root.
	 *  Level - 2: Try to remove network. The network may or may not have been created.
	 *  Level - 3: Disconnect & remove network and try to remove containers. The containers may not have been created.
	 *  Level - 4: Remove containers.
	 *  Level - 5: Remove db entry.
	 *
	 * @ignorecommand
	 */
	public function delete_site( $level, $site_name, $site_root ) {
		$this->fs   = new Filesystem();
		$proxy_type = EE_PROXY_TYPE;
		if ( $level >= 3 ) {
			if ( EE::docker()::docker_compose_down( $site_root ) ) {
				EE::log( "[$site_name] Docker Containers removed." );
			} else {
				\EE\Utils\default_launch( "docker rm -f $(docker ps -q -f=label=created_by=EasyEngine -f=label=site_name=$site_name)" );
				if ( $level > 3 ) {
					EE::warning( 'Error in removing docker containers.' );
				}
			}

			EE::docker()::disconnect_site_network_from( $site_name, $proxy_type );
		}

		if ( $level >= 2 ) {
			if ( EE::docker()::rm_network( $site_name ) ) {
				EE::log( "[$site_name] Docker container removed from network $proxy_type." );
			} else {
				if ( $level > 2 ) {
					EE::warning( "Error in removing Docker container from network $proxy_type" );
				}
			}
		}

		if ( $this->fs->exists( $site_root ) ) {
			try {
				$this->fs->remove( $site_root );
			}
			catch ( Exception $e ) {
				EE::debug( $e );
				EE::error( 'Could not remove site root. Please check if you have sufficient rights.' );
			}
			EE::log( "[$site_name] site root removed." );
		}

		if ( $level > 4 ) {
			if ( $this->le ) {
				EE::log( 'Removing ssl certs.' );
				$crt_file   = EE_CONF_ROOT . "/nginx/certs/$site_name.crt";
				$key_file   = EE_CONF_ROOT . "/nginx/certs/$site_name.key";
				$conf_certs = EE_CONF_ROOT . "/acme-conf/certs/$site_name";
				$conf_var   = EE_CONF_ROOT . "/acme-conf/var/$site_name";

				$cert_files = [ $conf_certs, $conf_var, $crt_file, $key_file ];
				try {
					$this->fs->remove( $cert_files );
				}
				catch ( Exception $e ) {
					EE::warning( $e );
				}

			}
			if ( EE::db()::delete( array( 'sitename' => $site_name ) ) ) {
				EE::log( 'Removing database entry.' );
			} else {
				EE::error( 'Could not remove the database entry' );
			}
		}
		EE::log( "Site $site_name deleted." );
	}

	public function create( $args, $assoc_args ) {}

	public function up( $args, $assoc_args ) {}

	public function down( $args, $assoc_args ) {}

}

