<?php

use Symfony\Component\Filesystem\Filesystem;

class Site_Function {

	public static function pre_run_checks() {
		EE::log( 'Running pre-update checks, please wait...' );
		try {
			$check_nginx = EE::exec_cmd( 'nginx -t', 'checking NGINX configuration ...' );
			if ( 0 == $check_nginx ) {
				return true;
			}
		} catch ( \Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::error( 'nginx configuration check failed.' );
		}

		return false;
	}

	public static function check_domain_exists( $domain ) {
		//Check in ee database.
	}

	public static function setupdomain( $data ) {
		$filesystem      = new Filesystem();
		$ee_domain_name  = $data['site_name'];
		$ee_site_webroot = ! empty( $data['webroot'] ) ? $data['webroot'] : '';
		EE::log( 'Setting up NGINX configuration' );
		try {
			$mustache_template = 'virtualconf-php7.mustache';
			if ( empty( $data['php7'] ) ) {
				$mustache_template = 'virtualconf.mustache';
			}
			EE::debug( 'Writting the nginx configuration to file /etc/nginx/conf.d/blockips.conf');
			EE\Utils\mustache_write_in_file( EE_NGINX_SITE_AVAIL_DIR . $ee_domain_name, $mustache_template, $data );
		} catch ( \Exception $e ) {
			EE::error( 'create nginx configuration failed for site' );
		} finally {
			EE::debug( 'Checking generated nginx conf, please wait...' );
			self::pre_run_checks();
			$filesystem->symlink( EE_NGINX_SITE_AVAIL_DIR . $ee_domain_name, EE_NGINX_SITE_ENABLE_DIR . $ee_domain_name );
		}
		if ( empty( $data['proxy'] ) ) {
			EE::log( 'Setting up webroot' );
			try {
				$filesystem->symlink( '/var/log/nginx/' . $ee_domain_name . '.access.log', $ee_site_webroot . '/logs/access.log' );
				$filesystem->symlink( '/var/log/nginx/' . $ee_domain_name . '.error.log', $ee_site_webroot . '/logs/error.log' );
			} catch ( Exception $e ) {
				EE::debug( $e->getMessage() );
				EE::error( 'setup webroot failed for site' );
			} finally {
				if ( $filesystem->exists( $ee_site_webroot . '/htdocs' ) && $filesystem->exists( $ee_site_webroot . '/logs' ) ) {
					EE::log( 'Done' );
				} else {
					EE::log( 'Fail' );
					EE::error( 'setup webroot failed for site' );
				}
			}
		}
	}
}