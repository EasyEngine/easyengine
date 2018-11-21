Internal-API
============
This folder contains the main part of EasyEngine cli.

* It conatins the `cli-command` which handles the most basic required functions like: `version, update` etc.

* The [WP-CLI internal-api](https://github.com/wp-cli/handbook/blob/master/internal-api.md), booting and auto-loading logic and other utilities.

* It also contains new classes:
    * `EE_DB` having the generic Sqlite3 functions for db management.
    * `EE_DOCKER` having various docker related functions like starting, stopping and inspecting containers, creating and connecting to networks, generating `docker-compose.yml`.

* Also, the internal-api has been slightly modified to remove WordPress specific functions and additional features like, logging of all the command invocation and related log, success, debug and error messages from `EE::info(), EE::success(), EE::debug(), EE::error()` outputs into a file for debugging purposes.