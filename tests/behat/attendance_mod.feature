@mod @uon @mod_attendance
Feature: Teachers and Students can record session attendance
    In order to record session attendance
    As a student
    I need to be able to mark my own attendance to a session
    And as a teacher
    I need to be able to mark any students attendance to a session
    In order to report on session attendance
    As a teacher
    I need to be able to export session attendance and run reports
    In order to contact students with poor attendance
    As a teacher
    I need the ability to message a group of students with low attendance

    Background:
        Given the following "courses" exist:
            | fullname | shortname | summary | category |
            | Course 1 | C101      | Prove the attendance activity works | 0 |
        And the following "users" exist:
            | username    | firstname | lastname | email            |
            | student1    | Sam       | Student  | student1@asd.com |
            | teacher1    | Teacher   | One      | teacher1@asd.com |
        And the following "course enrolments" exist:
            | user        | course | role    |
            | student1    | C101   | student |
            | teacher1    | C101   | editingteacher |
        And I log in as "teacher1"
        And I follow "Course 1"
        And I turn editing mode on
        And I add a "Attendance" to section "1"
        And I press "Save and display" 
        And I log out

    Scenario: Students can mark their own attendance
        When I log in as "teacher1"
        And I follow "Course 1"
        And I follow "Attendance"
        And I follow "Add"
        And I check "Allow students to record own attendance"
        And I set the following fields to these values:
            | id_sessiondate_hour     | 23 |
        And I click on "id_submitbutton" "button"
        And I follow "Continue"
        And I log out
        When I log in as "student1"
        And I follow "Course 1"
        And I follow "Attendance"
        And I follow "Submit attendance"
        And I check "Present"
        And I press "Save changes"
        Then I should see "Self-recorded"
        And I log out
        When I log in as "teacher1"
        And I follow "Course 1"
        And I expand "Reports" node
        And I follow "Logs"
        And I click on "Get these logs" "button"
        Then "attendance taken by student" "link" should exist

    Scenario: Teachers can view low grade report and send a message
        When I log in as "teacher1"
        And I follow "Course 1"
        And I follow "Attendance"
        And I follow "Add"
        And I set the following fields to these values:
            | id_sessiondate_hour     | 01 |
        And I click on "id_submitbutton" "button"
        And I follow "Continue"
        And I follow "Report"
        And I follow "Low grade"
        And I check "user3"
        And I click on "Send a message" "button"
        Then I should see "Message body"
        And I should see "student1@asd.com"
        And I expand "Reports" node
        And I follow "Logs"
        And I click on "Get these logs" "button"
        Then "attendance report viewed" "link" should exist

    Scenario: Export report id number, department and institution are unchecked by default
        When I log in as "teacher1"
        And I follow "Course 1"
        And I follow "Attendance"
        And I follow "Export"
        Then the "id_ident_idnumber" checkbox should not be checked
        And the "id_ident_institution" checkbox should not be checked
        And the "id_ident_department" checkbox should not be checked
