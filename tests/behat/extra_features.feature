@mod @mod_attendance @javascript
Feature: Test the various new features in the attendance module

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | student3 | Student   | 3        | student3@example.com |
      | student4 | Student   | 4        | student4@example.com |
      | student5 | Student   | 5        | student5@example.com |
    And the following "course enrolments" exist:
      | course | user     | role           |
      | C1     | teacher1 | editingteacher |
      | C1     | student1 | student        |
      | C1     | student2 | student        |
      | C1     | student3 | student        |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Attendance" to section "1" and I fill the form with:
      | Name | Test attendance |
    And I log out

  Scenario: A teacher can create and update temporary users
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test attendance"
    And I follow "Temporary users"

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

  Scenario: A teacher can take attendance for temporary users
    Given I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test attendance"
    And I follow "Temporary users"
    And I set the following fields to these values:
      | Full name | Temporary user 1 |
      | Email     |                  |
    And I press "Add user"
    And I set the following fields to these values:
      | Full name | Temporary user 2      |
      | Email     | tempuser2@example.com |
    And I press "Add user"

    And I follow "Add"
    And I set the following fields to these values:
      | Create multiple sessions | 0 |
    And I click on "submitbutton" "button"
    And I follow "Sessions"

    When I follow "Take attendance"
    # Present
    And I click on "td.c2 input" "css_element" in the "Student 1" "table_row"
    # Late
    And I click on "td.c3 input" "css_element" in the "Student 2" "table_row"
    # Excused
    And I click on "td.c4 input" "css_element" in the "Temporary user 1" "table_row"
    # Absent
    And I click on "td.c5 input" "css_element" in the "Temporary user 2" "table_row"
    And I press "Save attendance"
    And I follow "Report"
    Then "P" "text" should exist in the "Student 1" "table_row"
    And "L" "text" should exist in the "Student 2" "table_row"
    And "E" "text" should exist in the "Temporary user 1" "table_row"
    And "A" "text" should exist in the "Temporary user 2" "table_row"

    When I follow "Temporary user 2"
    Then I should see "Absent"

    # Merge user.
    When I follow "Test attendance"
    And I follow "Temporary users"
    And I click on "Merge user" "link" in the "Temporary user 2" "table_row"
    And I set the field "Participant" to "Student 3"
    And I press "Merge user"
    And I follow "Report"

    Then "P" "text" should exist in the "Student 1" "table_row"
    And "L" "text" should exist in the "Student 2" "table_row"
    And "E" "text" should exist in the "Temporary user 1" "table_row"
    And "A" "text" should exist in the "Student 3" "table_row"
    And I should not see "Temporary user 2"
