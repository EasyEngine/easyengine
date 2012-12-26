EasyEngine 
==========

Admin tools for Nginx based wordpress sites management. 

This is work in PROGRESS. This will break things on your end most likely!

## Install EasyEngine

curl -L http://goo.gl/FvARq | sudo bash

# TODO

## Usage

engine [system]	[install|remove|purge] [nginx|php|mysql|postfix|--all]"

engine [site]	[read]	 [all|active|sitename]"
engine [site]	[create] [sitename] [--with-wordpress]"
engine [site]	[update] [sitename] [single] [w3total|wpsuper|fastcgi]"
engine [site]	[update] [sitename] [multi]  []"
engine [site]	[delete] [sitename] [--database|--webroot|--all]"

engine [config]	[set|get] [memory|timeout]"

### engine Commands:

`engine system` system-wide commands

`engine site` #site-specific commands

`engine config` configuration commands

### engine system

`engine system install [php mysql nginx apc postfix] [--all] [--source]`

`engine system upgrade [php mysql nginx apc postfix] [--all]`

`engine system remove [php mysql nginx apc postfix] [--all]`

`engine system purge [php mysql nginx apc postfix] [--all]`

### engine site

`engine site create <site-name> [--with-wordpress]`

`engine site read --active`

`engine site update <site-name>`

`engine site delete <site-name>`

### engine config

`engine config set [memory | timeout] [value]`

`engine config get [[memory | timeout]|--all]`

`engine config --interactive` #reconfigure everything


## Files

### Logs location: 

Main Log: `/var/log/easyengine/main.log`

Debug Log: `/var/log/easyengine/debug.log`




Notes:

1. Folder /zzz contains some shell scripts by Pragati Sureka (a geekiest rtCamper)

