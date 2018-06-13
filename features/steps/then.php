<?php

$steps->Then(
	"/^STDOUT should return (something like|exactly)$/", function ( $world, $condition, $string ) {
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
	'/^Request on \'([^\']*)\' should contain following headers:$/', function ( $world, $site, $table ) {
	$url = 'http://' . $site;

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL,$url );
	curl_setopt( $ch, CURLOPT_HEADER, true );
	curl_setopt( $ch, CURLOPT_NOBODY, true );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	$headers = curl_exec( $ch );
	curl_close ($ch);

	$rows = $table->getHash();

	foreach ( $rows as $row ) {
		if( strpos( $headers, $row['header'] ) === false ) {
			throw new Exception( "Unable to find ". $row['header'] ."\nActual output is : " . $headers );
		}
	}
}
);


$steps->Then(
	'/^The site \'([^\']*)\' should have webroot$/', function ( $world, $site ) {
	if ( ! is_dir( getenv('HOME') . "/ee-sites/" . $site ) ) {
		throw new Exception( "Site root not created!" );
	}
}
);

$steps->Then(
	'/^The site \'([^\']*)\' should have WordPress$/', function ( $world, $site ) {
	if ( ! file_exists( getenv('HOME') . "/ee-sites/" . $site . "/app/src/wp-config.php" ) ) {
		throw new Exception( "WordPress data not found!" );
	}
}
);

// $steps->Then(
// 	'/^The \'([^\']*)\' containers should be removed$/', function ( $world, $site ) {
// 	$containers = array( 'php', 'nginx', 'db', 'mail' );
// 	$base_name  = implode( '', explode( '.', $site ) );

// 	foreach ( $containers as $container ) {
// 		$container_name = $base_name . '_' . $container . '_1';
// 		exec( "docker inspect -f '{{.State.Running}}' $container_name > /dev/null 2>&1", $exec_out, $return );
// 		if ( ! $return ) {
// 			throw new Exception( "$container_name has not been removed!" );
// 		}
// 	}
// }
// );

$steps->Then(
	'/^Following containers of site \'([^\']*)\' should be removed:$/', function ( $world, $site, $table ) {

	$containers = $table->getHash();

	foreach ( $containers as $container ) {
		$container_name = 'hellotest_' . $container . '_1';
		exec( "docker inspect -f '{{.State.Running}}' $container_name > /dev/null 2>&1", $exec_out, $return );
		if ( ! $return ) {
			throw new Exception( "$container_name has not been removed!" );
		}
	}
}
);

$steps->Then(
	'/^The \'([^\']*)\' webroot should be removed$/', function ( $world, $site ) {
	if ( file_exists( getenv('HOME') . "/ee-sites/" . $site ) ) {
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
