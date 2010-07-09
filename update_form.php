<?php  // $Id: update_form.php,v 1.3.2.2 2009/02/23 19:22:42 dlnsk Exp $

require_once($CFG->libdir.'/formslib.php');

class mod_attforblock_update_form extends moodleform {

    function definition() {

        global $CFG;
        $mform    =& $this->_form;

        $course        = $this->_customdata['course'];
        $cm            = $this->_customdata['cm'];
//        $coursecontext = $this->_customdata['coursecontext'];
        $modcontext    = $this->_customdata['modcontext'];
        $sessionid     = $this->_customdata['sessionid'];


        if (!$att = get_record('attendance_sessions', 'id', $sessionid) ) {
	        error('No such session in this course');
	    }
        $mform->addElement('header', 'general', get_string('changesession','attforblock'));
		$mform->setHelpButton('general', array('changesession', get_string('changesession','attforblock'), 'attforblock'));
        
		$mform->addElement('static', 'olddate', get_string('olddate','attforblock'), userdate($att->sessdate, get_string('strftimedmyhm', 'attforblock')));
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
		
        $mform->addElement('text', 'sdescription', get_string('description', 'attforblock'), 'size="48"');
        $mform->setType('sdescription', PARAM_TEXT);
        $mform->addRule('sdescription', get_string('maximumchars', '', 100), 'maxlength', 100, 'client'); 
        
        $dhours = floor($att->duration / HOURSECS);
        $dmins = floor(($att->duration - $dhours * HOURSECS) / MINSECS);
        $mform->setDefaults(array('sessiondate' => $att->sessdate, 
        						  'durtime' => array('hours'=>$dhours, 'minutes'=>$dmins),
        						  'sdescription' => $att->description));
		
//-------------------------------------------------------------------------------
        // buttons
        $submit_string = get_string('update', 'attforblock');
        $this->add_action_buttons(true, $submit_string);

        $mform->addElement('hidden', 'id', $cm->id);
        $mform->addElement('hidden', 'sessionid', $sessionid);
        $mform->addElement('hidden', 'action', 'update');

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
