<?php

/**
 * Attendance report
 *
 * @package    mod
 * @subpackage attforblock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$pageparams = new att_report_page_params();

$id                     = required_param('id', PARAM_INT);
$from                   = optional_param('from', NULL, PARAM_ACTION);
$pageparams->view       = optional_param('view', NULL, PARAM_INT);
$pageparams->curdate    = optional_param('curdate', NULL, PARAM_INT);
$pageparams->group    	= optional_param('group', null, PARAM_INT);
$pageparams->sort 		= optional_param('sort', null, PARAM_INT);

$cm             = get_coursemodule_from_id('attforblock', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att            = $DB->get_record('attforblock', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$pageparams->init($cm);
$att = new attforblock($att, $cm, $course, $PAGE->context, $pageparams);

$att->perm->require_view_reports_capability();

$PAGE->set_url($att->url_report());
$PAGE->set_pagelayout('report');
$PAGE->set_title($course->shortname. ": ".$att->name.' - '.get_string('report','attforblock'));
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->set_button($OUTPUT->update_module_button($cm->id, 'attforblock'));
$PAGE->navbar->add(get_string('report','attforblock'));

$output = $PAGE->get_renderer('mod_attforblock');
$tabs = new attforblock_tabs($att, attforblock_tabs::TAB_REPORT);
$filtercontrols = new attforblock_filter_controls($att);
$reportdata = new attforblock_report_data($att);

global $USER;
$att->log('report viewed', null, $USER->firstname.' '.$USER->lastname);

/// Output starts here

echo $output->header();
echo $output->heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);
echo $output->render($tabs);
echo $output->render($filtercontrols);
echo $output->render($reportdata);

echo $output->footer();

?>
