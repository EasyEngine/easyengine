<?php

use \Symfony\Component\Filesystem\Filesystem;
use \EE\Model\Site;

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
	 * @var bool $wildcard Whether the site is letsencrypt type is wildcard or not.
	 */
	private $wildcard;

	/**
	 * @var bool $ssl Whether the site has SSL or not.
	 */
	private $ssl;

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
		$format = EE\Utils\get_flag_value( $assoc_args, 'format' );
		$enabled = EE\Utils\get_flag_value( $assoc_args, 'enabled' );
		$disabled = EE\Utils\get_flag_value( $assoc_args, 'disabled' );

		$sites = Site::all();

		if ( $enabled && ! $disabled ) {
			$sites = Site::where( 'is_enabled', true );
		} elseif ( $disabled && ! $enabled ) {
			$sites = Site::where( 'is_enabled', false );
		}

		if ( empty( $sites ) ) {
			EE::error( 'No sites found!' );
		}

		if ( 'text' === $format ) {
			foreach ( $sites as $site ) {
				EE::log( $site->site_url );
			}
		} else {
			$result = array_map(
				function ( $site ) {
					$site->site = $site->site_url;
					$site->status = $site->site_enabled ? 'enabled' : 'disabled';

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
		EE::confirm( sprintf( 'Are you sure you want to delete %s?', $this->site['url'] ), $assoc_args );
		$this->delete_site( 5, $this->site['url'], $this->site['root'] );
		EE\Utils\delem_log( 'site delete end' );
	}

	/**
	 * Function to delete the given site.
	 *
	 * @param int    $level     Level of deletion.
	 *                          Level - 0: No need of clean-up.
	 *                          Level - 1: Clean-up only the site-root.
	 *                          Level - 2: Try to remove network. The network may or may not have been created.
	 *                          Level - 3: Disconnect & remove network and try to remove containers. The containers may
	 *                          not have been created. Level - 4: Remove containers. Level - 5: Remove db entry.
	 * @param string $site_name Name of the site to be deleted.
	 * @param string $site_root Webroot of the site.
	 */
	protected function delete_site( $level, $site_name, $site_root ) {

		$this->fs = new Filesystem();
		$proxy_type = EE_PROXY_TYPE;
		if ( $level >= 3 ) {
			if ( EE::docker()::docker_compose_down( $site_root ) ) {
				EE::log( "[$site_name] Docker Containers removed." );
			} else {
				EE::exec( "docker rm -f $(docker ps -q -f=label=created_by=EasyEngine -f=label=site_name=$site_name)" );
				if ( $level > 3 ) {
					EE::warning( 'Error in removing docker containers.' );
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

		$config_file_path = EE_CONF_ROOT . '/nginx/conf.d/' . $site_name . '-redirect.conf';

		if ( $this->fs->exists( $config_file_path ) ) {
			try {
				$this->fs->remove( $config_file_path );
			} catch ( Exception $e ) {
				EE::debug( $e );
				EE::error( 'Could not remove site redirection file. Please check if you have sufficient rights.' );
			}
		}


		if ( $level > 4 ) {
			if ( $this->ssl ) {
				EE::log( 'Removing ssl certs.' );
				$crt_file = EE_CONF_ROOT . "/nginx/certs/$site_name.crt";
				$key_file = EE_CONF_ROOT . "/nginx/certs/$site_name.key";
				$conf_certs = EE_CONF_ROOT . "/acme-conf/certs/$site_name";
				$conf_var = EE_CONF_ROOT . "/acme-conf/var/$site_name";

				$cert_files = [$conf_certs, $conf_var, $crt_file, $key_file];
				try {
					$this->fs->remove( $cert_files );
				} catch ( Exception $e ) {
					EE::warning( $e );
				}
			}

			if ( Site::find( $site_name )->delete() ) {
				EE::log( 'Removed database entry.' );
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
		$args = EE\SiteUtils\auto_site_name( $args, 'site', __FUNCTION__ );
		$this->populate_site_info( $args );
		$site  = Site::find( $this->site['url'] );

		if ( $site->site_enabled && ! $force ) {
			EE::error( sprintf( '%s is already enabled!', $site->site_url ) );
		}

		EE::log( sprintf( 'Enabling site %s.', $site->site_url ) );

		if ( EE::docker()::docker_compose_up( $this->site['root'] ) ) {
			$site->site_enabled = 1;
			$site->save();
			EE::success( "Site $site->site_url enabled." );
		} else {
			EE::error( sprintf( 'There was error in enabling %s. Please check logs.', $site->site_url ) );
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

		$site = Site::find($this->site['url']);

		EE::log( sprintf( 'Disabling site %s.', $site->site_url ) );

		if ( EE::docker()::docker_compose_down( $this->site['root'] ) ) {
			$site->site_enabled = 0;
			$site->save();

			EE::success( sprintf( 'Site %s disabled.', $this->site['url'] ) );
		} else {
			EE::error( sprintf( 'There was error in disabling %s. Please check logs.', $this->site['url'] ) );
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
		$args = EE\SiteUtils\auto_site_name( $args, 'site', __FUNCTION__ );
		$all = EE\Utils\get_flag_value( $assoc_args, 'all' );
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
		$all = EE\Utils\get_flag_value( $assoc_args, 'all' );
		if ( !array_key_exists( 'nginx', $reload_commands ) ) {
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
			EE\SiteUtils\run_compose_command( 'exec', $reload_commands[$service], 'reload', $service );
		}
	}

	/**
	 * Runs the acme le registration and authorization.
	 *
	 * @param string $site_name      Name of the site for ssl.
	 *
	 * @throws Exception
	 */
	protected function inherit_certs( $site_name ) {
		$parent_site_name = implode( '.', array_slice( explode( '.', $site_name ), 1 ) );
		$parent_site      = Site::find( $parent_site_name, [ 'site_ssl', 'site_ssl_wildcard' ] );

		if ( ! $parent_site ) {
			throw new Exception( 'Unable to find existing site: ' . $parent_site_name );
		}

		if ( ! $parent_site->site_ssl ) {
			throw new Exception( "Cannot inherit from $parent_site_name as site does not have SSL cert" . var_dump( $parent_site ) );
		}

		if ( ! $parent_site->site_ssl_wildcard ) {
			throw new Exception( "Cannot inherit from $parent_site_name as site does not have wildcard SSL cert" );
		}

		// We don't have to do anything now as nginx-proxy handles everything for us.
		EE::success( 'Inherited certs from parent' );
	}

	/**
	 * Runs SSL procedure.
	 *
	 * @param string $site_name Name of the site for ssl.
	 * @param string $site_root Webroot of the site.
	 * @param string $ssl_type  Type of ssl cert to issue.
	 * @param bool   $wildcard  SSL with wildcard or not.
	 *
	 * @throws \EE\ExitException If --ssl flag has unrecognized value
	 */
	protected function init_ssl( $site_name, $site_root, $ssl_type, $wildcard = false ) {
		EE::debug( 'Starting SSL procedure' );
		if ( 'le' === $ssl_type ) {
			EE::debug( 'Initializing LE' );
			$this->init_le( $site_name, $site_root, $wildcard );
		} elseif ( 'inherit' === $ssl_type ) {
			if ( $wildcard ) {
				EE::error( 'Cannot use --wildcard with --ssl=inherit', false );
			}
			EE::debug( 'Inheriting certs' );
			$this->inherit_certs( $site_name );
		} else {
			EE::error( "Unrecognized value in --ssl flag: $ssl_type" );
		}
	}

	/**
	 * Runs the acme le registration and authorization.
	 *
	 * @param string $site_name Name of the site for ssl.
	 * @param string $site_root Webroot of the site.
	 * @param bool   $wildcard  SSL with wildcard or not.
	 */
	protected function init_le( $site_name, $site_root, $wildcard = false ) {
		EE::debug( "Wildcard in init_le: $wildcard" );

		$this->site['url'] = $site_name;
		$this->site['root'] = $site_root;
		$this->wildcard = $wildcard;
		$client = new Site_Letsencrypt();
		$this->le_mail = EE::get_runner()->config['le-mail'] ?? EE::input( 'Enter your mail id: ' );
		EE::get_runner()->ensure_present_in_config( 'le-mail', $this->le_mail );
		if ( ! $client->register( $this->le_mail ) ) {
			$this->ssl = null;

			return;
		}

		$domains = $this->get_cert_domains( $site_name, $wildcard );

		if ( ! $client->authorize( $domains, $this->site['root'], $wildcard ) ) {
			$this->le = false;

			return;
		}
		if ( $wildcard ) {
			echo \cli\Colors::colorize( '%YIMPORTANT:%n Run `ee site le ' . $this->site['url'] . '` once the dns changes have propogated to complete the certification generation and installation.', null );
		} else {
			$this->le( [], [] );
		}
	}

	/**
	 * Returns all domains required by cert
	 *
	 * @param string $site_name Name of site
	 * @param $wildcard  Wildcard cert required?
	 *
	 * @return array
	 */
	private function get_cert_domains( string $site_name, $wildcard ) : array {
		$domains = [ $site_name ];
		$has_www = ( strpos( $site_name, 'www.' ) === 0 );

		if ( $wildcard ) {
			$domains[] = "*.{$site_name}";
		} else {
			$domains[] = $this->get_www_domain( $site_name );
		}
		return $domains;
	}

	/**
	 * If the domain has www in it, returns a domain without www in it.
	 * Else returns a domain with www in it.
	 *
	 * @param string $site_name Name of site
	 *
	 * @return string Domain name with or without www
	 */
	private function get_www_domain( string $site_name ) : string {
		$has_www = ( strpos( $site_name, 'www.' ) === 0 );

		if ( $has_www ) {
			return ltrim( $site_name, 'www.' );
		} else {
			return  'www.' . $site_name;
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
	public function le( $args = [], $assoc_args = [] ) {

		if ( !isset( $this->site['url'] ) ) {
			$this->populate_site_info( $args );
		}

		if ( !isset( $this->le_mail ) ) {
			$this->le_mail = EE::get_config( 'le-mail' ) ?? EE::input( 'Enter your mail id: ' );
		}

		$force   = EE\Utils\get_flag_value( $assoc_args, 'force' );
		$domains = $this->get_cert_domains( $this->site['url'], $this->wildcard );
		$client  = new Site_Letsencrypt();

		if ( ! $client->check( $domains, $this->wildcard ) ) {
			$this->ssl = null;
			return;
		}

		$san = array_values( array_diff( $domains, [ $this->site['url'] ] ) );
		$client->request( $this->site['url'], $san, $this->le_mail, $force );

		if ( ! $this->wildcard ) {
			$client->cleanup( $this->site['root'] );
		}
		EE::launch( 'docker exec ee-nginx-proxy sh -c "/app/docker-entrypoint.sh /usr/local/bin/docker-gen /app/nginx.tmpl /etc/nginx/conf.d/default.conf; /usr/sbin/nginx -s reload"' );
	}

	/**
	 * Populate basic site info from db.
	 */
	private function populate_site_info( $args ) {

		$this->site['url'] = EE\Utils\remove_trailing_slash( $args[0] );
		$site = Site::find( $this->site['url'] );
		if ( $site ) {

			$db_select = $site->site_url;

			$this->site['type'] = $site->site_type;
			$this->site['root'] = $site->site_fs_path;
			$this->ssl          = $site->site_ssl;
			$this->wildcard     = $site->site_ssl_wildcard;
		} else {
			EE::error( sprintf( 'Site %s does not exist.', $this->site['url'] ) );
		}
	}

	abstract public function create( $args, $assoc_args );

}
