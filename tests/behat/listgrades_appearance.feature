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

  @javascript
  Scenario Outline: Hide and display listgrades features
    Given I am on the "PageName1" "listgrades activity editing" listgrades logged in as admin
    And I expand all fieldsets
    And I set the field "<feature>" to "<value>"
    And I press "Save and display"
    Then I <shouldornot> see "<lookfor>" in the "region-main" "region"

    Examples:
      | feature                          | lookfor        | value | shouldornot |
      | Display listgrades description   | PageDesc1      | 1     | should      |
      | Display listgrades description   | PageDesc1      | 0     | should not  |
