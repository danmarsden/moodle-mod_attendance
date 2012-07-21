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

require_once($CFG->libdir.'/formslib.php');

class mod_attforblock_update_form extends moodleform {

    function definition() {

        global $CFG, $DB;
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
        $defopts = array('maxfiles'=>EDITOR_UNLIMITED_FILES, 'noclean'=>true, 'context'=>$modcontext);
        $sess = file_prepare_standard_editor($sess, 'description', $defopts, $modcontext, 'mod_attforblock', 'session', $sess->id);
        $data = array('sessiondate' => $sess->sessdate,
                'durtime' => array('hours' => $dhours, 'minutes' => $dmins),
                'sdescription' => $sess->description_editor);

        $mform->addElement('header', 'general', get_string('changesession','attforblock'));
        
		$mform->addElement('static', 'olddate', get_string('olddate','attforblock'), userdate($sess->sessdate, get_string('strftimedmyhm', 'attforblock')));
        $mform->addElement('date_time_selector', 'sessiondate', get_string('newdate','attforblock'));

        for ($i=0; $i<=23; $i++) {
            $hours[$i] = sprintf("%02d",$i);
        }
        for ($i=0; $i<60; $i+=5) {
            $minutes[$i] = sprintf("%02d",$i);
        }
        $durselect[] =& $mform->createElement('select', 'hours', '', $hours);
		$durselect[] =& $mform->createElement('select', 'minutes', '', $minutes, false, true);
		$mform->addGroup($durselect, 'durtime', get_string('duration','attforblock'), array(' '), true);
		
        $mform->addElement('editor', 'sdescription', get_string('description', 'attforblock'), null, $defopts);
        $mform->setType('sdescription', PARAM_RAW);
        
        $mform->setDefaults($data);
		
//-------------------------------------------------------------------------------
        // buttons
        $submit_string = get_string('update', 'attforblock');
        $this->add_action_buttons(true, $submit_string);
    }

//    function validation($data, $files) {
//        $errors = parent::validation($data, $files);
//        if (($data['timeend']!=0) && ($data['timestart']!=0)
//            && $data['timeend'] <= $data['timestart']) {
//                $errors['timeend'] = get_string('timestartenderror', 'forum');
//            }
//        return $errors;
//    }

}
