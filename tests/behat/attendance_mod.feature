@javascript @mod @uon @mod_attendance
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
            | username    | firstname | lastname | email            | idnumber | department       | institution |
            | student1    | Sam       | Student  | student1@asd.com | 1234     | computer science | University of Nottingham |
            | teacher1    | Teacher   | One      | teacher1@asd.com | 5678     | computer science | University of Nottingham |
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
        And I follow "Add session"
        And I set the field "Allow students to record own attendance" to "1"
        And I set the following fields to these values:
            | id_sestime_starthour | 22 |
            | id_sestime_endhour   | 23 |
        And I click on "id_submitbutton" "button"
        Then I should see "22:00 - 23:00"
        And I log out
        When I log in as "student1"
        And I follow "Course 1"
        And I follow "Attendance"
        And I follow "All"
        And I follow "Submit attendance"
        And I set the field "Present" to "1"
        And I press "Save changes"
        Then I should see "Self-recorded"
        And I log out
        When I log in as "teacher1"
        And I follow "Course 1"
        And I expand "Reports" node
        And I follow "Logs"
        And I click on "Get these logs" "button"
        Then "Attendance taken by student" "link" should exist

    Scenario: Teachers can view low grade report and send a message
        When I log in as "teacher1"
        And I follow "Course 1"
        And I follow "Attendance"
        And I follow "Add"
        And I set the following fields to these values:
            | id_sestime_starthour | 01 |
            | id_sestime_endhour   | 02 |
        And I click on "id_submitbutton" "button"
        And I follow "Report"
        And I follow "Below 100%"
        And I set the field "cb_selector" to "1"
        And I click on "Send a message" "button"
        Then I should see "Message body"
        And I should see "student1@asd.com"
        And I expand "Reports" node
        And I follow "Logs"
        And I click on "Get these logs" "button"
        Then "Attendance report viewed" "link" should exist

    Scenario: Export report includes id number, department and institution
        When I log in as "teacher1"
        And I follow "Course 1"
        And I follow "Attendance"
        And I follow "Add"
        And I set the following fields to these values:
            | id_sestime_starthour | 01 |
            | id_sestime_endhour   | 02 |
        And I click on "id_submitbutton" "button"
        And I follow "Export"
        Then the field "id_ident_idnumber" matches value ""
        And the field "id_ident_institution" matches value ""
        And the field "id_ident_department" matches value ""
    # Removed dependency on behat_download to allow automated Travis CI tests to pass.
    # It would be good to add these back at some point.
