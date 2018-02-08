<?php
/**
 * Test AutoloadSplitter class
 *
 * @package   EE\Tests\Unit
 * @author    EE Contributors
 * @copyright 2017 EE
 * @license   GPL-2.0+
 */

namespace EE\Tests\Unit;

use PHPUnit_Framework_TestCase;
use EE\AutoloadSplitter;

/**
 * Class AutoloadSplitterTest.
 *
 * @group autoloadsplitter
 */
class AutoloadSplitterTest extends PHPUnit_Framework_TestCase {
	/**
	 * Test that AutoloadSplitter returns correct login
	 *
	 * @dataProvider dataCodePaths
	 */
	public function testAutoloadSplitter( $code, $expected ) {
		$autoload_splitter = new AutoloadSplitter();

		$this->assertSame( $expected, $autoload_splitter('foo', $code) );
	}

	/**
	 * Data provider of code paths.
	 *
	 * @return array
	 */
	public function dataCodePaths() {
		return array(
			array( '/ee/a-command/', true ),
			array( '/ee/abcd-command/', true ),
			array( '/ee/a-b-c-d-e-f-g-h-i-j-k-l-m-n-o-p-q-r-s-t-u-v-w-x-y-z-command/', true ),
			array( 'xyz/ee/abcd-command/zxy', true ),

			array( '/php/commands/src/', true ),
			array( 'xyz/php/commands/src/zyx', true ),

			array( '/ee/-command/', false ), // No command name.
			array( '/ee/--command/', false ), // No command name.
			array( '/ee/abcd-command-/', false ), // End is not '-command/`
			array( '/ee/abcd-/', false ), // End is not '-command/'.
			array( '/ee/abcd-command', false ), // End is not '-command/'.
			array( 'ee/abcd-command/', false ), // Start is not '/ee/'.
			array( '/wp--cli/abcd-command/', false ),  // Start is not '/ee/'.
			array( '/eeabcd-command/', false ),  // Start is not '/ee/'.
			array( '/ee//abcd-command/', false ),  // Middle contains two '/'.

			array( '/php-/commands/src/', false ),  // Start is not '/php/'.
			array( 'php/commands/src/', false ), // Start is not '/php/'.
			array( '/php/commands/src', false ), // End is not '/src/'.
			array( '/php/commands/srcs/', false ),  // End is not '/src/'.
			array( '/php/commandssrc/', false ),  // End is not '/src/'.
		);
	}
}
