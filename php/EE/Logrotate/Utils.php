<?php

namespace EE\Logrotate;

/**
 * Logrotate setup utilities.
 */
class Utils {

	const CONFIG_DIR = '/etc/logrotate.d';
	const EE_LOG_CONFIG_FILE = '/etc/logrotate.d/ee_cli';
	const SITES_CONFIG_FILE = '/etc/logrotate.d/ee_sites';
	const NGINX_PROXY_CONFIG_FILE = '/etc/logrotate.d/ee_nginx_proxy';
	const EE_LOG_MAX_SIZE = '1G';
	// With dateext, a day-only suffix makes a second size rotation on the same day fail ("destination already exists").
	const DATE_FORMAT = '-%Y%m%d-%s';

	// rsyslog's rule (Debian/Ubuntu and RHEL 8+), and its older RHEL name.
	const HOST_SYSLOG_CONFIG_FILES = [ '/etc/logrotate.d/rsyslog', '/etc/logrotate.d/syslog' ];
	const HOST_LOG_MAX_SIZE = '2G';
	const TIMER_DROPIN_FILE = '/etc/systemd/system/logrotate.timer.d/hourly.conf';
	const CRON_DAILY_FILE = '/etc/cron.daily/logrotate';
	const CRON_HOURLY_FILE = '/etc/cron.hourly/ee-logrotate';

	/**
	 * Configure logrotate for EasyEngine CLI, site, and nginx-proxy logs.
	 */
	public static function setup_logrotate() {

		try {
			self::setup_logrotate_config();
		} catch ( \Throwable $e ) {
			\EE::warning( 'Unable to set up EasyEngine logrotate config: ' . $e->getMessage() );
		}
	}

	/**
	 * Cap host syslog growth: add maxsize to the rsyslog logrotate rule and run logrotate hourly.
	 *
	 * Postfix containers log through the host's /dev/log, so a logging loop lands in /var/log/syslog and mail.log,
	 * which distros rotate weekly with no size limit.
	 */
	public static function setup_host_log_cap() {

		try {
			if ( defined( 'IS_DARWIN' ) && IS_DARWIN ) {
				return;
			}

			if ( ! self::ensure_logrotate_installed() ) {
				return;
			}

			foreach ( self::HOST_SYSLOG_CONFIG_FILES as $file ) {
				if ( file_exists( $file ) ) {
					self::add_maxsize_to_config( $file, self::HOST_LOG_MAX_SIZE );
				}
			}

			self::schedule_hourly_rotation();
		} catch ( \Throwable $e ) {
			\EE::warning( 'Unable to cap host syslog size: ' . $e->getMessage() );
		}
	}

	/**
	 * Add maxsize to every stanza of a host logrotate config that has no size limit, keeping everything else.
	 *
	 * @param string $file     Config file path.
	 * @param string $max_size logrotate size, e.g. 2G.
	 * @return bool Whether the config is capped.
	 */
	private static function add_maxsize_to_config( $file, $max_size ) {

		// Replacing a symlink would detach the file from whatever manages it.
		if ( is_link( $file ) || ! is_file( $file ) || ! is_readable( $file ) ) {
			\EE::warning( "Not adding maxsize to $file: it is not a regular readable file." );
			return false;
		}

		$contents = file_get_contents( $file );
		$updated  = false === $contents ? null : self::insert_maxsize( $contents, $max_size );

		if ( null === $updated ) {
			\EE::warning( "Not adding maxsize to $file: unable to parse it. Add 'maxsize $max_size' to its rules by hand." );
			return false;
		}

		if ( $updated === $contents ) {
			\EE::debug( "Host logrotate config already has a size limit: $file" );
			return true;
		}

		$tmp = tempnam( sys_get_temp_dir(), 'ee-logrotate-' );
		if ( false === $tmp || false === file_put_contents( $tmp, $updated ) ) {
			\EE::warning( "Unable to add maxsize to $file: cannot write a temporary copy." );
			return false;
		}
		$new_errors = array_diff( self::dry_run_errors( $tmp ), self::dry_run_errors( $file ) );
		unlink( $tmp );

		if ( ! empty( $new_errors ) ) {
			\EE::warning( "Not adding maxsize to $file: logrotate rejects the result. " . implode( ' ', $new_errors ) );
			return false;
		}

		$backup = EE_BACKUP_DIR . '/logrotate/' . basename( $file );
		if ( ! is_dir( dirname( $backup ) ) ) {
			mkdir( dirname( $backup ), 0755, true );
		}
		if ( ! copy( $file, $backup ) ) {
			\EE::warning( "Not adding maxsize to $file: unable to back it up to $backup." );
			return false;
		}

		// The trailing ~ is in logrotate's tabooext, so a run in between never reads the half-written file.
		$new  = $file . '.ee-new~';
		$stat = stat( $file );
		if (
			false === file_put_contents( $new, $updated )
			|| ! chmod( $new, $stat['mode'] & 07777 )
			|| ! chown( $new, $stat['uid'] )
			|| ! chgrp( $new, $stat['gid'] )
			|| ! rename( $new, $file )
		) {
			if ( is_file( $new ) ) {
				unlink( $new );
			}
			\EE::warning( "Unable to add maxsize to $file." );
			return false;
		}

		\EE::log( "Added 'maxsize $max_size' to $file (original saved to $backup)." );

		return true;
	}

	/**
	 * Insert a maxsize line at the top of every stanza that has no maxsize or size directive, plus a dateformat that
	 * keeps same-day rotations apart unless the stanza or file sets its own.
	 *
	 * @param string $contents Config contents.
	 * @param string $max_size logrotate size.
	 * @return string|null Updated contents (unchanged when every stanza is limited), or null when unparseable.
	 */
	private static function insert_maxsize( $contents, $max_size ) {

		$lines        = preg_split( '/(?<=\n)/', $contents, -1, PREG_SPLIT_NO_EMPTY );
		$scripts      = [ 'postrotate', 'prerotate', 'firstaction', 'lastaction', 'preremove' ];
		$global_limit = false;
		$global_date  = false;
		$in_stanza    = false;
		$in_script    = false;
		$output       = '';
		$stanza       = [];
		$limited      = false;
		$dated        = false;
		$indent       = null;

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			$word    = strtolower( (string) strtok( $trimmed, " \t=" ) );

			if ( $in_script ) {
				$in_script = 'endscript' !== $word;
			} elseif ( '' === $trimmed || '#' === $trimmed[0] ) {
				// Comment or blank line.
			} elseif ( ! $in_stanza ) {
				if ( false !== strpos( $trimmed, '}' ) ) {
					return null;
				}
				if ( false !== strpos( $trimmed, '{' ) ) {
					if ( '{' !== substr( $trimmed, -1 ) || 1 !== substr_count( $trimmed, '{' ) ) {
						return null;
					}
					$in_stanza = true;
					$limited   = $global_limit;
					$dated     = $global_date;
					$indent    = null;
					$stanza    = [ $line ];
					continue;
				}
				$global_limit = $global_limit || in_array( $word, [ 'maxsize', 'size' ], true );
				$global_date  = $global_date || 'dateformat' === $word;
			} elseif ( '}' === $trimmed[0] ) {
				if ( false !== strpos( $trimmed, '{' ) ) {
					return null;
				}
				if ( ! $limited ) {
					$indent = null === $indent ? '    ' : $indent;
					$added  = [ $indent . '# Added by EasyEngine: rotate a log early when it outgrows maxsize.' . "\n", $indent . "maxsize $max_size\n" ];
					if ( ! $dated ) {
						$added[] = $indent . 'dateformat ' . self::DATE_FORMAT . "\n";
					}
					array_splice( $stanza, 1, 0, $added );
				}
				$output   .= implode( '', $stanza );
				$stanza    = [];
				$in_stanza = false;
			} elseif ( false !== strpos( $trimmed, '{' ) || false !== strpos( $trimmed, '}' ) ) {
				return null;
			} else {
				$in_script = in_array( $word, $scripts, true );
				$limited   = $limited || in_array( $word, [ 'maxsize', 'size' ], true );
				$dated     = $dated || 'dateformat' === $word;
				if ( null === $indent ) {
					$indent = substr( $line, 0, strspn( $line, " \t" ) );
				}
			}

			if ( $in_stanza ) {
				$stanza[] = $line;
			} else {
				$output .= $line;
			}
		}

		return $in_stanza || $in_script ? null : $output;
	}

	/**
	 * Errors `logrotate -d` reports for a config, with its path and line numbers masked so two copies compare equal.
	 *
	 * @param string $file Config file path.
	 * @return array
	 */
	private static function dry_run_errors( $file ) {

		$result = \EE::launch( 'logrotate -d ' . escapeshellarg( $file ) . ' 2>&1', false, true );
		$errors = [];

		foreach ( explode( "\n", $result->stdout ) as $line ) {
			if ( 0 === strpos( $line, 'error:' ) ) {
				// The inserted lines shift the line numbers of errors the original already has.
				$errors[] = trim( preg_replace( '/<config>:\d+/', '<config>', str_replace( $file, '<config>', $line ) ) );
			}
		}

		// A failure without an error line (e.g. a crash) still counts as one.
		if ( 0 !== $result->return_code && empty( $errors ) ) {
			$errors[] = 'logrotate exited with ' . $result->return_code;
		}

		return $errors;
	}

	/**
	 * Run logrotate hourly, so maxsize is checked before a flood fills the disk.
	 *
	 * systemd hosts get a logrotate.timer drop-in; hosts that run logrotate from cron.daily get an
	 * hourly cron job that runs the same script. An existing drop-in or job is left as it is.
	 */
	private static function schedule_hourly_rotation() {

		if ( is_dir( '/run/systemd/system' ) && self::command_exists( 'systemctl' ) ) {
			$load_state = \EE::launch( 'systemctl show -p LoadState logrotate.timer 2>/dev/null', false, true );

			if ( 'LoadState=loaded' === trim( $load_state->stdout ) ) {
				self::install_timer_dropin();
				return;
			}
		}

		if ( ! is_executable( self::CRON_DAILY_FILE ) || ! is_dir( dirname( self::CRON_HOURLY_FILE ) ) ) {
			\EE::debug( 'Neither logrotate.timer nor cron.daily runs logrotate; not scheduling hourly rotation.' );
			return;
		}

		// Newer cron.daily scripts exit when systemd runs, leaving rotation to logrotate.timer.
		if ( is_dir( '/run/systemd/system' ) && false !== strpos( (string) file_get_contents( self::CRON_DAILY_FILE ), '/run/systemd/system' ) ) {
			\EE::warning( 'logrotate.timer is not available and ' . self::CRON_DAILY_FILE . ' defers to it, so logrotate is not scheduled hourly and maxsize is only checked when logrotate runs.' );
			return;
		}

		if ( file_exists( self::CRON_HOURLY_FILE ) ) {
			return;
		}

		$script = '#!/bin/sh' . "\n"
			. '# Added by EasyEngine: run the daily logrotate job hourly too, so maxsize limits are checked every hour.' . "\n"
			. '[ -x ' . self::CRON_DAILY_FILE . ' ] || exit 0' . "\n"
			. 'exec ' . self::CRON_DAILY_FILE . "\n";

		if ( false === file_put_contents( self::CRON_HOURLY_FILE, $script ) || ! chmod( self::CRON_HOURLY_FILE, 0755 ) ) {
			\EE::warning( 'Unable to write the hourly logrotate cron job: ' . self::CRON_HOURLY_FILE );
			return;
		}

		\EE::log( 'Scheduled logrotate hourly in ' . self::CRON_HOURLY_FILE . '.' );
	}

	/**
	 * Install the logrotate.timer drop-in that replaces the daily schedule with an hourly one.
	 */
	private static function install_timer_dropin() {

		if ( file_exists( self::TIMER_DROPIN_FILE ) ) {
			\EE::debug( 'logrotate.timer drop-in already exists: ' . self::TIMER_DROPIN_FILE );
			return;
		}

		$dir = dirname( self::TIMER_DROPIN_FILE );
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) ) {
			\EE::warning( 'Unable to create ' . $dir );
			return;
		}

		// The empty OnCalendar= clears the unit's daily schedule.
		$dropin = '# Added by EasyEngine: rotate hourly, so maxsize limits are checked every hour.' . "\n"
			. "[Timer]\nOnCalendar=\nOnCalendar=hourly\n";

		if ( false === file_put_contents( self::TIMER_DROPIN_FILE, $dropin ) || ! chmod( self::TIMER_DROPIN_FILE, 0644 ) ) {
			\EE::warning( 'Unable to write the logrotate.timer drop-in: ' . self::TIMER_DROPIN_FILE );
			return;
		}

		// try-restart leaves a timer the admin stopped or disabled alone.
		$result = \EE::launch( 'systemctl daemon-reload && systemctl try-restart logrotate.timer', false, true );

		if ( 0 !== $result->return_code ) {
			\EE::warning( 'Unable to reload logrotate.timer: ' . trim( $result->stderr . ' ' . $result->stdout ) );
			return;
		}

		\EE::log( 'Scheduled logrotate.timer hourly in ' . self::TIMER_DROPIN_FILE . '.' );
	}

	/**
	 * Configure logrotate for EasyEngine CLI, site, and nginx-proxy logs.
	 */
	private static function setup_logrotate_config() {

		if ( defined( 'IS_DARWIN' ) && IS_DARWIN ) {
			\EE::debug( 'Skipping logrotate setup on macOS.' );
			return;
		}

		if ( ! self::ensure_logrotate_installed() ) {
			return;
		}

		if ( ! is_dir( self::CONFIG_DIR ) ) {
			if ( ! is_writable( dirname( self::CONFIG_DIR ) ) || ! mkdir( self::CONFIG_DIR, 0755, true ) ) {
				\EE::warning( 'Unable to create logrotate config directory: ' . self::CONFIG_DIR );
				return;
			}
		}

		$ee_log_written = self::write_config( self::EE_LOG_CONFIG_FILE, self::get_ee_log_config() );
		$sites_written  = self::write_config( self::SITES_CONFIG_FILE, self::get_sites_config() );
		$proxy_written  = self::write_config( self::NGINX_PROXY_CONFIG_FILE, self::get_nginx_proxy_config() );

		if ( $ee_log_written && $sites_written && $proxy_written && self::validate_configs( self::get_config_files() ) ) {
			self::cleanup_legacy_configs();
			self::force_rotate_configs( self::get_config_files() );
		}
	}

	/**
	 * Ensure logrotate is available on supported systems.
	 *
	 * @return bool
	 */
	private static function ensure_logrotate_installed() {

		if ( self::command_exists( 'logrotate' ) ) {
			return true;
		}

		if ( ! self::command_exists( 'apt-get' ) ) {
			\EE::warning( 'logrotate is not installed and apt-get is unavailable. Skipping EasyEngine logrotate setup.' );
			return false;
		}

		\EE::log( 'Installing logrotate.' );
		$result = \EE::launch( 'DEBIAN_FRONTEND=noninteractive apt-get install -y logrotate', false, true );

		if ( 0 !== $result->return_code ) {
			\EE::warning( 'Unable to install logrotate. Skipping EasyEngine logrotate setup.' );
			return false;
		}

		return true;
	}

	/**
	 * Check whether a command is available in PATH.
	 *
	 * @param string $command Command name.
	 * @return bool
	 */
	private static function command_exists( $command ) {

		$result = \EE::launch( 'command -v ' . escapeshellarg( $command ) . ' >/dev/null 2>&1', false, true );

		return 0 === $result->return_code;
	}

	/**
	 * Write a logrotate config if it is missing or stale.
	 *
	 * @param string $file Config file path.
	 * @param string $content Config content.
	 * @return bool
	 */
	private static function write_config( $file, $content ) {

		if ( file_exists( $file ) && is_readable( $file ) && $content === file_get_contents( $file ) ) {
			return true;
		}

		if ( file_exists( $file ) && ! is_writable( $file ) ) {
			\EE::warning( 'EasyEngine logrotate config is not writable: ' . $file );
			return false;
		}

		if ( ! file_exists( $file ) && ! is_writable( dirname( $file ) ) ) {
			\EE::warning( 'EasyEngine logrotate config directory is not writable: ' . dirname( $file ) );
			return false;
		}

		if ( false === file_put_contents( $file, $content ) ) {
			\EE::warning( 'Unable to write EasyEngine logrotate config: ' . $file );
			return false;
		}

		if ( ! chmod( $file, 0644 ) ) {
			\EE::warning( 'Unable to set permissions on EasyEngine logrotate config: ' . $file );
		}

		\EE::debug( 'EasyEngine logrotate config written: ' . $file );

		return true;
	}

	/**
	 * Validate generated logrotate configs.
	 *
	 * @param array $files Config files.
	 * @return bool
	 */
	private static function validate_configs( array $files ) {

		$is_valid = true;

		foreach ( $files as $file ) {
			$command = 'logrotate -d ' . escapeshellarg( $file ) . ' 2>&1';
			$result  = \EE::launch( $command, false, true );

			if ( 0 === $result->return_code ) {
				\EE::debug( 'EasyEngine logrotate config validated: ' . $file );
				continue;
			}

			$message = trim( $result->stdout );
			\EE::warning( 'Unable to validate EasyEngine logrotate config: ' . $file . ( $message ? '. ' . $message : '' ) );
			$is_valid = false;
		}

		return $is_valid;
	}

	/**
	 * Force rotate EasyEngine logs once after setup or update.
	 *
	 * @param array $files Config files.
	 * @return bool
	 */
	private static function force_rotate_configs( array $files ) {

		$is_rotated = true;

		foreach ( $files as $file ) {
			$command = 'logrotate -f ' . escapeshellarg( $file ) . ' 2>&1';
			$result  = \EE::launch( $command, false, true );

			if ( 0 === $result->return_code ) {
				\EE::debug( 'EasyEngine logs force rotated: ' . $file );
				continue;
			}

			$message = trim( $result->stdout );
			\EE::warning( 'Unable to force rotate EasyEngine logs for config: ' . $file . ( $message ? '. ' . $message : '' ) );
			$is_rotated = false;
		}

		return $is_rotated;
	}

	/**
	 * Return generated EasyEngine logrotate config files.
	 *
	 * @return array
	 */
	private static function get_config_files() {

		return [
			self::EE_LOG_CONFIG_FILE,
			self::SITES_CONFIG_FILE,
			self::NGINX_PROXY_CONFIG_FILE,
		];
	}

	/**
	 * Remove old per-site/proxy configs that duplicate the wildcard configs.
	 */
	private static function cleanup_legacy_configs() {

		$files = glob( self::CONFIG_DIR . '/ee_*' );

		if ( false === $files ) {
			return;
		}

		foreach ( $files as $file ) {
			$basename = basename( $file );

			if ( in_array( $basename, [ 'ee_cli', 'ee_sites', 'ee_nginx_proxy' ], true ) || ! is_file( $file ) ) {
				continue;
			}

			if ( ! is_readable( $file ) ) {
				continue;
			}

			$contents = file_get_contents( $file );

			if ( false === $contents || ! self::is_legacy_config( $basename, $contents ) ) {
				continue;
			}

			$directory = dirname( $file );

			if ( ! is_writable( $directory ) ) {
				\EE::warning( 'Unable to remove legacy EasyEngine logrotate config, directory is not writable: ' . $directory );
				continue;
			}

			if ( unlink( $file ) ) {
				\EE::debug( 'Removed legacy EasyEngine logrotate config: ' . $file );
			} else {
				\EE::warning( 'Unable to remove legacy EasyEngine logrotate config: ' . $file );
			}
		}
	}

	/**
	 * Check whether a config duplicates EasyEngine log rotation.
	 *
	 * @param string $basename Config file basename.
	 * @param string $contents Config contents.
	 * @return bool
	 */
	private static function is_legacy_config( $basename, $contents ) {

		return self::is_legacy_site_config( $basename, $contents )
			|| self::is_legacy_nginx_proxy_config( $basename, $contents );
	}

	/**
	 * Check whether a config matches old per-site generated configs.
	 *
	 * @param string $basename Config file basename.
	 * @param string $contents Config contents.
	 * @return bool
	 */
	private static function is_legacy_site_config( $basename, $contents ) {

		$site = substr( $basename, 3 );

		if ( ! preg_match( '/^ee_[A-Za-z0-9.-]+$/', $basename ) || false === strpos( $site, '.' ) ) {
			return false;
		}

		foreach ( self::get_legacy_roots() as $root_dir ) {
			$site_path = preg_quote( $root_dir . '/sites/' . $site, '/' );

			if (
				preg_match( '/' . $site_path . '\/logs\/nginx\/\*\.log/', $contents )
				&& preg_match( '/' . $site_path . '\/logs\/php\/\*\.log/', $contents )
				&& false !== strpos( $contents, 'docker inspect -f' )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a config matches old nginx-proxy generated config.
	 *
	 * @param string $basename Config file basename.
	 * @param string $contents Config contents.
	 * @return bool
	 */
	private static function is_legacy_nginx_proxy_config( $basename, $contents ) {

		if ( 'ee_nginx_proxy_logrotate' !== $basename ) {
			return false;
		}

		foreach ( self::get_legacy_roots() as $root_dir ) {
			$proxy_path = $root_dir . '/services/nginx-proxy/logs/*.log';

			if ( false !== strpos( $contents, $proxy_path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return root paths used by current and legacy EE logrotate configs.
	 *
	 * @return array
	 */
	private static function get_legacy_roots() {

		$roots = [ rtrim( EE_ROOT_DIR, '/' ) ];

		if ( '/opt/easyengine' !== $roots[0] ) {
			$roots[] = '/opt/easyengine';
		}

		return $roots;
	}

	/**
	 * Return the EasyEngine CLI logs config.
	 *
	 * @return string
	 */
	private static function get_ee_log_config() {

		$root_dir    = rtrim( EE_ROOT_DIR, '/' );
		$max_size    = self::EE_LOG_MAX_SIZE;
		$date_format = self::DATE_FORMAT;

		return <<<LOGROTATE
{$root_dir}/logs/*.log {
    daily
    maxsize {$max_size}
    dateformat {$date_format}
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    copytruncate
    sharedscripts
}
LOGROTATE
		. PHP_EOL;
	}

	/**
	 * Return the site logs config.
	 *
	 * @return string
	 */
	private static function get_sites_config() {

		$root_dir    = rtrim( EE_ROOT_DIR, '/' );
		$max_size    = self::EE_LOG_MAX_SIZE;
		$date_format = self::DATE_FORMAT;

		return <<<LOGROTATE
{$root_dir}/sites/*/logs/nginx/*.log
{$root_dir}/sites/*/logs/php/*.log {
    daily
    maxsize {$max_size}
    dateformat {$date_format}
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    copytruncate
    sharedscripts
}
LOGROTATE
		. PHP_EOL;
	}

	/**
	 * Return the nginx-proxy logs config.
	 *
	 * @return string
	 */
	private static function get_nginx_proxy_config() {

		$root_dir    = rtrim( EE_ROOT_DIR, '/' );
		$max_size    = self::EE_LOG_MAX_SIZE;
		$date_format = self::DATE_FORMAT;

		return <<<LOGROTATE
{$root_dir}/services/nginx-proxy/logs/*.log {
    daily
    maxsize {$max_size}
    dateformat {$date_format}
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    copytruncate
    sharedscripts
}
LOGROTATE
		. PHP_EOL;
	}
}
