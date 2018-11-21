<?php

class EE_Test extends PHPUnit_Framework_TestCase {

	public function testGetPHPBinary() {
		$this->assertSame( EE\Utils\get_php_binary(), EE::get_php_binary() );
	}
}
