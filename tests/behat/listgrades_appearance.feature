@mod @mod_listgrades
Feature: Configure listgrades appearance
  In order to change the appearance of the listgrades resource
  As an admin
  I need to configure the listgrades appearance settings

  Background:
    Given the following "courses" exist:
      | shortname | fullname   |
      | C1        | Course 1 |
    And the following "activities" exist:
      | activity       | name       | intro      | course | idnumber |
      | listgrades     | PageName1  | PageDesc1  | C1     | PAGE1    |

  Scenario Outline: Always display the description inside listgrades
    Given I am on the "PageName1" "listgrades activity editing" page logged in as admin
    And I expand all fieldsets
    And I set the field "showdescription" to "<value>"
    And I press "Save and display"
    Then I should see "PageDesc1" in the "region-main" "region"

    Examples:
      | value |
      | 1     |
      | 0     |
