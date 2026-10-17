Feature: ee cli completions

  Scenario: Completing an empty line lists every top-level command
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli completions --line="ee " --point=3'
    Then the command should exit with code 0
    And STDOUT should match "#^cli$#m"
    And STDOUT should match "#^help$#m"
    And STDOUT should match "#^site$#m"
    And STDOUT should match "#^service$#m"
    And STDERR should be empty

  Scenario: A partial subcommand is completed
    When I run 'bin/ee cli completions --line="ee cli ve" --point=100'
    Then the command should exit with code 0
    And STDOUT should be:
      """
      version
      """

  Scenario: Every cli subcommand is offered after "ee cli "
    When I run 'bin/ee cli completions --line="ee cli " --point=7'
    Then STDOUT should be:
      """
      alias
      check-update
      cmd-dump
      completions
      has-command
      info
      param-dump
      self-uninstall
      update
      version
      """

  Scenario: The line is cut at the cursor position
    When I run 'bin/ee cli completions --line="ee cli version" --point=9'
    Then STDOUT should be:
      """
      version
      """

  Scenario: zsh completions include the short description
    When I run 'bin/ee cli completions --line="ee cli ve" --point=100 --shell=zsh'
    Then STDOUT should be:
      """
      version:Print EE version.
      """

  Scenario: Options of a leaf command are completed
    When I run 'bin/ee cli completions --line="ee cli info --" --point=100'
    Then STDOUT should be:
      """
      --format=
      """
    When I run 'bin/ee cli completions --line="ee cli check-update --ma" --point=100'
    Then STDOUT should match "#^--major $#m"

  Scenario: Options already on the line are not offered again
    When I run 'bin/ee cli completions --line="ee cli info --format=json --" --point=100'
    Then the command should exit with code 0
    And STDOUT should be empty

  Scenario: help arguments are completed like commands
    When I run 'bin/ee cli completions --line="ee help cli ve" --point=100'
    Then STDOUT should be:
      """
      version
      """

  Scenario: Site subcommands are completed for the default html type
    When I run 'bin/ee cli completions --line="ee site cr" --point=100'
    Then STDOUT should be:
      """
      create
      """

  Scenario: Site options follow the --type on the line
    When I run 'bin/ee cli completions --line="ee site create --type=wp --admin-u" --point=100'
    Then STDOUT should match "#^--admin-user=$#m"
    When I run 'bin/ee cli completions --line="ee site create --type=html --admin-u" --point=100'
    Then STDOUT should be empty

  Scenario: Aliases from the config file are completed
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      @bh-one:
        path: /var/tmp
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli completions --line="ee @" --point=100'
    Then STDOUT should match "#^@bh-one $#m"
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli completions --line="ee @bh-one cli ve" --point=100'
    Then STDOUT should be:
      """
      version
      """

  Scenario: Nothing is completed for an unknown command
    When I run 'bin/ee cli completions --line="ee nosuchcommand " --point=100'
    Then the command should exit with code 0
    And STDOUT should be empty

  Scenario: completions rejects an unsupported shell
    When I run 'bin/ee cli completions --line="ee " --point=3 --shell=fish'
    Then the command should exit with code 1
    And STDERR should contain "Invalid value specified for 'shell' (Shell for completions output.)"

  Scenario: completions does not write to ee.log
    When I run 'bin/ee cli completions --line="ee cli bh{RAND}" --point=100'
    Then the command should exit with code 0
    And the file "{EE_ROOT_DIR}/logs/ee.log" should not contain "bh{RAND}"
