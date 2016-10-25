<?php

class EE_Service {

	/**
	 * Start service
	 * Similar to `service xyz start`
	 *
	 * @param $service_name
	 */
	public static function start_service( $service_name ) {
		try {
			$service_cmd = 'service ' . $service_name . ' start';
			if ( in_array( $service_name, array( 'nginx', 'php5-fpm' ) ) ) {
				$service_cmd = $service_name . ' -t && service ' . $service_name . ' start';
			}

			EE::log( 'Start : ' . $service_name );
			$service_start = \EE::exec_cmd( $service_cmd, 'Service start ' . $service_name );
			if ( 0 == $service_start ) {
				EE::success( '[OK]' );
			} else {
				EE::error( '[Failed]' );
			}
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::log( 'Failed to start service ' . $service_name );
		}
	}

	/**
	 * Stop service
	 * Similar to `service xyz stop`
	 *
	 * @param $service_name
	 */
	public static function stop_service( $service_name ) {
		try {
			$service_cmd = 'service ' . $service_name . ' stop';
			EE::log( 'Stop : ' . $service_name );
			$service_start = \EE::exec_cmd( $service_cmd, 'Service stop ' . $service_name );
			if ( 0 == $service_start ) {
				EE::success( '[OK]' );
			} else {
				EE::error( '[Failed]' );
			}
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::log( 'Failed to stop service ' . $service_name );
		}
	}

	/**
	 * Restart service
	 * Similar to `service xyz restart`
	 *
	 * @param $service_name
	 */
	public static function restart_service( $service_name ) {
		try {
			$service_cmd = 'service ' . $service_name . ' restart';
			if ( in_array( $service_name, array( 'nginx', 'php5-fpm' ) ) ) {
				$service_cmd = $service_name . ' -t && service ' . $service_name . ' restart';
			}

			EE::log( 'Restart : ' . $service_name );
			$service_start = \EE::exec_cmd( $service_cmd, 'Service Restart ' . $service_name );
			if ( 0 == $service_start ) {
				EE::success( '[OK]' );
			} else {
				EE::error( '[Failed]' );
			}
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::log( 'Failed to restart service ' . $service_name );
		}
	}

	/**
	 * Reload service
	 * Similar to `service xyz reload`
	 *
	 * @param $service_name
	 *
	 * @return bool
	 */
	public static function reload_service( $service_name ) {
		try {
			$service_cmd = 'service ' . $service_name . ' reload';
			if ( in_array( $service_name, array( 'nginx', 'php5-fpm' ) ) ) {
				$service_cmd = $service_name . ' -t && service ' . $service_name . ' reload';
			}

			EE::log( 'Reload : ' . $service_name );
			$service_start = \EE::exec_cmd( $service_cmd, 'Service Reload ' . $service_name );
			if ( 0 == $service_start ) {
				EE::success( '[OK]' );
				return true;
			} else {
				EE::error( '[Failed]' );
			}
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::log( 'Failed to reload service ' . $service_name );
		}
		return false;
	}

	/**
	 * Get service status.
	 * Similar to `service xyz status`
	 *
	 * @param $service_name
	 *
	 * @return bool
	 */
	public static function get_service_status( $service_name ) {
		try {
			$is_exist = EE::exec_cmd( 'which ' . $service_name );
			if ( 0 == $is_exist && in_array( $service_name, array( 'php7.0-fpm', 'php5.6-fpm' ) ) ) {
				$service_cmd    = 'service ' . $service_name . ' status';
				$service_status = EE::exec_cmd( $service_cmd );
				if ( 0 == $service_status ) {
					return true;
				}
			}

			return false;
		} catch ( Exception $e ) {
			EE::debug( $e->getMessage() );
			EE::log( 'Unable to get services status of ' . $service_name );

			return false;
		}
	}

}