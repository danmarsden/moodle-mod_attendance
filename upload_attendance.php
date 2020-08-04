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
 * Creates the "csv_upload_form" and processes the uploaded csv file to automatically update student attendance records.
 *
 * @package   mod_attendance
 * @copyright 2019 Jonathan Chan <jonathan.chan@sta.uwi.edu>
 * @copyright based on work by 2011 Artem Andreev <andreev.artem@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->libdir . '/csvlib.class.php');

global $DB;

$pageparams = new mod_attendance_take_page_params();

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

$id                     = required_param('id', PARAM_INT);
$pageparams->sessionid  = required_param('sessionid', PARAM_INT);
$pageparams->grouptype  = optional_param('grouptype', null, PARAM_INT);
$pageparams->page       = optional_param('page', 1, PARAM_INT);
$importid               = optional_param('importid', null, PARAM_INT);

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

$o = '';

// Form processing and displaying is done here.
$output = $PAGE->get_renderer('mod_attendance');

// If the csv file hasn't been imported yet then look for a form submission or show the initial submission form.
if (!$importid) {
    $mform = new \mod_attendance\import\csv_upload_form(null,
                                                        array('cm' => $cm->id,
                                                              'sessionid' => $pageparams->sessionid,
                                                              'grouptype' => $pageparams->grouptype));

    if ($mform->is_cancelled()) {
        redirect(new moodle_url('/mod/attendance/take.php',
                                array('id' => $cm->id,
                                      'sessionid' => $pageparams->sessionid,
                                      'grouptype' => $pageparams->grouptype)));
        return;
    } else if (($data = $mform->get_data()) &&
            ($csvdata = $mform->get_file_content('attendancefile'))) {
        $importid = csv_import_reader::get_new_iid('attendance');

        $attimporter = new mod_attendance_importer($importid, $att, $data->encoding, $data->separator);
        $attimporter->parsecsv($csvdata);
        $attimporter->preview($data->previewrows);

        echo $output->header();
        echo $output->heading(get_string('attendanceforthecourse', 'attendance').' :: ' .format_string($course->fullname));
        echo $output->preview_page($attimporter->get_headers($importid), $attimporter->get_previewdata());
    } else {
        // Output for the file upload form starts here.
        echo $output->header();
        echo $output->heading(get_string('attendanceforthecourse', 'attendance').' :: ' .format_string($course->fullname));
        $mform->display();

        echo $output->footer();
        die();
    }
}

// Form was submitted, so we can use the $importid to retrieve its data.
$attimporter = new mod_attendance_importer($importid, $att);
$header = $attimporter->get_headers($importid);

// A new form is created for handling the mapping data from the form to the database.
$mform2 = new \mod_attendance\import\csv_upload_mapping_form(null, array('cm' => $cm->id,
                                                                         'sessionid' => $pageparams->sessionid,
                                                                         'grouptype' => $pageparams->grouptype,
                                                                         'header' => $header,
                                                                         'importid' => $importid));

if ($mform2->is_cancelled()) {
    redirect(new moodle_url('/mod/attendance/take.php',
                            array('id' => $cm->id,
                                  'sessionid' => $pageparams->sessionid,
                                  'grouptype' => $pageparams->grouptype)));
    return;
} else if ($data = $mform2->get_data()) {
    // Make sure there are only one to one column mappings.
    if ($data->mapfrom == $data->encoding ||
        $data->mapfrom == $data->scantime) {
        echo $output->notification(get_string('mappingcollision', 'attendance'));
    }

    // Mapping the columns for the encoding, scantime to the corresponding indices.
    // Without this the program would not know which column contains which piece of information.
    // These must be set before prechecking the file.
    $attimporter->set_encodingindex($data->encoding);
    $attimporter->set_scantimeindex($data->scantime);

    // Set which column in the csv file to identify the student by and set which field in the database it should map to.
    $attimporter->set_studentindex($data->mapfrom);
    $attimporter->set_mapto($data->mapto);

    // An error message is displayed if something is wrong with the uploaded file during the precheck.
    if (!$attimporter->init()) {
        $thisurl = new moodle_url('/mod/attendance/upload_attendance.php',
                            array('id' => $cm->id,
                                  'sessionid' => $pageparams->sessionid,
                                  'grouptype' => $pageparams->grouptype));

        $missing = '';
        $err = '';

        // Action error flags here.
        if ($attimporter->studentcolempty == true) {
            $attimporter->studentcolempty = false;
            $missing .= get_string('studentcolempty', 'attendance');
        }
        if ($attimporter->encodingcolempty == true) {
            $attimporter->encodingcolempty = false;
            $missing .= get_string('encodingcolempty', 'attendance');
        }
        if ($attimporter->scantimecolempty == true) {
            $attimporter->scantimecolempty = false;
            $missing .= get_string('scantimecolempty', 'attendance');
        }
        if (!empty($missing)) {
            $missing .= get_string('missing', 'attendance');
            redirect($thisurl, $missing, null, \core\output\notification::NOTIFY_ERROR);
            return;
        }
        if ($attimporter->scantimeformaterr == true) {
            $attimporter->scantimeformaterr = false;
            $err .= get_string('scantimeformaterr', 'attendance');
        }
        if ($attimporter->multipledays == true) {
            $attimporter->multipledays = false;
            $err .= get_string('multipledays', 'attendance');
        } else {
            if ($attimporter->incompatsessdate == true) {
                $attimporter->incompatsessdate = false;
                $err .= get_string('incompatsessdate', 'attendance');
            }
            if ($attimporter->scantimeerr == true) {
                $attimporter->scantimeerr = false;
                $err .= get_string('scantimeerr', 'attendance', $attimporter->highestgradedstatus->description);
            }
        }
        if (!empty($err)) {
            redirect($thisurl, $err, null, \core\output\notification::NOTIFY_ERROR);
        }
        return;
    }

    // Processing the csv file data here.
    $att->take_from_csv_importer($attimporter, $session);

    redirect($att->url_manage(), get_string('attendancesuccess', 'attendance'));

    return;
} else {
    // Output for the mapping form starts here.
    $mform2->display();

    echo $output->footer();
}
