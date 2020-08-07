
<img src="https://i2.wp.com/easyengine.io/wp-content/uploads/sites/20/2019/06/EasyEngine-New-Logo-Banner@2x-Transparent-Background.png?fit=720%2C170&ssl=1" alt="EasyEngine Logo" />

# EasyEngine v4
[![Build Status](https://travis-ci.org/EasyEngine/easyengine.svg?branch=master-v4)](https://travis-ci.org/EasyEngine/easyengine) [![Join EasyEngine Slack Channel](http://slack.easyengine.io/badge.svg)](http://slack.easyengine.io/) [![Latest Stable Version](https://poser.pugx.org/easyengine/easyengine/v/stable)](https://packagist.org/packages/easyengine/easyengine) [![Latest Unstable Version](https://poser.pugx.org/easyengine/easyengine/v/unstable)](https://packagist.org/packages/easyengine/easyengine) [![License](https://poser.pugx.org/easyengine/easyengine/license)](https://packagist.org/packages/easyengine/easyengine)

EasyEngine makes it greatly easy to manage nginx, a fast web-server software that consumes little memory when handling increasing volumes of concurrent users.

<a href="https://rtcamp.com/?utm_source=github&utm_medium=readme" rel="nofollow"><img src="https://rtcamp.com/wp-content/uploads/2019/04/github-banner@2x.png" alt="Handcrafted Enterprise WordPress Solutions by rtCamp" /></a>

## Requirements

* Docker
* Docker-Compose
* PHP CLI (>=7.1)
* PHP Modules - `curl`, `sqlite3`, `pcntl`

## Installing

### Linux

For Linux, we have created an installer script which will install all the dependencies for you. We have tested this on Ubuntu 14.04, 16.04, 18.04 and Debian 8.

```bash
wget -qO ee https://rt.cx/ee4 && sudo bash ee
```

Even if the script doesn't work for your distribution, you can manually install the dependencies and then run the following commands to install EasyEngine

```bash
wget -O /usr/local/bin/ee https://raw.githubusercontent.com/EasyEngine/easyengine-builds/master/phar/easyengine.phar
chmod +x /usr/local/bin/ee
```

### Tab completions

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

To get started with EasyEngine and create a wordpress site, run

```
ee site create example.com --type=wp
```

Need a wordpress site with caching? Try

```
ee site create example.com --type=wp --cache
```

Need a wordpress multi-site with page cache?
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

## Donations

[![PayPal-Donate](https://cloud.githubusercontent.com/assets/4115/5297691/c7b50292-7bd7-11e4-987b-2dc21069e756.png)](http://rt.cx/eedonate)


