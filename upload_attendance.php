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
 * This file creates a page for the "csv_upload_form" and processes the uploaded csv attendance file to automatically
 * update student attendance records.
 *
 * @package   mod_attendance
 * @copyright 2019 Jonathan Chan <jonathan.chan@sta.uwi.edu>
 * @copyright based on work by 2011 Artem Andreev <andreev.artem@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/csv_upload_form.php');
require_once(dirname(__FILE__).'/importattendancelib.php');
require_once($CFG->libdir . '/csvlib.class.php');

global $DB;

$pageparams = new mod_attendance_take_page_params();

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

$id                     = required_param('id', PARAM_INT);
$pageparams->sessionid  = required_param('sessionid', PARAM_INT);
$pageparams->grouptype  = optional_param('grouptype', null, PARAM_INT);
$pageparams->page       = optional_param('page', 1, PARAM_INT);

$cm                     = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
$course                 = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att                    = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);
// Check this is a valid session for this attendance.
$session                = $DB->get_record('attendance_sessions', array('id' => $pageparams->sessionid, 'attendanceid' => $att->id),
                                  '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/attendance:takeattendances', $context);

$pageparams->init($course->id);
$att = new mod_attendance_structure($att, $cm, $course, $PAGE->context, $pageparams);

$PAGE->set_url('/mod/attendance/upload_attendance.php');
$PAGE->set_title($course->shortname. ": ".$att->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->navbar->add($att->name);


$mform = new csv_upload_form(null,
                             array('cm' => $cm->id,
                                   'sessionid' => $pageparams->sessionid,
                                   'grouptype' => $pageparams->grouptype));

$o = '';

// Form processing and displaying is done here.
$output = $PAGE->get_renderer('mod_attendance');

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/attendance/take.php',
                            array('id' => $cm->id,
                                  'sessionid' => $pageparams->sessionid,
                                  'grouptype' => $pageparams->grouptype)));
    return;
} else if (($data = $mform->get_data()) &&
        ($csvdata = $mform->get_file_content('attendancefile'))) {
    $importid = csv_import_reader::get_new_iid('attendance');

    $attimporter = new attendance_importer($importid, $att, $data->encoding, $data->separator);
    $attimporter->parsecsv($csvdata);
    
    // An error message is displayed if something is wrong with the uploaded file during the precheck.
    if (!$attimporter->init()) {
        $thisurl = new moodle_url('/mod/attendance/upload_attendance.php',
                            array('id' => $cm->id,
                                  'sessionid' => $pageparams->sessionid,
                                  'grouptype' => $pageparams->grouptype));
        
        $missing = '';
        $err = '';
        
        // Action error flags here.
        if ($attimporter->idnumcolempty == true) {
            $attimporter->idnumcolempty = false;
            $missing .= get_string('idnumcolempty', 'attendance');
        }
        if ($attimporter->encodingcolempty == true) {
            $attimporter->encodingcolempty = false;
            $missing .= get_string('encodingcolempty', 'attendance');
        }
        if ($attimporter->scantimecolempty == true) {
            $attimporter->scantimecolempty = false;
            $missing .= get_string('scantimecolempty', 'attendance');
        }
        if ($attimporter->scandatecolempty == true) {
            $attimporter->scandatecolempty = false;
            $missing .= get_string('scandatecolempty', 'attendance');
        }
        if (!empty($missing)) {
            $missing .= get_string('missing', 'attendance');
            redirect($thisurl, $missing, null, \core\output\notification::NOTIFY_ERROR);
        }        
        if ($attimporter->scantimeformaterr == true) {
            $attimporter->scantimeformaterr = false;
            $err .= get_string('scantimeformaterr', 'attendance');
        }        
        if ($attimporter->scandateformaterr == true) {
            $attimporter->scandateformaterr = false;
            $err .= get_string('scandateformaterr', 'attendance');
        }        
        if ($attimporter->incompatsessdate == true) {
            $attimporter->incompatsessdate = false;
            $err .= get_string('incompatsessdate', 'attendance');
        }        
        if ($attimporter->multipledays == true) {
            $attimporter->multipledays = false;
            $err .= get_string('multipledays', 'attendance');
        }        
        if ($attimporter->scantimeerr == true) {
            $attimporter->scantimeerr = false;
            $err .= get_string('scantimeerr', 'attendance');
        }        
        if (!empty($err)) {
            redirect($thisurl, $err, null, \core\output\notification::NOTIFY_ERROR);
        }
        return;
    }
    
    // Processing the csv file data starts here.
    // Prime status variables with the corresponding attendance status id.
    $statuses = $att->get_statuses();

    foreach ($statuses as $status) {        
        if ($status->description == 'Present') {
            $present = $status->id;
        }
        if ($status->description == 'Late') {
            $late = $status->id;
        }
        if ($status->description == 'Excused') {
            $excused = $status->id;
        }
        if ($status->description == 'Absent') {
            $absent = $status->id;
        }
    }
    
    $sessioninfo = $att->get_session_info($att->pageparams->sessionid);
    $statuses = implode(',', array_keys( (array)$att->get_statuses() ));
    $validusers = $att->get_users($att->pageparams->grouptype, 0);
    $now = time();
    $sesslog = array();
    
    // For loop generating the data to insert into the attendance_log database.
    foreach ($validusers as $student) {
        
        $sid = $student->id;
        $sesslog[$sid] = new stdClass();
        $sesslog[$sid]->studentid = $sid;
        $sesslog[$sid]->statusset = $statuses;
        $sesslog[$sid]->remarks = '';
        $sesslog[$sid]->sessionid = $pageparams->sessionid;
        $sesslog[$sid]->timetaken = $now;
        $sesslog[$sid]->takenby = $USER->id;
        $sesslog[$sid]->statusid = $absent;

        // While loop to set the student's attendance status based on scantime and presence in the csv file.
        while ($record = $attimporter->next()) {
            $userid = $record->user->id;
            if ($sid == $userid) {
                $scantime = $record->scantime;
                if (($scantime >= $sessioninfo->sessdate - 1800) &&
                    ($scantime <= $sessioninfo->sessdate + 900)) {
                    $sesslog[$sid]->statusid = $present;
                } else if (($scantime > $sessioninfo->sessdate + 900) &&
                          ($scantime <= $sessioninfo->sessdate + $sessioninfo->duration)) {
                    $sesslog[$sid]->statusid = $late;
                }
            }
        }
        $attimporter->restart();
    }
    
    $dbsesslog = $att->get_session_log($att->pageparams->sessionid);
    
    foreach ($sesslog as $log) {
        // Only save new records or remarked records.
        if (!empty($log->statusid) || !empty($log->remarks)) {
            if (array_key_exists($log->studentid, $dbsesslog)) {
                // Update records only if something important was changed.
                if ($dbsesslog[$log->studentid]->remarks <> $log->remarks ||
                        $dbsesslog[$log->studentid]->statusid <> $log->statusid ||
                        $dbsesslog[$log->studentid]->statusset <> $log->statusset) {
                    // To prevent users from changing the attendance of students who are marked as P,L or E,
                    // only allow the students with attendance status A to be changed to either P,L, or E.
                    if ($dbsesslog[$log->studentid]->statusid == $absent) {
                        
                        $log->id = $dbsesslog[$log->studentid]->id;
                        $DB->update_record('attendance_log', $log);
                    } else {
                        $att->attemptedfraud = true;
                    }
                }
            } else {
                $DB->insert_record('attendance_log', $log, false);
            }
        }
    }
    
    $session = $att->get_session_info($att->pageparams->sessionid);
    $session->lasttaken = $now;
    $session->lasttakenby = $USER->id;

    $DB->update_record('attendance_sessions', $session);

    if ($att->grade != 0) {
        $att->update_users_grade(array_keys($sesslog));
    }

    // Create url for link in log screen.
    $params = array(
        'sessionid' => $att->pageparams->sessionid,
        'grouptype' => $att->pageparams->grouptype);
    $event = \mod_attendance\event\attendance_taken::create(array(
        'objectid' => $att->id,
        'context' => $att->context,
        'other' => $params));
    $event->add_record_snapshot('course_modules', $att->cm);
    $event->add_record_snapshot('attendance_sessions', $session);
    $event->trigger();
    
    // If the user tries to change the attendance of a student who is P, L or E, they will be unable to do so and be
    // directed to the manual input page where they will be presented with a warning message.
    if ($att->attemptedfraud == true) {
        $att->attemptedfraud = false;
        $params = array(
                'sessionid' => $att->pageparams->sessionid,
                'grouptype' => $att->pageparams->grouptype);
        redirect($att->url_take($params), get_string('attemptedfraud', 'attendance'));
    }
    
    redirect($att->url_manage(), get_string('attendancesuccess', 'attendance'));
    
    return;
}

// Output starts here.
echo $output->header();
echo $output->heading(get_string('attendanceforthecourse', 'attendance').' :: ' .format_string($course->fullname));
$mform->display();

echo $output->footer();