Feature: Global parameters (--debug, --quiet, --color)

  Scenario: Without --debug nothing is written to STDERR
    When I run 'bin/ee cli version'
    Then STDERR should be empty

  Scenario: --debug prints bootstrap messages to STDERR only
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli version --debug'
    Then the command should exit with code 0
    And STDOUT should be:
      """
      EE {EE_VERSION}
      """
    And STDERR should contain "Debug (bootstrap): Using global config from EE_CONFIG_PATH env var: {TMP}/config.yml"
    And STDERR should contain "Debug (bootstrap): Running command: cli version"
    And STDERR should match "#^Debug \(bootstrap\): argv: .*cli version --debug \(\d+(\.\d+)?s\)$#m"

  Scenario: --debug is accepted before the command too
    When I run 'bin/ee --debug cli version'
    Then the command should exit with code 0
    And STDERR should contain "Debug (bootstrap): Running command: cli version"

  Scenario: --debug=<group> shows only that group, without the group label
    When I run 'bin/ee cli version --debug=bootstrap'
    Then STDERR should contain "Debug: Running command: cli version"
    And STDERR should not contain "Debug (bootstrap)"

  Scenario: --debug with an unknown group shows nothing
    When I run 'bin/ee cli version --debug=bh-no-such-group'
    Then the command should exit with code 0
    And STDERR should be empty

  Scenario: debug can be enabled from the config file
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      debug: true
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli version'
    Then STDERR should contain "Debug (bootstrap): Running command: cli version"

  Scenario: In strict args mode a global flag after the command is not global
    When I run 'EE_STRICT_ARGS_MODE=1 bin/ee cli version --debug'
    Then the command should exit with code 0
    And STDERR should be empty
    When I run 'EE_STRICT_ARGS_MODE=1 bin/ee --debug cli version'
    Then STDERR should contain "Debug (bootstrap): Running command: cli version"

  Scenario: Output is not colorized when it is piped
    When I run 'bin/ee cli verison'
    Then STDERR should contain "Error: 'verison' is not a registered subcommand of 'cli'."
    And STDERR should not contain ANSI color codes

  Scenario: SHELL_PIPE=0 makes the automatic color detection colorize
    When I run 'SHELL_PIPE=0 bin/ee cli verison'
    Then STDERR should contain ANSI color codes

  Scenario: Errors are still printed with --quiet
    When I run 'bin/ee cli verison --quiet'
    Then the command should exit with code 1
    And STDERR should contain "Error: 'verison' is not a registered subcommand of 'cli'."

  # LOG-1 (fixed in v4.13.2, easyengine#1944), regression check: the full argv used to be logged at debug level to ee.log, secrets included.
  Scenario: Secrets passed on the command line are not written to ee.log
    When I run 'bin/ee cli version --pass=bh-secret-{RAND}'
    Then the command should exit with code 0
    And the file "{EE_ROOT_DIR}/logs/ee.log" should not contain "bh-secret-{RAND}"
