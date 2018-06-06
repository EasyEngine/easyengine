EasyEngine Core Repository
===

The core repository contains the cli interface for EasyEngine and the internal api's to facilitate and accommodate the creation and execution of commands. It is built on top of [WP-CLI](https://github.com/wp-cli/wp-cli) and follows the same basic structure.

1. The `bin` directory contains the shell script entrypoint to the PHP files.
2. `ci` directory contains shell scripts to automate the phar building and deploying of the same to the [easyengine-builds](https://github.com/easyengine/easyengine-builds) repository with the help of [travis-ci](https://travis-ci.org/).
3. `ee4-config` directory has all the `nginx, php and redis` configurations required for different types of sites like `WordPress - Single-Site, Multi-Site` etc.
4. The `php` directory contains the core of EasyEngine cli.
    * It contains the `cli-command` which handles the most basic required functions like: `version, update` etc.
    * The [WP-CLI internal-api](https://github.com/wp-cli/handbook/blob/master/internal-api.md), booting and auto-loading logic and other utilities.
    * EasyEngine adds the following classes on top of the existing codebase:
        * `EE_DB` having the generic Sqlite3 functions for db management.
        * `EE_DOCKER` having various docker related functions like starting, stopping and inspecting containers, creating and connecting to networks etc.
    * Also, the internal-api has been slightly modified to remove WordPress specific functions and additional features like, logging of all the command invocation and related log, success, debug and error messages from `EE::info(), EE::success(), EE::debug(), EE::error()` outputs into a file for debugging purposes.
5. `templates` directory contains the [mustache](https://mustache.github.io/) templates for man page/help, generation of `docker-compose.yml` etc.
6. `utils` directory contains the scripts for `phar` generation.



