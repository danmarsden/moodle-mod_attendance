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
 * @package    mod_attendance
 * @copyright  2020 Florian Metzger-Noel (github.com/flocko-motion)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
// $PAGE->requires->js('/mod/attendance/js/userprofile.js');

$pageparams = new mod_attendance_manage_page_params();

$id                         = required_param('id', PARAM_INT);
$userid                     = required_param('userid', PARAM_INT);

$cm             = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att            = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
$capabilities = array(
    'mod/attendance:manageattendances',
    'mod/attendance:takeattendances',
    'mod/attendance:changeattendances'
);
if (!has_any_capability($capabilities, $context)) {
    $url = new moodle_url('/mod/attendance/view.php', array('id' => $cm->id));
    redirect($url);
}

$pageparams->init($cm);
$att = new mod_attendance_structure($att, $cm, $course, $context, $pageparams);

$PAGE->set_url($att->url_evaluation());
$PAGE->set_title($course->shortname. ": ".$att->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->force_settings_menu(true);
$PAGE->navbar->add($att->name);


$tabs = new attendance_tabs($att, attendance_tabs::TAB_EVALUATION);
$title = get_string('attendanceforthecourse', 'attendance').' :: ' .format_string($course->fullname);
$header = new mod_attendance_header($att, $title);
$output = $PAGE->get_renderer('mod_attendance');
echo $output->header();
echo $output->render($header);
mod_attendance_notifyqueue::show();
echo $output->render($tabs);

$user = $att->get_user($userid);

$days = (time() - $user->firstaccess) / (3600 * 24);
if ($days >= 365) {
    $user->signedupsince = get_string('signedupfor_years', 'attendance', floor($days / 365));
} else if ($days >= 31) {
    $user->signedupsince = get_string('signedupfor_months', 'attendance', floor($days / 30.42));
} else if ($days >= 14) {
    $user->signedupsince = get_string('signedupfor_weeks', 'attendance', floor($days / 7));
} else {
    $user->signedupsince = get_string('signedupfor_days', 'attendance', floor($days));
}


$templatecontext = [
    'user' => $user,
];
echo $OUTPUT->render_from_template('mod_attendance/userprofile', $templatecontext);

echo $output->footer();

