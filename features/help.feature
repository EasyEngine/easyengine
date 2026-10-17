Feature: ee help

  Scenario: Running ee without arguments shows the top-level help
    When I run 'bin/ee'
    Then the command should exit with code 0
    And STDOUT should contain:
      """
      NAME

        ee

      DESCRIPTION

        Manage EasyEngine through the command-line.

      SYNOPSIS

        ee <command>
      """
    And STDOUT should contain "GLOBAL PARAMETERS"
    And STDOUT should contain "--[no-]color"
    And STDOUT should contain "--debug[=<group>]"
    And STDOUT should contain "--quiet"
    And STDOUT should contain "Run 'ee help <command>' to get more information on a specific command."
    And STDERR should be empty

  Scenario: ee, ee help and ee --help print the same page
    When I run 'bin/ee'
    And I run 'bin/ee help'
    And I run 'bin/ee --help'
    Then STDOUT of command 1 should be identical to STDOUT of command 2
    And STDOUT of command 1 should be identical to STDOUT of command 3

  Scenario Outline: The top-level help lists every installed command
    When I run 'bin/ee help'
    Then STDOUT should match "#^  <command>\s+\S#m"

    Examples:
      | command     |
      | admin-tools |
      | auth        |
      | cli         |
      | config      |
      | cron        |
      | dash        |
      | help        |
      | log         |
      | mailhog     |
      | service     |
      | shell       |
      | site        |

  Scenario Outline: Every top-level command has a help page, also reachable with --help
    When I run 'bin/ee help <command>'
    And I run 'bin/ee <command> --help'
    Then the command should exit with code 0
    And STDOUT should contain:
      """
      NAME

        ee <command>

      """
    And STDOUT should contain:
      """
      SYNOPSIS

        ee <command>
      """
    And STDOUT of command 1 should be identical to STDOUT of command 2

    Examples:
      | command     |
      | admin-tools |
      | auth        |
      | cli         |
      | config      |
      | cron        |
      | dash        |
      | help        |
      | log         |
      | mailhog     |
      | service     |
      | shell       |
      | site        |

  Scenario Outline: Every subcommand has a help page, also reachable with --help
    When I run 'bin/ee help <command>'
    And I run 'bin/ee <command> --help'
    Then the command should exit with code 0
    And STDOUT should contain:
      """
      NAME

        ee <command>

      """
    And STDOUT should contain:
      """
      SYNOPSIS

        ee <command>
      """
    And STDOUT of command 1 should be identical to STDOUT of command 2

    Examples:
      | command |
      | admin-tools disable |
      | admin-tools enable |
      | auth create |
      | auth delete |
      | auth list |
      | auth update |
      | cli alias |
      | cli check-update |
      | cli cmd-dump |
      | cli completions |
      | cli has-command |
      | cli info |
      | cli param-dump |
      | cli self-uninstall |
      | cli update |
      | cli version |
      | config get |
      | config list |
      | config set |
      | config unset |
      | cron create |
      | cron delete |
      | cron list |
      | cron run-now |
      | cron update |
      | dash init |
      | log show |
      | mailhog disable |
      | mailhog enable |
      | mailhog status |
      | service disable |
      | service enable |
      | service refresh |
      | service reload |
      | service restart |
      | site backup |
      | site clean |
      | site clone |
      | site create |
      | site delete |
      | site disable |
      | site enable |
      | site info |
      | site list |
      | site refresh |
      | site reload |
      | site restart |
      | site restore |
      | site share |
      | site ssl-info |
      | site ssl-renew |
      | site ssl-verify |
      | site sync |
      | site update |

  Scenario Outline: site help follows the requested site type
    When I run 'bin/ee help site create --type=<type>'
    Then the command should exit with code 0
    And STDOUT should contain "<description>"

    Examples:
      | type | description                                     |
      | html | Runs the standard HTML site installation.       |
      | wp   | Runs the standard WordPress Site installation.  |
      | php  | Runs the standard PHP Site installation.        |

  Scenario: The default site type for help is html
    When I run 'bin/ee help site create'
    Then STDOUT should contain "Runs the standard HTML site installation."

  Scenario: WordPress-only options appear only in the wp site create help
    When I run 'bin/ee help site create --type=wp'
    Then STDOUT should contain "--admin-user=<admin-user>"
    When I run 'bin/ee help site create --type=html'
    Then the command should exit with code 0
    And STDOUT should contain "Runs the standard HTML site installation."
    And STDOUT should not contain "--admin-user=<admin-user>"

  Scenario: help for an unknown site type fails
    When I run 'bin/ee help site create --type=bogus'
    Then the command should exit with code 1
    And STDERR should contain "Error: 'bogus' is not a registered site type"

  # CORE-10 (site-command): the hint repeats the bogus type, and that command fails the same way.
  @known-bug
  Scenario: help for an unknown site type points to a working help command
    When I run 'bin/ee help site create --type=bogus'
    Then the command should exit with code 1
    And STDERR should not contain "ee help site --type=bogus"

  Scenario: help for an unknown command fails with a hint
    When I run 'bin/ee help nosuchcommand'
    Then the command should exit with code 1
    And STDERR should be:
      """
      Error: 'nosuchcommand' is not a registered ee command. See 'ee help' for available commands.
      """

  Scenario: help for an unknown subcommand fails with a hint
    When I run 'bin/ee help cli nosuchsubcommand'
    Then the command should exit with code 1
    And STDERR should be:
      """
      Error: 'nosuchsubcommand' is not a registered subcommand of 'cli'. See 'ee help cli' for available subcommands.
      """

  Scenario: help suggests the closest command for a typo
    When I run 'bin/ee help sitee'
    Then the command should exit with code 1
    And STDERR should be:
      """
      Error: 'sitee' is not a registered ee command. See 'ee help' for available commands.
      Did you mean 'site'?
      """
    When I run 'bin/ee help cli verison'
    Then STDERR should contain "Did you mean 'version'?"

  Scenario: help honours the PAGER environment variable
    When I run 'PAGER="sed s/^/bh-pager:/" bin/ee help cli version'
    Then the command should exit with code 0
    And STDOUT should match "#^bh-pager:  ee cli version#m"

  Scenario: help pages include the command examples
    When I run 'bin/ee help cli has-command'
    Then STDOUT should contain "EXAMPLES"
    And STDOUT should contain:
      """
      $ ee cli has-command "site delete"
      """
