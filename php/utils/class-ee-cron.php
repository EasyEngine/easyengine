<?php

/**
 * Set CRON on LINUX system.
 *
 * Class EE_Cron
 */
class EE_Cron {

	public static function set_cron_weekly( $cmd, $comment = 'Cron set by EasyEngine', $user = 'root' ) {
		$grep_cron = EE::exec_cmd( "crontab -l | grep -q '{$cmd}'" );
		if ( 0 !== $grep_cron ) {
			EE::exec_cmd( "/bin/bash -c \"crontab -l " .
			              "2> /dev/null | {{ cat; echo -e" .
			              " \\\"" .
			              "\\n0 0 * * 0 " .
			              "{$cmd}" .
			              " # {$comment}" .
			              "\\\"; } | crontab -\"" );
			EE::debug("Cron set");
		}
	}

	public static function remove_cron( $cmd ) {
		$grep_cron = EE::exec_cmd( "crontab -l | grep -q '{$cmd}'" );
		if ( 0 === $grep_cron ) {
			$remove_cron = EE::exec_cmd("/bin/bash -c " .
                                                    "\"crontab " .
                                                    "-l | sed '/{$cmd}/d'" .
                                                    "| crontab -\"");
			if ( 0 !== $remove_cron ) {
				EE::error( "Failed to remove crontab entry" );
			}
		} else {
			EE::log( "Cron not found" );
		}
	}
}