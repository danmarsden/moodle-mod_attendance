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
 * Adding attendance sessions
 *
 * @package    mod_attendance
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/add_form.php');
require_once(dirname(__FILE__).'/update_form.php');
require_once(dirname(__FILE__).'/duration_form.php');

$pageparams = new mod_attendance_sessions_page_params();

$id                     = required_param('id', PARAM_INT);
$pageparams->action     = required_param('action', PARAM_INT);

if (optional_param('deletehiddensessions', false, PARAM_TEXT)) {
    $pageparams->action = mod_attendance_sessions_page_params::ACTION_DELETE_HIDDEN;
}

if (empty($pageparams->action)) {
    // The form on manage.php can submit with the "choose" option - this should be fixed in the long term,
    // but in the meantime show a useful error and redirect when it occurs.
    $url = new moodle_url('/mod/attendance/view.php', array('id' => $id));
    redirect($url, get_string('invalidaction', 'mod_attendance'), 2);
}

$cm             = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att            = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/attendance:manageattendances', $context);

$att = new mod_attendance_structure($att, $cm, $course, $context, $pageparams);

$PAGE->set_url($att->url_sessions(array('action' => $pageparams->action)));
$PAGE->set_title($course->shortname. ": ".$att->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->set_button($OUTPUT->update_module_button($cm->id, 'attendance'));
$PAGE->navbar->add($att->name);

$currenttab = attendance_tabs::TAB_ADD;
$formparams = array('course' => $course, 'cm' => $cm, 'modcontext' => $context, 'att' => $att);
switch ($att->pageparams->action) {
    case mod_attendance_sessions_page_params::ACTION_ADD:
        $url = $att->url_sessions(array('action' => mod_attendance_sessions_page_params::ACTION_ADD));
        $mform = new mod_attendance_add_form($url, $formparams);

        if ($mform->is_cancelled()) {
            redirect($att->url_manage());
        }

        if ($formdata = $mform->get_data()) {
            $sessions = construct_sessions_data_for_add($formdata);
            $att->add_sessions($sessions);
            if (count($sessions) == 1) {
                $message = get_string('sessiongenerated', 'attendance');
            } else {
                $message = get_string('sessionsgenerated', 'attendance', count($sessions));
            }

            mod_attendance_notifyqueue::notify_success($message);
            // Redirect to the sessions tab always showing all sessions.
            $SESSION->attcurrentattview[$cm->course] = ATT_VIEW_ALL;
            redirect($att->url_manage());
        }
        break;
    case mod_attendance_sessions_page_params::ACTION_UPDATE:
        $sessionid = required_param('sessionid', PARAM_INT);

        $url = $att->url_sessions(array('action' => mod_attendance_sessions_page_params::ACTION_UPDATE, 'sessionid' => $sessionid));
        $formparams['sessionid'] = $sessionid;
        $mform = new mod_attendance_update_form($url, $formparams);

        if ($mform->is_cancelled()) {
            redirect($att->url_manage());
        }

        if ($formdata = $mform->get_data()) {
            $att->update_session_from_form_data($formdata, $sessionid);

            mod_attendance_notifyqueue::notify_success(get_string('sessionupdated', 'attendance'));
            redirect($att->url_manage());
        }
        $currenttab = attendance_tabs::TAB_UPDATE;
        break;
    case mod_attendance_sessions_page_params::ACTION_DELETE:
        $sessionid = required_param('sessionid', PARAM_INT);
        $confirm   = optional_param('confirm', null, PARAM_INT);

        if (isset($confirm) && confirm_sesskey()) {
            $att->delete_sessions(array($sessionid));
            if ($att->grade > 0) {
                attendance_update_all_users_grades($att, $cm);
            }
            redirect($att->url_manage(), get_string('sessiondeleted', 'attendance'));
        }

        $sessinfo = $att->get_session_info($sessionid);

        $message = get_string('deletecheckfull', '', get_string('session', 'attendance'));
        $message .= str_repeat(html_writer::empty_tag('br'), 2);
        $message .= userdate($sessinfo->sessdate, get_string('strftimedmyhm', 'attendance'));
        $message .= html_writer::empty_tag('br');
        $message .= $sessinfo->description;

        $params = array('action' => $att->pageparams->action, 'sessionid' => $sessionid, 'confirm' => 1, 'sesskey' => sesskey());

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('attendanceforthecourse', 'attendance').' :: ' .format_string($course->fullname));
        echo $OUTPUT->confirm($message, $att->url_sessions($params), $att->url_manage());
        echo $OUTPUT->footer();
        exit;
    case mod_attendance_sessions_page_params::ACTION_DELETE_SELECTED:
        $confirm    = optional_param('confirm', null, PARAM_INT);

        if (isset($confirm) && confirm_sesskey()) {
            $sessionsids = required_param('sessionsids', PARAM_ALPHANUMEXT);
            $sessionsids = explode('_', $sessionsids);

            $att->delete_sessions($sessionsids);
            if ($att->grade > 0) {
                attendance_update_all_users_grades($att, $cm);
            }
            redirect($att->url_manage(), get_string('sessiondeleted', 'attendance'));
        }
        $sessid = optional_param_array('sessid', '', PARAM_SEQUENCE);
        if (empty($sessid)) {
            print_error('nosessionsselected', 'attendance', $att->url_manage());
        }
        $sessionsinfo = $att->get_sessions_info($sessid);

        $message = get_string('deletecheckfull', '', get_string('session', 'attendance'));
        $message .= html_writer::empty_tag('br');
        foreach ($sessionsinfo as $sessinfo) {
            $message .= html_writer::empty_tag('br');
            $message .= userdate($sessinfo->sessdate, get_string('strftimedmyhm', 'attendance'));
            $message .= html_writer::empty_tag('br');
            $message .= $sessinfo->description;
        }

        $sessionsids = implode('_', $sessid);
        $params = array('action' => $att->pageparams->action, 'sessionsids' => $sessionsids,
                        'confirm' => 1, 'sesskey' => sesskey());

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('attendanceforthecourse', 'attendance').' :: ' .format_string($course->fullname));
        echo $OUTPUT->confirm($message, $att->url_sessions($params), $att->url_manage());
        echo $OUTPUT->footer();
        exit;
    case mod_attendance_sessions_page_params::ACTION_CHANGE_DURATION:
        $sessid = optional_param_array('sessid', '', PARAM_SEQUENCE);
        $ids = optional_param('ids', '', PARAM_ALPHANUMEXT);

        $slist = !empty($sessid) ? implode('_', $sessid) : '';

        $url = $att->url_sessions(array('action' => mod_attendance_sessions_page_params::ACTION_CHANGE_DURATION));
        $formparams['ids'] = $slist;
        $mform = new mod_attendance_duration_form($url, $formparams);

        if ($mform->is_cancelled()) {
            redirect($att->url_manage());
        }

        if ($formdata = $mform->get_data()) {
            $sessionsids = explode('_', $ids);
            $duration = $formdata->durtime['hours'] * HOURSECS + $formdata->durtime['minutes'] * MINSECS;
            $att->update_sessions_duration($sessionsids, $duration);
            redirect($att->url_manage(), get_string('sessionupdated', 'attendance'));
        }

        if ($slist === '') {
            print_error('nosessionsselected', 'attendance', $att->url_manage());
        }

        break;
    case mod_attendance_sessions_page_params::ACTION_DELETE_HIDDEN:
        $confirm  = optional_param('confirm', null, PARAM_INT);
        if ($confirm && confirm_sesskey()) {
            $sessions = $att->get_hidden_sessions();
            $att->delete_sessions(array_keys($sessions));
            redirect($att->url_manage(), get_string('hiddensessionsdeleted', 'attendance'));
        }

        $a = new stdClass();
        $a->count = $att->get_hidden_sessions_count();
        $a->date = userdate($course->startdate);
        $message = get_string('confirmdeletehiddensessions', 'attendance', $a);

        $params = array('action' => $att->pageparams->action, 'confirm' => 1, 'sesskey' => sesskey());
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('attendanceforthecourse', 'attendance').' :: ' .format_string($course->fullname));
        echo $OUTPUT->confirm($message, $att->url_sessions($params), $att->url_manage());
        echo $OUTPUT->footer();
        exit;
}

$output = $PAGE->get_renderer('mod_attendance');
$tabs = new attendance_tabs($att, $currenttab);
echo $output->header();
echo $output->heading(get_string('attendanceforthecourse', 'attendance').' :: ' .format_string($course->fullname));
echo $output->render($tabs);

$mform->display();

echo $OUTPUT->footer();

function construct_sessions_data_for_add($formdata) {
    global $CFG;

    $sesstarttime = $formdata->sestime['starthour'] * HOURSECS + $formdata->sestime['startminute'] * MINSECS;
    $sesendtime = $formdata->sestime['endhour'] * HOURSECS + $formdata->sestime['endminute'] * MINSECS;
    $sessiondate = $formdata->sessiondate + $sesstarttime;
    $duration = $sesendtime - $sesstarttime;
    $now = time();

    $sessions = array();
    if (isset($formdata->addmultiply)) {
        $startdate = $sessiondate;
        $enddate = $formdata->sessionenddate + DAYSECS; // Because enddate in 0:0am.

        if ($enddate < $startdate) {
            return null;
        }

        // Getting first day of week.
        $sdate = $startdate;
        $dinfo = usergetdate($sdate);
        if ($CFG->calendar_startwday === '0') { // Week start from sunday.
            $startweek = $startdate - $dinfo['wday'] * DAYSECS; // Call new variable.
        } else {
            $wday = $dinfo['wday'] === 0 ? 7 : $dinfo['wday'];
            $startweek = $startdate - ($wday - 1) * DAYSECS;
        }

        $wdaydesc = array(0 => 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');

        while ($sdate < $enddate) {
            if ($sdate < $startweek + WEEKSECS) {
                $dinfo = usergetdate($sdate);
                if (isset($formdata->sdays) && array_key_exists($wdaydesc[$dinfo['wday']], $formdata->sdays)) {
                    $sess = new stdClass();
                    $sess->sessdate = make_timestamp($dinfo['year'], $dinfo['mon'], $dinfo['mday'],
                                                     $formdata->sestime['starthour'], $formdata->sestime['startminute']);
                    $sess->duration = $duration;
                    $sess->descriptionitemid = $formdata->sdescription['itemid'];
                    $sess->description = $formdata->sdescription['text'];
                    $sess->descriptionformat = $formdata->sdescription['format'];
                    $sess->timemodified = $now;
                    if (isset($formdata->studentscanmark)) { // Students will be able to mark their own attendance.
                        $sess->studentscanmark = 1;
                    }
                    $sess->statusset = $formdata->statusset;

                    fill_groupid($formdata, $sessions, $sess);
                }
                $sdate += DAYSECS;
            } else {
                $startweek += WEEKSECS * $formdata->period;
                $sdate = $startweek;
            }
        }
    } else {
        $sess = new stdClass();
        $sess->sessdate = $sessiondate;
        $sess->duration = $duration;
        $sess->descriptionitemid = $formdata->sdescription['itemid'];
        $sess->description = $formdata->sdescription['text'];
        $sess->descriptionformat = $formdata->sdescription['format'];
        $sess->timemodified = $now;
        if (isset($formdata->studentscanmark)) { // Students will be able to mark their own attendance.
            $sess->studentscanmark = 1;
        }
        $sess->statusset = $formdata->statusset;

        fill_groupid($formdata, $sessions, $sess);
    }

    return $sessions;
}

function fill_groupid($formdata, &$sessions, $sess) {
    if ($formdata->sessiontype == mod_attendance_structure::SESSION_COMMON) {
        $sess = clone $sess;
        $sess->groupid = 0;
        $sessions[] = $sess;
    } else {
        foreach ($formdata->groups as $groupid) {
            $sess = clone $sess;
            $sess->groupid = $groupid;
            $sessions[] = $sess;
        }
    }
}
