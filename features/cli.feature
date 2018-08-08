Feature: CLI Command

  Scenario: ee uninstall works properly
    When I run 'sudo bin/ee cli self-uninstall --yes'
    Then ee should be deleted
