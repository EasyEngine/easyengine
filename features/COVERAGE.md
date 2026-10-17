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
| runs the migration check only when docker is up | migration.feature `@destructive`: "cli info skips the version check when docker is not available" and the newer/older version scenarios; requirements.feature: "cli info still works when docker is not available" |
| no migration when versions match | cli-info.feature: "cli info lists every diagnostic row" (no "Executing migrations") |
| phar values (`ee_dir_path` = `phar://ee.phar`, phar path) | phar.feature (1 + 1 `@known-bug`) |

### `ee cli check-update [--patch] [--minor] [--major] [--field] [--fields] [--format]`

| Item | Covered by |
|---|---|
| default table or "latest version" success | cli-update.feature `@network` |
| `--major`, `--minor`, `--patch` | cli-update.feature `@network` outline |
| `--format=json`, `--format=count` | cli-update.feature `@network` |
| invalid `--format` (validated before any request) | cli-update.feature |
| GitHub unreachable: clean error, exit 1 | cli-update.feature (proxy to a closed port, `no_proxy` cleared, no network needed) |
| HTTP error status (`GITHUB_TOKEN` sent, invalid token gives 401) | cli-update.feature `@network` |
| `--field`, `--fields`, `--format=csv|yaml`, precedence of several type flags | not covered: output depends on live release data; formatter covered by package suites |
| nightly branch (`-alpha-` versions, `NIGHTLY_VERSION`) | not covered: needs a nightly VERSION |

### `ee cli update [--stable] [--nightly] [--yes]`

| Item | Covered by |
|---|---|
| refuses to run from source (`You can only self-update Phar files.`) with every flag combination | cli-update.feature outline (4 rows) |
| real update (prompt, download, md5 check, child `cli info`, phar swap), macOS brew message, not-writable errors | not covered on purpose: an accepted prompt downloads a phar whose `cli info` runs the new version's migrations on the live DB (MIG-6); a declined prompt or an immutable (`chattr +i`) phar would be testable but a regression there would do exactly that; not on macOS |
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
| `site cmd-dump` merges site types (`create --type=wp`) | cli-dump.feature |
| `site cmd-dump` bypasses the requirement checks (works without docker) | cli-dump.feature: "site cmd-dump works without docker"; migration.feature outline |

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
| unknown `--type` in site completions doesn't crash | cli-completions.feature `@known-bug` (CORE-13) |
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
| a group passes the command's flags to every member | cli-alias.feature |
| `@all`/group exit status, `@all` running group members twice | cli-alias.feature `@known-bug` (2, CORE-12) |
| aliases with `ssh:`/`http:`, `--ssh`, `--http`, `ssh:` in config | cli-alias.feature: refused (CORE-4, core `fix/refuse-remote-aliases`; they ran locally before) |
| ssh schemes (docker, docker-compose, vagrant, ssh), `EE_SSH_PRE_CMD` | not covered: `run_ssh_command()` is dead code in EE; would need a remote host |

### `ee cli has-command <command_name>...`

| Item | Covered by |
|---|---|
| registered commands (quoted and split forms, packages' commands) exit 0 silently | cli-has-command.feature outline (8 rows) |
| unknown commands and empty name exit 1 silently | cli-has-command.feature outline (3 rows) |
| trailing words after a leaf (`cli version extra`) | cli-has-command.feature `@known-bug` (CORE-6) |
| `site <anything>` is "registered" (site is a leaf) | not covered: documented in CORE-6; the passing `site create` rows use the same shortcut |
| missing argument prints usage | cli-has-command.feature |
| disabled command reported as missing | cli-has-command.feature |

### `ee cli self-uninstall [--yes]`

| Item | Covered by |
|---|---|
| declining the prompt removes nothing | cli.feature `@destructive` |
| full uninstall (containers, networks, volumes, images, `/opt/easyengine`; site dirs not checked) | cli.feature `@destructive @uninstall` (existing scenario, TEST-3) |
| refuses when docker is down (and its exit status) | requirements.feature (+1 `@known-bug`, CORE-11) |
| phar self-delete | not covered: needs a phar install, and would delete it |

### `ee help [<command>...]`

| Item | Covered by |
|---|---|
| `ee`, `ee help`, `ee --help` print the same top-level page, with global parameters | help.feature |
| every top-level command (12) and subcommand (54) has a help page equal to `<command> --help` | help.feature outlines |
| site type routing (`--type=html|wp|php`, default html, wp-only options, unknown type) | help.feature (+1 `@known-bug`, CORE-10) |
| unknown command/subcommand errors and suggestions | help.feature |
| `PAGER` (a `sed` pager that prefixes every line), examples section | help.feature |
| works without docker | requirements.feature |
| no output without `less` | requirements.feature `@known-bug` (CORE-5) |
| disabled command: error, left out of `ee help` and `ee help cli` lists | config-file.feature |

### Global parameters and runner behaviour

| Item | Covered by |
|---|---|
| `--debug`, `--debug` before the command, `--debug=<group>`, unknown group, `debug:` in config | global-flags.feature |
| `EE_STRICT_ARGS_MODE` | global-flags.feature |
| color auto-detection (piped, `SHELL_PIPE=0`) | global-flags.feature |
| `--color`, `--no-color`, `color:` in config | global-flags.feature `@known-bug` (3, CORE-3) |
| `--quiet`, `quiet:` in config (warnings, success), errors still shown | global-flags.feature (1 + 3 `@known-bug`, CORE-3) |
| unknown parameter suggestions | cli-info.feature, cli-dump.feature |
| `--sites_path`, `--locale`, `--le-mail`, `--env`, `--wp-mail`, `--sysctl`, `--skip-tty`, `--custom-compose` | partly: only `--le-mail` (runtime) and `locale`/`le-mail`/`wp-mail` (file) are exercised (cli-dump.feature, config-file.feature); their effects belong to the package suites |
| unknown command/subcommand suggestions, shorthand map (`v`, `check`), no suggestion | unknown-command.feature |
| unknown top-level command runs the checks before the lookup | unknown-command.feature (guarded) + `@known-bug` (CORE-9), migration.feature `@destructive` |
| composite command usage, disabled subcommand hidden | unknown-command.feature, config-file.feature (+1 `@known-bug`) |
| missing required parameters | unknown-command.feature |
| requirement checks: missing docker-compose, docker retry/backoff | requirements.feature |
| failed requirement exit status (CORE-1) | requirements.feature `@known-bug` |
| argv logged to `ee.log` with secrets (LOG-1) | global-flags.feature `@known-bug` |
| `EE_PACKAGES_DIR` (package autoloader loaded, commands registered) | config-file.feature |
| `BEHAT_RUN`, `EE_DISABLE_AUTO_CHECK_UPDATE`, `EE_AUTO_CHECK_UPDATE_DAYS`, `EE_PHP` | not covered: only affect error reporting or the phar+tty auto update (the phar scenarios set `EE_DISABLE_AUTO_CHECK_UPDATE`) |

### Config files

| Item | Covered by |
|---|---|
| `EE_CONFIG_PATH` file created with `locale`/`ee_installer_version` defaults | config-file.feature |
| existing values and extra keys kept when defaults are added | config-file.feature |
| path resolved before the defaults file exists (first run reads nothing) | config-file.feature |
| project config `ee.yml`, from a subdirectory, `ee.local.yml` priority, runtime override, none found | config-file.feature |
| `_: inherit:` | config-file.feature |
| `disabled_commands` (run, help, usage, alias, a whole namespace) | config-file.feature, cli-has-command.feature |
| per-command defaults (a `cli param-dump:` section), runtime flag wins | config-file.feature |
| unreadable/unwritable config errors | not covered: root bypasses file permissions; only reachable with `chattr +i` or `/proc` paths (fragile) |
| `_: merge:` for extra config, relative `inherit:` paths | not covered |

### Migration trigger

| Item | Covered by |
|---|---|
| cli/help/config/`site cmd-dump`/no-args never check (14 commands) | migration.feature `@destructive` outline (with a NEWER DB version, so a regression errors instead of migrating) |
| DB version newer: warning for `cli info`, error otherwise, before command lookup, a newer nightly DB version | migration.feature `@destructive` (3) |
| nightly suffix stripping itself | not covered: a distinguishing test would run a real migration when it fails |
| DB version older: full pipeline runs, version bumped | migration.feature `@destructive @migration` (never on a stack in use) |
| versions equal: nothing runs | cli-info.feature |
| fresh install (`ee successfully setup.`), nightly VERSION always migrating, 5 GB disk check, rollback | not covered: needs a wiped machine, a modified VERSION or a full disk; MIG-1..19 are architectural and need the live upgrade tests |

### Phar

| Item | Covered by |
|---|---|
| build (from a `--no-dev` copy of the checkout into `/var/tmp`), version, help, bundled packages registered, `cli info` root | phar.feature `@phar` |
| `EE_PHAR_PATH` is the working directory | phar.feature `@known-bug` (CORE-8) |


### Coverage estimate

The inventory has 107 rows: 93 covered, 1 partly covered and 13 not covered (about 87% of rows, about 78% when rows with several flags are counted per flag). What is left is the real self-update and phar self-delete (unsafe on a live stack), ssh aliases (never wired up; refused since CORE-4), permission errors that root cannot hit, fresh-install and nightly migration paths, and output that depends on live GitHub release data.

## How to run

The suite runs from the core root as root (see `/root/workspace/tools/run-behat.sh core`).

| Command | Runs |
|---|---|
| `vendor/bin/behat` | everything except `@destructive` (242 scenarios) |
| `EE_BEHAT_DESTRUCTIVE=1 vendor/bin/behat -p destructive` | `@destructive` without `@migration`/`@uninstall` (19 scenarios: DB version changes, the declined self-uninstall prompt) |
| `EE_BEHAT_DESTRUCTIVE=1 EE_BEHAT_ALLOW_MIGRATION=1 vendor/bin/behat -p migration` | the real upgrade migration (1 scenario; never on a stack in use) |
| `EE_BEHAT_DESTRUCTIVE=1 EE_BEHAT_ALLOW_UNINSTALL=1 vendor/bin/behat -p uninstall` | `ee cli self-uninstall --yes` (1 scenario; run it alone and last) |
| `EE_BEHAT_DESTRUCTIVE=1 vendor/bin/behat -p all` | everything that can run in one pass (261 scenarios) |
| `vendor/bin/behat --tags='~@destructive&&~@known-bug&&~@network&&~@phar'` | fast, deterministic subset (212 scenarios) |

A CLI `--tags` replaces the profile filter, so the tags alone can't keep the dangerous scenarios off: a `@BeforeScenario` hook refuses (fails) every `@destructive`, `@migration` or `@uninstall` scenario whose env var (`EE_BEHAT_DESTRUCTIVE`, `EE_BEHAT_ALLOW_MIGRATION`, `EE_BEHAT_ALLOW_UNINSTALL`) isn't set. On a machine without `/opt/easyengine/db/ee.sqlite` (a fresh CI runner) also set `EE_BEHAT_ALLOW_INIT=1`.

Tags: `@destructive` (21, changes global state; restored where possible), `@migration` (1, the real upgrade pipeline), `@uninstall` (1, self-uninstall), `@known-bug` (19, assert the correct behaviour and fail today), `@network` (8, query the GitHub releases API; unauthenticated calls share GitHub's 60/hour limit), `@phar` (5, build a phar, about 1 min). No `@le` or `@remote` scenarios: core has no Let's Encrypt or backup code.

Safety rules built into the suite:

- `Given no EasyEngine migration is pending` guards every scenario whose command reaches `maybe_trigger_migration()` with docker available (`cli info`, unknown top-level commands, phar `cli info`, the self-uninstall). It fails the scenario instead of running a migration when `options.version` differs from `VERSION`, when `VERSION` is a nightly, or when `/opt/easyengine/db/ee.sqlite` is missing (unless `EE_BEHAT_ALLOW_INIT=1`, which lets the first command initialise a fresh machine, as the old CI flow did).
- Only the `@migration` scenario writes an older `options.version`; the others use `99.0.0`, so a regression, or an `ee` run by cron or another user meanwhile, gets the "not running latest version" error instead of a migration. Changed options are restored in `@AfterScenario` and, if the run dies, by a shutdown function (Behat exits on Ctrl-C) and a SIGTERM handler when pcntl is available.
- The phar is built from a `--no-dev` copy of the checkout under `/var/tmp/ee-behat-core/phar-<pid>/` and removed after the suite; the checkout, its `vendor/` autoloader and `/usr/local/bin/ee` are never touched, `cli update` is never run from the phar and `help` runs with `EE_DISABLE_AUTO_CHECK_UPDATE=1`.
- Config scenarios point `EE_CONFIG_PATH` at a per-scenario file under `/var/tmp/ee-behat-core/` (`{TMP}`), removed after the scenario; the real `/opt/easyengine/config/config.yml` is never written (scenarios without `EE_CONFIG_PATH` read it, so `debug:`, aliases or `disabled_commands` there would break exact-output checks).
- Requirement scenarios hide docker/docker-compose with a private `PATH` instead of stopping anything.
- The suite creates no sites. The `@AfterFeature` cleanup only deletes sites named `bh-easyengine-*`.
- Commands are run through `EE\Process` directly, not `EE::launch()`, so the harness does not write test command lines into `ee.log`.

Estimated runtime: default profile about 4 minutes (help outlines about 40 s, five 7 s docker-retry scenarios, about 1 min for the phar build, a few seconds of GitHub API calls). `-p migration` takes 1 to 3 min (image pulls, a cron container restart) and `-p uninstall` about 1 min, after which the next site command re-initialises EasyEngine and pulls about 8 GB of images.

## Known bugs exposed (`@known-bug`)

| Scenario | Issue |
|---|---|
| requirements.feature: failed requirement exits non-zero | CORE-1 |
| global-flags.feature: secrets not written to ee.log | LOG-1 |
| global-flags.feature: `--color`, `--no-color`, `color:` config, `--quiet`, `quiet:` config, success with `--quiet` (6) | CORE-3: `php/bootstrap.php` sets up colours and the logger before reading the config |
| requirements.feature: help without `less` | CORE-5: nothing printed, exit 0 |
| cli-has-command.feature: trailing words after a leaf | CORE-6: `has-command` stops at the first leaf |
| config-file.feature: composite usage prefix | CORE-7: `CompositeCommand::show_usage()` counts the skipped disabled subcommand |
| phar.feature: phar path | CORE-8: `EE_PHAR_PATH` is `getcwd()` |
| unknown-command.feature: typo reported without docker | CORE-9: checks run before the command lookup |
| help.feature: hint for an unknown site type | CORE-10 (site-command): the hint repeats the bogus type |
| requirements.feature: self-uninstall refusal exit status | CORE-11 |
| cli-alias.feature: `@all` exit status, `@all` running group members twice (2) | CORE-12 |
| cli-completions.feature: unknown `--type` in site completions | CORE-13: uncaught `ReflectionException` |
