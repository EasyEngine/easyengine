# Behat coverage: easyengine (core)

Core owns the runner (bootstrap, config, aliases, requirement checks, migration trigger, logging), the `cli` command group and `help`. Every other command belongs to a package suite; core only checks that each one has a help page and is registered.

## Inventory and mapping

### `ee cli version`

| Item | Covered by |
|---|---|
| prints `EE <VERSION>` | cli-version.feature: "cli version prints the source VERSION" |
| global `--version` rewrite to `cli version` | cli-version.feature: "The global --version flag ..." |
| no synopsis, so extra args/flags are ignored | cli-version.feature: "cli version takes no arguments ..." |
| `--help` rewrite to `help cli version` | cli-version.feature, help.feature subcommand outline |
| no requirement check (works without docker) | cli-version.feature: "cli version does not need docker ..." |
| from the phar | phar.feature: "The phar reports the source VERSION" |

### `ee cli info [--format=list|json]`

| Item | Covered by |
|---|---|
| list output, every row (OS, Shell, PHP binary/version, php.ini, EE root/vendor dir, phar path, packages dir, global/project config, version) | cli-info.feature: "cli info lists every diagnostic row" |
| `--format=json` keys and values | cli-info.feature: "cli info --format=json ..." |
| global config path from `EE_CONFIG_PATH` (json and list) | cli-info.feature: "cli info reports the global config ..." |
| project config path | config-file.feature: "cli info reports the project config file" |
| invalid `--format`, typo suggestion, extra positional | cli-info.feature (3 scenarios) |
| runs the migration check only when docker is up | requirements.feature: "cli info still works when docker is not available"; migration.feature: newer/older version scenarios |
| no migration when versions match | cli-info.feature: "cli info lists every diagnostic row" (no "Executing migrations") |
| phar values (`ee_dir_path` = `phar://ee.phar`, phar path) | phar.feature (1 + 1 `@known-bug`) |

### `ee cli check-update [--patch] [--minor] [--major] [--field] [--fields] [--format]`

| Item | Covered by |
|---|---|
| default table or "latest version" success | cli-update.feature `@network` |
| `--major`, `--minor`, `--patch` | cli-update.feature `@network` (outline + `--major`) |
| `--format=json`, `--format=count` | cli-update.feature `@network` |
| invalid `--format` (validated before any request) | cli-update.feature |
| GitHub unreachable: clean error, exit 1 | cli-update.feature (proxy to a closed port, no network needed) |
| `--field`, `--fields`, `--format=csv|yaml` | not covered: output depends on live release data; formatter covered by package suites |
| nightly branch (`-alpha-` versions, `NIGHTLY_VERSION`) | not covered: needs a nightly VERSION |
| `GITHUB_TOKEN` header | not covered: no observable output |

### `ee cli update [--stable] [--nightly] [--yes]`

| Item | Covered by |
|---|---|
| refuses to run from source (`You can only self-update Phar files.`) with every flag combination | cli-update.feature outline (4 rows) |
| real update (download, md5 check, child `cli info`, phar swap), macOS brew message, not-writable errors | not covered: would replace the phar and run the new version's migrations (MIG-6); root can write everything; not on macOS |
| `auto_check_update()` on help (phar + tty only) | not covered: needs a tty and a writable phar |

### `ee cli param-dump [--with-values] [--format=json|var_export]`

| Item | Covered by |
|---|---|
| json spec with every global parameter and its fields | cli-dump.feature |
| `--format=var_export` | cli-dump.feature |
| `--with-values` from the global config, defaults, null values | cli-dump.feature |
| runtime flag overrides config (`--le-mail`) | cli-dump.feature, config-file.feature |
| invalid format, typo suggestion (`--with-value`) | cli-dump.feature |

### `ee cli cmd-dump` and `ee site cmd-dump`

| Item | Covered by |
|---|---|
| json tree, root name/description, all 12 top-level commands | cli-dump.feature |
| nested subcommands, synopsis, description | cli-dump.feature |
| `site cmd-dump` bypasses requirement checks, merges site types (`create --type=wp`) | cli-dump.feature |

### `ee cli completions --line --point [--shell=bash|zsh]`

| Item | Covered by |
|---|---|
| top-level list, partial subcommand, all cli subcommands | cli-completions.feature |
| `--point` truncation | cli-completions.feature |
| `--shell=zsh` descriptions | cli-completions.feature |
| options of a leaf, flags with trailing space, already used options skipped | cli-completions.feature |
| `help` prefix | cli-completions.feature |
| site subcommands and `--type`-aware site options | cli-completions.feature |
| aliases (`ee @`, `ee @alias cli ...`) | cli-completions.feature |
| unknown command, invalid shell, missing `--line/--point` | cli-completions.feature, unknown-command.feature |
| does not log to `ee.log` | cli-completions.feature |
| `<file>` positional completion, site type from an existing site (`Site::find`) | not covered: no core command has a `<file>` positional; needs a site |

### `ee cli alias` / `ee cli aliases [--format=yaml|json]` and `@alias` execution

| Item | Covered by |
|---|---|
| empty list (yaml, json) | cli-alias.feature |
| yaml listing with `@all` first | cli-alias.feature |
| json: only alias keys kept, relative `path` absolutized, groups | cli-alias.feature |
| `aliases` alias of `alias` | cli-alias.feature |
| invalid format | cli-alias.feature |
| `EE_RUNTIME_ALIAS` replaces file aliases, filters keys and names | cli-alias.feature |
| run through a local alias, `@all`, a group, a group with an unknown member, unknown alias suggestion, `@all` without aliases | cli-alias.feature |
| aliases with `ssh:` run remotely | cli-alias.feature `@known-bug` (they run locally) |
| ssh schemes (docker, docker-compose, vagrant, ssh), `EE_SSH_PRE_CMD` | not covered: `run_ssh_command()` is dead code in EE; would need a remote host |

### `ee cli has-command <command_name>...`

| Item | Covered by |
|---|---|
| registered commands (quoted and split forms, packages' commands) exit 0 silently | cli-has-command.feature outline (8 rows) |
| unknown commands and empty name exit 1 silently | cli-has-command.feature outline (3 rows) |
| trailing words after a leaf, `site <anything>` | cli-has-command.feature `@known-bug` outline (2 rows) |
| missing argument prints usage | cli-has-command.feature |
| disabled command reported as missing | cli-has-command.feature |

### `ee cli self-uninstall [--yes]`

| Item | Covered by |
|---|---|
| declining the prompt removes nothing | cli.feature `@destructive` |
| full uninstall (containers, networks, volumes, images, site dirs, `/opt/easyengine`) | cli.feature `@destructive` (existing scenario, TEST-3) |
| refuses when docker is down, phar self-delete | not covered: needs docker stopped / a phar install |

### `ee help [<command>...]`

| Item | Covered by |
|---|---|
| `ee`, `ee help`, `ee --help` print the same top-level page, with global parameters | help.feature |
| every top-level command (12) and subcommand (54) has a help page equal to `<command> --help` | help.feature outlines |
| site type routing (`--type=html|wp|php`, default html, wp-only options, unknown type) | help.feature |
| unknown command/subcommand errors and suggestions | help.feature |
| `PAGER`, examples section | help.feature |
| works without docker | requirements.feature |
| no output without `less` | requirements.feature `@known-bug` |
| disabled command | config-file.feature |

### Global parameters and runner behaviour

| Item | Covered by |
|---|---|
| `--debug`, `--debug` before the command, `--debug=<group>`, unknown group, `debug:` in config | global-flags.feature |
| `EE_STRICT_ARGS_MODE` | global-flags.feature |
| color auto-detection (piped, `SHELL_PIPE=0`) | global-flags.feature |
| `--color`, `--no-color`, `color:` in config | global-flags.feature `@known-bug` (3) |
| `--quiet`, `quiet:` in config (warnings, success), errors still shown | global-flags.feature (1 + 3 `@known-bug`) |
| unknown parameter suggestions | cli-info.feature, cli-dump.feature |
| `--sites_path`, `--locale`, `--le-mail`, `--env`, `--wp-mail`, `--sysctl`, `--skip-tty`, `--custom-compose` | runtime parsing covered through `--le-mail` (cli-dump.feature, config-file.feature); their effects belong to the package suites |
| unknown command/subcommand suggestions, shorthand map (`v`, `check`), no suggestion | unknown-command.feature |
| composite command usage, disabled subcommand hidden | unknown-command.feature, config-file.feature (+1 `@known-bug`) |
| missing required parameters | unknown-command.feature |
| requirement checks: missing docker-compose, docker retry/backoff | requirements.feature |
| failed requirement exit status (CORE-1) | requirements.feature `@known-bug` |
| argv logged to `ee.log` with secrets (LOG-1) | global-flags.feature `@known-bug` |
| `BEHAT_RUN`, `EE_PACKAGES_DIR`, `EE_DISABLE_AUTO_CHECK_UPDATE`, `EE_AUTO_CHECK_UPDATE_DAYS`, `EE_PHP` | not covered: only affect error reporting, a packages dir nobody uses, or the phar+tty auto update |

### Config files

| Item | Covered by |
|---|---|
| `EE_CONFIG_PATH` file created with `locale`/`ee_installer_version` defaults | config-file.feature |
| existing values and extra keys kept when defaults are added | config-file.feature |
| path resolved before the defaults file exists (first run reads nothing) | config-file.feature |
| project config `ee.yml`, from a subdirectory, `ee.local.yml` priority, runtime override, none found | config-file.feature |
| `_: inherit:` | config-file.feature |
| `disabled_commands` (run, help, usage, alias) | config-file.feature, cli-has-command.feature |
| unreadable/unwritable config errors | not covered: root bypasses file permissions |
| `_: merge:` for extra config | not covered: only used by package config keys |

### Migration trigger

| Item | Covered by |
|---|---|
| cli/help/config/`site cmd-dump`/no-args never check (14 commands) | migration.feature `@destructive` outline |
| DB version newer: warning for `cli info`, error otherwise, before command lookup, nightly suffix ignored | migration.feature `@destructive` (3) |
| DB version older: full pipeline runs, version bumped | migration.feature `@destructive @migration` |
| versions equal: nothing runs | cli-info.feature |
| fresh install (`ee successfully setup.`), nightly VERSION always migrating, 5 GB disk check, rollback | not covered: needs a wiped machine, a modified VERSION or a full disk; MIG-1..19 are architectural and need the live upgrade tests |

### Phar

| Item | Covered by |
|---|---|
| build from the checkout, version, help, bundled packages registered, `cli info` root | phar.feature `@phar` |
| `EE_PHAR_PATH` is the working directory | phar.feature `@known-bug` |

### Coverage estimate

The inventory has 97 rows: 85 covered and 12 not covered (about 87% of rows, about 80% when rows with several flags are counted per flag). What is left is the real self-update, ssh aliases (dead code), permission errors that root cannot hit, fresh-install and nightly migration paths, and output that depends on live GitHub release data.

## How to run

The suite runs from the core root as root (see `/root/workspace/tools/run-behat.sh core`). `behat.yml` excludes `@destructive` by default.

| Command | Runs |
|---|---|
| `vendor/bin/behat` | everything except `@destructive` (229 scenarios) |
| `vendor/bin/behat -p destructive` | only `@destructive` (20 scenarios: self-uninstall, DB version changes, a real migration) |
| `vendor/bin/behat -p all` | all 249 scenarios |
| `vendor/bin/behat --tags='~@destructive&&~@known-bug&&~@network&&~@phar'` | fast, deterministic subset |

A CLI `--tags` replaces the profile filter, so always add `&&~@destructive` to it unless you mean to run destructive scenarios.

Tags: `@destructive` (20, changes global state; restored where possible), `@migration` (1, runs the real upgrade pipeline, also `@destructive`), `@known-bug` (14, assert the correct behaviour and fail today), `@network` (7, query the GitHub releases API), `@phar` (5, build `ee.phar`, about 1 min). No `@le` or `@remote` scenarios: core has no Let's Encrypt or backup code.

Safety rules built into the suite:

- `Given no EasyEngine migration is pending` guards every scenario whose command reaches `maybe_trigger_migration()` (`cli info`, unknown top-level commands, phar `cli info`). It fails the scenario instead of running a migration when `options.version` differs from `VERSION`, when `VERSION` is a nightly, or when `/opt/easyengine/db/ee.sqlite` is missing (set `EE_BEHAT_ALLOW_INIT=1` to allow the first command to initialise a fresh machine, as the old CI flow did).
- Scenarios that change `options.version` restore it in `@AfterScenario`.
- Config scenarios point `EE_CONFIG_PATH` at a per-scenario file under `/var/tmp/ee-behat-core/` (`{TMP}`), removed after the scenario; the real `/opt/easyengine/config/config.yml` is never written.
- Requirement scenarios hide docker/docker-compose with a private `PATH` instead of stopping anything.
- The suite creates no sites. The `@AfterFeature` cleanup only deletes sites named `bh-easyengine-*`.
- Commands are run through `EE\Process` directly, not `EE::launch()`, so the harness does not write test command lines into `ee.log`.

Estimated runtime: default profile about 3 to 4 minutes (help outlines about 40 s, two 7 s docker-retry scenarios, about 1 min for the phar build, a few seconds of GitHub API calls). `-p destructive` adds the migration scenario (1 to 3 min, image pulls and a cron container restart) and the self-uninstall (about 1 min, after which the next site command re-initialises EasyEngine and pulls about 8 GB of images).

## Known bugs exposed (`@known-bug`)

| Scenario | Issue |
|---|---|
| requirements.feature: failed requirement exits non-zero | CORE-1 |
| global-flags.feature: secrets not written to ee.log | LOG-1 |
| global-flags.feature: `--color`, `--no-color`, `color:` config (3) | new: `php/bootstrap.php` runs InitializeColorization and InitializeLogger before ConfigureRunner, so the runner config is still empty when colors and the logger are chosen |
| global-flags.feature: `--quiet`, `quiet:` config, success with `--quiet` (3) | new: same bootstrap order bug, the Quiet logger is never selected |
| cli-has-command.feature: trailing words / `site <anything>` (2) | new: `find_command_to_run()` stops at the first leaf; `site` is a leaf (`Site_Command::__invoke`) |
| config-file.feature: composite usage prefix | new: `CompositeCommand::show_usage()` counts a skipped disabled subcommand, so the first line loses `usage:` |
| requirements.feature: help without `less` | new: help pipes through `less -r` unless `PAGER` is set; without it nothing is printed and the exit code is 0 |
| cli-alias.feature: ssh alias runs locally | new: `Runner::run_ssh_command()` is never called, so `ee @prod <command>` with `ssh:` runs on the local machine |
| phar.feature: phar path | new: `php/boot-phar.php` defines `EE_PHAR_PATH` as `getcwd()`, so `cli info` shows the working directory (and `phar_safe_path()` uses it) |
