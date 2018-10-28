<p align="center">
  <br>
  <a href="https://easyengine.io">
    <img src="https://easyengine.io/wp-content/uploads/2015/11/cropped-favicon-easyengine.png" alt="EasyEngine Logo" width="200" height="200"/>
  </a>
</p>

<h1 align="center">EasyEngine v4</h1>

<p align="center">
  Command Line tool to manage WordPress sites with NGINX, PHP, MySQL, and Let's Encrypt called <a href="https://easyengine.io">EasyEngine.io</a>
</p>

<p align="center">
  <a title="Build Status" href="https://travis-ci.org/EasyEngine/easyengine">
    <img src="https://travis-ci.org/EasyEngine/easyengine.svg?branch=master-v4">
  </a>
  <a title="Join EasyEngine Slack Channel" href="http://slack.easyengine.io/">
    <img src="http://slack.easyengine.io/badge.svg">
  </a>
  <a title="MIT License" href="LICENSE">
    <img src="https://img.shields.io/github/license/EasyEngine/easyengine.svg?style=flat-square">
  </a>
  <a title="Follow on Twitter" href="https://twitter.com/easyengine">
    <img src="https://img.shields.io/twitter/follow/easyengine.svg?style=social&label=Follow">
  </a>
  <br>
  <br>
</p>

> This project is under active development. Any feedback or contributions would be appreciated.

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

### Tab Completions

EasyEngine also comes with a tab completion script for Bash and ZSH. Just download [ee-completion.bash](https://raw.githubusercontent.com/EasyEngine/easyengine/develop-v4/utils/ee-completion.bash) and source it from `~/.bash_profile`:

```bash
source /FULL/PATH/TO/ee-completion.bash
```

Don't forget to run `source ~/.bash_profile` afterwards.

If using zsh for your shell, you may need to load and start `bashcompinit` before sourcing. Put the following in your `.zshrc`:

```bash
autoload bashcompinit
bashcompinit
source /FULL/PATH/TO/ee-completion.bash
```

## Usage

To get started with EasyEngine and create a WordPress site, run

```
ee site create example.com --type=wp
```

Need a WordPress site with caching? Try

```
ee site create example.com --type=wp --cache
```

Need a WordPress multi-site with page cache?
```
ee site create example.com --type=wp --mu=wpsubdir --cache
```

Need a plain and simple html site?
```
ee site create example.com
```

Want to play around with your new site?
```
ee shell example.com
```

Want to know more? Checkout readme of these commands -
 * [site command](https://github.com/EasyEngine/site-command/)
 * [site-wp command](https://github.com/EasyEngine/site-wp-command/)
 * [cron command](https://github.com/EasyEngine/cron-command/)
 * [shell command](https://github.com/EasyEngine/shell-command/)

> :warning: Note: EasyEngine will currently only run with root privileges. You can run `ee help`, `ee help site` and `ee help site create` to get all the details about the various commands and subcommands that you can run.

## Development

Development of EasyEngine is done entirely on GitHub.

We've used [wp-cli](https://github.com/wp-cli/wp-cli/) framework as a base and built EasyEngine on top of it.

This repo contains main core of EasyEngine (the framework).
All top level commands(except `ee cli`) i.e. `ee site`, `ee shell` have their own repos.

Currently we have following commands which are bundled by default in EasyEngine:

* [site command](https://github.com/EasyEngine/site-command/)
* [shell command](https://github.com/EasyEngine/shell-command/)

In future, community will be able to make their own packages and commands!

## Contributing

We warmheartedly welcome all contributions however and in whatever capacity you can either through Pull Requests or by reporting Issues. You can contribute here or in any of the above mentioned commands repo.

## Donations

[![PayPal-Donate](https://cloud.githubusercontent.com/assets/4115/5297691/c7b50292-7bd7-11e4-987b-2dc21069e756.png)](http://rt.cx/eedonate)
[![BitCoin-Donate](https://bitpay.com/img/donate-button.svg)](https://bitpay.com/417008/donate)
