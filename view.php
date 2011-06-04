<?php

/**
 * Prints attendance info for particular user
 *
 * @package    mod
 * @subpackage attforblock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$id         = required_param('id', PARAM_INT);   // Course Module ID, or
$student  	= optional_param('student', 0, PARAM_INT);
$printing	= optional_param('printing', 0, PARAM_INT);
$mode 		= optional_param('mode', 'thiscourse', PARAM_ALPHA);
$view       = optional_param('view', NULL, PARAM_INT);        // which page to show
$current	= optional_param('current', 0, PARAM_INT);

$cm             = get_coursemodule_from_id('attforblock', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$attforblock    = $DB->get_record('attforblock', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

// Not specified studentid for displaying attendance?
// Redirect to appropriate page if can
if (!$studentid) {
    if (has_capability('mod/attforblock:manageattendances', $PAGE->context) ||
                    has_capability('mod/attforblock:takeattendances', $PAGE->context) ||
                    has_capability('mod/attforblock:changeattendances', $PAGE->context)) {
        redirect("manage.php?id=$cm->id");
    }
    elseif (has_capability('mod/attforblock:viewreports', $PAGE->context)) {
        redirect("report.php?id=$cm->id");
    }
}

if ($view)
    set_current_view($course->id, $_GET['view']);
else
    $view = get_current_view($course->id, 'months');

require_capability('mod/attforblock:view', $PAGE->context);


?>
