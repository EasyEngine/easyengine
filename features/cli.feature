# The @uninstall scenario wipes every EasyEngine container, volume, network, image, /opt/easyengine and all
# sites. It is excluded from every profile but `uninstall`, refused without EE_BEHAT_ALLOW_UNINSTALL=1,
# and must run alone and last (later scenarios would re-initialise EasyEngine).
@destructive
Feature: CLI Command

  Scenario: Declining the self-uninstall prompt removes nothing
    Given I record the number of containers with label "org.label-schema.vendor=EasyEngine"
    When I run 'echo n | bin/ee cli self-uninstall'
    Then the command should exit with code 0
    And STDOUT should contain "Are you sure you want to remove EasyEngine and all its sites(along with their data)?"
    And the number of containers with label "org.label-schema.vendor=EasyEngine" should be unchanged
    And the file "{EE_ROOT_DIR}/db/ee.sqlite" should exist

  @uninstall
  Scenario: ee uninstall works properly
    Given no EasyEngine migration is pending
    When I run 'sudo bin/ee cli info'
    And I run 'sudo bin/ee cli self-uninstall --yes'
    Then ee should be deleted
