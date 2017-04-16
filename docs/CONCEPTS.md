## EasyEngine Concepts

This is higher-level view of easyegnine concepts. Reading this is not essential to use easyengine but will make it easy to undertsand.

### Filesystem Presence

Apart form vaious packages easyenigne installs, easyengine itself uses many filesystem locations for different purpose:

#### Config files

`/etc/easyengine/` 					=>		easyengine config file folder
`/etc/easyengine/ee.conf` 			=>		actaul easyengine config file which you can customize

#### Template files

`/usr/share/easyengine/` 			=>		easyengine template files for different site creation


#### Executables

`/usr/local/sbin/easyengine`		=> 		main executable shell script for easyengine
`/usr/local/sbin/ee`				=> 		symlink to `/usr/local/sbin/easyengine` to just save typing

#### Source code

`/usr/local/src/easyengine/`		=> 		easyengine source code folder
`/usr/local/src/easyengine/lib/`	=> 		easyengine source code lib. This is `src/lib/` folder from github repo
`/usr/local/src/easyengine/core/`	=> 		easyengine source code lib. This is `src/core/` folder from github repo
`/usr/local/src/easyengine/modules/`=> 		easyengine source code lib. This is `src/modules/` folder from github repo


#### Logs folder

`/var/log/easyengine/`				=> 		easyengine logs folder
`/var/log/easyengine/error.log`		=> 		easyengine error logs
`/var/log/easyengine/install.log`	=> 		easyengine installer log (for easyengine instalaltion)
`/var/log/easyengine/command.log`	=> 		easyengine command log 
`/var/log/easyengine/update.log`	=> 		easyengine update log 



