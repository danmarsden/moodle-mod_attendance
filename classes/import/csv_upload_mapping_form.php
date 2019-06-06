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

/**
 * This file contains the form used to upload a csv attendance file to automatically update attendance records.
 *
 * @package   mod_attendance
 * @copyright 2019 Jonathan Chan <jonathan.chan@sta.uwi.edu>
 * @copyright based on work by 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later */
namespace mod_attendance\import;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

use core_text;
use moodleform;
require_once($CFG->libdir.'/formslib.php');

/**
 * Class for displaying the csv upload form.
 *
 * @package   mod_attendance
 * @copyright 2019 Jonathan Chan <jonathan.chan@sta.uwi.edu>
 * @copyright based on work by 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_upload_mapping_form extends moodleform {

    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {
        global $COURSE;

        $mform = $this->_form;
        $params = $this->_customdata;

        $mform->addElement('header', 'general', get_string('identifier', 'attendance'));

        // This is the array or column headers from the uploaded csv file.
        $header = $params['header'];
        // This allows the user to choose which column of the csv file will be used to identify the students.
        $mapfromoptions = array();
        if ($header) {
            foreach ($header as $i => $h) {
                $mapfromoptions[$i] = s($h);
            }
        }
        $mform->addElement('select', 'mapfrom', get_string('mapfrom', 'attendance'), $mapfromoptions);
        $mform->addHelpButton('mapfrom', 'mapfrom', 'attendance');
        // This allows the user to choose which field in the user database the identifying column will map to.
        $maptooptions = array(
            'userid'       => get_string('userid', 'attendance'),
            'username'     => get_string('username'),
            'useridnumber' => get_string('idnumber'),
            'useremail'    => get_string('email')
        );
        $mform->addElement('select', 'mapto', get_string('mapto', 'attendance'), $maptooptions);
        $mform->setDefault('mapto', 'useridnumber');
        $mform->addHelpButton('mapto', 'mapto', 'attendance');

        $mform->addElement('header', 'column_map', get_string('columnmap', 'attendance'));
        $mform->addHelpButton('column_map', 'columnmap', 'attendance');
        // The user maps Encoding, Scan Time and Scan Date to the corresponding columns in the csv file.
        $mform->addElement('select', 'encoding', get_string('encoding', 'attendance'), $mapfromoptions);
        $mform->setDefault('encoding', 1);
        $mform->addHelpButton('encoding', 'encoding', 'attendance');
        $mform->addElement('select', 'scantime', get_string('scantime', 'attendance'), $mapfromoptions);
        $mform->setDefault('scantime', 2);
        $mform->addElement('select', 'scandate', get_string('scandate', 'attendance'), $mapfromoptions);
        $mform->setDefault('scandate', 3);

        $mform->addElement('hidden', 'id', $params['cm']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sessionid', $params['sessionid']);
        $mform->setType('sessionid', PARAM_INT);
        $mform->addElement('hidden', 'grouptype', $params['grouptype']);
        $mform->setType('grouptype', PARAM_INT);
        $mform->addElement('hidden', 'importid', $params['importid']);
        $mform->setType('importid', PARAM_INT);
        $mform->setConstant('importid', $params['importid']);
        $this->add_action_buttons(true, get_string('uploadattendance', 'attendance'));
    }
}
