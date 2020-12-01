@mod @uon @mod_presence @mod_presence_preferences
Feature: Teachers can't change status variables to have empty acronyms or descriptions
  In order to update status variables
  As a teacher
  I need to see an error notice below each acronym / description that I try to set to be empty

  Background:
    Given the following "courses" exist:
      | fullname | shortname | summary                             | category | timecreated   | timemodified  |
      | Course 1 | C1        | Prove the presence activity works | 0        | ##yesterday## | ##yesterday## |
    And the following "users" exist:
      | username    | firstname | lastname |
      | student1    | Sam       | Student  |
      | teacher1    | Teacher   | One      |
    And the following "course enrolments" exist:
      | course | user     | role           | timestart     |
      | C1     | student1 | student        | ##yesterday## |
      | C1     | teacher1 | editingteacher | ##yesterday## |
    And I log in as "admin"
    And I navigate to "Plugins > presence" in site administration
    And I set the field "Enable rooms management" to "1"
    And I click on "Save changes" "button"
    And I should see "Changes saved"
    And I follow "Rooms"
    And I click on "Add room" "button"
    And I set the following fields to these values:
      | id_name | Room1 |
      | id_description   | Test Room 1 |
      | id_capacity | 2 |
    And I log out

  @javascript
  Scenario: Modified default status set added to new presence
    Given I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "presence" to section "1" and I fill the form with:
      | Name        | presence1       |
    And I follow "presence1"
    And I follow "Status set"
    Then the field with xpath "//*[@id='preferencesform']/table/tbody/tr[2]/td[3]/input" matches value "customstatusdescription"

