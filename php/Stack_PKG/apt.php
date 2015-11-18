<?php

//EasyEngine package installation using apt module.


class APT extends PKG_MANAGER {

	private $pkg = array();
	public function __construct($data=array())
	{
		$this->pkg = $data;

		print_r($this->pkg);  //debugging purpose only

	}

	function install() {
		$this->cmd = 'apt-get install  '.$this->pkg['package_name'] ;
		$this->execute_local();

	}

	private function update(){
		$this->cmd = 'apt-get update  ';
		$this->execute_local();
	}


	function execute_local() {
		$output = array();
		$res = exec( $this->cmd, $output, $return );
		$this->res = $output;
		print_r( $this->res );
	}

	function repo_add($repo_url){

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

	function repo_add_key($keyids, $keyserver) {

    	if (isset($keyserver)){
			$this->cmd = 'gpg --keyserver  ' . $keyserver . '--recv-keys ' . $keyids ;
			$this->execute_local();

		}
		else {
			$this->cmd = 'gpg --keyserver  ' . 'hkp://keys.gnupg.net' . '--recv-keys ' . $keyids ;
			$this->execute_local();
		}

		$this->cmd = 'gpg -a --export --armor  ' . $keyids . ' | apt-key add - ' ;
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


