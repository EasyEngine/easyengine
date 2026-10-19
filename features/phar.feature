# Builds a phar from a private copy of the checkout into /var/tmp (~1 min, once per run) and checks the phar bootstrap.
# `cli update` is not exercised: from a phar it would really download and swap the phar.
# EE_DISABLE_AUTO_CHECK_UPDATE keeps `help` from ever offering a self-update of the built phar.
@phar
Feature: Phar build

  Background:
    Given ee phar is generated

  Scenario: The phar reports the source VERSION
    When I run '{PHP} {PHAR} cli version'
    Then the command should exit with code 0
    And STDOUT should be:
      """
      EE {EE_VERSION}
      """

  Scenario: The phar serves help pages
    When I run 'EE_DISABLE_AUTO_CHECK_UPDATE=1 {PHP} {PHAR} help cli'
    Then the command should exit with code 0
    And STDOUT should contain "ee cli"
    And STDOUT should contain "self-uninstall"

  Scenario: The phar registers the bundled command packages
    When I run '{PHP} {PHAR} cli has-command "site create"'
    Then the command should exit with code 0
    When I run '{PHP} {PHAR} cli has-command "service restart"'
    Then the command should exit with code 0
    When I run '{PHP} {PHAR} cli has-command "admin-tools enable"'
    Then the command should exit with code 0

  Scenario: The phar cli info reports the phar as EE root
    Given no EasyEngine migration is pending
    When I run '{PHP} {PHAR} cli info --format=json'
    Then the command should exit with code 0
    And the json in STDOUT at "ee_dir_path" should be "phar://ee.phar"
    And the json in STDOUT at "ee_version" should be "{EE_VERSION}"
