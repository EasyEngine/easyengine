EasyEngine 
==========

Admin tools for Nginx based wordpress sites management. 

This is work in PROGRESS. This will break things on your end most likely!

# TODO

## Usage

### engine Commands:

`engine site` site-specific commands
`engine system` system-wide commands
`engine config` configuration commands

## engine site

`engine site create <site-name> [--with-wordpress]`
`engine site read --active`
`engine site update <site-name>`
`engine site delete <site-name>`

## engine system

`engine system install [php mysql nginx apc postfix] [--all] [--source]`
`engine system upgrade [php mysql nginx apc postfix] [--all]`
`engine system remove [php mysql nginx apc postfix] [--all]`
`engine system purge [php mysql nginx apc postfix] [--all]`

## engine config

`engine config set [memory | timeout] [value]`
`engine config get [[memory | timeout]|--all]`
`engine config --interactive` #reconfigure everything





Notes:

1. Folder /zzz contains some shell scripts by Pragati Sureka (a geekiest rtCamper)

