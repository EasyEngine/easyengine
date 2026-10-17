# Builds ee.phar from the checkout (~1 min, cached for the feature) and checks the phar bootstrap.
# `cli update` is not exercised: from a phar it would really download and swap the phar.
@phar
Feature: Phar build

  Background:
    Given ee phar is generated

  Scenario: The phar reports the source VERSION
    When I run 'php ee.phar cli version'
    Then the command should exit with code 0
    And STDOUT should be:
      """
      EE {EE_VERSION}
      """

  Scenario: The phar serves help pages
    When I run 'php ee.phar help cli'
    Then the command should exit with code 0
    And STDOUT should contain "ee cli"
    And STDOUT should contain "self-uninstall"

  Scenario: The phar registers the bundled command packages
    When I run 'php ee.phar cli has-command "site create"'
    Then the command should exit with code 0
    When I run 'php ee.phar cli has-command "service restart"'
    Then the command should exit with code 0
    When I run 'php ee.phar cli has-command "admin-tools enable"'
    Then the command should exit with code 0

  Scenario: The phar cli info reports the phar as EE root
    Given no EasyEngine migration is pending
    When I run 'php ee.phar cli info --format=json'
    Then the command should exit with code 0
    And the json in STDOUT at "ee_dir_path" should be "phar://ee.phar"
    And the json in STDOUT at "ee_version" should be "{EE_VERSION}"

  # New (suspected): php/boot-phar.php defines EE_PHAR_PATH as getcwd(), so "EE phar path" shows the
  # working directory; phar_safe_path() relies on the same constant.
  @known-bug
  Scenario: The phar cli info shows the path of the phar file
    Given no EasyEngine migration is pending
    When I run 'cd {TMP} && php {EE_ROOT}/ee.phar cli info'
    Then the command should exit with code 0
    And STDOUT should match "#^\| EE phar path\s+\| {EE_ROOT}/ee\.phar\s+\|#m"
