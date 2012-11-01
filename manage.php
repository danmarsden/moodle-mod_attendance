<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Manage attendance sessions
 *
 * @package    mod
 * @subpackage attforblock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$pageparams = new att_manage_page_params();

$id                         = required_param('id', PARAM_INT);
$from                       = optional_param('from', NULL, PARAM_ACTION);
$pageparams->view           = optional_param('view', NULL, PARAM_INT);
$pageparams->curdate        = optional_param('curdate', NULL, PARAM_INT);

$cm             = get_coursemodule_from_id('attforblock', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att            = $DB->get_record('attforblock', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$pageparams->init($cm);
$att = new attforblock($att, $cm, $course, $PAGE->context, $pageparams);
if (!$att->perm->can_manage() && !$att->perm->can_take() && !$att->perm->can_change())
    redirect($att->url_view());

// if teacher is coming from block, then check for a session exists for today
if ($from === 'block') {
    $sessions = $att->get_today_sessions();
    $size = count($sessions);
    if ($size == 1) {
        $sess = reset($sessions);
        $nottaken = !$sess->lasttaken && has_capability('mod/attforblock:takeattendances', $PAGE->context);
        $canchange = $sess->lasttaken && has_capability('mod/attforblock:changeattendances', $PAGE->context);
        if ($nottaken || $canchange)
            redirect($att->url_take(array('sessionid' => $sess->id, 'grouptype' => $sess->groupid)));
    } elseif ($size > 1) {
        $att->curdate = $today;
        //temporally set $view for single access to page from block
        $att->view = ATT_VIEW_DAYS;
    }
}

$PAGE->set_url($att->url_manage());
$PAGE->set_title($course->shortname. ": ".$att->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->set_button($OUTPUT->update_module_button($cm->id, 'attforblock'));
$PAGE->navbar->add($att->name);

$output = $PAGE->get_renderer('mod_attforblock');
$tabs = new attforblock_tabs($att, attforblock_tabs::TAB_SESSIONS);
$filtercontrols = new attforblock_filter_controls($att);
$sesstable = new attforblock_manage_data($att);

/// Output starts here

echo $output->header();
echo $output->heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);
echo $output->render($tabs);
echo $output->render($filtercontrols);
echo $output->render($sesstable);

echo $output->footer();

