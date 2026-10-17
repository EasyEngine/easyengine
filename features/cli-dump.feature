Feature: ee cli param-dump and cmd-dump

  Scenario: param-dump prints the global parameter spec as json by default
    When I run 'bin/ee cli param-dump'
    Then the command should exit with code 0
    And STDOUT should be valid json
    And the json in STDOUT should have the keys "disabled_commands, sites_path, locale, le-mail, env, wp-mail, sysctl, skip-tty, custom-compose, ee_installer_version, color, debug, quiet"
    And the json in STDOUT at "debug" should have the keys "runtime, file, synopsis, default, multiple, desc"
    And the json in STDOUT at "color.default" should be "auto"
    And the json in STDOUT at "debug.runtime" should be "[=<group>]"
    And the json in STDOUT at "disabled_commands.runtime" should be "false"
    And the json in STDOUT at "quiet.desc" should be "Suppress informational messages."

  Scenario: param-dump without --with-values has no current values
    When I run 'bin/ee cli param-dump'
    Then STDOUT should not contain "current"

  Scenario: param-dump --format=var_export prints a PHP array
    When I run 'bin/ee cli param-dump --format=var_export'
    Then the command should exit with code 0
    And STDOUT should match "#^array \(#"
    And STDOUT should contain "'disabled_commands' =>"
    And STDOUT should contain "'desc' => 'Whether to colorize the output.',"

  Scenario: param-dump rejects an unsupported format
    When I run 'bin/ee cli param-dump --format=yaml'
    Then the command should exit with code 1
    And STDERR should contain "Invalid value specified for 'format'"

  Scenario: param-dump suggests the closest flag for a typo
    When I run 'bin/ee cli param-dump --with-value'
    Then the command should exit with code 1
    And STDERR should contain "unknown --with-value parameter"
    And STDERR should contain "Did you mean '--with-values'?"

  Scenario: param-dump --with-values shows values from the global config file
    Given a file "{TMP}/config.yml" with:
      """
      locale: fr_FR
      ee_installer_version: stable
      le-mail: global@example.test
      disabled_commands:
        - cli alias
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli param-dump --with-values'
    Then the command should exit with code 0
    And the json in STDOUT at "le-mail.current" should be "global@example.test"
    And the json in STDOUT at "locale.current" should be "fr_FR"
    And the json in STDOUT at "disabled_commands.current" should be '["cli alias"]'
    And the json in STDOUT at "sites_path.current" should be "null"
    And the json in STDOUT at "quiet.current" should be "false"

  Scenario: A runtime flag overrides the global config value
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      le-mail: global@example.test
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli param-dump --with-values --le-mail=runtime@example.test'
    Then the command should exit with code 0
    And the json in STDOUT at "le-mail.current" should be "runtime@example.test"

  Scenario: cmd-dump prints the whole command tree as json
    When I run 'bin/ee cli cmd-dump'
    Then the command should exit with code 0
    And STDOUT should be valid json
    And the json in STDOUT at "name" should be "ee"
    And the json in STDOUT at "description" should be "Manage EasyEngine through the command-line."
    And the json in STDOUT at "subcommands.*.name" should contain "admin-tools"
    And the json in STDOUT at "subcommands.*.name" should contain "auth"
    And the json in STDOUT at "subcommands.*.name" should contain "cli"
    And the json in STDOUT at "subcommands.*.name" should contain "config"
    And the json in STDOUT at "subcommands.*.name" should contain "cron"
    And the json in STDOUT at "subcommands.*.name" should contain "dash"
    And the json in STDOUT at "subcommands.*.name" should contain "help"
    And the json in STDOUT at "subcommands.*.name" should contain "log"
    And the json in STDOUT at "subcommands.*.name" should contain "mailhog"
    And the json in STDOUT at "subcommands.*.name" should contain "service"
    And the json in STDOUT at "subcommands.*.name" should contain "shell"
    And the json in STDOUT at "subcommands.*.name" should contain "site"

  Scenario: cmd-dump includes subcommands with their synopsis
    When I run 'bin/ee cli cmd-dump'
    Then the json in STDOUT at "subcommands.name=cli.subcommands.*.name" should contain "has-command"
    And the json in STDOUT at "subcommands.name=cli.subcommands.*.name" should contain "self-uninstall"
    And the json in STDOUT at "subcommands.name=cli.subcommands.name=info.synopsis" should be "[--format=<format>]"
    And the json in STDOUT at "subcommands.name=cli.subcommands.name=has-command.synopsis" should be "<command_name>..."
    And the json in STDOUT at "subcommands.name=cli.subcommands.name=version.description" should be "Print EE version."
    And the json in STDOUT at "subcommands.name=config.subcommands.*.name" should contain "set"

  Scenario: site cmd-dump bypasses the requirement checks and dumps every site type
    When I run 'bin/ee site cmd-dump'
    Then the command should exit with code 0
    And STDOUT should be valid json
    And STDOUT should not contain "Executing migrations"
    And the json in STDOUT at "subcommands.name=site.subcommands.*.name" should contain "create --type=wp"
    And the json in STDOUT at "subcommands.name=site.subcommands.*.name" should contain "create --type=php"
    And the json in STDOUT at "subcommands.*.name" should not contain "site_type_wp"
