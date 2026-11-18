Feature: ee cli has-command

  Scenario Outline: Registered commands exit with 0 and print nothing
    When I run 'bin/ee cli has-command <command>'
    Then the command should exit with code 0
    And STDOUT should be empty
    And STDERR should be empty

    Examples:
      | command             |
      | cli                 |
      | "cli version"       |
      | cli version         |
      | "site create"       |
      | "site delete"       |
      | "config get"        |
      | "service restart"   |
      | help                |

  Scenario Outline: Unknown commands exit with 1 and print nothing
    When I run 'bin/ee cli has-command <command>'
    Then the command should exit with code 1
    And STDOUT should be empty
    And STDERR should be empty

    Examples:
      | command          |
      | foo              |
      | "foo bar"        |
      | ""               |

  Scenario: has-command without a command prints its usage
    When I run 'bin/ee cli has-command'
    Then the command should exit with code 1
    And STDOUT should contain "usage: ee cli has-command <command_name>..."

  Scenario: A command disabled in the config file is reported as missing
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      disabled_commands:
        - cli version
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli has-command "cli version"'
    Then the command should exit with code 1
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli has-command "cli info"'
    Then the command should exit with code 0
