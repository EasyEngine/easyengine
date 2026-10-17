# The runner compares options.version in /opt/easyengine/db/ee.sqlite with VERSION:
# - cli, config and help commands (and `site cmd-dump`) never check; `cli info` checks only when docker is up;
# - a DB version newer than VERSION: warning for `cli`, error for everything else, nothing migrated;
# - an older DB version: the full upgrade pipeline runs (db, custom container and image migrations,
#   docker-compose update, cron config rewrite, logrotate) and the DB version is bumped.
# Every scenario here changes options.version, which the context restores after the scenario.
@destructive
Feature: Migration triggering

  Scenario Outline: Commands that skip the requirement checks never run the migration
    Given no EasyEngine migration is pending
    And the EasyEngine option "version" is set to "4.0.0"
    When I run '<command>'
    Then STDOUT should not contain "Executing migrations"
    And STDERR should not contain "It seems you're not running latest version"
    And the EasyEngine option "version" should be "4.0.0"

    Examples:
      | command                                              |
      | bin/ee                                               |
      | bin/ee --version                                     |
      | bin/ee cli version                                   |
      | bin/ee help                                          |
      | bin/ee help site create                              |
      | bin/ee site create --help                            |
      | bin/ee cli has-command site                          |
      | bin/ee cli completions --line="ee " --point=3        |
      | bin/ee cli param-dump                                |
      | bin/ee cli cmd-dump                                  |
      | bin/ee cli alias                                     |
      | bin/ee cli update                                    |
      | bin/ee site cmd-dump                                 |
      | bin/ee config list                                   |

  Scenario: A newer DB version makes cli info warn and other commands refuse to run
    Given no EasyEngine migration is pending
    And the EasyEngine option "version" is set to "99.0.0"
    When I run 'bin/ee cli info'
    Then the command should exit with code 0
    And STDERR should contain:
      """
      Warning: It seems you're not running latest version. Update EasyEngine using `ee cli update --stable --yes`.
      """
    And STDOUT should contain "EE version"
    When I run 'bin/ee site list'
    Then the command should exit with code 1
    And STDERR should contain:
      """
      Error: It seems you're not running latest version. Update EasyEngine using `ee cli update --stable --yes`.
      """
    And the EasyEngine option "version" should be "99.0.0"

  Scenario: The version check runs before an unknown command is looked up
    Given no EasyEngine migration is pending
    And the EasyEngine option "version" is set to "99.0.0"
    When I run 'bin/ee sitee'
    Then the command should exit with code 1
    And STDERR should contain "Error: It seems you're not running latest version."
    And STDERR should not contain "is not a registered ee command"

  Scenario: The nightly suffix is ignored when comparing versions
    Given no EasyEngine migration is pending
    And the EasyEngine option "version" is set to "99.0.0-nightly-bh"
    When I run 'bin/ee site list'
    Then the command should exit with code 1
    And STDERR should contain "Error: It seems you're not running latest version."

  # Runs a real migration: image pulls (MIG-4), cron container restart and log rotation (MIG-5).
  @migration
  Scenario: An older DB version triggers the upgrade on the next checked command
    Given no EasyEngine migration is pending
    And the EasyEngine option "version" is set to "4.0.0"
    When I run 'bin/ee cli info'
    Then the command should exit with code 0
    And STDOUT should contain "Executing migrations. This might take some time."
    And the EasyEngine option "version" should be "{EE_VERSION}"
