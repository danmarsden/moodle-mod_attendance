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
 * @package    mod_presence
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_presence\form;

defined('MOODLE_INTERNAL') || die();

/**
 * class for displaying update session form.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class updatesession extends \moodleform {

    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {

        global $DB;
        $mform    =& $this->_form;

        $modcontext    = $this->_customdata['modcontext'];
        $sessionid     = $this->_customdata['sessionid'];
        $presence           = $this->_customdata['att'];

        if (!$sess = $DB->get_record('presence_sessions', array('id' => $sessionid) )) {
            error('No such session in this course');
        }

        $presencesubnet = $DB->get_field('presence', 'subnet', array('id' => $sess->presenceid));
        $maxfiles = intval(get_config('enableunlimitedfiles', 'mod_presence')) ? EDITOR_UNLIMITED_FILES : 0;
        $defopts = array('maxfiles' => $maxfiles, 'noclean' => true, 'context' => $modcontext);
        $sess = file_prepare_standard_editor($sess, 'description', $defopts, $modcontext, 'mod_presence', 'session', $sess->id);

        $sess->bookings = presence_sessionbookings($sess->id);

        $starttime = $sess->sessdate - usergetmidnight($sess->sessdate);
        $starthour = floor($starttime / HOURSECS);
        $startminute = floor(($starttime - $starthour * HOURSECS) / MINSECS);

        $enddate = $sess->sessdate + $sess->duration;
        $endtime = $enddate - usergetmidnight($enddate);
        $endhour = floor($endtime / HOURSECS);
        $endminute = floor(($endtime - $endhour * HOURSECS) / MINSECS);

        $data = array(
            'sessiondate' => $sess->sessdate,
            'sestime' => array('starthour' => $starthour, 'startminute' => $startminute,
            'endhour' => $endhour, 'endminute' => $endminute),
            'sdescription' => $sess->description,
            'calendarevent' => $sess->calendarevent,
            'roomid' => $sess->roomid,
            'maxattendants' => $sess->maxattendants,
        );

        $mform->addElement('header', 'general', get_string('changesession', 'presence'));


        $olddate = construct_session_full_date_time($sess->sessdate, $sess->duration);
        $mform->addElement('static', 'olddate', get_string('olddate', 'presence'), $olddate);

        presence_form_sessiondate_selector($mform);

        $mform->addElement('textarea', 'sdescription', get_string('description', 'presence'),
                           array('rows' => 2, 'columns' => 100), $defopts);
        $mform->setType('sdescription', PARAM_RAW);

        presence_form_session_room($mform, $presence, $sess);

        $mform->setDefaults($data);
        $this->add_action_buttons(true);
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

        return $errors;
    }
}
