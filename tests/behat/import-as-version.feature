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
    And the following "core_question > Tags" exist:
      | question      | tag        |
      | Test question | source-tag |

  Scenario: The import process can be cancelled
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I choose "Import a new version" action for "Test question" in the question bank
    And I press "Cancel"
    Then I should see "v1" in the "Test question" "table_row"

  @javascript @_file_upload
  Scenario: Question can be imported as a new version
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I choose "Import a new version" action for "Test question" in the question bank
    And I upload "question/bank/importasversion/tests/fixtures/edited-true-false-question.xml" file to "Import" filemanager
    And I press "Import"
    Then I should see "New version of question 'Test question' imported successfully."
    And I should not see "The right answer is 'False'."
    And I should see "v2" in the "Updated question" "table_row"

  @javascript @_file_upload
  Scenario: Plain Import does not carry over the replaced version's tags
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I choose "Import a new version" action for "Test question" in the question bank
    And I upload "question/bank/importasversion/tests/fixtures/edited-true-false-question.xml" file to "Import" filemanager
    And I press "Import"
    Then I should see "v2" in the "Updated question" "table_row"
    And I should not see "source-tag" in the "Updated question" "table_row"

  @javascript @_file_upload
  Scenario: Import and merge tags carries over the replaced version's tags
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I choose "Import a new version" action for "Test question" in the question bank
    And I upload "question/bank/importasversion/tests/fixtures/edited-true-false-question.xml" file to "Import" filemanager
    And I press "Import and merge tags"
    Then I should see "v2" in the "Updated question" "table_row"
    And I should see "source-tag" in the "Updated question" "table_row"

  @javascript @_file_upload
  Scenario: Import and merge tags unions file tags with the replaced version's tags
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I choose "Import a new version" action for "Test question" in the question bank
    And I upload "question/bank/importasversion/tests/fixtures/merge-source-tagged.xml" file to "Import" filemanager
    And I press "Import and merge tags"
    Then I should see "v2" in the "Updated question" "table_row"
    And I should see "source-tag" in the "Updated question" "table_row"
    And I should see "file-tag" in the "Updated question" "table_row"

  @javascript @_file_upload
  Scenario: Problems are displayed
    When I am on the "Course 1" "core_question > course question bank" page logged in as teacher
    And I choose "Import a new version" action for "Test question" in the question bank
    And I upload "question/bank/importasversion/tests/fixtures/multiple-questions.xml" file to "Import" filemanager
    And I press "Import"
    Then I should see "Question import failed for 'Test question'."
    And I should see "Your file did not contain exactly one question!"
    And I should not see "The right answer is 'False'."
    And I should see "v1" in the "Test question" "table_row"
