@qbank @qbank_importasversion
Feature: Importing a question as a new version of an existing question
  As a teacher
  To efficiently edit questions in certain ways
  I want to be able to export them, edit the exported file, then re-import as a new version of the same question

  Background:
    Given the following "users" exist:
      | username |
      | teacher  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name          |
      | Test questions   | truefalse | Test question |

  Scenario: The import process can be cancelled
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I choose "Import new version" action for "Test question" in the question bank
    And I press "Cancel"
    Then I should see "v1" in the "Test question" "table_row"

  Scenario: Form validations verifies that a file was uploaded
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I choose "Import new version" action for "Test question" in the question bank
    Then I should see "Accepted file types"
    And I should see "application/xml .xml"
    And I press "Import"
    And I should see "Required" in the "id_newfile_fieldset" "fieldset"

