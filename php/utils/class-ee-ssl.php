<?php

class EE_Ssl {

	/**
	 * Get ssl certification expiration remaining days.
	 *
	 * @param string $domain_name
	 * @param bool   $return_on_error
	 *
	 * @return float|int
	 */
	public static function get_expiration_days( $domain_name, $return_on_error = false ) {
		if ( ! ee_file_exists( "/etc/letsencrypt/live/{$domain_name}/cert.pem" ) ) {
			EE::error( "File Not Found : /etc/letsencrypt/live/{$domain_name}/cert.pem" );
			if ( $return_on_error ) {
				return - 1;
			}
			EE::error( "Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
		}
		$current_date    = EE::exec_cmd_output( "date -d \"now\" +%s" );
		$expiration_date = EE::exec_cmd_output( "date -d \"`openssl x509 -in /etc/letsencrypt/live/{$domain_name}/cert.pem" .
		                                        " -text -noout|grep \"Not After\"|cut -c 25-`" );

		$days_left = ( ( intval( $expiration_date ) - intval( $current_date ) ) / 86400 );
		if ( $days_left > 0 ) {
			return $days_left;
		} else {
			return - 1;
		}
	}

	/**
	 * Get ssl expiration date of domain.
	 *
	 * @param $domain_name
	 *
	 * @return string
	 */
	public static function get_expiration_date( $domain_name ) {
		if ( ! ee_file_exists( "/etc/letsencrypt/live/{$domain_name}/cert.pem" ) ) {
			EE::error( "File Not Found : /etc/letsencrypt/live/{$domain_name}/cert.pem" );
			EE::error( "Check logs for reason `tail /var/log/ee/ee.log` & Try Again!!!" );
		}
		$expiration_date = EE::exec_cmd_output( "date -d \"`openssl x509 -in /etc/letsencrypt/live/{$domain_name}/cert.pem" .
		                                        " -text -noout|grep \"Not After\"|cut -c 25-`" );

		return $expiration_date;
	}

}