<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException as ExpectationException,
    Behat\Behat\Exception\PendingException as PendingException;

/**
 * Attendance steps definitions.
 *
 * @package    mod
 * @subpackage attendance
 * @category   test
 * @copyright  2014 University of Nottingham
 * @author     Joseph Baxter (joseph.baxter@nottingham.ac.uk)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_attendance extends behat_base {

    protected $file_contents;

    /**
     * @Then /^attendance export file is ok$/
     */
    public function attendance_export_file_is_ok() {

        global $CFG;
        
        $check = true;

        // Location selenium will download to.
        $dir = $CFG->behat_download;
        $files = scandir($dir, 1);
        $filename = $files[0];
        $file = fopen($dir . $filename, "r");

        $count = 0;
        $header = null;

        // The file is tab seperated but not exactly a tsv.
        while (($row = fgetcsv($file, 0, "\t")) !== FALSE) {

            // Ignore unwanted information at the start of the file.
            if ($count < 3) {
                $count++;
                continue;
            }
            
            if (!$header) {
                $header = $row;
            } else {
                $this->file_contents = array_combine($header, $row);
            }
            
            $count++;
        }
        
        fclose($file);
        unlink($dir . $filename);
        
        // Check if data rows exist.
        if ($count < 2) {
            $check = false;
        }

        if ($check) {

            return true;

        } else {

            throw new ExpectationException('Attendance export file not ok', $this->getSession());
        }

    }

    /**
     * @Given /^I should see "([^"]*)" as "([^"]*)" in the file$/
     */
    public function i_should_see_as_in_the_file($field, $value) {
      
        foreach ($this->file_contents as $array_field => $array_value) {

            if ($field == $array_field) {

                if ($value == $array_value) {

                    return true;

                } else {
                    
                    throw new PendingException();

                }
            }
        }
    }
    
}
