<?php

$steps->Then(
	"/^STDOUT should return (something like|exactly)$/",
	function ( $world, $condition, $string ) {
		if ( "exactly" === $condition ) {
			if ( (string) $string !== $world->output ) {
				throw new Exception(
					"Actual output is:\n" . $world->output
				);
			}
		} else if ( "something like" === $condition ) {
			if ( strpos( $world->output, (string) $string ) !== false ) {
				throw new Exception( "Actual output is : " . $world->output );
			}
		}
	}
);


$steps->Then(
	'/^The \'([^\']*)\' should have webroot$/', function ( $world, $site ) {
	if ( is_dir( $_SERVER['HOME'] . "/Sites/" . $site ) ) {
		if ( ! file_exists( $_SERVER['HOME'] . "/Sites/" . $site . "/app/src/wp-config.php" ) ) {
			throw new Exception( "WordPress data not found!" );
		}
	} else {
		throw new Exception( "Site root not created!" );
	}
}
);

$steps->Then(
	'/^The \'([^\']*)\' should have tables$/', function ( $world, $site ) {
	if ( is_dir( $_SERVER['HOME'] . "/Sites/" . $site ) ) {
		exec( "bin/ee wp $site db check", $output );
		if ( strpos( $world->output, 'Success' ) === false ) {
			throw new Exception( "WordPress db check failed!" );
		}
	}
}
);

$steps->Then(
	'/^The \'([^\']*)\' containers should be removed$/', function ( $world, $site ) {
	$containers = array( 'php', 'nginx', 'db', 'mail' );
	$base_name  = implode( '', explode( '.', $site ) );

	foreach ( $containers as $container ) {
		$container_name = $base_name . '_' . $container . '_1';
		exec( "docker inspect -f '{{.State.Running}}' $container_name > /dev/null 2>&1", $exec_out, $return );
		if ( ! $return ) {
			throw new Exception( "$container_name has not been removed!" );
		}
	}
}
);

$steps->Then(
	'/^The \'([^\']*)\' webroot should be removed$/', function ( $world, $site ) {
	if ( file_exists( $_SERVER['HOME'] . "/Sites/" . $site ) ) {
		throw new Exception( "Webroot has not been removed!" );
	}
}
);

$steps->Then(
	'/^The \'([^\']*)\' db entry should be removed$/', function ( $world, $site ) {
	$out = shell_exec( "bin/ee site list" );
	if ( strpos( $out, $site ) !== false ) {
		throw new Exception( "$site db entry not been removed!" );
	}
}
);
