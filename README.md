# EasyEngine v4

[![Build Status](https://travis-ci.org/EasyEngine/easyengine.svg?branch=master-v4)](https://travis-ci.org/EasyEngine/easyengine)

## Requirements

* Docker
* Docker-Compose
* PHP CLI (>=7.1)
* PHP Modules - `curl`, `sqlite3`, `pcntl`

## Installing

### Mac

Once you have verified the requirements exist, download the `easyengine.phar` file using `wget` or `curl` and give it executable permissions:

```bash
wget -O /usr/local/bin/ee https://raw.githubusercontent.com/EasyEngine/easyengine-builds/master/phar/easyengine.phar
chmod +x /usr/local/bin/ee
```

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

EasyEngine will currently only run with root privileges. You can run `ee help` or `ee help site` to get all the details about the various commands and subcommands that you can run.
