<?php

use \Symfony\Component\Filesystem\Filesystem;

/**
 * Base class for Site command
 *
 * @package ee
 */
abstract class EE_Site_Command {
	/**
	 * @var Filesystem $fs Symfony Filesystem object.
	 */
	private $fs;

	/**
	 * @var bool $le Whether the site is letsencrypt or not.
	 */
	private $le;

	/**
	 * @var string $le_mail Mail id to be used for letsencrypt registration and certificate generation.
	 */
	private $le_mail;

	/**
	 * @var array $site Associative array containing essential site related information.
	 */
	private $site;

	public function __construct() {
	}

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

		EE\Utils\delem_log( 'site list start' );
		$format   = EE\Utils\get_flag_value( $assoc_args, 'format' );
		$enabled  = EE\Utils\get_flag_value( $assoc_args, 'enabled' );
		$disabled = EE\Utils\get_flag_value( $assoc_args, 'disabled' );

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

			$formatter = new EE\Formatter( $assoc_args, [ 'site', 'status' ] );

			$formatter->display_items( $result );
		}

		EE\Utils\delem_log( 'site list end' );
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

		EE\Utils\delem_log( 'site delete start' );
		$this->populate_site_info( $args );
		EE::confirm( sprintf( 'Are you sure you want to delete %s?', $this->site['name'] ), $assoc_args );
		$this->delete_site( 5, $this->site['name'], $this->site['root'] );
		EE\Utils\delem_log( 'site delete end' );
	}

	/**
	 * Function to delete the given site.
	 *
	 * @param int $level        Level of deletion.
	 *                          Level - 0: No need of clean-up.
	 *                          Level - 1: Clean-up only the site-root.
	 *                          Level - 2: Try to remove network. The network may or may not have been created.
	 *                          Level - 3: Disconnect & remove network and try to remove containers. The containers may
	 *                          not have been created. Level - 4: Remove containers. Level - 5: Remove db entry.
	 * @param string $site_name Name of the site to be deleted.
	 * @param string $site_root Webroot of the site.
	 */
	protected function delete_site( $level, $site_name, $site_root ) {

		$this->fs   = new Filesystem();
		$proxy_type = EE_PROXY_TYPE;
		if ( $level >= 3 ) {
			if ( EE::docker()::docker_compose_down( $site_root ) ) {
				EE::log( "[$site_name] Docker Containers removed." );
			} else {
				EE\Utils\default_launch( "docker rm -f $(docker ps -q -f=label=created_by=EasyEngine -f=label=site_name=$site_name)" );
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
			} catch ( Exception $e ) {
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
				} catch ( Exception $e ) {
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

	/**
	 * Enables a website. It will start the docker containers of the website if they are stopped.
	 *
	 * ## OPTIONS
	 *
	 * [<site-name>]
	 * : Name of website to be enabled.
	 *
	 * [--force]
	 * : Force execution of site up.
	 */
	public function up( $args, $assoc_args ) {

		EE\Utils\delem_log( 'site enable start' );
		$force = EE\Utils\get_flag_value( $assoc_args, 'force' );
		$args  = EE\SiteUtils\auto_site_name( $args, 'site', __FUNCTION__ );
		$this->populate_site_info( $args );
		if ( EE::db()::site_enabled( $this->site['name'] ) && ! $force ) {
			EE::error( sprintf( '%s is already enabled!', $this->site['name'] ) );
		}
		EE::log( "Enabling site $this->site['name']." );
		if ( EE::docker()::docker_compose_up( $this->site['root'] ) ) {
			EE::db()::update( [ 'is_enabled' => '1' ], [ 'sitename' => $this->site['name'] ] );
			EE::success( "Site $this->site['name'] enabled." );
		} else {
			EE::error( sprintf( 'There was error in enabling %s. Please check logs.', $this->site['name'] ) );
		}
		EE\Utils\delem_log( 'site enable end' );
	}

	/**
	 * Disables a website. It will stop and remove the docker containers of the website if they are running.
	 *
	 * ## OPTIONS
	 *
	 * [<site-name>]
	 * : Name of website to be disabled.
	 */
	public function down( $args, $assoc_args ) {

		EE\Utils\delem_log( 'site disable start' );
		$args = EE\SiteUtils\auto_site_name( $args, 'site', __FUNCTION__ );
		$this->populate_site_info( $args );
		EE::log( sprintf( 'Disabling site %s.', $this->site['name'] ) );
		if ( EE::docker()::docker_compose_down( $this->site['root'] ) ) {
			EE::db()::update( [ 'is_enabled' => '0' ], [ 'sitename' => $this->site['name'] ] );
			EE::success( sprintf( 'Site %s disabled.', $this->site['name'] ) );
		} else {
			EE::error( sprintf( 'There was error in disabling %s. Please check logs.', $this->site['name'] ) );
		}
		EE\Utils\delem_log( 'site disable end' );
	}

	/**
	 * Restarts containers associated with site.
	 * When no service(--nginx etc.) is specified, all site containers will be restarted.
	 *
	 * [<site-name>]
	 * : Name of the site.
	 *
	 * [--all]
	 * : Restart all containers of site.
	 *
	 * [--nginx]
	 * : Restart nginx container of site.
	 */
	public function restart( $args, $assoc_args, $whitelisted_containers = [] ) {

		EE\Utils\delem_log( 'site restart start' );
		$args                 = EE\SiteUtils\auto_site_name( $args, 'site', __FUNCTION__ );
		$all                  = EE\Utils\get_flag_value( $assoc_args, 'all' );
		$no_service_specified = count( $assoc_args ) === 0;

		$this->populate_site_info( $args );

		chdir( $this->site['root'] );

		if ( $all || $no_service_specified ) {
			$containers = $whitelisted_containers;
		} else {
			$containers = array_keys( $assoc_args );
		}

		foreach ( $containers as $container ) {
			EE\Siteutils\run_compose_command( 'restart', $container );
		}
		EE\Utils\delem_log( 'site restart stop' );
	}

	/**
	 * Reload services in containers without restarting container(s) associated with site.
	 * When no service(--nginx etc.) is specified, all services will be reloaded.
	 *
	 * [<site-name>]
	 * : Name of the site.
	 *
	 * [--all]
	 * : Reload all services of site(which are supported).
	 *
	 * [--nginx]
	 * : Reload nginx service in container.
	 *
	 */
	public function reload( $args, $assoc_args, $whitelisted_containers = [], $reload_commands = [] ) {

		EE\Utils\delem_log( 'site reload start' );
		$args = EE\SiteUtils\auto_site_name( $args, 'site', __FUNCTION__ );
		$all  = EE\Utils\get_flag_value( $assoc_args, 'all' );
		if ( ! array_key_exists( 'nginx', $reload_commands ) ) {
			$reload_commands['nginx'] = 'nginx sh -c \'nginx -t && service openresty reload\'';
		}
		$no_service_specified = count( $assoc_args ) === 0;

		$this->populate_site_info( $args );

		chdir( $this->site['root'] );

		if ( $all || $no_service_specified ) {
			$this->reload_services( $whitelisted_containers, $reload_commands );
		} else {
			$this->reload_services( array_keys( $assoc_args ), $reload_commands );
		}
		EE\Utils\delem_log( 'site reload stop' );
	}

	/**
	 * Executes reload commands. It needs separate handling as commands to reload each service is different.
	 *
	 * @param array $services        Services to reload.
	 * @param array $reload_commands Commands to reload the services.
	 */
	private function reload_services( $services, $reload_commands ) {

		foreach ( $services as $service ) {
			EE\SiteUtils\run_compose_command( 'exec', $reload_commands[ $service ], 'reload', $service );
		}
	}

	/**
	 * Runs the acme le registration and authorization.
	 *
	 * @param string $site_name Name of the site for ssl.
	 * @param string $site_root Webroot of the site.
	 * @param bool $wildcard    SSL with wildcard or not.
	 */
	protected function init_le( $site_name, $site_root, $wildcard = false ) {

		$this->site['name'] = $site_name;
		$this->site['root'] = $site_root;
		$client             = new Site_Letsencrypt();
		$this->le_mail      = EE::get_runner()->config['le-mail'] ?? EE::input( 'Enter your mail id: ' );
		EE::get_runner()->ensure_present_in_config( 'le-mail', $this->le_mail );
		if ( ! $client->register( $this->le_mail ) ) {
			$this->le = false;

			return;
		}

		$domains = $wildcard ? [
			sprintf( '*.%s', $this->site['name'] ),
			$this->site['name']
		] : [ $this->site['name'] ];
		if ( ! $client->authorize( $domains, $this->site['root'], $wildcard ) ) {
			$this->le = false;

			return;
		}
		if ( $wildcard ) {
			echo \cli\Colors::colorize( '%YIMPORTANT:%n Run `ee site le ' . $this->site['name'] . '` once the dns changes have propogated to complete the certification generation and installation.', null );
		} else {
			$this->le( [], [], $wildcard );
		}
	}


	/**
	 * Runs the acme le.
	 *
	 * ## OPTIONS
	 *
	 * <site-name>
	 * : Name of website.
	 *
	 * [--force]
	 * : Force renewal.
	 */
	public function le( $args = [], $assoc_args = [], $wildcard = false ) {

		if ( ! isset( $this->site['name'] ) ) {
			$this->populate_site_info( $args );
		}
		if ( ! isset( $this->le_mail ) ) {
			$this->le_mail = EE::get_config( 'le-mail' ) ?? EE::input( 'Enter your mail id: ' );
		}
		$force   = EE\Utils\get_flag_value( $assoc_args, 'force' );
		$domains = $wildcard ? [ "*.$this->site['name']", $this->site['name'] ] : [ $this->site['name'] ];
		$client  = new Site_Letsencrypt();
		if ( ! $client->check( $domains, $wildcard ) ) {
			$this->le = false;

			return;
		}
		if ( $wildcard ) {
			$client->request( "*.$this->site['name']", [ $this->site['name'] ], $this->le_mail, $force );
		} else {
			$client->request( $this->site['name'], [], $this->le_mail, $force );
			$client->cleanup( $this->site['root'] );
		}
		EE::launch( 'docker exec ee-nginx-proxy sh -c "/app/docker-entrypoint.sh /usr/local/bin/docker-gen /app/nginx.tmpl /etc/nginx/conf.d/default.conf; /usr/sbin/nginx -s reload"' );
	}

	/**
	 * Populate basic site info from db.
	 */
	private function populate_site_info( $args ) {

		$this->site['name'] = EE\Utils\remove_trailing_slash( $args[0] );

		if ( EE::db()::site_in_db( $this->site['name'] ) ) {

			$db_select = EE::db()::select( [], [ 'sitename' => $this->site['name'] ], 'sites', 1 );

			$this->site['type'] = $db_select['site_type'];
			$this->site['root'] = $db_select['site_path'];
			$this->le           = $db_select['is_ssl'];

		} else {
			EE::error( sprintf( 'Site %s does not exist.', $this->site['name'] ) );
		}
	}

	public function create( $args, $assoc_args ) {}

}
