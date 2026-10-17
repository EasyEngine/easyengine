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
    And STDERR should match "#^Debug \(bootstrap\): argv: .*cli version --debug \(\d+\.\d+s\)$#m"

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

  # New (suspected): InitializeColorization/InitializeLogger run before ConfigureRunner in
  # php/bootstrap.php, so --color, --no-color and `color:` in the config file are ignored.
  @known-bug
  Scenario: --color forces colors even when piped
    When I run 'bin/ee cli verison --color'
    Then STDERR should contain ANSI color codes

  @known-bug
  Scenario: --no-color disables colors
    When I run 'SHELL_PIPE=0 bin/ee cli verison --no-color'
    Then STDERR should not contain ANSI color codes

  @known-bug
  Scenario: color can be disabled from the config file
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      color: false
      """
    When I run 'SHELL_PIPE=0 EE_CONFIG_PATH={TMP}/config.yml bin/ee cli verison'
    Then STDERR should not contain ANSI color codes

  # New (suspected): same bootstrap order bug, the Quiet logger is never selected, so --quiet
  # and `quiet: true` do not hide warnings. The missing docker-compose path warns without a migration.
  @known-bug
  Scenario: --quiet hides warnings
    Given the executable "docker" is linked into "{TMP}/bin"
    When I run 'PATH={TMP}/bin {PHP} {EE_ROOT}/php/boot-fs.php site list --quiet'
    Then STDERR should contain "Error: EasyEngine requires docker-compose."
    And STDERR should not contain "Warning:"

  @known-bug
  Scenario: quiet can be enabled from the config file
    Given the executable "docker" is linked into "{TMP}/bin"
    And a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      quiet: true
      """
    When I run 'PATH={TMP}/bin EE_CONFIG_PATH={TMP}/config.yml {PHP} {EE_ROOT}/php/boot-fs.php site list'
    Then STDERR should contain "Error: EasyEngine requires docker-compose."
    And STDERR should not contain "Warning:"

  @known-bug @network
  Scenario: --quiet hides success messages
    When I run 'bin/ee cli check-update --major --quiet'
    Then the command should exit with code 0
    And STDOUT should not contain "Success:"

  Scenario: Errors are still printed with --quiet
    When I run 'bin/ee cli verison --quiet'
    Then the command should exit with code 1
    And STDERR should contain "Error: 'verison' is not a registered subcommand of 'cli'."

  # LOG-1 (won't fix for now): the full argv is logged at debug level to ee.log, including secrets.
  @known-bug
  Scenario: Secrets passed on the command line are not written to ee.log
    When I run 'bin/ee cli version --pass=bh-secret-{RAND}'
    Then the command should exit with code 0
    And the file "{EE_ROOT_DIR}/logs/ee.log" should not contain "bh-secret-{RAND}"
