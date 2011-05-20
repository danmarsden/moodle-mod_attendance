<?php  // $Id: update_form.php,v 1.3.2.2 2009/02/23 19:22:42 dlnsk Exp $

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
        $durselect[] =& MoodleQuickForm::createElement('select', 'hours', '', $hours);
		$durselect[] =& MoodleQuickForm::createElement('select', 'minutes', '', $minutes, false, true);
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
?>
