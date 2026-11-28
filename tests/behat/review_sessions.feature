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
      | reviewresponseoptions | I have observations about the review process. | listgrades |
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
    And I should see "Review" in the ".breadcrumb" "css_element"
    And "Grade review" "link" should exist in the ".breadcrumb" "css_element"
    And ".breadcrumb a[href*='/mod/listgrades/view.php?id=']" "css_element" should exist
    And I should see "Alice"
    And I should see "Bob"
    And I should not see "Published grade:"
    And I set the field "comment" in the "Alice" "table_row" to "Alice private comment"
    And I click on "Add comment" "button" in the "Alice" "table_row"
    And I set the field "comment" in the "Bob" "table_row" to "Bob private comment"
    And I click on "Add comment" "button" in the "Bob" "table_row"
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

  @javascript
  Scenario: Cancellation and a new request preserve the review conversation
    Given I am on the "Grade review" "listgrades activity" page logged in as student1
    And I press "Request a review"
    And I log out
    And I am on the "Grade review" "listgrades activity" page logged in as teacher1
    And I follow "Manage reviews"
    And I set the field "comment" in the "Alice" "table_row" to "A retained comment"
    And I click on "Add comment" "button" in the "Alice" "table_row"
    And I log out
    And I am on the "Grade review" "listgrades activity" page logged in as student1
    When I press "Cancel review request"
    Then I should see "A retained comment"
    And I should see "Request cancelled" in the ".listgrades-review-chat" "css_element"
    And I press "Request a review"
    And I should see "A retained comment"
    And I should see "Request cancelled" in the ".listgrades-review-chat" "css_element"
    And I log out
    And I am on the "Grade review" "listgrades activity" page logged in as teacher1
    And I follow "Manage reviews"
    And ".listgrades-review-history:not([open])" "css_element" should exist in the "Alice" "table_row"
    And I should not see "A retained comment" in the "Alice" "table_row"
    And I click on ".listgrades-review-history summary" "css_element" in the "Alice" "table_row"
    And I should see "A retained comment" in the "Alice" "table_row"
    And I should see "Request cancelled" in the "Alice" "table_row"
    And I click on ".listgrades-review-history summary" "css_element" in the "Alice" "table_row"
    And I should not see "A retained comment" in the "Alice" "table_row"
    And "Add comment" "button" should exist in the "Alice" "table_row"

  Scenario: The review date can be displayed without enabling the review process
    Given the following config values are set as admin:
      | reviewprocess | 0 | listgrades |
    And the following "activity" exists:
      | activity   | listgrades       |
      | course     | C1               |
      | name       | Review date only |
      | reviewdate | ##tomorrow##     |
      | footer     | End of report    |
    When I am on the "Review date only" "listgrades activity" page logged in as student1
    Then I should see "Review date"
    And I should see "End of report"
    And I should not see "Grade review process"
    And "//*[contains(@class, 'generalbox') and contains(., 'Review date')]/following-sibling::*[contains(., 'End of report')]" "xpath_element" should exist
    And I log out
    And I am on the "Review date only" "listgrades activity editing" page logged in as admin
    And I expand all fieldsets
    And I should see "Review date"

  Scenario: A review response does not reveal grades after publication closes
    Given I am on the "Grade review" "listgrades activity" page logged in as student1
    And I press "Request a review"
    And I log out
    And I am on the "Grade review" "listgrades activity editing" page logged in as admin
    And I expand all fieldsets
    And I set the field "id_reviewdate_year" to "2020"
    And I set the field "id_closedate_enabled" to "1"
    And I set the field "id_closedate_year" to "2020"
    And I press "Save and display"
    And I log out
    When I am on the "Grade review" "listgrades activity" page logged in as student1
    And I set the field "I have observations about the review process." to "1"
    And I press "Record completion of the review"
    Then I should see "I have observations about the review process."
    And I should not see "Published grade:"

  @javascript
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
    And I click on ".listgrades-review-history summary" "css_element" in the "Alice" "table_row"
    And I should see "My review comment"
    And I should see "I have observations about the review process."
    And I should see "Published grade:"
    And I click on "Allow another student response" "button" in the "Alice" "table_row"
    And the following config values are set as admin:
      | reviewresponseoptions | I have seen my assessment work and received explanations. | listgrades |
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
