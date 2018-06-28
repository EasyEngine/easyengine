# EasyEngine v4

[![Build Status](https://travis-ci.org/EasyEngine/easyengine.svg?branch=master-v4)](https://travis-ci.org/EasyEngine/easyengine)

## Requirements

* Docker
* Docker-Compose
* PHP CLI (>=7.1)
* PHP Modules - `curl`, `sqlite3`, `pcntl`

## Installing

### Linux

For Linux, we have created an installer script which will install all the dependencies for you. We have tested this on Ubuntu 14.04, 16.04, 18.04 and Debian 8.

```bash
wget -qO ee rt.cx/ee4beta && sudo bash ee
```

Even if the script doesn't work for your distribution, you can manually install the dependencies and then run the following commands to install EasyEngine

```bash
wget -O /usr/local/bin/ee https://raw.githubusercontent.com/EasyEngine/easyengine-builds/master/phar/easyengine.phar
chmod +x /usr/local/bin/ee
```

## Usage

## Basic Commands
Currently there are three top level commands of `ee`.
 * [ee site](#ee-site)
 * [ee shell](#ee-shell)
 * [ee cli](#ee-cli)

Note: :warning: EasyEngine will currently only run with root privileges. You can run `ee help`, `ee help site` and `ee help site create` to get all the details about the various commands and subcommands that you can run.

### ee site
Contains basic site management commands

`site` command contains following subcommand
 * [ee site create](#ee-site-create)
 * [ee site delete](#ee-site-delete)
 * [ee site disable](#ee-site-disable)
 * [ee site enable](#ee-site-enable)
 * [ee site info](#ee-site-info)
 * [ee site list](#ee-site-list)
 * [ee site start](#ee-site-start)
 * [ee site stop](#ee-site-stop)
 * [ee site restart](#ee-site-restart)
 * [ee site reload](#ee-site-reload)

#### ee site create
Runs the site creation.

```bash
ee site create example.com --wp                 # install wordpress without any page caching
ee site create example.com --wpredis            # install wordpress + redis caching
ee site create example.com --wpsubir            # install wpmu-subdirectory without any page caching
ee site create example.com --wpsubir --wpredis  # install wpmu-subdirectory + redis caching
ee site create example.com --wpsubdom           # install wpmu-subdomain without any page caching
ee site create example.com --wpsubdom --wpredis # install wpmu-subdomain + redis caching
```

#### ee site delete
Deletes an existing EasyEngine site.

```bash
ee site delete example.com
```

#### ee site disable
Disables a website. It will stop and remove the docker containers of the website if they are running.

```bash
ee site disable example.com
```

#### ee site enable
Enables a website. It will start the docker containers of the website if they are stopped.

```bash
ee site enable example.com
```

#### ee site info
Display all the relevant site information, credentials and useful links.

```bash
ee site info example.com
```

#### ee site list
Lists the created websites.

```bash
ee site list
```

#### ee site start
Starts containers associated with site.

```bash
ee site start example.com
ee site start example.com --nginx
```

#### ee site stop
Stops containers associated with site.

```bash
ee site stop example.com
ee site stop example.com --nginx
```

#### ee site restart
Restarts containers associated with site.

```bash
ee site restart example.com
ee site restart example.com --nginx
```

#### ee site reload
Reload services in containers without restarting container(s) associated with site.

```bash
ee site reload example.com
ee site reload example.com --nginx
```

### ee shell
Gives you a shell where you can manage and interact with your site.

```bash
ee shell example.com
```

### ee cli
Commands to manage easyengine itself

`cli` command has following subcommands:
 * [ee cli info](#ee-cli-info)
 * [ee cli update](#ee-cli-update)
 * [ee cli version](#ee-cli-version)
 * [ee cli has-command](#ee-cli-has-command)

#### ee cli info
Print various details about the EE environment.

```bash
ee cli info
```
#### ee cli update
Updates EasyEngine to the latest release.

```bash
ee cli update
```
#### ee cli version
Print EasyEngine version.

```bash
ee cli version
```
#### ee cli has-command
Detects if a command exists

```bash
ee cli has-command site
```

## Tests

EasyEngine is currently using [behat](http://behat.org/) v3.4.x functional tests. The tests for site-command are inside the `features/` directory in the core repository and can be run using 
```
vendor/bin/behat
```
