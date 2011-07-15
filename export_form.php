<?php  // $Id: export_form.php,v 1.2.2.3 2009/03/11 18:17:38 dlnsk Exp $

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
        $ident[] =& MoodleQuickForm::createElement('checkbox', 'id', '', get_string('studentid', 'attforblock'));
        $ident[] =& MoodleQuickForm::createElement('checkbox', 'uname', '', get_string('username'));
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
?>
