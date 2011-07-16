<?php  // $Id: add_form.php,v 1.1.2.2 2009/02/23 19:22:42 dlnsk Exp $

require_once($CFG->libdir.'/formslib.php');

class mod_attforblock_add_form extends moodleform {

    function definition() {

        global $CFG, $USER;
        $mform    =& $this->_form;

        $course        = $this->_customdata['course'];
        $cm            = $this->_customdata['cm'];
        $modcontext    = $this->_customdata['modcontext'];


        $mform->addElement('header', 'general', get_string('addsession','attforblock'));//fill in the data depending on page params
                                                    //later using set_data

        $groupmode = groups_get_activity_groupmode($cm);
        switch ($groupmode) {
            case NOGROUPS:
                $mform->addElement('static', 'sessiontypedescription', get_string('sessiontype', 'attforblock'),
                                  get_string('commonsession', 'attforblock'));
                $mform->addHelpButton('sessiontypedescription', 'sessiontype', 'attforblock');
                $mform->addElement('hidden', 'sessiontype', attforblock::SESSION_COMMON);
                break;
            case SEPARATEGROUPS:
                $mform->addElement('static', 'sessiontypedescription', get_string('sessiontype', 'attforblock'),
                                  get_string('groupsession', 'attforblock'));
                $mform->addHelpButton('sessiontypedescription', 'sessiontype', 'attforblock');
                $mform->addElement('hidden', 'sessiontype', attforblock::SESSION_GROUP);
                break;
            case VISIBLEGROUPS:
                $radio=array();
                $radio[] = &MoodleQuickForm::createElement('radio', 'sessiontype', '', get_string('commonsession','attforblock'), attforblock::SESSION_COMMON);
                $radio[] = &MoodleQuickForm::createElement('radio', 'sessiontype', '', get_string('groupsession','attforblock'), attforblock::SESSION_GROUP);
                $mform->addGroup($radio, 'sessiontype', get_string('sessiontype','attforblock'), ' ', false);
                $mform->addHelpButton('sessiontype', 'sessiontype', 'attforblock');
                $mform->setDefault('sessiontype', attforblock::SESSION_COMMON);
                break;
        }
        if ($groupmode == SEPARATEGROUPS or $groupmode == VISIBLEGROUPS) {
            if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext))
                $groups = groups_get_all_groups ($course->id, $USER->id);
            else
                $groups = groups_get_all_groups($course->id);
            if ($groups) {
                $selectgroups = array();
                foreach ($groups as $group) {
                    $selectgroups[$group->id] = $group->name;
                }
                $select = &$mform->addElement('select', 'groups', get_string('groups', 'group'), $selectgroups);
                $select->setMultiple(true);
                $mform->disabledIf('groups','sessiontype','neq', attforblock::SESSION_GROUP);
            }
            else {
                $mform->updateElementAttr($radio, array('disabled'=>'disabled'));
                $mform->addElement('static', 'groups', get_string('groups', 'group'),
                                  get_string('nogroups', 'attforblock'));
                if ($groupmode == SEPARATEGROUPS)
                    return;
            }
        }
        
        $mform->addElement('checkbox', 'addmultiply', '', get_string('createmultiplesessions','attforblock'));
		$mform->addHelpButton('addmultiply', 'createmultiplesessions', 'attforblock');
		
//        $mform->addElement('date_selector', 'sessiondate', get_string('sessiondate','attforblock'));
        $mform->addElement('date_time_selector', 'sessiondate', get_string('sessiondate','attforblock'));

        for ($i=0; $i<=23; $i++) {
            $hours[$i] = sprintf("%02d",$i);
        }
        for ($i=0; $i<60; $i+=5) {
            $minutes[$i] = sprintf("%02d",$i);
        }
        $durtime = array();
        $durtime[] =& MoodleQuickForm::createElement('select', 'hours', get_string('hour', 'form'), $hours, false, true);
		$durtime[] =& MoodleQuickForm::createElement('select', 'minutes', get_string('minute', 'form'), $minutes, false, true);
        $mform->addGroup($durtime, 'durtime', get_string('duration','attforblock'), array(' '), true);
        
        $mform->addElement('date_selector', 'sessionenddate', get_string('sessionenddate','attforblock'));
		$mform->disabledIf('sessionenddate', 'addmultiply', 'notchecked');
        
        $sdays = array();
		if ($CFG->calendar_startwday === '0') { //week start from sunday
        	$sdays[] =& MoodleQuickForm::createElement('checkbox', 'Sun', '', get_string('sunday','calendar'));
		}
        $sdays[] =& MoodleQuickForm::createElement('checkbox', 'Mon', '', get_string('monday','calendar'));
        $sdays[] =& MoodleQuickForm::createElement('checkbox', 'Tue', '', get_string('tuesday','calendar'));
        $sdays[] =& MoodleQuickForm::createElement('checkbox', 'Wed', '', get_string('wednesday','calendar'));
        $sdays[] =& MoodleQuickForm::createElement('checkbox', 'Thu', '', get_string('thursday','calendar'));
        $sdays[] =& MoodleQuickForm::createElement('checkbox', 'Fri', '', get_string('friday','calendar'));
        $sdays[] =& MoodleQuickForm::createElement('checkbox', 'Sat', '', get_string('saturday','calendar'));
		if ($CFG->calendar_startwday !== '0') { //week start from sunday
        	$sdays[] =& MoodleQuickForm::createElement('checkbox', 'Sun', '', get_string('sunday','calendar'));
		}
        $mform->addGroup($sdays, 'sdays', get_string('sessiondays','attforblock'), array(' '), true);
		$mform->disabledIf('sdays', 'addmultiply', 'notchecked');
        
        $period = array(1=>1,2,3,4,5,6,7,8);
        $periodgroup = array();
        $periodgroup[] =& MoodleQuickForm::createElement('select', 'period', '', $period, false, true);
        $periodgroup[] =& MoodleQuickForm::createElement('static', 'perioddesc', '', get_string('week','attforblock'));
        $mform->addGroup($periodgroup, 'periodgroup', get_string('period','attforblock'), array(' '), false);
		$mform->disabledIf('periodgroup', 'addmultiply', 'notchecked');
        
        $mform->addElement('editor', 'sdescription', get_string('description', 'attforblock'), null, array('maxfiles'=>EDITOR_UNLIMITED_FILES, 'noclean'=>true, 'context'=>$modcontext));
        $mform->setType('sdescription', PARAM_RAW);
		
//-------------------------------------------------------------------------------
        // buttons
        $submit_string = get_string('addsession', 'attforblock');
        $this->add_action_buttons(false, $submit_string);
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['sessiontype'] == attforblock::SESSION_GROUP and empty($data['groups'])) {
            $errors['groups'] = get_string('errorgroupsnotselected','attforblock');
        }
        return $errors;
    }

}
?>
