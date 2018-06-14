Feature: Site Command

  # Scenario: ee throws error when run without root
  #   Given 'bin/ee' is installed
  #   When I run 'bin/ee'
  #   Then STDOUT should return something like
  #   """
  #   Error: Please run `ee` with root privileges.
  #   """

  # Scenario: ee executable is command working correctly
  #   Given 'bin/ee' is installed
  #   When I run 'sudo bin/ee'
  #   Then STDOUT should return something like
  #    """
  #    NAME

  #    ee
  #    """

  # Scenario: Check site command is present
  #   When I run 'sudo bin/ee site'
  #   Then STDOUT should return something like
  #    """
  #     usage: ee site
  #    """

  # Scenario Outline: 'site create' is running successfully
  #   When I run 'sudo bin/ee site create <site> --wp'
  #   Then The site '<site>' should have webroot
  #   Then The site '<site>' should have WordPress
  #   Then Request on '<site>' should contain following headers:
  #   | header           |
  #   | HTTP/1.1 200 OK  |

  #   # And The '<site>' should have tables

  #   Examples:
  #     | site       |
  #     | hello.test |

 Scenario: List the sites
   When I run 'sudo bin/ee site list'
   Then STDOUT should return exactly
    """
    List of all Sites:

    hello.test
    """

  Scenario: Delete the sites
    When I run 'sudo bin/ee site delete hello.test'
    Then STDERR should be empty
    And The 'hello.test' db entry should be removed
    And The 'hello.test' webroot should be removed
    And Following containers of site 'hello.test' should be removed:
      | container  |
      | nginx      |
      | php        |
      | db         |
      | redis      |
      | phpmyadmin |



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
