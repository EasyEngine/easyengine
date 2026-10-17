<?php
/**
 * Behat tests.
 *
 * @package ee-cli
 */

/* Start: Loading required files to enable EE::launch() in tests. */
define( 'EE_ROOT', __DIR__ . '/../..' );

include_once( EE_ROOT . '/php/class-ee.php' );
include_once( EE_ROOT . '/php/EE/Runner.php' );
include_once( EE_ROOT . '/php/utils.php' );

define( 'EE', true );
define( 'EE_VERSION', trim( file_get_contents( EE_ROOT . '/VERSION' ) ) );

$root_alias = '/opt';
if ( 'Darwin' === php_uname( 's' ) ) {
	$sys_name   = posix_getpwuid(posix_geteuid())['name'];
	if ( ! empty( $sys_name ) ) {
		$root_alias = '/Users/' . $sys_name;
	}
}
// site-command's autoloaded site-command.php may already define it.
if ( ! defined( 'EE_ROOT_DIR' ) ) {
	define( 'EE_ROOT_DIR', $root_alias . '/easyengine' );
}

require_once EE_ROOT . '/php/bootstrap.php';

if ( ! class_exists( 'EE\Runner' ) ) {
	require_once EE_ROOT . '/php/EE/Runner.php';
}

if ( ! class_exists( 'EE\Configurator' ) ) {
	require_once EE_ROOT . '/php/EE/Configurator.php';
}

$logger_dir = EE_ROOT . '/php/EE/Loggers';
$iterator   = new \DirectoryIterator( $logger_dir );

// Make sure the base class is declared first.
include_once "$logger_dir/Base.php";

foreach ( $iterator as $filename ) {
	if ( '.php' !== pathinfo( $filename, PATHINFO_EXTENSION ) ) {
		continue;
	}

	include_once "$logger_dir/$filename";
}
$runner = \EE::get_runner();
$runner->init_logger();
$runner->init_config();
/* End. Loading required files to enable EE::launch() in tests. */

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterFeatureScope;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;

use Behat\Gherkin\Node\PyStringNode,
	Behat\Gherkin\Node\TableNode;

define( 'EE_SITE_ROOT', EE_ROOT_DIR . '/sites' );

class FeatureContext implements Context
{
	public $commands;
	public $webroot_path;
	public $ee_path;

	/**
	 * Prefix of every site this suite may create; cleanup never touches other sites.
	 */
	const SITE_PREFIX = 'bh-easyengine-';

	/**
	 * Base directory of the per-scenario scratch directories ({TMP}).
	 */
	const TMP_BASE = '/var/tmp/ee-behat-core';

	/**
	 * Tags that change the live EasyEngine state, each with the env var that must be set to run them.
	 * A CLI --tags replaces the behat.yml filters, so the tags alone can't keep these scenarios off.
	 */
	const GUARDED_TAGS = [
		'destructive' => 'EE_BEHAT_DESTRUCTIVE',
		'migration'   => 'EE_BEHAT_ALLOW_MIGRATION',
		'uninstall'   => 'EE_BEHAT_ALLOW_UNINSTALL',
	];

	/**
	 * Phar built by "ee phar is generated", shared by the whole run ({PHAR}).
	 *
	 * @var string|null
	 */
	private static $phar;

	/**
	 * Whether the shutdown/signal restore of changed options is registered.
	 *
	 * @var bool
	 */
	private $restore_registered = false;

	/**
	 * Per-scenario scratch directory, created on first use of {TMP}.
	 *
	 * @var string|null
	 */
	private $tmp_dir;

	/**
	 * Per-scenario random token ({RAND}).
	 *
	 * @var string|null
	 */
	private $rand;

	/**
	 * Original values of EasyEngine options changed by a scenario, restored afterwards.
	 *
	 * @var array
	 */
	private $saved_options = [];

	/**
	 * Container counts recorded by "I record the number of containers with label".
	 *
	 * @var array
	 */
	private $container_counts = [];

	/**
	 * Core container migrations written by a scenario (file => created its directory), removed afterwards.
	 *
	 * @var array
	 */
	private $probe_migrations = [];

	/**
	 * /etc/hosts before "Docker Hub is unreachable", restored afterwards.
	 *
	 * @var string|null
	 */
	private static $hosts_backup;

	/**
	 * Initializes context.
	 */
	public function __construct()
	{
		$this->commands = [];
		$this->ee_path = getcwd();
	}

	/**
	 * @Given ee phar is generated
	 */
	public function eePharIsPresent()
	{
		// Building takes about a minute, so the phar is shared by the whole run.
		if ( self::$phar && file_exists( self::$phar ) ) {
			return;
		}
		// Build from a private copy with a --no-dev autoloader: a phar built on a dev vendor requires
		// require-dev files it doesn't contain. The checkout and /usr/local/bin/ee are never touched.
		$root = realpath( EE_ROOT );
		$dir  = self::TMP_BASE . '/phar-' . getmypid();
		$src  = "$dir/src";
		$phar = "$dir/ee.phar";
		$cmd  = sprintf(
			'rm -rf %1$s && mkdir -p %1$s && tar -C %2$s --exclude=.git --exclude=./features --exclude="./*.phar" -cf - . | tar -C %1$s -xf -'
			. ' && cd %1$s && COMPOSER_ALLOW_SUPERUSER=1 %3$scomposer dump-autoload --no-dev -q'
			. ' && %4$s -dphar.readonly=0 utils/make-phar.php %5$s --quiet',
			escapeshellarg( $src ),
			escapeshellarg( $root ),
			file_exists( "$root/composer.dev.json" ) ? 'COMPOSER=composer.dev.json ' : '',
			escapeshellarg( PHP_BINARY ),
			escapeshellarg( $phar )
		);
		exec( "( $cmd ) 2>&1", $output, $status );
		exec( 'rm -rf ' . escapeshellarg( $src ) );
		if ( 0 !== $status || ! file_exists( $phar ) ) {
			throw new Exception( "Unable to generate the phar (exit $status):\n" . implode( "\n", $output ) );
		}
		self::$phar = $phar;
	}

	/**
	 * @Given :command is installed
	 */
	public function isInstalled($command)
	{
		exec("type " . $command, $output, $return_status);
		if (0 !== $return_status) {
			throw new Exception($command . " is not installed! Exit code is:" . $return_status);
		}
	}

	/**
	 * @When /^I run (?|'(.*)'|"(.*)")$/
	 */
	public function iRun($command)
	{
		// Not EE::launch(): it would log the command line to ee.log, polluting log assertions.
		$this->commands[] = \EE\Process::create( $this->replace_variables( $command ), null, null )->run();
	}

	/**
	 * @When I create subsite :subsite in :site
	 */
	public function iCreateSubsiteInOfType($subsite, $site )
	{
		$php_container = implode( explode( '.', $subsite ) ) . '_php_1';

		EE::launch( "docker exec --user='www-data' $php_container sh -c 'wp site create --slug=$site'", false, true );
	}

	/**
	 * @Then return value should be 0
	 */
	public function returnValueShouldBe0()
	{
		if ( 0 !== $this->commands[0]->return_code ) {
			throw new Exception("Actual return code is not zero: \n" . $this->commands[0]);
		}
	}

	/**
	 * @Then return value of command :index should be 0
	 */
	public function returnValueOfCommandShouldBe0($index)
	{
		if ( 0 !== $this->commands[ $index - 1 ]->return_code ) {
			throw new Exception("Actual return code is not zero: \n" . $this->commands[ $index - 1 ]);
		}
	}

	/**
	 * @Then After delay of :time seconds
	 */
	public function afterDelayOfSeconds( $time )
	{
		sleep( $time );
	}

	/**
	 * @Then /^(STDOUT|STDERR) should return exactly$/
	 */
	public function stdoutShouldReturnExactly($output_stream, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[0]->stdout : $this->commands[0]->stderr;

		$command_output = str_replace(["\033[1;31m","\033[0m"],'',$command_output);

		if ( $this->replace_variables( $expected_output->getRaw() ) !== trim($command_output)) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) of command (\d+) should return exactly$/
	 */
	public function stdoutOfCommandShouldReturnExactly($output_stream, $index, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[ $index - 1 ]->stdout : $this->commands[ $index - 1 ]->stderr;

		$command_output = str_replace(["\033[1;31m","\033[0m"],'',$command_output);

		if ( $this->replace_variables( $expected_output->getRaw() ) !== trim($command_output)) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should return something like$/
	 */
	public function stdoutShouldReturnSomethingLike($output_stream, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[0]->stdout : $this->commands[0]->stderr;

		$expected_out = $this->replace_variables( $expected_output->getRaw() );
		if (strpos($command_output, $expected_out) === false) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}


	/**
	 * @Then /^(STDOUT|STDERR) of command (\d+) should return something like$/
	 */
	public function stdoutOfCommandShouldReturnSomethingLike($output_stream, $index, PyStringNode $expected_output)
	{
		$command_output = $output_stream === "STDOUT" ? $this->commands[ $index - 1 ]->stdout : $this->commands[ $index - 1 ]->stderr;

		$expected_out = $this->replace_variables( $expected_output->getRaw() );
		if (strpos($command_output, $expected_out) === false) {
			throw new Exception("Actual output is:\n" . $command_output);
		}
	}

	/**
	 * @Then The site :site should be :type multisite
	 */
	public function theSiteShouldBeMultisite( $site, $type )
	{
		$result = EE::launch( "cd " . EE_SITE_ROOT . "/$site && docker-compose exec -T --user='www-data' php sh -c 'wp config get SUBDOMAIN_INSTALL'", false, true );

		if( $result->stderr ) {
			throw new Exception("Found error while executing command: $result->stderr");
		}

		if($type === 'subdomain' && trim($result->stdout) !== '1') {
			throw new Exception("Expecting SUBDOMAIN_INSTALL to be 1. Got: $result->stdout");
		}
		else if($type === 'subdir' && trim($result->stdout) !== '') {
			throw new Exception("Expecting SUBDOMAIN_INSTALL to be empty. Got: $result->stdout");
		}
		else if($type !== 'subdomain' && $type !== 'subdir') {
			throw new Exception("Found unknown site type: $type");
		}
	}

	/**
	 * @Then The :site db entry should be removed
	 */
	public function theDbEntryShouldBeRemoved($site)
	{
		$out = shell_exec("sudo bin/ee site list");
		if (strpos($out, $site) !== false) {
			throw new Exception("$site db entry not been removed!");
		}

	}

	/**
	 * @Then The :site webroot should be removed
	 */
	public function theWebrootShouldBeRemoved($site)
	{
		if ( file_exists( EE_SITE_ROOT . '/' . $site ) ) {
			throw new Exception("Webroot has not been removed!");
		}
	}

	/**
	 * @Then Following containers of site :site should be removed:
	 */
	public function followingContainersOfSiteShouldBeRemoved($site, TableNode $table)
	{
		$containers = $table->getHash();
		$site_name = implode(explode('.', $site));

		foreach ($containers as $container) {

			$sevice = $container['container'];
			$container_name = $site_name . '_' . $sevice . '_1';

			exec("docker inspect -f '{{.State.Running}}' $container_name > /dev/null 2>&1", $exec_out, $return);
			if (!$return) {
				throw new Exception("$container_name has not been removed!");
			}
		}
	}

	/**
	 * @Then The site :site should have webroot
	 */
	public function theSiteShouldHaveWebroot($site)
	{
		if ( ! file_exists( EE_SITE_ROOT . '/' . $site ) ) {
			throw new Exception("Webroot has not been created!");
		}
	}

	/**
	 * @Then The site :site should have WordPress
	 */
	public function theSiteShouldHaveWordpress($site)
	{
		if ( ! file_exists( EE_SITE_ROOT . '/' . $site . '/app/wp-config.php' ) ) {
			throw new Exception("WordPress data not found!");
		}
	}

	/**
	 * @Then Request on :site should contain following headers:
	 */
	public function requestOnShouldContainFollowingHeaders($site, TableNode $table)
	{
		$url = 'http://' . $site;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$headers = curl_exec($ch);

		curl_close($ch);

		$rows = $table->getHash();

		foreach ($rows as $row) {
			if (strpos($headers, $row['header']) === false) {
				throw new Exception("Unable to find " . $row['header'] . "\nActual output is : " . $headers);
			}
		}
	}

	/**
	 * @Then Request on :host with header :header should contain following headers:
	 */
	public function requestOnWithHeaderShouldContainFollowingHeaders($host, $header, TableNode $table)
	{

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $host);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [ $header ]);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$headers = curl_exec($ch);

		curl_close($ch);

		$rows = $table->getHash();

		foreach ($rows as $row) {
			if (strpos($headers, $row['header']) === false) {
				throw new Exception("Unable to find " . $row['header'] . "\nActual output is : " . $headers);
			}
		}
	}

	/**
	 * @Then Request on :host with resolve option :resolve should contain following headers:
	 */
	public function requestOnWithResolveOptionShouldContainFollowingHeaders($host, $resolve, TableNode $table)
	{

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $host);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RESOLVE, [ $resolve ]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$headers = curl_exec($ch);
		curl_close($ch);

		$rows = $table->getHash();

		foreach ($rows as $row) {
			if (strpos($headers, $row['header']) === false) {
				throw new Exception("Unable to find " . $row['header'] . "\nActual output is : " . $headers);
			}
		}
	}

	/**
	 * @Then There should be :expected_running_containers containers with labels
	 */
	public function thereShouldBeContainersWithLabel($expected_running_containers, PyStringNode $pyStringNode)
	{
		$labels = $pyStringNode->getStrings();
		$label_string = implode(' -f label=',$labels);

		$result = EE::launch( "docker ps -qf label=$label_string | wc -l", false, true );
		$running_containers = (int) trim($result->stdout);

		if((int) $expected_running_containers !== $running_containers) {
			throw new Exception("Expected $expected_running_containers running containers. Found: $running_containers");
		}
	}

	/**
	 * @Then ee should be deleted
	 */
	public function eeShouldBeDeleted()
	{
		$result = EE::launch("docker ps -aqf label=org.label-schema.vendor=\"EasyEngine\" | wc -l", false, true);
		if( trim($result->stdout) !== '0' ) {
			throw new Exception("All containers have not been removed.");
		}
		foreach ( [ 'network', 'volume', 'image' ] as $object ) {
			$left = trim( EE::launch( "docker $object ls -q -f label=org.label-schema.vendor=EasyEngine | wc -l", false, true )->stdout );
			if ( '0' !== $left ) {
				throw new Exception( "$left EasyEngine docker {$object}(s) have not been removed." );
			}
		}
		$home = getenv('HOME');
		if(file_exists("$home/.ee/")){
			throw new Exception("~/.ee/ has not been removed");
		}
		if(file_exists("$home/ee-sites/")){
			throw new Exception("~/ee-sites/ has not been removed");
		}
		if(file_exists('/opt/easyengine/')){
			throw new Exception("/opt/easyengine/ has not been removed");
		}
	}

	/*
	 * ---------------------------------------------------------------------
	 * Shared generic steps (easyengine core suite).
	 *
	 * Output steps act on the LAST command run in the scenario. Step strings
	 * support the placeholders {TMP} (per-scenario scratch dir under
	 * /var/tmp), {EE} (absolute bin/ee), {EE_ROOT}, {EE_ROOT_DIR},
	 * {EE_VERSION}, {PHP} (PHP binary running Behat), {PHAR} (the built phar) and {RAND}.
	 * ---------------------------------------------------------------------
	 */

	/**
	 * @Then the command should exit with code :code
	 */
	public function theCommandShouldExitWithCode( $code )
	{
		$last = $this->last_command();
		if ( (int) $code !== $last->return_code ) {
			throw new Exception( "Expected exit code $code, got {$last->return_code}.\n" . $this->describe( $last ) );
		}
	}

	/**
	 * @Then the command should succeed
	 */
	public function theCommandShouldSucceed()
	{
		$this->theCommandShouldExitWithCode( 0 );
	}

	/**
	 * @Then the command should fail
	 */
	public function theCommandShouldFail()
	{
		$last = $this->last_command();
		if ( 0 === $last->return_code ) {
			throw new Exception( "Expected a non-zero exit code, got 0.\n" . $this->describe( $last ) );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should ((?:not )?)contain "(.*)"$/
	 */
	public function streamShouldContainText( $stream, $negate, $text )
	{
		$this->assert_contains( $stream, $this->replace_variables( $text ), '' !== $negate );
	}

	/**
	 * @Then /^(STDOUT|STDERR) should ((?:not )?)contain:$/
	 */
	public function streamShouldContainBlock( $stream, $negate, PyStringNode $text )
	{
		$this->assert_contains( $stream, $this->replace_variables( $text->getRaw() ), '' !== $negate );
	}

	/**
	 * @Then /^(STDOUT|STDERR) should be:$/
	 */
	public function streamShouldBe( $stream, PyStringNode $expected )
	{
		$actual   = trim( $this->stream_output( $stream ) );
		$expected = trim( $this->replace_variables( $expected->getRaw() ) );
		if ( $expected !== $actual ) {
			throw new Exception( "$stream is not exactly:\n$expected\n\n" . $this->describe( $this->last_command() ) );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should be empty$/
	 */
	public function streamShouldBeEmpty( $stream )
	{
		if ( '' !== trim( $this->stream_output( $stream ) ) ) {
			throw new Exception( "$stream is not empty.\n" . $this->describe( $this->last_command() ) );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should ((?:not )?)match "(.*)"$/
	 */
	public function streamShouldMatch( $stream, $negate, $regex )
	{
		$regex   = $this->replace_variables( $regex );
		$matched = preg_match( $regex, $this->stream_output( $stream ) );
		if ( false === $matched ) {
			throw new Exception( "Invalid regex: $regex" );
		}
		if ( ( '' === $negate ) !== ( 1 === $matched ) ) {
			throw new Exception( "$stream should " . $negate . "match $regex\n" . $this->describe( $this->last_command() ) );
		}
	}

	/**
	 * @Then /^(STDOUT|STDERR) should ((?:not )?)contain ANSI color codes$/
	 */
	public function streamShouldContainAnsiCodes( $stream, $negate )
	{
		$has_codes = 1 === preg_match( '/\x1b\[[0-9;]*m/', $this->stream_output( $stream ) );
		if ( ( '' === $negate ) !== $has_codes ) {
			throw new Exception( "$stream should " . $negate . "contain ANSI color codes.\n" . $this->describe( $this->last_command(), true ) );
		}
	}

	/**
	 * @Then STDOUT of command :first should be identical to STDOUT of command :second
	 */
	public function stdoutOfCommandsShouldBeIdentical( $first, $second )
	{
		$a = $this->command_at( $first );
		$b = $this->command_at( $second );
		if ( $a->stdout !== $b->stdout ) {
			throw new Exception( "STDOUT differs.\nCommand $first:\n" . $this->describe( $a ) . "\nCommand $second:\n" . $this->describe( $b ) );
		}
	}

	/**
	 * @Then STDOUT should be valid json
	 */
	public function stdoutShouldBeValidJson()
	{
		$this->decoded_stdout();
	}

	/**
	 * @Then STDOUT should be empty or valid json
	 */
	public function stdoutShouldBeEmptyOrValidJson()
	{
		if ( '' !== trim( $this->last_command()->stdout ) ) {
			$this->decoded_stdout();
		}
	}

	/**
	 * @Then the json in STDOUT should have the keys :keys
	 */
	public function theJsonShouldHaveTheKeys( $keys )
	{
		$this->theJsonAtShouldHaveTheKeys( '', $keys );
	}

	/**
	 * @Then the json in STDOUT at :path should have the keys :keys
	 */
	public function theJsonAtShouldHaveTheKeys( $path, $keys )
	{
		$node    = $this->single_json_node( $path );
		$missing = [];
		foreach ( array_map( 'trim', explode( ',', $this->replace_variables( $keys ) ) ) as $key ) {
			if ( ! is_array( $node ) || ! array_key_exists( $key, $node ) ) {
				$missing[] = $key;
			}
		}
		if ( $missing ) {
			throw new Exception( 'Missing keys at "' . $path . '": ' . implode( ', ', $missing ) . "\n" . $this->describe( $this->last_command() ) );
		}
	}

	/**
	 * @Then the json in STDOUT at :path should be :value
	 */
	public function theJsonAtShouldBe( $path, $value )
	{
		$actual   = self::json_scalar( $this->single_json_node( $path ) );
		$expected = $this->replace_variables( $value );
		if ( $expected !== $actual ) {
			throw new Exception( "Expected \"$path\" to be \"$expected\", got \"$actual\".\n" . $this->describe( $this->last_command() ) );
		}
	}

	/**
	 * @Then the json in STDOUT at :path should match :regex
	 */
	public function theJsonAtShouldMatch( $path, $regex )
	{
		$actual = self::json_scalar( $this->single_json_node( $path ) );
		if ( 1 !== preg_match( $this->replace_variables( $regex ), $actual ) ) {
			throw new Exception( "Expected \"$path\" to match $regex, got \"$actual\".\n" . $this->describe( $this->last_command() ) );
		}
	}

	/**
	 * @Then /^the json in STDOUT at "([^"]*)" should ((?:not )?)contain "([^"]*)"$/
	 */
	public function theJsonAtShouldContain( $path, $negate, $value )
	{
		$nodes = $this->json_nodes( $path );
		// A single array node means "this list contains"; several nodes come from a "*" path.
		if ( 1 === count( $nodes ) && is_array( $nodes[0] ) ) {
			$nodes = array_values( $nodes[0] );
		}
		$value  = $this->replace_variables( $value );
		$values = array_map( array( __CLASS__, 'json_scalar' ), $nodes );
		if ( ( '' === $negate ) !== in_array( $value, $values, true ) ) {
			throw new Exception( "Expected \"$path\" to " . $negate . "contain \"$value\". Values: " . implode( ', ', $values ) );
		}
	}

	/**
	 * @Given a file :path with:
	 */
	public function aFileWith( $path, PyStringNode $content )
	{
		$path = $this->replace_variables( $path );
		if ( ! is_dir( dirname( $path ) ) ) {
			mkdir( dirname( $path ), 0755, true );
		}
		if ( false === file_put_contents( $path, $this->replace_variables( $content->getRaw() ) . "\n" ) ) {
			throw new Exception( "Could not write $path" );
		}
	}

	/**
	 * @Given the directory :path exists
	 */
	public function theDirectoryExists( $path )
	{
		$path = $this->replace_variables( $path );
		if ( ! is_dir( $path ) && ! mkdir( $path, 0755, true ) ) {
			throw new Exception( "Could not create $path" );
		}
	}

	/**
	 * @Given the file :path does not exist
	 */
	public function theFileDoesNotExist( $path )
	{
		$path = $this->replace_variables( $path );
		if ( file_exists( $path ) && ! unlink( $path ) ) {
			throw new Exception( "Could not delete $path" );
		}
	}

	/**
	 * @Given the executable :name is linked into :dir
	 */
	public function theExecutableIsLinkedInto( $name, $dir )
	{
		$dir = $this->replace_variables( $dir );
		$this->theDirectoryExists( $dir );
		$binary = trim( (string) shell_exec( 'command -v ' . escapeshellarg( $name ) ) );
		if ( '' === $binary ) {
			throw new Exception( "$name is not installed." );
		}
		if ( ! file_exists( "$dir/$name" ) && ! symlink( $binary, "$dir/$name" ) ) {
			throw new Exception( "Could not link $binary into $dir" );
		}
	}

	/**
	 * @Then the file :path should exist
	 */
	public function theFileShouldExist( $path )
	{
		$path = $this->replace_variables( $path );
		if ( ! file_exists( $path ) ) {
			throw new Exception( "$path does not exist." );
		}
	}

	/**
	 * @Then the file :path should not exist
	 */
	public function theFileShouldNotExist( $path )
	{
		$path = $this->replace_variables( $path );
		if ( file_exists( $path ) ) {
			throw new Exception( "$path exists." );
		}
	}

	/**
	 * @Then the file :path should contain :text
	 */
	public function theFileShouldContain( $path, $text )
	{
		$this->assert_file_contains( $path, $text, false );
	}

	/**
	 * @Then the file :path should not contain :text
	 */
	public function theFileShouldNotContain( $path, $text )
	{
		$this->assert_file_contains( $path, $text, true );
	}

	/**
	 * @Given no EasyEngine migration is pending
	 */
	public function noEasyEngineMigrationIsPending()
	{
		// Commands that check requirements (e.g. `cli info`) would otherwise run a real migration.
		if ( false !== strpos( EE_VERSION, 'nightly' ) ) {
			throw new Exception( 'VERSION ' . EE_VERSION . ' is a nightly: every checked command would run the migration. Refusing to continue.' );
		}
		if ( ! file_exists( self::db_path() ) ) {
			if ( getenv( 'EE_BEHAT_ALLOW_INIT' ) ) {
				return;
			}
			throw new Exception( self::db_path() . ' does not exist: the next checked command would initialise EasyEngine. Set EE_BEHAT_ALLOW_INIT=1 to allow that.' );
		}
		$db_version = self::get_option( 'version' );
		if ( EE_VERSION !== $db_version ) {
			throw new Exception( "DB version '$db_version' differs from VERSION '" . EE_VERSION . "': a checked command would run the migration. Refusing to continue." );
		}
	}

	/**
	 * @Given the EasyEngine option :key is set to :value
	 */
	public function theEasyEngineOptionIsSetTo( $key, $value )
	{
		if ( ! array_key_exists( $key, $this->saved_options ) ) {
			$this->saved_options[ $key ] = self::get_option( $key );
		}
		if ( ! $this->restore_registered ) {
			// Also restore when the run dies mid-scenario (Behat's SIGINT handler exits, fatals, SIGTERM).
			register_shutdown_function( [ $this, 'restoreOptions' ] );
			if ( function_exists( 'pcntl_signal' ) ) {
				pcntl_async_signals( true );
				pcntl_signal( SIGTERM, function () {
					exit( 143 );
				} );
			}
			$this->restore_registered = true;
		}
		self::set_option( $key, $this->replace_variables( $value ) );
	}

	/**
	 * @Then the EasyEngine option :key should be :value
	 */
	public function theEasyEngineOptionShouldBe( $key, $value )
	{
		$actual   = self::get_option( $key );
		$expected = $this->replace_variables( $value );
		if ( $expected !== $actual ) {
			throw new Exception( "Expected option '$key' to be '$expected', got " . var_export( $actual, true ) );
		}
	}

	/**
	 * @Given I record the number of containers with label :label
	 */
	public function iRecordTheNumberOfContainersWithLabel( $label )
	{
		$this->container_counts[ $label ] = self::count_containers( $label );
	}

	/**
	 * @Then the number of containers with label :label should be unchanged
	 */
	public function theNumberOfContainersWithLabelShouldBeUnchanged( $label )
	{
		if ( ! isset( $this->container_counts[ $label ] ) ) {
			throw new Exception( "No recorded container count for $label." );
		}
		$now = self::count_containers( $label );
		if ( $now !== $this->container_counts[ $label ] ) {
			throw new Exception( "Containers with label $label: {$this->container_counts[ $label ]} before, $now now." );
		}
	}

	/**
	 * @Given the core container migration :file with class :class logs its up and down calls to :log
	 */
	public function theCoreContainerMigrationLogsTo( $file, $class, $log )
	{
		$dir     = EE_ROOT . '/migrations/container';
		$created = ! is_dir( $dir );
		if ( $created && ! mkdir( $dir, 0755, true ) ) {
			throw new Exception( "Could not create $dir" );
		}
		$this->probe_migrations[ $file ] = $created;
		$log  = var_export( $this->replace_variables( $log ), true );
		$code = "<?php\nnamespace EE\\Migration;\n\nclass $class extends Base {\n"
			. "\tpublic function up() { file_put_contents( $log, 'up,', FILE_APPEND ); }\n"
			. "\tpublic function down() { file_put_contents( $log, 'down,', FILE_APPEND ); }\n}\n";
		if ( false === file_put_contents( "$dir/$file", $code ) ) {
			throw new Exception( "Could not write $dir/$file" );
		}
	}

	/**
	 * @Then /^the container migration "([^"]*)" should ((?:not )?)be recorded$/
	 */
	public function theContainerMigrationShouldBeRecorded( $file, $negate )
	{
		$query = self::db()->prepare( "SELECT COUNT(*) FROM migrations WHERE migration = ? AND type = 'container'" );
		$query->execute( [ $file ] );
		$count = (int) $query->fetchColumn();
		if ( $negate ? 0 !== $count : 1 !== $count ) {
			throw new Exception( "Expected $file to " . ( $negate ? 'not ' : '' ) . "be recorded once, found $count rows." );
		}
	}

	/**
	 * @Given Docker Hub is unreachable
	 */
	public function dockerHubIsUnreachable()
	{
		if ( null === self::$hosts_backup ) {
			self::$hosts_backup = file_get_contents( '/etc/hosts' );
			register_shutdown_function( [ self::class, 'restoreHosts' ] );
		}
		file_put_contents( '/etc/hosts', self::$hosts_backup . "\n127.0.0.1 registry-1.docker.io auth.docker.io index.docker.io\n" );
		// dockerd's Go resolver re-reads /etc/hosts at most every 5 s.
		sleep( 6 );
		exec( 'timeout 60 docker pull -q busybox:1.36 2>&1', $out, $rc );
		if ( 0 === $rc ) {
			exec( 'docker rmi busybox:1.36' );
			self::restoreHosts();
			throw new Exception( 'Blocking Docker Hub in /etc/hosts had no effect (a registry mirror?).' );
		}
	}

	/**
	 * @When Docker Hub is reachable again
	 */
	public function dockerHubIsReachableAgain()
	{
		self::restoreHosts();
		sleep( 6 );
	}

	/**
	 * Puts back /etc/hosts saved by "Docker Hub is unreachable".
	 */
	public static function restoreHosts()
	{
		if ( null !== self::$hosts_backup ) {
			file_put_contents( '/etc/hosts', self::$hosts_backup );
			self::$hosts_backup = null;
		}
	}

	/**
	 * @BeforeScenario
	 */
	public function refuseGuardedScenarios( BeforeScenarioScope $scope )
	{
		$tags = array_merge( $scope->getFeature()->getTags(), $scope->getScenario()->getTags() );
		foreach ( self::GUARDED_TAGS as $tag => $env ) {
			if ( in_array( $tag, $tags, true ) && ! getenv( $env ) ) {
				throw new Exception( "Refusing to run a @$tag scenario: it changes the live EasyEngine state. Set $env=1 to allow it." );
			}
		}
	}

	/**
	 * Puts back the options changed by "the EasyEngine option :key is set to :value".
	 */
	public function restoreOptions()
	{
		foreach ( $this->saved_options as $key => $value ) {
			if ( false === $value ) {
				self::delete_option( $key );
			} else {
				self::set_option( $key, $value );
			}
		}
		$this->saved_options = [];
	}

	/**
	 * @AfterScenario
	 */
	public function cleanupScenario(AfterScenarioScope $scope)
	{
		self::restoreHosts();
		foreach ( $this->probe_migrations as $file => $created_dir ) {
			@unlink( EE_ROOT . "/migrations/container/$file" );
			if ( $created_dir ) {
				@rmdir( EE_ROOT . '/migrations/container' );
			}
			if ( file_exists( self::db_path() ) ) {
				self::db()->prepare( 'DELETE FROM migrations WHERE migration = ?' )->execute( [ $file ] );
			}
		}
		$this->probe_migrations = [];
		$this->restoreOptions();
		$this->container_counts = [];
		$this->commands         = [];
		$this->rand             = null;

		if ( $this->tmp_dir && 0 === strpos( $this->tmp_dir, self::TMP_BASE . '/' ) ) {
			exec( 'rm -rf ' . escapeshellarg( $this->tmp_dir ) );
		}
		$this->tmp_dir = null;

		chdir($this->ee_path);
	}

	/**
	 * @AfterFeature
	 */
	public static function cleanup(AfterFeatureScope $scope)
	{
		// Only this suite's sites, never every site on the host.
		foreach ( glob( EE_SITE_ROOT . '/' . self::SITE_PREFIX . '*', GLOB_ONLYDIR ) ?: [] as $site_dir ) {
			exec( 'bin/ee site delete ' . escapeshellarg( basename( $site_dir ) ) . ' --yes' );
		}

	}

	/**
	 * @AfterSuite
	 */
	public static function removePhar()
	{
		if ( self::$phar ) {
			exec( 'rm -rf ' . escapeshellarg( dirname( self::$phar ) ) );
			self::$phar = null;
		}
	}

	private function replace_variables( $string )
	{
		$map = [
			'{EE}'          => realpath( EE_ROOT ) . '/bin/ee',
			'{EE_ROOT_DIR}' => EE_ROOT_DIR,
			'{EE_ROOT}'     => realpath( EE_ROOT ),
			'{EE_VERSION}'  => EE_VERSION,
			'{PHP}'         => PHP_BINARY,
		];
		if ( false !== strpos( $string, '{TMP}' ) ) {
			$map['{TMP}'] = $this->tmp_dir();
		}
		if ( false !== strpos( $string, '{PHAR}' ) ) {
			if ( null === self::$phar ) {
				throw new Exception( '{PHAR} needs "Given ee phar is generated" first.' );
			}
			$map['{PHAR}'] = self::$phar;
		}
		if ( false !== strpos( $string, '{RAND}' ) ) {
			if ( null === $this->rand ) {
				$this->rand = bin2hex( random_bytes( 6 ) );
			}
			$map['{RAND}'] = $this->rand;
		}

		return strtr( $string, $map );
	}

	private function tmp_dir()
	{
		if ( null === $this->tmp_dir ) {
			$this->tmp_dir = self::TMP_BASE . '/' . uniqid( 'scenario-', true );
			if ( ! mkdir( $this->tmp_dir, 0755, true ) ) {
				throw new Exception( "Could not create {$this->tmp_dir}" );
			}
		}

		return $this->tmp_dir;
	}

	private function last_command()
	{
		if ( empty( $this->commands ) ) {
			throw new Exception( 'No command has been run in this scenario.' );
		}

		return end( $this->commands );
	}

	private function command_at( $index )
	{
		if ( ! isset( $this->commands[ $index - 1 ] ) ) {
			throw new Exception( "Command $index has not been run in this scenario." );
		}

		return $this->commands[ $index - 1 ];
	}

	private function stream_output( $stream )
	{
		$last = $this->last_command();

		return 'STDOUT' === $stream ? $last->stdout : $last->stderr;
	}

	private function describe( $run, $escape = false )
	{
		$show = function ( $text ) use ( $escape ) {
			return $escape ? addcslashes( $text, "\033" ) : $text;
		};

		return "Command: {$run->command}\nExit code: {$run->return_code}\nSTDOUT:\n" . $show( $run->stdout ) . "\nSTDERR:\n" . $show( $run->stderr );
	}

	private function assert_contains( $stream, $text, $negate )
	{
		$found = false !== strpos( $this->stream_output( $stream ), $text );
		if ( $found === $negate ) {
			throw new Exception( "$stream should " . ( $negate ? 'not ' : '' ) . "contain:\n$text\n\n" . $this->describe( $this->last_command() ) );
		}
	}

	private function assert_file_contains( $path, $text, $negate )
	{
		$path = $this->replace_variables( $path );
		$text = $this->replace_variables( $text );
		if ( ! is_readable( $path ) ) {
			throw new Exception( "$path is not readable." );
		}
		$found = false !== strpos( file_get_contents( $path ), $text );
		if ( $found === $negate ) {
			throw new Exception( "$path should " . ( $negate ? 'not ' : '' ) . "contain \"$text\". Content:\n" . file_get_contents( $path ) );
		}
	}

	private function decoded_stdout()
	{
		$stdout  = $this->last_command()->stdout;
		$decoded = json_decode( $stdout, true );
		if ( null === $decoded && 'null' !== trim( $stdout ) ) {
			throw new Exception( 'STDOUT is not valid json: ' . json_last_error_msg() . "\n" . $this->describe( $this->last_command() ) );
		}

		return $decoded;
	}

	/**
	 * Resolves a dot path: "key", "*" (every element) or "field=value" (elements whose field matches).
	 */
	private function json_nodes( $path )
	{
		$nodes = [ $this->decoded_stdout() ];
		$path  = $this->replace_variables( $path );
		foreach ( '' === $path ? [] : explode( '.', $path ) as $part ) {
			$next = [];
			foreach ( $nodes as $node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}
				if ( '*' === $part ) {
					$next = array_merge( $next, array_values( $node ) );
				} elseif ( false !== strpos( $part, '=' ) ) {
					list( $field, $value ) = explode( '=', $part, 2 );
					foreach ( $node as $element ) {
						if ( is_array( $element ) && isset( $element[ $field ] ) && (string) $element[ $field ] === $value ) {
							$next[] = $element;
						}
					}
				} elseif ( array_key_exists( $part, $node ) ) {
					$next[] = $node[ $part ];
				}
			}
			$nodes = $next;
		}

		return $nodes;
	}

	private function single_json_node( $path )
	{
		$nodes = $this->json_nodes( $path );
		if ( 1 !== count( $nodes ) ) {
			throw new Exception( 'Expected one value at "' . $path . '", found ' . count( $nodes ) . ".\n" . $this->describe( $this->last_command() ) );
		}

		return $nodes[0];
	}

	private static function json_scalar( $value )
	{
		return is_string( $value ) ? $value : json_encode( $value, JSON_UNESCAPED_SLASHES );
	}

	private static function db_path()
	{
		return EE_ROOT_DIR . '/db/ee.sqlite';
	}

	private static function db()
	{
		if ( ! file_exists( self::db_path() ) ) {
			throw new Exception( self::db_path() . ' does not exist.' );
		}
		$pdo = new PDO( 'sqlite:' . self::db_path() );
		$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );

		return $pdo;
	}

	private static function get_option( $key )
	{
		$query = self::db()->prepare( 'SELECT value FROM options WHERE "key" = ?' );
		$query->execute( [ $key ] );

		return $query->fetchColumn();
	}

	private static function set_option( $key, $value )
	{
		$db = self::db();
		if ( false === self::get_option( $key ) ) {
			$db->prepare( 'INSERT INTO options ("key", value) VALUES (?, ?)' )->execute( [ $key, $value ] );
		} else {
			$db->prepare( 'UPDATE options SET value = ? WHERE "key" = ?' )->execute( [ $value, $key ] );
		}
	}

	private static function delete_option( $key )
	{
		self::db()->prepare( 'DELETE FROM options WHERE "key" = ?' )->execute( [ $key ] );
	}

	private static function count_containers( $label )
	{
		return (int) trim( (string) shell_exec( 'docker ps -aq --filter ' . escapeshellarg( 'label=' . $label ) . ' | wc -l' ) );
	}
}
