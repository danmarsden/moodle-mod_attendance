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
 * Attendance import forms
 *
 * @package   mod_attendance
 * @copyright  2016 David Herney <davidherney@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir.'/formslib.php');

/**
 * class for displaying import attendance form.
 *
 * @copyright  2016 David Herney <davidherney@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_attendance_attendanceimport_form extends moodleform {

    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {

        $mform    =& $this->_form;
        $cm            = $this->_customdata['cm'];
        $att           = $this->_customdata['att'];

        $mform->addElement('header', 'general', get_string('attendanceimport', 'attendance'));

        $attendances = $att->get_past_sessions();
        if (empty($attendances)) {
            $mform->addElement('static', 'nosessionexists', '', get_string('nosessionexists', 'attendance'));
            return;
        }

        $statuses = $att->get_statuses();
        if (empty($statuses)) {
            $mform->addElement('static', 'nostatusesexists', '', get_string('nostatusesexists', 'attendance'));
            return;
        }

        // Attendance list.
        $attendanceslist = array();
        $userformatdate = get_string('strftimedatetime');
        foreach ($attendances as $attendance) {
            $attendanceslist[$attendance->id] = $attendance->description . ' (' . userdate($attendance->sessdate, $userformatdate) . ')';
        }
        $mform->addElement('select', 'sessionid', get_string('session', 'attendance'), $attendanceslist);

        // Ident type list.
        $ident = array();
        $ident['id'] = get_string('studentid', 'attendance');
        $ident['username'] = get_string('username');
        $ident['idnumber'] = get_string('idnumber');
        $ident['email'] = get_string('email');

        $mform->addElement('select', 'userfield', get_string('identifyby', 'attendance'), $ident);
        $mform->setDefault('userfield', 'username');

        // Statuses list.
        $statuseslist = array();
        foreach ($statuses as $status) {
            $statuseslist[$status->id] = $status->description;
        }
        $mform->addElement('select', 'status', get_string('setstatus', 'attendance'), $statuseslist);

        // Users list.
        $mform->addElement('textarea', 'userslist', get_string('userslisttoimport', 'attendance'), array('cols' => 40, 'rows' => 15));
        $mform->addHelpButton('userslist', 'userslisttoimport', 'attendance');
        $mform->addRule('userslist', get_string('userslist_required', 'attendance'), 'required', null, 'client');

        $submitstring = get_string('import', 'attendance');
        $this->add_action_buttons(false, $submitstring);

        $mform->addElement('hidden', 'id', $cm->id);
        $mform->setType('id', PARAM_INT);
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate the 'users' field.
        if ($data['selectedusers'] && empty($data['users'])) {
            $errors['users'] = get_string('mustselectusers', 'mod_attendance');
        }

        return $errors;
    }
}

