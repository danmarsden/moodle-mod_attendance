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
 * Attendance import
 *
 * @package    mod_attendance
 * @copyright  2016 David Herney <davidherney@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/attendanceimport_form.php');

$pageparams = new mod_attendance_manage_page_params();

$id                     = required_param('id', PARAM_INT);

$cm             = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att            = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);

$pageparams->view           = null;
$pageparams->curdate        = null;
$pageparams->perpage        = 0;

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendance:takeattendances', $context);

$pageparams->init($cm);
$att = new mod_attendance_structure($att, $cm, $course, $context, $pageparams);

$PAGE->set_url($att->url_attendanceimport());
$PAGE->set_title($course->shortname. ": ".$att->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->set_button($OUTPUT->update_module_button($cm->id, 'attendance'));
$PAGE->navbar->add($att->name);

$output = $PAGE->get_renderer('mod_attendance');
$tabs = new attendance_tabs($att);

// Output starts here.

echo $output->header();
echo $output->heading(get_string('attendanceforthecourse', 'attendance').' :: ' .format_string($course->fullname));
echo $output->render($tabs);

if ($formdata = data_submitted()) {

    $usersimport_list = $formdata->userslist;

    if (!empty($usersimport_list)) {

        $session = $att->get_session_info($formdata->sessionid);

        $usersimport_list = explode("\n", $usersimport_list);

        $imported = array();
        $not_imported = array();

        // Restrict importation to selected users.
        $namefields = get_all_user_name_fields(true, 'u');
        $allusers = get_enrolled_users($context, 'mod/attendance:canbelisted', 0, 'u.id, u.username, u.idnumber, u.email,'.$namefields);
        $userlist = array();
        $userlist_ids = array();
        foreach ($allusers as $user) {
            $user->fullname = fullname($user);
            $userlist[$user->{$formdata->userfield}] = $user;
        }
        unset($allusers);

        //Temp users only are available if is by email
        if ($formdata->userfield == 'email') {
            $tempusers = $DB->get_records('attendance_tempusers', array('courseid' => $course->id), 'studentid, fullname');
            foreach ($tempusers as $user) {
                if (!empty($user->email)) {
                    $user->id = $user->studentid;
                    $userlist[$user->email] = $user;
                }
            }
        }

        foreach($usersimport_list as $user_in_list) {

            $user_in_list = trim($user_in_list);

            if (empty($user_in_list)) {
                continue;
            }

            if (isset($userlist[$user_in_list])) {
                $user_is = $userlist[$user_in_list];
                $att->take_student($formdata->status, $formdata->sessionid, $user_is->id);
                $imported[$user_in_list] = $user_is->fullname;
            }
            else {
                $not_imported[] = $user_in_list;
            }
        }

        //Insert a log entry
        $params = array(
            'sessionid' => $formdata->sessionid,
            'grouptype' => 0);
        $event = \mod_attendance\event\attendance_taken::create(array(
            'objectid' => $id,
            'context' => $context,
            'other' => $params));
        $event->add_record_snapshot('course_modules', $cm);
        $event->add_record_snapshot('attendance_sessions', $session);
        $event->trigger();

        if (count($imported) > 0) {
            $txt = get_string('importedsuccess', 'attendance');
            $txt .= '<ul>';
            foreach($imported as $key=>$name) {
                $txt .= '<li>' . $key . ': ' . $name . '</li>';
            }
            $txt .= '</ul>';
            echo $OUTPUT->notification($txt, 'notifysuccess');
        }

        if (count($not_imported) > 0) {
            $txt = get_string('notimported', 'attendance');
            $txt .= '<ul>';
            foreach($not_imported as $userkey) {
                $txt .= '<li>' . $userkey . '</li>';
            }
            $txt .= '</ul>';
            echo $OUTPUT->notification($txt);
        }

        echo $OUTPUT->single_button(new moodle_url($att->url_attendanceimport(), array('id'=>$id)), get_string('back'), 'get');
    }
    else {
        print_error('userslistempty', 'attendance', $att->url_attendanceimport());
    }

}
else {

    $formparams = array('course' => $course, 'cm' => $cm, 'modcontext' => $context, 'att'=>$att);
    $mform = new mod_attendance_attendanceimport_form($att->url_attendanceimport(), $formparams);

    $mform->display();

}



echo $output->footer();
