<?php

//EasyEngine package installation using apt module.
include EE_CLI_ROOT . '/php/Stack/package_manager.php';
use  EE_CLI\Process;


class APT extends PKG_MANAGER {

	protected $stack_os_scope = array('Ubuntu','Debian');
	private $pkg = array();

	function __construct($data=array())
	{
		parent::__construct($this->stack_os_scope);
		$this->pkg = $data;

		//print_r($this->pkg);  //debugging purpose only

	}

	public function install() {

		$process = EE_CLI\Process::create( "sudo apt-get install {$this->pkg['package_name']}" );
		$result = $process->run();

		if ( 0 !== $result->return_code ) {
			//logging part here if installation fail
		}

	}

	private function update(){
		$process = EE_CLI\Process::create( "sudo apt-get update" );
		$result = $process->run();
	}


	function execute_local() {
		$output = array();
		$res = exec( $this->cmd, $output, $return );
		$this->res = $output;
		print_r( $this->res );
	}

	public function repo_add($repo_url){

		if (isset($repo_url)) {
			$repo_file_path = "/etc/apt/sources.list.d/" . ee_repo_file ;
		}

		if (!file_exists($repo_file_path)) {
			$myfile = fopen($repo_file_path , "w") or die("Unable to open file!");
			fwrite($myfile, $repo_url . '\n');
			fclose($myfile);
		} else {

			$handle = @fopen($repo_file_path, "w");
			$matches = false;
			if ($handle)
			{
				while (!feof($handle))
				{
					$buffer = fgets($handle);
					if(strpos($buffer, $repo_url) !== FALSE)
						$matches = true;
				}

				if(!$matches) {
					fwrite($handle, $repo_url . '\n');
				}

				fclose($handle);
			}


		}

	}

	public function repo_add_key($keyids, $keyserver) {

    	if (isset($keyserver)){
			$this->cmd = 'gpg --keyserver  ' . $keyserver . '--recv-keys ' . $keyids ;
			$this->execute_local();

		}
		else {
			$this->cmd = 'gpg --keyserver  ' . 'hkp://keys.gnupg.net' . '--recv-keys ' . $keyids ;
			$this->execute_local();
		}

		$this->cmd = 'gpg -a --export --armor  ' . $keyids . ' | sudo apt-key add - ' ;
		$this->execute_local();


	}

	function repo_remove(){

	}
	/*
    is_installed
    auto_remove
    auto_clean

    */



}


