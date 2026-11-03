# Change log for the Import question as new version question bank plugin

## Changes in version 1.6

* Added a bulk import: import many questions from one Moodle XML file at once, each as a new version of an existing question that is matched by its category and ID number. A dry run shows what would happen before anything is saved. Questions that cannot be matched are skipped, and no new questions are created.
* Added proper context handling during question import.
* Reject question-type save notices by default in the upload form, with an explicit override.
* The form override explicitly imports versions with warnings as Draft; imports without warnings remain Ready.
* Preserve permissive imports and Ready status for existing API callers by default.
* Roll back failed saves, including false return values, before committing or emitting an import event.
* Various code quality improvements dealing with edge cases.

## Changes in version 1.5.1

* Fixed typo in `version.php`.

## Changes in version 1.5

* Add verified compatibility with Moodle 5.2.
* Made plugin available via Composer.

## Changes in version 1.4

* Changed to default page style instead of popup style.
* Added pass through of error messages and notices during import.
* Add verified compatibility with Moodle 5.1.

## Changes in version 1.3

* Add verified compatibility with Moodle 5.0.

## Changes in version 1.2

* Add verified compatibility with Moodle 4.4 and 4.5.

## Changes in version 1.1

* Provide compatibility with Moodle 4.3.
* Fix a bug with importing Cloze questions.

## Changes in version 1.0

* We are declaring this stable. (Has been used at the Open University for several months.)
* The Question text is no longer output during the import process.
* Provide compatibility with Moodle 4.3.

## First Beta release 0.9

* Initial implementation of the functionality. It works!
* The first thing to improve is to add nicer validation.
