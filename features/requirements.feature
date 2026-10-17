# The runner only checks requirements (docker, docker-compose) for commands other than cli/config/help.
# These scenarios hide the binaries with a private PATH, so they never reach a migration.
Feature: Requirement checks

  Scenario: help works without docker or docker-compose
    # The default pager (`less -r`) is looked up in PATH too, so point PAGER at an absolute cat.
    Given the directory "{TMP}/emptybin" exists
    When I run 'PAGER=/bin/cat PATH={TMP}/emptybin {PHP} {EE_ROOT}/php/boot-fs.php help cli'
    Then the command should exit with code 0
    And STDOUT should contain "ee cli"

  # CORE-5: help pipes through `less -r` unless PAGER is set; without less it prints nothing and exits 0.
  @known-bug
  Scenario: help still prints when the default pager is not installed
    Given the directory "{TMP}/emptybin" exists
    When I run 'PATH={TMP}/emptybin {PHP} {EE_ROOT}/php/boot-fs.php help cli'
    Then STDOUT should contain "ee cli"

  Scenario: cli info still works when docker is not available
    # Requirements are only probed (4 attempts, ~7s of backoff), so no migration can run.
    Given the directory "{TMP}/emptybin" exists
    When I run 'PATH={TMP}/emptybin {PHP} {EE_ROOT}/php/boot-fs.php cli info --format=json'
    Then the command should exit with code 0
    And the json in STDOUT at "ee_version" should be "{EE_VERSION}"

  Scenario: A checked command reports a missing docker-compose
    Given the executable "docker" is linked into "{TMP}/bin"
    When I run 'PATH={TMP}/bin {PHP} {EE_ROOT}/php/boot-fs.php site list'
    Then STDERR should contain "Warning: EasyEngine requires docker-compose version 2.26.1 or greater."
    And STDERR should contain "Error: EasyEngine requires docker-compose."
    And STDOUT should not contain "Executing migrations"

  # CORE-1: a failed requirement exits with status 0 (`EE::error( ..., false ); die;`).
  @known-bug
  Scenario: A failed requirement exits with a non-zero status
    Given the executable "docker" is linked into "{TMP}/bin"
    When I run 'PATH={TMP}/bin {PHP} {EE_ROOT}/php/boot-fs.php site list'
    Then STDERR should contain "Error: EasyEngine requires docker-compose."
    And the command should fail

  Scenario: A checked command retries the docker probe before giving up
    Given the directory "{TMP}/emptybin" exists
    When I run 'PATH={TMP}/emptybin {PHP} {EE_ROOT}/php/boot-fs.php site list --debug'
    Then STDERR should contain "Docker check failed (attempt 1/4), retrying in 1s..."
    And STDERR should contain "Docker check failed (attempt 3/4), retrying in 4s..."
    And STDERR should contain "Error: Docker not installed or not running (checked 4 times)."

  # Safe by construction: without docker the command stops before the prompt, which `n` would decline anyway.
  Scenario: self-uninstall refuses to run when docker is not available
    Given the directory "{TMP}/emptybin" exists
    When I run 'echo n | PATH={TMP}/emptybin {PHP} {EE_ROOT}/php/boot-fs.php cli self-uninstall'
    Then STDERR should contain "Error: Unable to proceed with uninstallation. Seems there is a dependency down."
    And STDOUT should not contain "Are you sure"

  # CORE-11: the refusal uses `EE::error( ..., false ); die;`, so it exits 0 like CORE-1.
  @known-bug
  Scenario: self-uninstall refuses with a non-zero status when docker is not available
    Given the directory "{TMP}/emptybin" exists
    When I run 'echo n | PATH={TMP}/emptybin {PHP} {EE_ROOT}/php/boot-fs.php cli self-uninstall'
    Then STDERR should contain "Unable to proceed with uninstallation"
    And the command should fail
