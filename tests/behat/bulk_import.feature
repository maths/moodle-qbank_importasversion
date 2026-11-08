@qbank @qbank_importasversion
Feature: Import new versions of many questions from one file
  As a teacher
  I want to upload one file with updated questions
  So that each matching question gets a new version

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

  @javascript @_file_upload
  Scenario: A teacher previews the file, then imports the matching questions
    Given I am on the "Course 1" "core_question > course question bank" page logged in as "teacher"
    When I set the field "Question bank tertiary navigation" to "Import new versions"
    And I upload "question/bank/importasversion/tests/fixtures/bulk-versions.xml" file to "Import" filemanager
    And I press "Import"
    Then I should see "2 questions will get a new version. Nothing has been imported yet."
    And I should see "Skipped: no question in this bank has this ID number." in the "TQ-9" "table_row"
    When I press "Import 2 new versions"
    Then I should see "Imported 2 new versions."
    And I press "Continue"
    And I should see "v2" in the "Updated one" "table_row"
    And I should see "v2" in the "Updated two" "table_row"
