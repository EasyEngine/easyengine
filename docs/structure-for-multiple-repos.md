Structure For Multiple Reops
=====

EasyEngine v4 follows [WP-CLI](https://github.com/wp-cli/wp-cli) as the base of creation, hence it consists of multiple repos. The core repo [EasyEngine](https://github.com/EasyEngine/easyengine) contains the main structure and cli interface to facilitate the execution of all the commands.

Each command has a seperate repository like: [site-command](https://github.com/easyengine/site-command), [wp-command](https://github.com/easyengine/wp-command) etc., and they are added as commands to EasyEngine via composer packages.

Apart from the commands there are also other miscellaneous repositories like the [easyengine-builds](https://github.com/easyengine/easyengine-builds) that contains the stable as well as nightly builds of phar generated from the main repository, [dockerfiles](https://github.com/easyengine/dockerfiles) repository contains all the dockerfiles that are in use by EasyEngine.