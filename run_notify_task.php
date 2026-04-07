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
 * Run the attendance "Send warnings to users" scheduled task via URL.
 * Use when CLI is not available and the standard "Run now" is not visible.
 *
 * @package    mod_attendance
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('managemodules');

require_capability('moodle/site:config', context_system::instance());

$PAGE->set_url(new moodle_url('/mod/attendance/run_notify_task.php'));
$PAGE->set_title(get_string('notifytask', 'mod_attendance'));

$run = optional_param('run', 0, PARAM_INT);
$clear = optional_param('clear', 0, PARAM_INT);
$confirmclear = optional_param('confirmclear', 0, PARAM_INT);
$message = '';
$messagetype = 'success';

if ($clear && $confirmclear && confirm_sesskey()) {
    $DB->delete_records('attendance_warning_done');
    redirect(new moodle_url($PAGE->url), get_string('clearsentwarningsdone', 'mod_attendance'), null, 'success');
}

if ($run && confirm_sesskey()) {
    try {
        $task = \core\task\manager::get_scheduled_task('mod_attendance\task\notify');
        if (!$task) {
            $message = get_string('errortasknotfound', 'mod_attendance', 'mod_attendance\task\notify');
            $messagetype = 'error';
        } else {
            $task->execute();
            $message = get_string('notifytaskruncomplete', 'mod_attendance');
        }
    } catch (Throwable $e) {
        $message = get_string('errortaskrun', 'mod_attendance') . ' ' . s($e->getMessage());
        $messagetype = 'error';
    }
    redirect(new moodle_url($PAGE->url), $message, null, $messagetype);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('notifytask', 'mod_attendance'));

echo $OUTPUT->box(get_string('run_notify_task_help', 'mod_attendance'), 'generalbox');

$url = new moodle_url($PAGE->url, ['run' => 1, 'sesskey' => sesskey()]);
echo $OUTPUT->single_button($url, get_string('runnotifynow', 'mod_attendance'), 'get');

echo $OUTPUT->heading(get_string('clearsentwarnings', 'mod_attendance'), 3);
echo $OUTPUT->box(get_string('clearsentwarnings_help', 'mod_attendance'), 'generalbox');
if ($clear && !$confirmclear) {
    $confirmurl = new moodle_url($PAGE->url, ['clear' => 1, 'confirmclear' => 1, 'sesskey' => sesskey()]);
    $cancelurl = new moodle_url($PAGE->url);
    echo $OUTPUT->confirm(get_string('clearsentwarningsconfirm', 'mod_attendance'), $confirmurl, $cancelurl);
} else {
    $clearurl = new moodle_url($PAGE->url, ['clear' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->single_button($clearurl, get_string('clearsentwarningsbutton', 'mod_attendance'), 'get');
}

echo $OUTPUT->footer();
