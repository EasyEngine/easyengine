Feature: Global and project config files

  Scenario: A missing EE_CONFIG_PATH file is created with the default keys
    Given the file "{TMP}/new/config.yml" does not exist
    When I run 'EE_CONFIG_PATH={TMP}/new/config.yml bin/ee cli version'
    Then the command should exit with code 0
    And the file "{TMP}/new/config.yml" should contain "locale: en_US"
    And the file "{TMP}/new/config.yml" should contain "ee_installer_version: stable"

  Scenario: Missing default keys are added without touching existing values
    Given a file "{TMP}/config.yml" with:
      """
      locale: fr_FR
      le-mail: behat@example.test
      rclone-path: bh-remote:bucket/path
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli version'
    Then the command should exit with code 0
    And the file "{TMP}/config.yml" should contain "locale: fr_FR"
    And the file "{TMP}/config.yml" should contain "le-mail: behat@example.test"
    And the file "{TMP}/config.yml" should contain "rclone-path: bh-remote:bucket/path"
    And the file "{TMP}/config.yml" should contain "ee_installer_version: stable"
    And the file "{TMP}/config.yml" should not contain "locale: en_US"

  Scenario: The config path is resolved before the defaults file is created
    Given the file "{TMP}/new/config.yml" does not exist
    When I run 'EE_CONFIG_PATH={TMP}/new/config.yml bin/ee cli version --debug'
    Then STDERR should contain "Debug (bootstrap): No readable global config found"
    When I run 'EE_CONFIG_PATH={TMP}/new/config.yml bin/ee cli version --debug'
    Then STDERR should contain "Debug (bootstrap): Using global config from EE_CONFIG_PATH env var: {TMP}/new/config.yml"

  Scenario: Without a project config file the debug output says so
    When I run 'cd {TMP} && EE_CONFIG_PATH={TMP}/config.yml {EE} cli version --debug'
    Then STDERR should contain "Debug (bootstrap): No project config found"

  Scenario: ee.yml in the working directory is used as project config
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      le-mail: global@example.test
      """
    And a file "{TMP}/project/ee.yml" with:
      """
      le-mail: project@example.test
      """
    When I run 'cd {TMP}/project && EE_CONFIG_PATH={TMP}/config.yml {EE} cli param-dump --with-values'
    Then the command should exit with code 0
    And the json in STDOUT at "le-mail.current" should be "project@example.test"
    When I run 'cd {TMP}/project && EE_CONFIG_PATH={TMP}/config.yml {EE} cli version --debug'
    Then STDERR should contain "Debug (bootstrap): Using project config: {TMP}/project/ee.yml"

  Scenario: The project config is found from a subdirectory
    Given a file "{TMP}/project/ee.yml" with:
      """
      le-mail: project@example.test
      """
    And the directory "{TMP}/project/sub/dir" exists
    When I run 'cd {TMP}/project/sub/dir && EE_CONFIG_PATH={TMP}/config.yml {EE} cli param-dump --with-values'
    Then the json in STDOUT at "le-mail.current" should be "project@example.test"

  Scenario: ee.local.yml takes priority over ee.yml
    Given a file "{TMP}/project/ee.yml" with:
      """
      le-mail: project@example.test
      """
    And a file "{TMP}/project/ee.local.yml" with:
      """
      le-mail: local@example.test
      """
    When I run 'cd {TMP}/project && EE_CONFIG_PATH={TMP}/config.yml {EE} cli param-dump --with-values'
    Then the json in STDOUT at "le-mail.current" should be "local@example.test"

  Scenario: A runtime flag overrides the project config
    Given a file "{TMP}/project/ee.yml" with:
      """
      le-mail: project@example.test
      """
    When I run 'cd {TMP}/project && EE_CONFIG_PATH={TMP}/config.yml {EE} cli param-dump --with-values --le-mail=runtime@example.test'
    Then the json in STDOUT at "le-mail.current" should be "runtime@example.test"

  Scenario: cli info reports the project config file
    Given no EasyEngine migration is pending
    And a file "{TMP}/project/ee.yml" with:
      """
      le-mail: project@example.test
      """
    When I run 'cd {TMP}/project && EE_CONFIG_PATH={TMP}/config.yml {EE} cli info --format=json'
    Then the command should exit with code 0
    And the json in STDOUT at "project_config_path" should be "{TMP}/project/ee.yml"

  Scenario: A config file can inherit another one
    Given a file "{TMP}/base.yml" with:
      """
      le-mail: inherited@example.test
      wp-mail: base@example.test
      """
    And a file "{TMP}/config.yml" with:
      """
      _:
        inherit: {TMP}/base.yml
      locale: en_US
      ee_installer_version: stable
      wp-mail: child@example.test
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli param-dump --with-values'
    Then the json in STDOUT at "le-mail.current" should be "inherited@example.test"
    And the json in STDOUT at "wp-mail.current" should be "child@example.test"

  Scenario: A disabled command cannot be run
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      disabled_commands:
        - cli version
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli version'
    Then the command should exit with code 1
    And STDOUT should be empty
    And STDERR should be:
      """
      Error: The 'cli version' command has been disabled from the config file.
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli param-dump'
    Then the command should exit with code 0

  Scenario: help for a disabled command reports that it is disabled
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      disabled_commands:
        - cli version
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee help cli version'
    Then the command should exit with code 1
    And STDERR should contain "Error: The 'cli version' command has been disabled from the config file."

  Scenario: A disabled subcommand is left out of the composite usage
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      disabled_commands:
        - cli version
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli'
    Then the command should exit with code 0
    And STDOUT should contain "ee cli update"
    And STDOUT should not contain "ee cli version"

  Scenario: Disabling a command also disables its alias
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      disabled_commands:
        - cli alias
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli aliases'
    Then the command should exit with code 1
    And STDERR should contain "has been disabled from the config file."

  Scenario: A command section in the config file sets that command's default flags
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      cli param-dump:
        format: var_export
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli param-dump'
    Then the command should exit with code 0
    And STDOUT should match "#^array \(#"
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli param-dump --format=json'
    Then the command should exit with code 0
    And STDOUT should be valid json

  Scenario: help leaves disabled commands out of the command lists
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      disabled_commands:
        - cli version
        - mailhog
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee help cli'
    Then the command should exit with code 0
    And STDOUT should match "#^  update\s#m"
    And STDOUT should not match "#^  version\s#m"
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee help'
    Then the command should exit with code 0
    And STDOUT should match "#^  log\s#m"
    And STDOUT should not match "#^  mailhog\s#m"

  Scenario: Disabling a namespace disables its subcommands
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      disabled_commands:
        - cli
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli version'
    Then the command should exit with code 1
    And STDERR should contain "Error: The 'cli' command has been disabled from the config file."

  Scenario: Commands from EE_PACKAGES_DIR are loaded
    Given a file "{TMP}/pkgs/vendor/autoload.php" with:
      """
      <?php
      EE::add_command( 'cli bh-pkg', function () { EE::line( 'bh-pkg ran' ); } );
      """
    When I run 'EE_PACKAGES_DIR={TMP}/pkgs bin/ee cli bh-pkg --debug'
    Then the command should exit with code 0
    And STDOUT should be:
      """
      bh-pkg ran
      """
    And STDERR should contain "Debug (bootstrap): Loading packages from: {TMP}/pkgs/vendor/autoload.php"
    When I run 'bin/ee cli has-command "cli bh-pkg"'
    Then the command should exit with code 1
