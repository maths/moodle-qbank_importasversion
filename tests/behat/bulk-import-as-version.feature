@qbank @qbank_importasversion
Feature: Importing many questions as new versions of existing questions
  As a teacher
  To update many questions at once
  I want to import one file and see what will happen before anything is saved

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
      | questioncategory | qtype     | name       | idnumber |
      | Test questions   | truefalse | First one  | TQ-1     |
      | Test questions   | truefalse | Second one | TQ-2     |

  Scenario: The bulk import page shows the form
    When I am on the "Course 1" "qbank_importasversion > course bulk import" page logged in as teacher
    Then I should see "Import many questions from one Moodle XML file at once"
    And "Preview" "button" should exist

  @javascript @_file_upload
  Scenario: The dry run shows what would happen and saves nothing
    When I am on the "Course 1" "qbank_importasversion > course bulk import" page logged in as teacher
    And I upload "question/bank/importasversion/tests/fixtures/bulk-new-versions.xml" file to "Import" filemanager
    And I press "Preview"
    Then I should see "Dry run: nothing has been saved yet"
    And I should see "2 would be imported as a new version, 1 would be skipped."
    And I should see "The question in the file has no ID number"
    When I press "Cancel"
    Then I should see "v1" in the "First one" "table_row"
    And I should see "v1" in the "Second one" "table_row"

  @javascript @_file_upload
  Scenario: Confirming the dry run imports the questions as new versions
    When I am on the "Course 1" "qbank_importasversion > course bulk import" page logged in as teacher
    And I upload "question/bank/importasversion/tests/fixtures/bulk-new-versions.xml" file to "Import" filemanager
    And I press "Preview"
    And I press "Import as new versions (2)"
    Then I should see "2 imported as a new version, 1 skipped, 0 failed."
    When I press "Continue"
    Then I should see "v2" in the "Updated one" "table_row"
    And I should see "v2" in the "Updated two" "table_row"
