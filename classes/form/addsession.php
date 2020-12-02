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
 * This file contains the forms to add session.
 *
 * @package   mod_presence
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_presence\form;

defined('MOODLE_INTERNAL') || die();

use moodleform;
use mod_presence_structure;
use DateTime;
use DateInterval;
use DatePeriod;

/**
 * class for displaying add form.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class addsession extends moodleform {

    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {

        global $CFG, $USER;
        $mform    =& $this->_form;

        $course        = $this->_customdata['course'];
        $cm            = $this->_customdata['cm'];
        $modcontext    = $this->_customdata['modcontext'];
        $presence           = $this->_customdata['att'];

        $pluginconfig = get_config('presence');

        presence_form_sessiondate_selector($mform);

        // For multiple sessions.
        $mform->addElement('checkbox', 'addmultiply', '', get_string('repeatasfollows', 'presence'));
        $mform->addHelpButton('addmultiply', 'createmultiplesessions', 'presence');

        $sdays = array();
        if ($CFG->calendar_startwday === '0') { // Week start from sunday.
            $sdays[] =& $mform->createElement('checkbox', 'Sun', '', get_string('sunday', 'calendar'));
        }
        $sdays[] =& $mform->createElement('checkbox', 'Mon', '', get_string('monday', 'calendar'));
        $sdays[] =& $mform->createElement('checkbox', 'Tue', '', get_string('tuesday', 'calendar'));
        $sdays[] =& $mform->createElement('checkbox', 'Wed', '', get_string('wednesday', 'calendar'));
        $sdays[] =& $mform->createElement('checkbox', 'Thu', '', get_string('thursday', 'calendar'));
        $sdays[] =& $mform->createElement('checkbox', 'Fri', '', get_string('friday', 'calendar'));
        $sdays[] =& $mform->createElement('checkbox', 'Sat', '', get_string('saturday', 'calendar'));
        if ($CFG->calendar_startwday !== '0') { // Week start from sunday.
            $sdays[] =& $mform->createElement('checkbox', 'Sun', '', get_string('sunday', 'calendar'));
        }
        $mform->addGroup($sdays, 'sdays', get_string('repeaton', 'presence'), array('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'), true);
        $mform->hideIf('sdays', 'addmultiply', 'notchecked');

        $period = array(1 => 1, 2, 3, 4);
        $periodgroup = array();
        $periodgroup[] =& $mform->createElement('select', 'period', '', $period, false, true);
        $periodgroup[] =& $mform->createElement('static', 'perioddesc', '', get_string('week', 'presence'));
        $mform->addGroup($periodgroup, 'periodgroup', get_string('repeatevery', 'presence'), array(' '), false);
        $mform->hideIf('periodgroup', 'addmultiply', 'notchecked');

        $mform->addElement('date_selector', 'sessionenddate', get_string('repeatuntil', 'presence'),
        ['startyear' => date('Y'), 'stopyear' => date('Y') + 1]);
        $mform->hideIf('sessionenddate', 'addmultiply', 'notchecked');


        $maxfiles = 0; // intval(get_config('enableunlimitedfiles', 'mod_presence')) ? EDITOR_UNLIMITED_FILES : 0;
        $mform->addElement('textarea', 'sdescription', get_string('description', 'presence'), array('rows' => 2, 'columns' => 80),
                            array('maxfiles' => $maxfiles, 'noclean' => true, 'context' => $modcontext));
        $mform->setType('sdescription', PARAM_TEXT);

        presence_form_session_room($mform, $presence);

        $mform->addElement('html', '<div id="presence_collisions"></div>');


        $this->add_action_buttons(true, get_string('add', 'presence'));
    }

    /**
     * Perform minimal validation on the settings form
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        $sesstarttime = $data['sestime']['starthour'] * HOURSECS + $data['sestime']['startminute'] * MINSECS;
        $sesendtime = $data['sestime']['endhour'] * HOURSECS + $data['sestime']['endminute'] * MINSECS;
        if ($sesendtime < $sesstarttime) {
            $errors['sestime'] = get_string('invalidsessionendtime', 'presence');
        }

        if (!empty($data['addmultiply']) && $data['sessiondate'] != 0 && $data['sessionenddate'] != 0 &&
                $data['sessionenddate'] < $data['sessiondate']) {
            $errors['sessionenddate'] = get_string('invalidsessionenddate', 'presence');
        }

        $addmulti = isset($data['addmultiply']) ? (int)$data['addmultiply'] : 0;
        if (($addmulti != 0) && (!array_key_exists('sdays', $data) || empty($data['sdays']))) {
            $data['sdays'] = array();
            $errors['sdays'] = get_string('required', 'presence');
        }
        if (isset($data['sdays'])) {
            if (!$this->checkweekdays($data['sessiondate'], $data['sessionenddate'], $data['sdays']) ) {
                $errors['sdays'] = get_string('checkweekdays', 'presence');
            }
        }
        if ($addmulti && ceil(($data['sessionenddate'] - $data['sessiondate']) / YEARSECS) > 1) {
            $errors['sessionenddate'] = get_string('timeahead', 'presence');
        }
        $sessstart = $data['sessiondate'] + $sesstarttime;
//        if ($sessstart < $data['coursestartdate'] && $sessstart != $data['previoussessiondate']) {
//            $errors['sessiondate'] = get_string('priorto', 'presence',
//                userdate($data['coursestartdate'], get_string('strftimedmyhm', 'presence')));
//            $this->_form->setConstant('previoussessiondate', $sessstart);
//        }

        return $errors;
    }

    /**
     * Check weekdays function.
     * @param int $sessiondate
     * @param int $sessionenddate
     * @param int $sdays
     * @return bool
     */
    private function checkweekdays($sessiondate, $sessionenddate, $sdays) {

        $found = false;

        $daysofweek = array(0 => "Sun", 1 => "Mon", 2 => "Tue", 3 => "Wed", 4 => "Thu", 5 => "Fri", 6 => "Sat");
        $start = new DateTime( date("Y-m-d", $sessiondate) );
        $interval = new DateInterval('P1D');
        $end = new DateTime( date("Y-m-d", $sessionenddate) );
        $end->add( new DateInterval('P1D') );

        $period = new DatePeriod($start, $interval, $end);
        foreach ($period as $date) {
            if (!$found) {
                foreach ($sdays as $name => $value) {
                    $key = array_search($name, $daysofweek);
                    if ($date->format("w") == $key) {
                        $found = true;
                        break;
                    }
                }
            }
        }

        return $found;
    }
}
