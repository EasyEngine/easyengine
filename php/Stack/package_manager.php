<?php

/**
 * Base class for package managements
 *
 */




abstract class PKG_MANAGER {

	protected $stack_os_scope ;

	function __construct($stack=array()) {

		$this->stack_os_scope = $stack;
	}

	function validate_stack_type($stacktype){
		$os_type = EE_CLI\Utils\get_OS();
		$flag = false;
		foreach ($this->stack_os_scope as $value) {

			if ($value == trim($os_type['DISTRIB_ID'])) {
				$flag = true;
				print_r($os_type['DISTRIB_ID']);
				return $flag;
			}
		}

		if (!$flag) die("Configuration does not match with system status \n");
	}


}
