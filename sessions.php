<?php

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/add_form.php');
require_once(dirname(__FILE__).'/update_form.php');
require_once(dirname(__FILE__).'/duration_form.php');

$pageparams = new att_sessions_page_params();

$id                     = required_param('id', PARAM_INT);
$pageparams->action    = required_param('action', PARAM_INT);

$cm             = get_coursemodule_from_id('attforblock', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att            = $DB->get_record('attforblock', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$att = new attforblock($att, $cm, $course, $PAGE->context, $pageparams);

$att->perm->require_manage_capability();

$PAGE->set_url($att->url_sessions());
$PAGE->set_title($course->shortname. ": ".$att->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->set_button($OUTPUT->update_module_button($cm->id, 'attforblock'));
$PAGE->navbar->add($att->name);

$formparams = array('course' => $course, 'cm' => $cm, 'modcontext' => $PAGE->context);
switch ($att->pageparams->action) {
    case att_sessions_page_params::ACTION_ADD:
        $url = $att->url_sessions(array('action' => att_sessions_page_params::ACTION_ADD));
		$mform = new mod_attforblock_add_form($url, $formparams);
        if ($mform->is_submitted()) {
            $formdata = $mform->get_data();
            if (isset($formdata->addmultiply)) {
                notice(get_string('sessionsgenerated','attforblock'), $url);
            }
            else {
                $att->add_session_from_form_data($formdata);
                notice(get_string('sessionadded','attforblock'), $url);
            }
        }

        break;
    case att_sessions_page_params::ACTION_UPDATE:
		$sessionid	= required_param('sessionid');

        $url = $att->url_sessions(array('action' => att_sessions_page_params::ACTION_UPDATE, 'sessionid' => $sessionid));
        $formparams['sessionid'] = $sessionid;
		$mform = new mod_attforblock_update_form($url, $formparams);
        
	    if ($mform_update->is_cancelled()) {
	    	redirect($att->url_manage());
	    }

        if ($mform->is_submitted()) {
            
        }
        break;
}

$output = $PAGE->get_renderer('mod_attforblock');
$tabs = new attforblock_tabs($att, attforblock_tabs::TAB_ADD);
echo $output->header();
echo $output->heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);
echo $output->render($tabs);

$mform->display();

echo $OUTPUT->footer();

?>
