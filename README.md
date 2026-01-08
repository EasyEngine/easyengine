
![EasyEngine Logo](https://github.com/user-attachments/assets/fcde9a0e-0569-41e1-bc98-5060af8cd7d3)

# EasyEngine v4
[![Build 🔨 + Test 👨‍🔧](https://github.com/EasyEngine/easyengine/actions/workflows/test_and_build.yml/badge.svg?branch=develop)](https://github.com/EasyEngine/easyengine/actions/workflows/test_and_build.yml) 
[![Latest Stable Version](https://poser.pugx.org/easyengine/easyengine/v/stable)](https://github.com/EasyEngine/easyengine/releases) [![License](https://poser.pugx.org/easyengine/easyengine/license)](https://packagist.org/packages/easyengine/easyengine)

EasyEngine makes it greatly easy to manage nginx, a fast web-server software that consumes little memory when handling increasing volumes of concurrent users.

## Requirements

* Docker
* Docker-Compose
* PHP CLI (>=7.1)
* PHP Modules - `curl`, `sqlite3`, `pcntl`

## Installing

### Linux

For Linux, we have created an installer script that will install all the dependencies for you. We have tested this on Ubuntu 14.04, 16.04, 18.04, 20.04, 22.04 and Debian 8, Debian 10.

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
ee site create example.com --type=wp --mu=subdir --cache
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

Note: :warning: EasyEngine will currently only run with root privileges. You can run `ee help`, `ee help site` and `ee help site create --type=wp` to get all the details about the various commands and subcommands that you can run.

## Development

Development of easyengine is done entirely on GitHub.

We've used [wp-cli](https://github.com/wp-cli/wp-cli/) framework as a base and built EasyEngine on top of it.

This repo contains the main core of easyengine (the framework).
All top level commands(except `ee cli`) i.e. `ee site`, `ee shell` have their own repos.

Currently, we have the following commands which are bundled by default in EasyEngine:

* [site command](https://github.com/EasyEngine/site-command/)
* [shell command](https://github.com/EasyEngine/shell-command/)

In future, the community will be able to make their own packages and commands!

## Contributing

We warmheartedly welcome all contributions however and in whatever capacity you can either through Pull Requests or by reporting Issues. You can contribute here or in any of the above mentioned commands repo.

## Donations

[![PayPal-Donate](https://cloud.githubusercontent.com/assets/4115/5297691/c7b50292-7bd7-11e4-987b-2dc21069e756.png)](http://rt.cx/eedonate)

## Does this interest you?

<a href="https://rtcamp.com/?utm_source=github&utm_medium=readme" rel="nofollow"><img src="https://rtcamp.com/wp-content/uploads/sites/2/2019/04/github-banner@2x.png" alt="Handcrafted Enterprise WordPress Solutions by rtCamp" /></a>
