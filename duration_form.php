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

class mod_attforblock_duration_form extends moodleform {

    function definition() {

        global $CFG;
        $mform    =& $this->_form;

        $course        = $this->_customdata['course'];
        $cm            = $this->_customdata['cm'];
        $modcontext    = $this->_customdata['modcontext'];
        $ids		   = $this->_customdata['ids'];

        $mform->addElement('header', 'general', get_string('changeduration','attforblock'));
		$mform->addElement('static', 'count', get_string('countofselected','attforblock'), count(explode('_', $ids)));
        
        for ($i=0; $i<=23; $i++) {
            $hours[$i] = sprintf("%02d",$i);
        }
        for ($i=0; $i<60; $i+=5) {
            $minutes[$i] = sprintf("%02d",$i);
        }
        $durselect[] =& $mform->createElement('select', 'hours', '', $hours);
		$durselect[] =& $mform->createElement('select', 'minutes', '', $minutes, false, true);
		$mform->addGroup($durselect, 'durtime', get_string('newduration','attforblock'), array(' '), true);
		
        $mform->addElement('hidden', 'ids', $ids);
       	$mform->addElement('hidden', 'id', $cm->id);
        $mform->addElement('hidden', 'action', att_sessions_page_params::ACTION_CHANGE_DURATION);
        
        $mform->setDefaults(array('durtime' => array('hours'=>0, 'minutes'=>0)));
		
//-------------------------------------------------------------------------------
        // buttons
        $submit_string = get_string('update', 'attforblock');
        $this->add_action_buttons(true, $submit_string);

//        $mform->addElement('hidden', 'id', $cm->id);
//        $mform->addElement('hidden', 'sessionid', $sessionid);
//        $mform->addElement('hidden', 'action', 'changeduration');

    }

}
