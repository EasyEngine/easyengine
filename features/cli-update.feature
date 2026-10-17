Feature: ee cli check-update and ee cli update

  @network
  Scenario: check-update reports either the available updates or that EE is current
    When I run 'bin/ee cli check-update'
    Then the command should exit with code 0
    And STDOUT should match "#(^Success: EasyEngine is at the latest version\.$|^\| version\s+\| update_type\s+\| package_url\s+\|$)#m"

  @network
  Scenario: check-update --major reports that no major release exists
    When I run 'bin/ee cli check-update --major'
    Then the command should exit with code 0
    And STDOUT should be:
      """
      Success: EasyEngine is at the latest major version.
      """

  @network
  Scenario Outline: check-update filters by update type
    When I run 'bin/ee cli check-update --<type>'
    Then the command should exit with code 0
    And STDOUT should match "#(^Success: EasyEngine is at the latest <type> version\.$|\| <type>\s+\|)#m"

    Examples:
      | type  |
      | minor |
      | patch |

  @network
  Scenario: check-update --format=json prints nothing or a json list
    When I run 'bin/ee cli check-update --format=json'
    Then the command should exit with code 0
    And STDOUT should be empty or valid json
    And STDOUT should not contain "Success:"

  @network
  Scenario: check-update --format=count prints nothing or a number
    When I run 'bin/ee cli check-update --format=count'
    Then the command should exit with code 0
    And STDOUT should match "#^\s*\d*\s*$#"

  Scenario: check-update rejects an unsupported format before any request
    When I run 'https_proxy=http://127.0.0.1:9 bin/ee cli check-update --format=xml'
    Then the command should exit with code 1
    And STDERR should contain "Invalid value specified for 'format'"

  Scenario: check-update fails cleanly when GitHub is unreachable
    When I run 'https_proxy=http://127.0.0.1:9 HTTPS_PROXY=http://127.0.0.1:9 bin/ee cli check-update'
    Then the command should exit with code 1
    And STDERR should contain "Error: Failed to get url 'https://api.github.com/repos/EasyEngine/easyengine/releases?per_page=100'"

  Scenario Outline: cli update refuses to run from a source checkout
    When I run 'bin/ee cli update <flags>'
    Then the command should exit with code 1
    And STDERR should be:
      """
      Error: You can only self-update Phar files.
      """

    Examples:
      | flags           |
      |                 |
      | --yes           |
      | --stable --yes  |
      | --nightly --yes |
