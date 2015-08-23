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
 * Update form
 *
 * @package    mod_attendance
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->libdir.'/formslib.php');

/**
 * class for displaying update form.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_attendance_update_form extends moodleform {

    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {

        global $DB;
        $mform    =& $this->_form;

        $course        = $this->_customdata['course'];
        $cm            = $this->_customdata['cm'];
        $modcontext    = $this->_customdata['modcontext'];
        $sessionid     = $this->_customdata['sessionid'];

        if (!$sess = $DB->get_record('attendance_sessions', array('id'=> $sessionid) )) {
            error('No such session in this course');
        }
        $dhours = floor($sess->duration / HOURSECS);
        $dmins = floor(($sess->duration - $dhours * HOURSECS) / MINSECS);
        $sesendtime = $sess->sessdate + $sess->duration;
        $defopts = array('maxfiles'=>EDITOR_UNLIMITED_FILES, 'noclean'=>true, 'context'=>$modcontext);
        $sess = file_prepare_standard_editor($sess, 'description', $defopts, $modcontext, 'mod_attendance', 'session', $sess->id);
        $data = array('sessiondate' => $sess->sessdate,
                'sesstarttime' => array('hours' => userdate($sess->sessdate, '%H'), 'minutes' => userdate($sess->sessdate,'%M')),
                'sesendtime' => array('hours' => userdate($sesendtime, '%H'), 'minutes' => userdate($sesendtime,'%M')),
                'durtime' => array('hours' => $dhours, 'minutes' => $dmins),
                'sdescription' => $sess->description_editor);

        $mform->addElement('header', 'general', get_string('changesession', 'attendance'));

        $mform->addElement('static', 'olddate', get_string('olddate', 'attendance'),
                           userdate($sess->sessdate, get_string('strftimedmyhm', 'attendance')));
        $mform->addElement('date_selector', 'sessiondate', get_string('newdate', 'attendance'));

        for ($i=0; $i<=23; $i++) {
            $hours[$i] = sprintf("%02d", $i);
        }
        for ($i=0; $i<60; $i+=5) {
            $minutes[$i] = sprintf("%02d", $i);
        }

        $sesstarttime = array();
        $sesstarttime[] =& $mform->createElement('select', 'hours', '', $hours, false, true);
        $sesstarttime[] =& $mform->createElement('select', 'minutes', '', $minutes, false, true);
        $mform->addGroup($sesstarttime, 'sesstarttime', get_string('sessiontime', 'attendance'), array(' '), true);

        $mform->addElement('hidden', 'coursestartdate', $course->startdate);
        $mform->setType('coursestartdate', PARAM_INT);

        if (get_config('attendance', 'sessionendtime') == ATT_DURATION) {
            $durselect = array();
            $durselect[] =& $mform->createElement('select', 'hours', '', $hours, false, true);
            $durselect[] =& $mform->createElement('select', 'minutes', '', $minutes, false, true);
            $mform->addGroup($durselect, 'durtime', get_string('duration', 'attendance'), array(' '), true);
        } else {
            $sesendtime = array();
            $sesendtime[] =& $mform->createElement('select', 'hours', '', $hours, false, true);
            $sesendtime[] =& $mform->createElement('select', 'minutes', '', $minutes, false, true);
            $mform->addGroup($sesendtime, 'sesendtime', get_string('endtime', 'attendance'), array(' '), true);
        }

        // Show which status set is in use.
        $maxstatusset = attendance_get_max_statusset($this->_customdata['att']->id);
        if ($maxstatusset > 0) {
            $mform->addElement('static', 'statusset', get_string('usestatusset', 'mod_attendance'),
                               att_get_setname($this->_customdata['att']->id, $sess->statusset));
        }
        $mform->addElement('editor', 'sdescription', get_string('description', 'attendance'), null, $defopts);
        $mform->setType('sdescription', PARAM_RAW);

        $mform->setDefaults($data);

        $submit_string = get_string('update', 'attendance');
        $this->add_action_buttons(true, $submit_string);
    }

    /**
     * Perform minimal validation on the settings form
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $sesstarttime = $data['sesstarttime']['hours']*HOURSECS + $data['sesstarttime']['minutes']*MINSECS;
        $sessiondate = $data['sessiondate'] + $sesstarttime;

        $allowoldsessions = get_config('attendance', 'allowoldsessions');
        if (!$allowoldsessions && $sessiondate < $data['coursestartdate']) {
            $errors['sessiondate'] = get_string('sessionenddatepriortocoursestartdate', 'attendance',
                userdate($data['coursestartdate'], get_string('strftimedmy', 'attendance')));
        }

        if (get_config('attendance', 'sessionendtime') == ATT_ENDTIME) {
            $sesendtime = $data['sesendtime']['hours']*HOURSECS + $data['sesendtime']['minutes']*MINSECS;
            if ($sesendtime < $sesstarttime) {
                $errors['sesendtime'] = get_string('invalidsessionendtime', 'attendance');
            }
        }

        return $errors;
    }
}
