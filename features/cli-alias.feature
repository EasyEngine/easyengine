Feature: ee cli alias and @alias execution

  Scenario: No aliases are listed for a config without aliases
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli alias'
    Then the command should exit with code 0
    And STDOUT should be:
      """
      ---
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli alias --format=json'
    Then STDOUT should be:
      """
      []
      """

  Scenario: Aliases from the config file are listed with @all first
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      @bh-one:
        path: /var/tmp
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli alias'
    Then the command should exit with code 0
    And STDOUT should be:
      """
      ---
      @all: Run command against every registered alias.
      @bh-one:
        path: /var/tmp
      """

  Scenario: Alias json output keeps only alias keys and absolutizes relative paths
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      @bh-one:
        path: /var/tmp
        bogus: ignored
      @bh-rel:
        path: relative/dir
      @bh-group:
        - @bh-one
        - @bh-rel
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli alias --format=json'
    Then the command should exit with code 0
    And the json in STDOUT should have the keys "@all, @bh-one, @bh-rel, @bh-group"
    And the json in STDOUT at "@all" should be "Run command against every registered alias."
    And the json in STDOUT at "@bh-one" should be '{"path":"/var/tmp"}'
    And the json in STDOUT at "@bh-rel.path" should be "{TMP}/relative/dir"
    And the json in STDOUT at "@bh-group" should be '["@bh-one","@bh-rel"]'

  Scenario: cli aliases is an alias of cli alias
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      @bh-one:
        path: /var/tmp
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli alias --format=json'
    And I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli aliases --format=json'
    Then the command should exit with code 0
    And STDOUT of command 1 should be identical to STDOUT of command 2

  Scenario: cli alias rejects an unsupported format
    When I run 'bin/ee cli alias --format=table'
    Then the command should exit with code 1
    And STDERR should contain "Invalid value specified for 'format'"

  Scenario: Aliases can be provided at runtime with EE_RUNTIME_ALIAS
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      @bh-file:
        path: /var/tmp
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml EE_RUNTIME_ALIAS='{"@bh-rt":{"path":"/srv","bogus":"x"},"not-an-alias":{"path":"/x"}}' bin/ee cli alias --format=json'
    Then the command should exit with code 0
    And the json in STDOUT should have the keys "@all, @bh-rt"
    And the json in STDOUT at "@bh-rt" should be '{"path":"/srv"}'
    And STDOUT should not contain "@bh-file"
    And STDOUT should not contain "not-an-alias"

  Scenario: A command runs locally through an alias without ssh
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      @bh-one:
        path: /var/tmp
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee @bh-one cli version'
    Then the command should exit with code 0
    And STDOUT should be:
      """
      EE {EE_VERSION}
      """

  Scenario: @all runs the command once per alias
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      @bh-one:
        path: /var/tmp
      @bh-two:
        path: /var/tmp
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee @all cli version'
    Then the command should exit with code 0
    And STDOUT should be:
      """
      @bh-one
      EE {EE_VERSION}
      @bh-two
      EE {EE_VERSION}
      """

  Scenario: An alias group runs the command for each member
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      @bh-one:
        path: /var/tmp
      @bh-two:
        path: /var/tmp
      @bh-group:
        - @bh-two
        - @bh-one
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee @bh-group cli version'
    Then the command should exit with code 0
    And STDOUT should be:
      """
      @bh-two
      EE {EE_VERSION}
      @bh-one
      EE {EE_VERSION}
      """

  Scenario: A group with an unknown member is rejected
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      @bh-one:
        path: /var/tmp
      @bh-broken:
        - @bh-one
        - @bh-missing
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee @bh-broken cli version'
    Then the command should exit with code 1
    And STDERR should contain "Error: Group '@bh-broken' contains one or more invalid aliases: @bh-missing"

  Scenario: An unknown alias fails with a suggestion
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      @bh-one:
        path: /var/tmp
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee @bh-onee cli version'
    Then the command should exit with code 1
    And STDERR should be:
      """
      Error: Alias '@bh-onee' not found.
      Did you mean '@bh-one'?
      """

  Scenario: @all fails when no alias is registered
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee @all cli version'
    Then the command should exit with code 1
    And STDERR should contain "Error: Cannot use '@all' when no aliases are registered."

  # New (suspected): Runner::run_ssh_command() is never called, so an alias with `ssh:` silently runs the
  # command on the local machine instead of the remote one.
  @known-bug
  Scenario: A command for an ssh alias is not run locally
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      @bh-remote:
        ssh: bh-nobody@127.0.0.1:1
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee @bh-remote cli version'
    Then the command should fail
    And STDOUT should not contain "EE {EE_VERSION}"
