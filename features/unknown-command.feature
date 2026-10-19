Feature: Unknown commands and composite commands

  Scenario: An unknown cli subcommand suggests the closest one
    When I run 'bin/ee cli verison'
    Then the command should exit with code 1
    And STDOUT should be empty
    And STDERR should be:
      """
      Error: 'verison' is not a registered subcommand of 'cli'. See 'ee help cli' for available subcommands.
      Did you mean 'version'?
      """

  Scenario Outline: Common shorthands map to the real subcommand
    When I run 'bin/ee cli <typed>'
    Then the command should exit with code 1
    And STDERR should contain "Did you mean '<suggestion>'?"

    Examples:
      | typed     | suggestion   |
      | v         | version      |
      | check     | check-update |
      | cmd-dmp   | cmd-dump     |
      | param-dum | param-dump   |
      | alais     | alias        |

  Scenario: No suggestion is offered when nothing is close
    When I run 'bin/ee cli zzzzzzzzzz'
    Then the command should exit with code 1
    And STDERR should contain "Error: 'zzzzzzzzzz' is not a registered subcommand of 'cli'."
    And STDERR should not contain "Did you mean"

  # CORE-9: unknown top-level commands pass the requirement and migration checks before the lookup.
  Scenario: An unknown top-level command suggests the closest one
    Given no EasyEngine migration is pending
    When I run 'bin/ee sitee'
    Then the command should exit with code 1
    And STDERR should contain:
      """
      Error: 'sitee' is not a registered ee command. See 'ee help' for available commands.
      Did you mean 'site'?
      """

  Scenario: A composite command without a subcommand prints its usage
    When I run 'bin/ee cli'
    Then the command should exit with code 0
    And STDOUT should contain "usage: ee cli alias [--format=<format>]"
    And STDOUT should contain "   or: ee cli version"
    And STDOUT should contain "See 'ee help cli <command>' for more information on a specific command."

  Scenario Outline: Every cli subcommand appears in the cli usage
    When I run 'bin/ee cli'
    Then STDOUT should match "#^(usage|   or): ee cli <subcommand>( |$)#m"

    Examples:
      | subcommand     |
      | alias          |
      | check-update   |
      | cmd-dump       |
      | completions    |
      | has-command    |
      | info           |
      | param-dump     |
      | self-uninstall |
      | update         |
      | version        |

  Scenario: A subcommand called without its required parameters fails
    When I run 'bin/ee cli completions'
    Then the command should exit with code 1
    And STDERR should contain "missing --line parameter"
    And STDERR should contain "missing --point parameter"
