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
 * Bulk mark attendance across multiple sessions using CSV import.
 *
 * @package   mod_attendance
 * @copyright 2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/mod/attendance/lib.php');
require_once($CFG->dirroot . '/mod/attendance/locallib.php');

$id = required_param('id', PARAM_INT);
$importid = optional_param('importid', null, PARAM_INT);

$cm = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$att = $DB->get_record('attendance', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendance:takeattendances', $context);

$PAGE->set_context($context);
$url = new moodle_url('/mod/attendance/import/bulkmarksessions.php', ['id' => $cm->id]);
$PAGE->set_url($url);
$PAGE->set_title($course->shortname . ': ' . $att->name . ' - ' . get_string('bulkuploadattendance', 'attendance'));
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->navbar->add($att->name, new moodle_url('/mod/attendance/view.php', ['id' => $cm->id]));
$PAGE->navbar->add(get_string('bulkuploadattendance', 'attendance'));

$att = new mod_attendance_structure($att, $cm, $course, $PAGE->context);

$output = $PAGE->get_renderer('mod_attendance');

$formparams = [
    'id' => $cm->id,
];

$form = null;
if (optional_param('confirm', 0, PARAM_BOOL)) {
    $importer = new \mod_attendance\import\bulkmarksessions($att, null, null, null, $importid);
    $formparams['importer'] = $importer;
    $form = new \mod_attendance\form\import\bulkmarksessions_confirm(null, $formparams);
} else {
    $form = new \mod_attendance\form\import\bulkmarksessions($url->out(false), $formparams);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/attendance/manage.php', ['id' => $cm->id]));
    return;
} else if ($data = $form->get_data()) {
    if ($data->confirm) {
        $importid = $data->importid;
        $importer = new \mod_attendance\import\bulkmarksessions($att, null, null, null, $importid, $data, true);
        $error = $importer->get_error();
        if ($error) {
            $form = new \mod_attendance\form\import\bulkmarksessions($url->out(false), $formparams);
            $form->set_import_error($error);
        } else {
            echo $output->header();
            $importer->import();
            mod_attendance_notifyqueue::show();
            $url = new moodle_url('/mod/attendance/manage.php', ['id' => $att->cmid]);
            echo $output->continue_button($url);
            echo $output->footer();
            die();
        }
    } else {
        $text = $form->get_file_content('attendancefile');
        $encoding = $data->encoding;
        $delimiter = $data->separator;
        $importer = new \mod_attendance\import\bulkmarksessions($att, $text, $encoding, $delimiter, 0, null, true);
        $formparams['importer'] = $importer;
        $confirmform = new \mod_attendance\form\import\bulkmarksessions_confirm(null, $formparams);
        $form = $confirmform;
    }
}

// Output form.
echo $output->header();
echo $output->box(get_string('bulkmarksessionimportcsvhelp', 'attendance'));
mod_attendance_notifyqueue::show();
$form->display();
echo $output->footer();
