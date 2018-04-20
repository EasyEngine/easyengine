EasyEngine Core Repository
===

The core repository contains the cli interface for EasyEngine and the internal api's to facilitate and accomodate the creation and execution of commands. It is built on top of [WP-CLI](https://github.com/wp-cli/wp-cli) and follows the same basic structure.

1. `bin` folder contains the wrapper shell script to execute EasyEngine.
2. `ci` folder contains shell scripts to automate the phar building and deploying of the same to the [easyengine-builds](https://github.com/easyengine/easyengine-builds) repository with the help of [travis-ci](https://travis-ci.org/).
3. `ee4-config` folder has all the `nginx, php and redis` configurations requiered for different types of sites like `WordPress - Single-Site, Multi-Site` etc.
4. The `php` folder contains the main part of EasyEngine cli.
    * It conatins the `cli-command` which handles the most basic required functions like: `version, update` etc.
    * The [WP-CLI internal-api](https://github.com/wp-cli/handbook/blob/master/internal-api.md), booting and auto-loading logic and other utilities.
    * It also contains new classes:
        * `EE_DB` having the generic Sqlite3 functions for db management.
        * `EE_DOCKER` having various docker related functions like starting, stopping and inspecting containers, creating and connecting to networks, generating `docker-compose.yml`.
    * Also, the internal-api has been slightly modified to remove WordPress specific functions and additional features like, logging of all the command invocation and related log, success, debug and error messages from `EE::info(), EE::success(), EE::debug(), EE::error()` outputs into a file for debugging purposes.
5. `templates` folder conatins the [mustache](https://mustache.github.io/) templates for man/help, generation of `docker-compose.yml` etc.
6. `utils` folder contains the scripts for `phar` generation.



