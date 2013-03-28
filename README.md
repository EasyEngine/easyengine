EasyEngine 
==========

Admin tools for Nginx based wordpress sites management. 

This is work in PROGRESS. This will break things on your end most likely!

## Install EasyEngine

curl -L http://rt.cx/ee | sudo bash

# TODO

## Usage

`ee [system]	[install|remove|purge] [nginx|php|mysql|postfix|--all]`

`ee [site]	[read]	 [all|active|sitename]`

`ee [site]	[create]     [sitename] [--with-wordpress]`

`ee [site]	[update]     [sitename] [singlesite] [w3total|wpsuper|fastcgi]`
`ee [site]	[update]     [sitename] [multisite]  [subdirectory|subdomain] [w3total|wpsuper|fastcgi]`

`ee [site]	[delete]     [sitename] [--database|--webroot|--all]`

`ee [site]      [dis[aable]]   [sitename] `
`ee [site]      [en[able]]     [sitename] `

`ee [config]	[set|get] [memory|timeout]`

`ee [update]`

### engine commands:

`ee system`	system-wide commands

`ee site`	site-specific commands

`ee config`	configuration commands

### ee system example:
* **Install nginx**
	`ee system install nginx`
	
* **Insall nginx php mysql postfix**
	`ee system install --all`
	
	
### ee site example:

* **List all the sites**
	`ee site read all`
	
* **List only active sites**
	`ee site read active`
	
* **Read nginx configuration for example.com**
	`ee site read example.com`
	
* **Create a domain**
	`ee site create example.com`
	
* **Create a wordpress site**
	`ee site create example.com --with-wordpress`
	
* **Update nginx configuration for w3total cache**
	`ee site update example.com singlesite w3total`
	
* **Delete site**
	`ee site delete example.com`	
	

### engine config:

`engine config set [memory | timeout] [value]`

`engine config get [[memory | timeout]|--all]`

`engine config --interactive` #reconfigure everything


### ee update:

* **Update easy engine**
	`ee update`
	
	
## Files:

### Logs location: 

Main Log: `/var/log/easyengine/main.log`

Debug Log: `/var/log/easyengine/debug.log`




Notes:

1. Folder /zzz contains some shell scripts by Pragati Sureka (a geekiest rtCamper)

