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
wget -qO ee https://rt.cx/ee4beta && sudo bash ee
```

Even if the script doesn't work for your distribution, you can manually install the dependencies and then run the following commands to install EasyEngine

```bash
wget -O /usr/local/bin/ee https://raw.githubusercontent.com/EasyEngine/easyengine-builds/master/phar/easyengine.phar
chmod +x /usr/local/bin/ee
```

## Usage

To get started with EasyEngine and create a wordpress site, run

```
ee site create example.com
```

Need a wordpress site with caching? Try

```
ee site create example.com --wpredis
```

Need a wordpress multi-site with page cache?
```
ee site create example.com --wpsubdir --wpredis
```

Want to play around with your new site?
```
ee shell example.com
```

Want to know more? Checkout readme of these commands -
 * [site command](https://github.com/EasyEngine/site-command/)
 * [shell command](https://github.com/EasyEngine/shell-command/)

Note: :warning: EasyEngine will currently only run with root privileges. You can run `ee help`, `ee help site` and `ee help site create` to get all the details about the various commands and subcommands that you can run.

## Development

Development of easyengine is done entirely on GitHub.

We've used [wp-cli](https://github.com/wp-cli/wp-cli/) framework as a base and built EasyEngine on top of it.

This repo contains main core of easyengine (the framework). 
All top level commands(except `ee cli`) i.e. `ee site`, `ee shell` have their own repos.

Currently we have following commands which are bundled by default in EasyEngine:

* [site command](https://github.com/EasyEngine/site-command/)
* [shell command](https://github.com/EasyEngine/shell-command/)

In future, community will be able to make their own packages and commands!

## Contributing

We warmheartedly welcome all contributions however and in whatever capacity you can either through Pull Requests or by reporting Issues. You can contribute here or in any of the above mentioned commands repo.
