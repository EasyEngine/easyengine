Feature: ee cli version

  Scenario: cli version prints the source VERSION
    When I run 'bin/ee cli version'
    Then the command should exit with code 0
    And STDOUT should be:
      """
      EE {EE_VERSION}
      """
    And STDERR should be empty

  Scenario: The global --version flag is an alias of cli version
    When I run 'bin/ee --version'
    Then the command should exit with code 0
    And STDOUT should be:
      """
      EE {EE_VERSION}
      """

  Scenario: cli version takes no arguments, so extra ones are ignored
    When I run 'bin/ee cli version extra --unknown-flag'
    Then the command should exit with code 0
    And STDOUT should be:
      """
      EE {EE_VERSION}
      """

  Scenario: cli version --help shows the command help instead of the version
    When I run 'bin/ee cli version --help'
    Then the command should exit with code 0
    And STDOUT should contain "ee cli version"
    And STDOUT should contain "Print EE version."
    And STDOUT should not contain "EE {EE_VERSION}"

  Scenario: cli version does not need docker or docker-compose
    Given the directory "{TMP}/emptybin" exists
    When I run 'PATH={TMP}/emptybin {PHP} {EE_ROOT}/php/boot-fs.php cli version'
    Then the command should exit with code 0
    And STDOUT should be:
      """
      EE {EE_VERSION}
      """
