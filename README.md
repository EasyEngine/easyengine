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

### create
Runs the site creation.

```bash
ee site create example.com --wp                 # install wordpress without any page caching
ee site create example.com --wpredis            # install wordpress + redis caching
ee site create example.com --wpsubir            # install wpmu-subdirectory without any page caching
ee site create example.com --wpsubir --wpredis  # install wpmu-subdirectory + redis caching
ee site create example.com --wpsubdom           # install wpmu-subdomain without any page caching
ee site create example.com --wpsubdom --wpredis # install wpmu-subdomain + redis caching
```

### delete
Deletes an existing EasyEngine site.

```bash
ee site delete example.com
```

### disable
Disables a website. It will stop and remove the docker containers of the website if they are running.

```bash
ee site disable example.com
```

### enable
Enables a website. It will start the docker containers of the website if they are stopped.

```bash
ee site enable example.com
```

### info
Display all the relevant site information, credentials and useful links.

```bash
ee site info example.com
```

### list
Lists the created websites.

```bash
ee site list
```

EasyEngine will currently only run with root privileges. You can run `ee help`, `ee help site` and `ee help site create` to get all the details about the various commands and subcommands that you can run.
