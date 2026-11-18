# `cli info` runs the migration check when docker is available, so every scenario is guarded.
# COLUMNS keeps cli-tools from wrapping the table to the terminal width.
Feature: ee cli info

  Background:
    Given no EasyEngine migration is pending

  Scenario: cli info lists every diagnostic row
    When I run 'COLUMNS=250 bin/ee cli info'
    Then the command should exit with code 0
    And STDOUT should not contain "Executing migrations"
    And STDOUT should match "#^\| OS\s+\| \S+#m"
    And STDOUT should match "#^\| Shell\s+\|#m"
    And STDOUT should match "#^\| PHP binary\s+\| \S+#m"
    And STDOUT should match "#^\| PHP version\s+\| \d+\.\d+#m"
    And STDOUT should match "#^\| php\.ini used\s+\|#m"
    And STDOUT should match "#^\| EE root dir\s+\| {EE_ROOT}\s+\|#m"
    And STDOUT should match "#^\| EE vendor dir\s+\| {EE_ROOT}/vendor\s+\|#m"
    And STDOUT should match "#^\| EE phar path\s+\|\s+\|#m"
    And STDOUT should match "#^\| EE packages dir\s+\|#m"
    And STDOUT should match "#^\| EE global config\s+\|#m"
    And STDOUT should match "#^\| EE project config\s+\|#m"
    And STDOUT should match "#^\| EE version\s+\| {EE_VERSION}\s+\|#m"

  Scenario: cli info --format=json returns the documented keys
    When I run 'bin/ee cli info --format=json'
    Then the command should exit with code 0
    And STDOUT should be valid json
    And the json in STDOUT should have the keys "php_binary_path, global_config_path, project_config_path, ee_dir_path, ee_packages_dir_path, ee_version, system_os, shell"
    And the json in STDOUT at "ee_version" should be "{EE_VERSION}"
    And the json in STDOUT at "ee_dir_path" should be "{EE_ROOT}"
    And the json in STDOUT at "project_config_path" should be "null"

  Scenario: cli info reports the global config chosen with EE_CONFIG_PATH
    Given a file "{TMP}/config.yml" with:
      """
      locale: en_US
      ee_installer_version: stable
      """
    When I run 'EE_CONFIG_PATH={TMP}/config.yml bin/ee cli info --format=json'
    Then the command should exit with code 0
    And the json in STDOUT at "global_config_path" should be "{TMP}/config.yml"
    When I run 'COLUMNS=250 EE_CONFIG_PATH={TMP}/config.yml bin/ee cli info'
    Then STDOUT should match "#^\| EE global config\s+\| {TMP}/config\.yml\s+\|#m"

  Scenario: cli info rejects an unsupported format
    When I run 'bin/ee cli info --format=yaml'
    Then the command should exit with code 1
    And STDERR should contain:
      """
      Error: Parameter errors:
       Invalid value specified for 'format' (Render output in a particular format.)
      """

  Scenario: cli info suggests the closest flag for a typo
    When I run 'bin/ee cli info --fromat=json'
    Then the command should exit with code 1
    And STDERR should contain "unknown --fromat parameter"
    And STDERR should contain "Did you mean '--format'?"

  Scenario: cli info rejects positional arguments
    When I run 'bin/ee cli info extra'
    Then the command should exit with code 1
    And STDERR should contain "Error: Too many positional arguments: extra"
