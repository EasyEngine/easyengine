<?php



class APT extends PKG_MANAGER
{

	function install() {
		$this->cmd = 'apt-get install  '.$this->pkg ;
		$this->execute_local();

	}


	function execute_local() {
		$output = array();
		$res = exec( $this->cmd, $output, $return );
		$this->res = $output;
		print_r( $this->res );
	}



}


