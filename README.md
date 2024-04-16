# Import question as new version #

This plugin allows you to import a question from a Moodle XML file as a new version of an existing question.

Once this plugin is installed, in the question bank, in the action menu for each question, there is a new option
'Import new version'. If you choose that, you see a form where you can upload a Moodle XML file containing a
single question. If possible (e.g. if the question type matches) then that question is imported as the latest
version of this question. (This is different from the standard way of importing questions, which always
creates new questions.)


## Installing from the Moodle plugins database

Install from the Moodle plugins database https://moodle.org/plugins/qbank_importasversion.


### Install using git

Or you can install using git. Type this commands in the root of your Moodle install

    git clone https://github.com/maths/moodle-qbank_importasversion.git question/bank/importasversion
    echo /question/bank/importasversion/ >> .git/info/exclude

Then run the moodle update process
Site administration > Notifications


## Credits ##

This plugin was created at the 2023 MootDACH DevCamp by Tim Hunt, Michael Kallweit, Andreas Steiger.


## License ##

2023 MootDACH DevCamp contributors

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.
