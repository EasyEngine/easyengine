Feature: Site Command

  Scenario: ee throws error when run without root
    Given 'bin/ee' is installed
    When I run 'bin/ee'
    Then STDOUT should return something like
    """
    Error: Please run `ee` with root privileges.
    """

  Scenario: ee executable is command working correctly
    Given 'bin/ee' is installed
    When I run 'sudo bin/ee'
    Then STDOUT should return something like
     """
     NAME

     ee
     """

  Scenario: Check site command is present
    When I run 'sudo bin/ee site'
    Then STDOUT should return something like
     """
      usage: ee site
     """

  Scenario Outline: 'site create' is running successfully
    When I run 'sudo bin/ee site create <site> --wp'
    Then The site '<site>' should have webroot
    Then The site '<site>' should have WordPress
    Then Request on '<site>' should contain following headers:
    | header           |
    | HTTP/1.1 200 OK  |

    # And The '<site>' should have tables

    Examples:
      | site       |
      | hello.test |

  Scenario Outline: List the sites
    When I run 'sudo bin/ee site list'
    Then STDOUT should return something like
     """
      List of Sites:

      hello.test
     """

    Examples:
      | site       |
      | hello.test |


  Scenario Outline: Delete the sites
    When I run 'sudo bin/ee site delete <site>'
    Then The '<site>' db entry should be removed
    And The '<site>' webroot should be removed
    And Following containers of site '<site>' should be removed:
      | container  |
      | nginx      |
      | php        |
      | db         |
      | redis      |
      | phpmyadmin |


    Examples:
      | site       |
      | hello.test |


#Scenario: Site Clean-up works properly
#    When I cleanup test 'sudo bin/ee site create <site>'
#    Then The '<site>' containers should be removed
#    And The '<site>' webroot should be removed
#    And The '<site>' db entry should be removed
#
#    Examples:
#      | site       |
#      | hello.test |
#      | world.test |
