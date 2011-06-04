<?php

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$pageparams = new att_take_page_params();

$id                     = required_param('id', PARAM_INT);
$pageparams->sessionid  = required_param('sessionid', PARAM_INT);
$pageparams->grouptype  = required_param('grouptype', PARAM_INT);
$pageparams->group    	= optional_param('group', null, PARAM_INT);
$pageparams->sort 		= optional_param('sort', null, PARAM_INT);
$pageparams->copyfrom   = optional_param('copyfrom', null, PARAM_INT);
$pageparams->viewmode   = optional_param('viewmode', null, PARAM_INT);
$pageparams->gridcols   = optional_param('gridcols', null, PARAM_INT);

$cm             = get_coursemodule_from_id('attforblock', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att            = $DB->get_record('attforblock', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$pageparams->init($course->id);
$att = new attforblock($att, $cm, $course, $PAGE->context, $pageparams);

if ($formdata = data_submitted()) {
    $att->take_from_form_data($formdata);
}

$PAGE->set_url($att->url_take());
$PAGE->set_title($course->shortname. ": ".$att->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->set_button($OUTPUT->update_module_button($cm->id, 'attforblock'));
$PAGE->navbar->add($att->name);

$output = $PAGE->get_renderer('mod_attforblock');
$tabs = new attforblock_tabs($att);
$sesstable = new attforblock_take_data($att);

/// Output starts here

echo $output->header();
echo $output->heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);
echo $output->render($tabs);
echo $output->render($sesstable);

echo $output->footer();



?>
