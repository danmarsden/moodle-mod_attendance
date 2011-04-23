<?php

/**
 * Manage attendance sessions
 *
 * @package    mod
 * @subpackage attforblock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id             = optional_param('id', 0, PARAM_INT);   // Course Module ID, or
$from           = optional_param('from', NULL, PARAM_ACTION);
$view           = optional_param('view', NULL, PARAM_INT);        // which page to show
$curdate        = optional_param('curdate', 0, PARAM_INT);
$showendtime    = optional_param('showendtime', NULL, PARAM_INT);

$cm             = get_coursemodule_from_id('attforblock', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att            = $DB->get_record('attforblock', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$canmanage = !has_capability('mod/attforblock:manageattendances', $PAGE->context);
$cantake = !has_capability('mod/attforblock:takeattendances', $PAGE->context);
$canchange = !has_capability('mod/attforblock:changeattendances', $PAGE->context);
if ($canmanage && $cantake && $canchange)
    redirect("view.php?id=$cm->id");

$att = new attforblock($att, $cm, $course, $PAGE->context);
$att->init_view_params($view, $curdate, NULL, $showendtime);

// if teacher is coming from block, then check for a session exists for today
if ($from === 'block') {
    $atts = $att->get_today_sessions();
    $size = count($atts);
    if ($size == 1) {
        $att = reset($atts);
        $nottaken = !$att->lasttaken && has_capability('mod/attforblock:takeattendances', $context);
        $canchange = $att->lasttaken && has_capability('mod/attforblock:changeattendances', $context);
        if ($nottaken || $canchange)
            redirect('attendances.php?id='.$id.'&amp;sessionid='.$att->id.'&amp;grouptype='.$att->groupid);
    } elseif ($size > 1) {
        $att->curdate = $today;
        //temporally set $view for single access to page from block
        $att->$view = attforblock::VIEW_DAYS;
    }
}

$PAGE->set_url($att->url_manage());
$PAGE->set_title($course->shortname. ": ".$att->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_focuscontrol('');
$PAGE->set_cacheable(true);
$PAGE->set_button($OUTPUT->update_module_button($cm->id, 'attforblock'));
$PAGE->navbar->add($att->name);

$output = $PAGE->get_renderer('mod_attforblock');
$tabs = new attforblock_tabs($att);
$filtercontrols = new attforblock_filter_controls($att);
$sesstable = new attforblock_sessions_manage_data($att);

/// Output starts here

echo $output->header();
echo $output->heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);
echo $output->render($tabs);
echo $output->render($filtercontrols);
echo $output->render($sesstable);

echo $output->footer();


?>
