Feature: Create WordPress site

  Scenario: ee site command working correctly
    Given 'bin/ee' is installed
    When I run 'bin/ee'
    Then STDOUT should return something like
     """
     NAME

     ee
     """

  Scenario Outline: Created site is running successfully
    When I run 'bin/ee site create <site> --wp'
    Then The '<site>' should have webroot
    And The '<site>' should have tables

    Examples:
      | site       |
      | hello.test |
      | world.test |

  Scenario Outline: List the sites
    When I run 'bin/ee site list'
    Then STDOUT should return something like
     """
      List of Sites:

       - hello.test
       - world.test
     """

    Examples:
      | site       |
      | hello.test |
      | world.test |


  Scenario Outline: Delete the sites
    When I run 'bin/ee site delete <site>'
    Then The '<site>' containers should be removed
    And The '<site>' webroot should be removed
    And The '<site>' db entry should be removed

    Examples:
      | site       |
      | hello.test |
      | world.test |


#Scenario: Site Clean-up works properly
#    When I cleanup test 'bin/ee site create <site>'
#    Then The '<site>' containers should be removed
#    And The '<site>' webroot should be removed
#    And The '<site>' db entry should be removed
#
#    Examples:
#      | site       |
#      | hello.test |
#      | world.test |
