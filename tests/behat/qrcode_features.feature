@mod @mod_attendance
Feature: Teachers can display QR code or password pages showing course and session information
  In order to avoid student confusion when back-to-back classes scan old QR codes
  As a teacher
  I need to see the course and session information displayed on the QR code/password page

  Background:
    Given the following "courses" exist:
      | fullname        | shortname | summary                             | category | timecreated   | timemodified  |
      | Science Class 1 | SCI101    | Prove the attendance activity works | 0        | ##yesterday## | ##yesterday## |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "course enrolments" exist:
      | course | user     | role           | timestart     |
      | SCI101 | teacher1 | editingteacher | ##yesterday## |
      | SCI101 | student1 | student        | ##yesterday## |
    And the following "activities" exist:
      | activity   | name             | course |
      | attendance | Test Attendance  | SCI101 |

  @javascript
  Scenario: QR code page shows course name and session date for a session with password and QR code
    Given I am on the "Test Attendance" "mod_attendance > View" page logged in as "teacher1"
    And I click on "Add session" "button"
    And I set the field "Allow students to record own attendance" to "1"
    And I set the field "studentpassword" to "secret99"
    And I set the field "Include QR code" to "1"
    And I set the following fields to these values:
      | id_sestime_starthour | 09 |
      | id_sestime_endhour   | 10 |
    And I click on "id_submitbutton" "button"
    When I click on "a.btn" "css_element"
    Then I should see "Science Class 1"
    And I should see "SCI101"

  @javascript
  Scenario: Password-only page shows course name and session date
    Given I am on the "Test Attendance" "mod_attendance > View" page logged in as "teacher1"
    And I click on "Add session" "button"
    And I set the field "Allow students to record own attendance" to "1"
    And I set the field "studentpassword" to "secret99"
    And I set the following fields to these values:
      | id_sestime_starthour | 09 |
      | id_sestime_endhour   | 10 |
    And I click on "id_submitbutton" "button"
    When I click on "a.btn" "css_element"
    Then I should see "Science Class 1"
    And I should see "SCI101"
