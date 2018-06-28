Feature: CLI Command

  Scenario: ee update nightly works properly
    Given ee phar is generated
    When I run 'sudo php ee.phar cli update --nightly --yes'
    Then return value should be 0

  Scenario: ee update stable works properly
    Given ee phar is generated
    When I run 'sudo php ee.phar cli update --stable --yes'
    Then return value should be 0

  Scenario: ee update works properly
    Given ee phar is generated
    When I run 'sudo php ee.phar cli update --yes'
    Then return value should be 0