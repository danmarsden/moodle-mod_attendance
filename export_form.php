<?php  // $Id: export_form.php,v 1.2.2.3 2009/03/11 18:17:38 dlnsk Exp $

require_once($CFG->libdir.'/formslib.php');

class mod_attforblock_export_form extends moodleform {

    function definition() {

        global $CFG, $USER;
        $mform    =& $this->_form;

        $course        = $this->_customdata['course'];
        $cm            = $this->_customdata['cm'];
//        $coursecontext = $this->_customdata['coursecontext'];
        $modcontext    = $this->_customdata['modcontext'];
//        $forum         = $this->_customdata['forum'];
//        $post          = $this->_customdata['post']; // hack alert


        $mform->addElement('header', 'general', get_string('export','quiz'));
		$mform->setHelpButton('general', array('export', get_string('export','quiz'), 'attforblock'));
		
//        $mform->addElement('date_selector', 'sessiondate', get_string('sessiondate','attforblock'));
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
        
        
        
        

//        for ($i=0; $i<=23; $i++) {
//            $hours[$i] = sprintf("%02d",$i);
//        }
//        for ($i=0; $i<60; $i+=5) {
//            $minutes[$i] = sprintf("%02d",$i);
//        }
//        $stime = array();
//        $stime[] =& MoodleQuickForm::createElement('select', 'hours', get_string('hour', 'form'), $hours, false, true);
//		$stime[] =& MoodleQuickForm::createElement('select', 'minutes', get_string('minute', 'form'), $minutes, false, true);
//        $mform->addGroup($stime, 'stime', get_string('sessionstarttime','attforblock'), array(' '), true);
        
//        $durtime = array();
//        $durtime[] =& MoodleQuickForm::createElement('select', 'hours', get_string('hour', 'form'), $hours, false, true);
//		$durtime[] =& MoodleQuickForm::createElement('select', 'minutes', get_string('minute', 'form'), $minutes, false, true);
//        $mform->addGroup($durtime, 'durtime', get_string('duration','attforblock'), array(' '), true);
        
        $mform->addElement('checkbox', 'includenottaken', get_string('includenottaken','attforblock'), get_string('yes'));
        $mform->addElement('date_selector', 'sessionenddate', get_string('endofperiod','attforblock'));
		$mform->disabledIf('sessionenddate', 'includenottaken', 'notchecked');
        
        $mform->addElement('select', 'format', get_string('format'), 
        					array('excel' => get_string('downloadexcel','attforblock'),
        						  'ooo' => get_string('downloadooo','attforblock'),
        						  'text' => get_string('downloadtext','attforblock')
        					));
        					
//        $opts = array();
//        $opts[] =& MoodleQuickForm::createElement('checkbox', 'Mon', '', get_string('monday','calendar'));
////        $opts[] =& MoodleQuickForm::createElement('checkbox', 'Tue', '', get_string('tuesday','calendar'));
////        $opts[] =& MoodleQuickForm::createElement('checkbox', 'Wed', '', get_string('wednesday','calendar'));
////        $opts[] =& MoodleQuickForm::createElement('checkbox', 'Thu', '', get_string('thursday','calendar'));
////        $opts[] =& MoodleQuickForm::createElement('checkbox', 'Fri', '', get_string('friday','calendar'));
////        $opts[] =& MoodleQuickForm::createElement('checkbox', 'Sat', '', get_string('saturday','calendar'));
//        $mform->addGroup($opts, 'opts', get_string('sessiondays','attforblock'), array(' '), true);
//		$mform->disabledIf('opts', 'addmultiply', 'notchecked');
//        
//        $period = array(1=>1,2,3,4,5,6,7,8);
//        $periodgroup = array();
//        $periodgroup[] =& MoodleQuickForm::createElement('select', 'period', '', $period, false, true);
//        $periodgroup[] =& MoodleQuickForm::createElement('static', 'perioddesc', '', get_string('week','attforblock'));
//        $mform->addGroup($periodgroup, 'periodgroup', get_string('period','attforblock'), array(' '), false);
//		$mform->disabledIf('periodgroup', 'addmultiply', 'notchecked');
//        
//        $mform->addElement('text', 'sdescription', get_string('description', 'attforblock'), 'size="48"');
//        $mform->setType('sdescription', PARAM_TEXT);
//        $mform->addRule('sdescription', get_string('maximumchars', '', 100), 'maxlength', 100, 'client'); 
		
//-------------------------------------------------------------------------------
        // buttons
        $submit_string = get_string('ok');
        $this->add_action_buttons(false, $submit_string);

        $mform->addElement('hidden', 'id', $cm->id);
//        $mform->addElement('hidden', 'action', 'add');

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
