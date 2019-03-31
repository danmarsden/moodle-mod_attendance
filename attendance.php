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
 * Prints attendance info for particular user
 *
 * @package   mod_attendance
 * @copyright  2014 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/student_attendance_form.php');

$pageparams = new mod_attendance_sessions_page_params();

// Check that the required parameters are present.
$id = required_param('sessid', PARAM_INT);
$qrpass = optional_param('qrpass', '', PARAM_TEXT);

$attforsession = $DB->get_record('attendance_sessions', array('id' => $id), '*', MUST_EXIST);
$attendance = $DB->get_record('attendance', array('id' => $attforsession->attendanceid), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('attendance', $attendance->id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

// Require the user is logged in.
require_login($course, true, $cm);

list($canmark, $reason) = attendance_can_student_mark($attforsession);
if (!$canmark) {
    redirect(new moodle_url('/mod/attendance/view.php', array('id' => $cm->id)), get_string($reason, 'attendance'));
    exit;
}

// Check if subnet is set and if the user is in the allowed range.
if (!empty($attforsession->subnet) && !address_in_subnet(getremoteaddr(), $attforsession->subnet)) {
    $url = new moodle_url('/mod/attendance/view.php', array('id' => $cm->id));
    notice(get_string('subnetwrong', 'attendance'), $url);
    exit; // Notice calls this anyway.
}

$pageparams->sessionid = $id;
$att = new mod_attendance_structure($attendance, $cm, $course, $PAGE->context, $pageparams);

if (empty($attforsession->includeqrcode)) {
    $qrpass = ''; // Override qrpass if set, as it is not allowed.
}

// Check to see if autoassignstatus is in use and no password required.
if ($attforsession->autoassignstatus && empty($attforsession->studentpassword)) {
    $statusid = attendance_session_get_highest_status($att, $attforsession);
    $url = new moodle_url('/mod/attendance/view.php', array('id' => $cm->id));
    if (empty($statusid)) {
        print_error('attendance_no_status', 'mod_attendance', $url);
    }
    $take = new stdClass();
    $take->status = $statusid;
    $take->sessid = $attforsession->id;
    $success = $att->take_from_student($take);

    if ($success) {
        // Redirect back to the view page.
        redirect($url, get_string('studentmarked', 'attendance'));
    } else {
        print_error('attendance_already_submitted', 'mod_attendance', $url);
    }
}

// Check to see if autoassignstatus is in use and if qrcode is being used.
if (!empty($qrpass) && !empty($attforsession->autoassignstatus)) {
    $fromform = new stdClass();

    // Check if password required and if set correctly.
    if (!empty($attforsession->studentpassword) &&
        $attforsession->studentpassword !== $qrpass) {

        $url = new moodle_url('/mod/attendance/attendance.php', array('sessid' => $id, 'sesskey' => sesskey()));
        redirect($url, get_string('incorrectpassword', 'mod_attendance'), null, \core\output\notification::NOTIFY_ERROR);
    }

    // Set the password and session id in the form, because they are saved in the attendance log.
    $fromform->studentpassword = $qrpass;
    $fromform->sessid = $attforsession->id;

    $fromform->status = attendance_session_get_highest_status($att, $attforsession);
    if (empty($fromform->status)) {
        $url = new moodle_url('/mod/attendance/view.php', array('id' => $cm->id));
        print_error('attendance_no_status', 'mod_attendance', $url);
    }

    if (!empty($fromform->status)) {
        $success = $att->take_from_student($fromform);

        $url = new moodle_url('/mod/attendance/view.php', array('id' => $cm->id));
        if ($success) {
            // Redirect back to the view page.
            redirect($url, get_string('studentmarked', 'attendance'));
        } else {
            print_error('attendance_already_submitted', 'mod_attendance', $url);
        }
    }
}

$PAGE->set_url($att->url_sessions());

// Create the form.
$mform = new mod_attendance_student_attendance_form(null,
        array('course' => $course, 'cm' => $cm, 'modcontext' => $PAGE->context, 'session' => $attforsession,
              'attendance' => $att, 'password' => $qrpass));

if ($mform->is_cancelled()) {
    // The user cancelled the form, so redirect them to the view page.
    $url = new moodle_url('/mod/attendance/view.php', array('id' => $cm->id));
    redirect($url);
} else if ($fromform = $mform->get_data()) {
    // Check if password required and if set correctly.
    if (!empty($attforsession->studentpassword) &&
        $attforsession->studentpassword !== $fromform->studentpassword) {

        $url = new moodle_url('/mod/attendance/attendance.php', array('sessid' => $id, 'sesskey' => sesskey()));
        redirect($url, get_string('incorrectpassword', 'mod_attendance'), null, \core\output\notification::NOTIFY_ERROR);
    }
    if ($attforsession->autoassignstatus) {
        $fromform->status = attendance_session_get_highest_status($att, $attforsession);
        if (empty($fromform->status)) {
            $url = new moodle_url('/mod/attendance/view.php', array('id' => $cm->id));
            print_error('attendance_no_status', 'mod_attendance', $url);
        }
    }

    if (!empty($fromform->status)) {
        $success = $att->take_from_student($fromform);

        $url = new moodle_url('/mod/attendance/view.php', array('id' => $cm->id));
        if ($success) {
            // Redirect back to the view page.
            redirect($url, get_string('studentmarked', 'attendance'));
        } else {
            print_error('attendance_already_submitted', 'mod_attendance', $url);
        }
    }

    // The form did not validate correctly so we will set it to display the data they submitted.
    $mform->set_data($fromform);
}

$PAGE->set_title($course->shortname. ": ".$att->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->navbar->add($att->name);

$output = $PAGE->get_renderer('mod_attendance');
echo $output->header();
$mform->display();
echo $output->footer();
