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

class mod_attforblock_export_form extends moodleform {

    function definition() {

        global $CFG, $USER;
        $mform    =& $this->_form;

        $course        = $this->_customdata['course'];
        $cm            = $this->_customdata['cm'];
        $modcontext    = $this->_customdata['modcontext'];


        $mform->addElement('header', 'general', get_string('export','quiz'));
		
		$groupmode=groups_get_activity_groupmode($cm);
        $groups = groups_get_activity_allowed_groups($cm, $USER->id);
		if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
			$grouplist[0] = get_string('allparticipants');
		}
		if ($groups) {
            foreach ($groups as $group) {
                $grouplist[$group->id] = $group->name;
            }
        }
        $mform->addElement('select', 'group', get_string('group'), $grouplist);
        
        $ident = array();
        $ident[] =& $mform->createElement('checkbox', 'id', '', get_string('studentid', 'attforblock'));
        $ident[] =& $mform->createElement('checkbox', 'uname', '', get_string('username'));
        $mform->addGroup($ident, 'ident', get_string('identifyby','attforblock'), array('<br />'), true);
        $mform->setDefaults(array('ident[id]' => true, 'ident[uname]' => true));
        
        $mform->addElement('checkbox', 'includeallsessions', get_string('includeall','attforblock'), get_string('yes'));
        $mform->setDefault('includeallsessions', true);
        $mform->addElement('checkbox', 'includenottaken', get_string('includenottaken','attforblock'), get_string('yes'));
        $mform->addElement('date_selector', 'sessionstartdate', get_string('startofperiod','attforblock'));
        $mform->setDefault('sessionstartdate', $course->startdate);
        $mform->disabledIf('sessionstartdate', 'includeallsessions', 'checked');
        $mform->addElement('date_selector', 'sessionenddate', get_string('endofperiod','attforblock'));
        $mform->disabledIf('sessionenddate', 'includeallsessions', 'checked');
        
        $mform->addElement('select', 'format', get_string('format'), 
        					array('excel' => get_string('downloadexcel','attforblock'),
        						  'ooo' => get_string('downloadooo','attforblock'),
        						  'text' => get_string('downloadtext','attforblock')
        					));
        					
        // buttons
        $submit_string = get_string('ok');
        $this->add_action_buttons(false, $submit_string);

        $mform->addElement('hidden', 'id', $cm->id);

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

