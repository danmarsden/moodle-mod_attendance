@mod @mod_attendance
Feature: Test the various new features in the attendance module

  Background:
    Given the following "courses" exist:
      | fullname | shortname | summary                             | category | timecreated   | timemodified  |
      | Course 1 | C1        | Prove the attendance activity works | 0        | ##yesterday## | ##yesterday## |
      | Course 2 | C2        | Prove the attendance activity works | 0        | ##yesterday## | ##yesterday## |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | student3 | Student   | 3        | student3@example.com |
      | student4 | Student   | 4        | student4@example.com |
      | student5 | Student   | 5        | student5@example.com |
    And the following "course enrolments" exist:
      | course | user     | role           | timestart     |
      | C1     | teacher1 | editingteacher | ##yesterday## |
      | C1     | student1 | student        | ##yesterday## |
      | C1     | student2 | student        | ##yesterday## |
      | C1     | student3 | student        | ##yesterday## |
      | C2     | teacher1 | editingteacher | ##yesterday## |
      | C2     | student1 | student        | ##yesterday## |
      | C2     | student2 | student        | ##yesterday## |
      | C2     | student3 | student        | ##yesterday## |
    And the following "activity" exists:
      | activity | attendance            |
      | course   | C1                    |
      | idnumber | 00001                 |
      | name     | Test attendance       |

    And the following "activity" exists:
      | activity | attendance            |
      | course   | C2                    |
      | idnumber | 00002                 |
      | name     | Test2attendance      |

  @javascript
  Scenario: A teacher can create and update temporary users
    Given I am on the "Test attendance" "mod_attendance > View" page logged in as "teacher1"
    And I click on "More" "link" in the ".secondary-navigation" "css_element"
    And I select "Temporary users" from secondary navigation

    When I set the following fields to these values:
      | Full name | Temporary user 1 |
      | Email     |                  |
    And I press "Add user"
    And I set the following fields to these values:
      | Full name | Temporary user test 2     |
      | Email     | tempuser2test@example.com |
    And I press "Add user"
    Then I should see "Temporary user 1"
    And "tempuser2test@example.com" "text" should exist in the "Temporary user test 2" "table_row"

    When I click on "Edit user" "link" in the "Temporary user test 2" "table_row"
    And the following fields match these values:
      | Full name | Temporary user test 2     |
      | Email     | tempuser2test@example.com |
    And I set the following fields to these values:
      | Full name | Temporary user 2      |
      | Email     | tempuser2@example.com |
    And I press "Edit user"
    Then "tempuser2@example.com" "text" should exist in the "Temporary user 2" "table_row"

    When I click on "Delete user" "link" in the "Temporary user 1" "table_row"
    And I press "Continue"
    Then I should not see "Temporary user 1"
    And I should see "Temporary user 2"

  @javascript
  Scenario: A teacher can take attendance for temporary users
    Given I am on the "Test attendance" "mod_attendance > View" page logged in as "teacher1"
    And I click on "More" "link" in the ".secondary-navigation" "css_element"
    And I select "Temporary users" from secondary navigation
    And I set the following fields to these values:
      | Full name | Temporary user 1 |
      | Email     |                  |
    And I press "Add user"
    And I set the following fields to these values:
      | Full name | Temporary user 2      |
      | Email     | tempuser2@example.com |
    And I press "Add user"
    And I am on the "Test attendance" "mod_attendance > View" page
    And I click on "Add session" "button"
    And I set the following fields to these values:
      | id_addmultiply | 0 |
    And I click on "submitbutton" "button"

    And I follow "Take attendance"
    # Present
    And I click on "td.cell.c2 input" "css_element" in the "Student 1" "table_row"
    # Late
    And I click on "td.cell.c3 input" "css_element" in the "Student 2" "table_row"
    # Excused
    And I click on "td.cell.c4 input" "css_element" in the "Temporary user 1" "table_row"
    # Absent
    And I click on "td.cell.c5 input" "css_element" in the "Temporary user 2" "table_row"
    And I press "Save and show next page"
    And I am on the "Test attendance" "mod_attendance > Report" page
    And "P" "text" should exist in the "Student 1" "table_row"
    And "L" "text" should exist in the "Student 2" "table_row"
    And "E" "text" should exist in the "Temporary user 1" "table_row"
    And "A" "text" should exist in the "Temporary user 2" "table_row"

    And I follow "Temporary user 2"
    And I should see "Absent"

    # Merge user.
    And I am on the "Test attendance" "mod_attendance > View" page
    And I click on "More" "link" in the ".secondary-navigation" "css_element"
    And I select "Temporary users" from secondary navigation
    And I click on "Merge user" "link" in the "Temporary user 2" "table_row"
    And I set the field "Participant" to "Student 3"
    And I press "Merge user"
    And I am on the "Test attendance" "mod_attendance > Report" page

    And "P" "text" should exist in the "Student 1" "table_row"
    And "L" "text" should exist in the "Student 2" "table_row"
    And "E" "text" should exist in the "Temporary user 1" "table_row"
    And "A" "text" should exist in the "Student 3" "table_row"
    Then I should not see "Temporary user 2"

  @javascript
  Scenario: A teacher can select a subset of users for export
    Given the following "groups" exist:
      | course | name   | idnumber |
      | C1     | Group1 | Group1   |
      | C1     | Group2 | Group2   |
    And the following "group members" exist:
      | group  | user     |
      | Group1 | student1 |
      | Group1 | student2 |
      | Group2 | student2 |
      | Group2 | student3 |

    And I am on the "Test attendance" "mod_attendance > View" page logged in as "teacher1"
    And I click on "Add session" "button"
    And I set the following fields to these values:
      | id_addmultiply | 0 |
    And I click on "submitbutton" "button"

    And I follow "Export"

    When I set the field "Export specific users" to "Yes"
    And I set the field "Group" to "Group1"
    Then the "Users to export" select box should contain "Student 1"
    And the "Users to export" select box should contain "Student 2"
    And the "Users to export" select box should not contain "Student 3"

    When I set the field "Group" to "Group2"
    Then the "Users to export" select box should contain "Student 2"
    And the "Users to export" select box should contain "Student 3"
    And the "Users to export" select box should not contain "Student 1"
    # Ideally the download would be tested here, but that is difficult to configure.

  @javascript
  Scenario: A teacher can create and use multiple status lists
    Given I am on the "Test attendance" "mod_attendance > View" page logged in as "teacher1"
    And I click on "More" "link" in the ".secondary-navigation" "css_element"
    And I select "Status set" from secondary navigation
    And I set the field "jump" to "New set of statuses"
    And I set the field with xpath "//*[@id='statuslastrow']/td[2]/input" to "G"
    And I set the field with xpath "//*[@id='statuslastrow']/td[3]/input" to "Great"
    And I set the field with xpath "//*[@id='statuslastrow']/td[4]/input" to "3"
    And I click on "Add" "button" in the ".lastrow" "css_element"
    And I set the field with xpath "//*[@id='statuslastrow']/td[2]/input" to "O"
    And I set the field with xpath "//*[@id='statuslastrow']/td[3]/input" to "OK"
    And I set the field with xpath "//*[@id='statuslastrow']/td[4]/input" to "2"
    And I click on "Add" "button" in the ".lastrow" "css_element"
    And I set the field with xpath "//*[@id='statuslastrow']/td[2]/input" to "B"
    And I set the field with xpath "//*[@id='statuslastrow']/td[3]/input" to "Bad"
    And I set the field with xpath "//*[@id='statuslastrow']/td[4]/input" to "0"
    And I click on "Add" "button" in the ".lastrow" "css_element"
    And I click on "Update" "button" in the "#preferencesform" "css_element"
    And I am on the "Test attendance" "mod_attendance > View" page
    And I click on "Add session" "button"
    And I set the following fields to these values:
      | id_addmultiply            | 0                      |
      | Status set                | Status set 1 (P L E A) |
      | id_sestime_starthour      | 10                     |
      | id_sestime_startminute    | 0                      |
      | id_sestime_endhour        | 11 |
    And I click on "submitbutton" "button"
    And I click on "Add session" "button"
    And I set the following fields to these values:
      | id_addmultiply            | 0                    |
      | Status set                | Status set 2 (G O B) |
      | id_sestime_starthour      | 12                   |
      | id_sestime_startminute    | 0                    |
      | id_sestime_endhour        | 13 |
    And I click on "submitbutton" "button"

    When I click on "Take attendance" "link" in the "10AM" "table_row"
    Then "Set status to «Present»" "link" should exist
    And "Set status to «Late»" "link" should exist
    And "Set status to «Excused»" "link" should exist
    And "Set status to «Absent»" "link" should exist
    And I am on the "Test attendance" "mod_attendance > View" page
    And I click on "Take attendance" "link" in the "12PM" "table_row"
    Then "Set status to «Great»" "link" should exist
    And "Set status to «OK»" "link" should exist
    And "Set status to «Bad»" "link" should exist

  @javascript
  Scenario: A teacher can use the radio buttons to set attendance values for all users
    Given I am on the "Test attendance" "mod_attendance > View" page logged in as "teacher1"
    And I click on "Add session" "button"
    And I set the following fields to these values:
      | id_addmultiply | 0 |
    And I click on "submitbutton" "button"
    And I click on "Take attendance" "link"
    And I set the field "Set status for" to "all"
    When I click on "setallstatuses" "field" in the ".takelist tbody td.c3" "css_element"
    And I press "Save and show next page"
    And I am on the "Test attendance" "mod_attendance > Report" page
    Then "L" "text" should exist in the "Student 1" "table_row"
    And "L" "text" should exist in the "Student 2" "table_row"
    And "L" "text" should exist in the "Student 3" "table_row"

  @javascript
  Scenario: A teacher can use the radio buttons to set attendance values for unselected users
    Given I am on the "Test2attendance" "mod_attendance > View" page logged in as "teacher1"
    And I click on "Add session" "button"
    And I set the following fields to these values:
      | id_addmultiply | 0 |
    And I click on "submitbutton" "button"
    And I click on "Take attendance" "link"
    And I set the field "Set status for" to "unselected"
    # Set student 1 as present.
    And I click on "td.cell.c2 input" "css_element" in the "Student 1" "table_row"
    And I click on "setallstatuses" "field" in the ".takelist tbody td.c3" "css_element"
    And I wait until the page is ready
    And I press "Save and show next page"
    And I am on the "Test2attendance" "mod_attendance > Report" page
    Then "P" "text" should exist in the "Student 1" "table_row"
    And "L" "text" should exist in the "Student 2" "table_row"
    And "L" "text" should exist in the "Student 3" "table_row"
