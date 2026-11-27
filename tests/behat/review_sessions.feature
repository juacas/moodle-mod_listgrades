@mod @mod_listgrades
Feature: Review sessions are private to each student
  Teachers manage all review sessions on a separate page.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | groupmode |
      | Course 1 | C1        | 1         |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Alice     | Student  |
      | student2 | Bob       | Student  |
      | teacher1 | Taylor    | Teacher  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group A | C1     | GA       |
      | Group B | C1     | GB       |
    And the following "group members" exist:
      | user     | group |
      | student1 | GA    |
      | student2 | GB    |
    And the following config values are set as admin:
      | reviewprocess | 1 | listgrades |
    And the following config values are set as admin:
      | reviewresponseoptions | I have seen my assessment work and received explanations.\nI have requested a copy of my assessment work.\nI have online access to or a copy of my assessment work.\nI have observations about the review process. | listgrades |
    And the following "activity" exists:
      | activity      | listgrades    |
      | course        | C1            |
      | name          | Grade review  |
      | reviewenabled | 1             |
      | reviewdate    | ##tomorrow##  |

  Scenario: Students see only their own review comments
    Given I am on the "Grade review" "listgrades activity" page logged in as student1
    And I press "Request a review"
    And I log out
    And I am on the "Grade review" "listgrades activity" page logged in as student2
    And I press "Request a review"
    And I log out
    When I am on the "Grade review" "listgrades activity" page logged in as teacher1
    Then I should not see "Alice Student"
    And I should see "2 review requests"
    And I follow "Manage reviews"
    And I should see "Alice Student"
    And I should see "Bob Student"
    And I should not see "Published grade:"
    And I set the field "comment" in the "Alice Student" "table_row" to "Alice private comment"
    And I click on "Add comment" "button" in the "Alice Student" "table_row"
    And I set the field "comment" in the "Bob Student" "table_row" to "Bob private comment"
    And I click on "Add comment" "button" in the "Bob Student" "table_row"
    And I log out
    And I am on the "Grade review" "listgrades activity" page logged in as student1
    Then I should see "Alice private comment"
    And I should not see "Bob private comment"
    And I should not see "Manage reviews"

  Scenario: A student without a request does not see a waiting message after the review date
    Given the following "activity" exists:
      | activity      | listgrades    |
      | course        | C1            |
      | name          | Past review   |
      | reviewenabled | 1             |
      | reviewdate    | ##yesterday## |
    When I am on the "Past review" "listgrades activity" page logged in as student1
    Then I should see "You have not requested a review."
    And I should not see "The review date has not yet arrived."
    And "Request a review" "button" should not exist

  Scenario: An incomplete review response shows a friendly validation message
    Given I am on the "Grade review" "listgrades activity" page logged in as student1
    And I press "Request a review"
    And I log out
    And I am on the "Grade review" "listgrades activity editing" page logged in as admin
    And I expand all fieldsets
    And I set the field "id_reviewdate_year" to "2020"
    And I press "Save and display"
    And I log out
    And I am on the "Grade review" "listgrades activity" page logged in as student1
    And I set the field "Comments" to "My review comment"
    When I press "Record completion of the review"
    Then I should see "Select at least one option." in the ".alert-danger" "css_element"
    And the field "Comments" matches value "My review comment"
    And I set the field "I have observations about the review process." to "1"
    And I press "Record completion of the review"
    And I should see "My review comment"
    And I should see "I have observations about the review process."
    And I should see "Published grade:"
    And "Record completion of the review" "button" should not exist
    And I log out
    And I am on the "Grade review" "listgrades activity" page logged in as teacher1
    And I follow "Manage reviews"
    And I should see "My review comment"
    And I should see "I have observations about the review process."
    And I should see "Published grade:"
    And I click on "Allow another student response" "button" in the "Alice Student" "table_row"
    And I log out
    And I am on the "Grade review" "listgrades activity" page logged in as student1
    And I should see "My review comment"
    And "My review comment" "text" should appear before "Record completion of the review" "button"
    And I set the field "I have seen my assessment work and received explanations." to "1"
    And I set the field "Comments" to "My second response"
    And I press "Record completion of the review"
    Then I should see "My review comment"
    And I should see "My second response"
    And I should see "I have observations about the review process."
    And I should see "I have seen my assessment work and received explanations."
    And "Record completion of the review" "button" should not exist
