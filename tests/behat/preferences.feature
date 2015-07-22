@mod @uon @mod_attendance @mod_attendance_preferences
Feature: Teachers can't change status variables to have empty acronyms or descriptions
    In order to update status variables
    As a teacher
    I need to see an error notice below each acronym / description that I try to set to be empty

    Background:
        Given the following "courses" exist:
            | fullname | shortname | summary | category |
            | Course 1 | C101      | Prove the attendance activity settings works | 0 |
        And the following "users" exist:
            | username    | firstname | lastname |
            | student1    | Sam       | Student  |
            | teacher1    | Teacher   | One      |
        And the following "course enrolments" exist:
            | user        | course | role    |
            | student1    | C101   | student |
            | teacher1    | C101   | editingteacher |
        And I log in as "teacher1"
        And I follow "Course 1"
        And I turn editing mode on
        And I add a "Attendance" to section "1"
        And I press "Save and display"
        And I follow "Settings"

    @javascript
    Scenario: Teachers can add status variables
        # Set the second status acronym to be empty
        Given I set the field with xpath "//*[@id='preferencesform']/table/tbody/tr[2]/td[2]/input" to ""
        # Set the second status description to be empty
        And I set the field with xpath "//*[@id='preferencesform']/table/tbody/tr[2]/td[3]/input" to ""
        # Set the second status grade to be empty
        And I set the field with xpath "//*[@id='preferencesform']/table/tbody/tr[2]/td[4]/input" to ""
        When I click on "Update" "button" in the "#preferencesform" "css_element"
        Then I should see "Empty acronyms are not allowed" in the "//*[@id='preferencesform']/table/tbody/tr[2]/td[2]/p" "xpath_element"
        And I should see "Empty descriptions are not allowed" in the "//*[@id='preferencesform']/table/tbody/tr[2]/td[3]/p" "xpath_element"
        And I click on "Update" "button" in the "#preferencesform" "css_element"

        # Set the first status acronym to be empty
        Given I set the field with xpath "//*[@id='preferencesform']/table/tbody/tr[1]/td[2]/input" to ""
        # Set the first status description to be empty
        And I set the field with xpath "//*[@id='preferencesform']/table/tbody/tr[1]/td[3]/input" to ""
        # Set the first status grade to be empty
        And I set the field with xpath "//*[@id='preferencesform']/table/tbody/tr[1]/td[4]/input" to ""
        # Set the third status acronym to be empty
        And I set the field with xpath "//*[@id='preferencesform']/table/tbody/tr[3]/td[2]/input" to ""
        # Set the third status description to be empty
        And I set the field with xpath "//*[@id='preferencesform']/table/tbody/tr[3]/td[3]/input" to ""
        # Set the third status grade to be empty
        And I set the field with xpath "//*[@id='preferencesform']/table/tbody/tr[3]/td[4]/input" to ""
        When I click on "Update" "button" in the "#preferencesform" "css_element"
        Then I should see "Empty acronyms are not allowed" in the "//*[@id='preferencesform']/table/tbody/tr[1]/td[2]/p" "xpath_element"
        And I should see "Empty descriptions are not allowed" in the "//*[@id='preferencesform']/table/tbody/tr[1]/td[3]/p" "xpath_element"
        And I should see "Empty acronyms are not allowed" in the "//*[@id='preferencesform']/table/tbody/tr[3]/td[2]/p" "xpath_element"
        And I should see "Empty descriptions are not allowed" in the "//*[@id='preferencesform']/table/tbody/tr[3]/td[3]/p" "xpath_element"
