@mod @mod_attendance @javascript
Feature: Test the calendar related features in the attendance module

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | course | user     | role           |
      | C1     | teacher1 | editingteacher |
      | C1     | student1 | student        |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Attendance" to section "1" and I fill the form with:
      | Name | Test attendance |
    And I log out

  Scenario: Calendar events can be created automatically with sessions creation
    When I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test attendance"
    And I follow "Add session"
    And I set the following fields to these values:
      | id_sestime_starthour | 01 |
      | id_sestime_endhour   | 02 |
    And I click on "id_submitbutton" "button"
    And I follow "Course 1"
    And I follow "Go to calendar"
    Then I should see "Test attendance"
    And I log out
    And I log in as "student1"
    And I follow "Go to calendar"
    Then I should see "Test attendance"

  Scenario: Teacher can delete and create calendar events for sessions
    When I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test attendance"
    And I follow "Add session"
    And I set the following fields to these values:
      | id_sestime_starthour | 01 |
      | id_sestime_endhour   | 02 |
    And I click on "id_submitbutton" "button"
    And I set the following fields to these values:
      | cb_selector | 1                      |
      | menuaction  | Delete calendar events |
    And I click on "OK" "button"
    And I click on "Continue" "button"
    And I follow "Course 1"
    And I follow "Go to calendar"
    Then I should not see "Test attendance"
    And I log out
    And I log in as "student1"
    And I follow "Go to calendar"
    Then I should not see "Test attendance"
    And I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test attendance"
    And I follow "Add session"
    And I set the following fields to these values:
      | id_sestime_starthour | 01 |
      | id_sestime_endhour   | 02 |
    And I click on "id_submitbutton" "button"
    And I set the following fields to these values:
      | cb_selector | 1                      |
      | menuaction  | Create calendar events |
    And I click on "OK" "button"
    And I click on "Continue" "button"
    And I follow "Course 1"
    And I follow "Go to calendar"
    Then I should see "Test attendance"
    And I log out
    And I log in as "student1"
    And I follow "Go to calendar"
    Then I should see "Test attendance"