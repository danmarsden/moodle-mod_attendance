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
 * local functions and constants for module attendance
 *
 * @package   mod_attendance
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');
require_once(dirname(__FILE__).'/renderhelpers.php');

define('ATT_VIEW_DAYS', 1);
define('ATT_VIEW_WEEKS', 2);
define('ATT_VIEW_MONTHS', 3);
define('ATT_VIEW_ALLPAST', 4);
define('ATT_VIEW_ALL', 5);
define('ATT_VIEW_NOTPRESENT', 6);

define('ATT_SORT_LASTNAME', 1);
define('ATT_SORT_FIRSTNAME', 2);

function attendance_get_statuses($attid, $onlyvisible=true, $statusset = -1) {
    global $DB;

    // Set selector.
    $params = array('aid' => $attid);
    $setsql = '';
    if ($statusset >= 0) {
        $params['statusset'] = $statusset;
        $setsql = ' AND setnumber = :statusset ';
    }

    if ($onlyvisible) {
        $statuses = $DB->get_records_select('attendance_statuses', "attendanceid = :aid AND visible = 1 AND deleted = 0 $setsql",
                                            $params, 'setnumber ASC, grade DESC');
    } else {
        $statuses = $DB->get_records_select('attendance_statuses', "attendanceid = :aid AND deleted = 0 $setsql",
                                            $params, 'setnumber ASC, grade DESC');
    }

    return $statuses;
}

/**
 * Get the name of the status set.
 *
 * @param int $attid
 * @param int $statusset
 * @param bool $includevalues
 * @return string
 */
function attendance_get_setname($attid, $statusset, $includevalues = true) {
    $statusname = get_string('statusset', 'mod_attendance', $statusset + 1);
    if ($includevalues) {
        $statuses = attendance_get_statuses($attid, true, $statusset);
        $statusesout = array();
        foreach ($statuses as $status) {
            $statusesout[] = $status->acronym;
        }
        if ($statusesout) {
            if (count($statusesout) > 6) {
                $statusesout = array_slice($statusesout, 0, 6);
                $statusesout[] = '&helip;';
            }
            $statusesout = implode(' ', $statusesout);
            $statusname .= ' ('.$statusesout.')';
        }
    }

    return $statusname;
}

function attendance_get_user_taken_sessions_count($attid, $coursestartdate, $userid, $coursemodule, $startdate = '', $enddate = '') {
    global $DB, $COURSE;
    $groupmode = groups_get_activity_groupmode($coursemodule, $COURSE);
    if (!empty($groupmode)) {
        $qry = "SELECT count(*) as cnt
              FROM {attendance_log} al
              JOIN {attendance_sessions} ats ON al.sessionid = ats.id
              LEFT JOIN {groups_members} gm ON gm.userid = al.studentid AND gm.groupid = ats.groupid
             WHERE ats.attendanceid = :aid AND
                   ats.sessdate >= :cstartdate AND
                   al.studentid = :uid AND
                   (ats.groupid = 0 or gm.id is NOT NULL)";
    } else {
        $qry = "SELECT count(*) as cnt
              FROM {attendance_log} al
              JOIN {attendance_sessions} ats
                ON al.sessionid = ats.id
             WHERE ats.attendanceid = :aid AND
                   ats.sessdate >= :cstartdate AND
                   al.studentid = :uid";
    }
    $params = array(
        'aid'           => $attid,
        'cstartdate'    => $coursestartdate,
        'uid'           => $userid);

    if (!empty($startdate) && !empty($enddate)) {
        $qry .= ' AND sessdate >= :sdate AND sessdate < :edate ';
        $params['sdate'] = $startdate;
        $params['edate'] = $enddate;
    }

    return $DB->count_records_sql($qry, $params);
}

function attendance_get_user_statuses_stat($attid, $coursestartdate, $userid, $coursemodule) {
    global $DB, $COURSE;
    $groupmode = groups_get_activity_groupmode($coursemodule, $COURSE);
    if (!empty($groupmode)) {
        $qry = "SELECT al.statusid, count(al.statusid) AS stcnt
              FROM {attendance_log} al
              JOIN {attendance_sessions} ats ON al.sessionid = ats.id
              LEFT JOIN {groups_members} gm ON gm.userid = al.studentid AND gm.groupid = ats.groupid
             WHERE ats.attendanceid = :aid AND
                   ats.sessdate >= :cstartdate AND
                   al.studentid = :uid AND
                   (ats.groupid = 0 or gm.id is NOT NULL)
          GROUP BY al.statusid";
    } else {
        $qry = "SELECT al.statusid, count(al.statusid) AS stcnt
              FROM {attendance_log} al
              JOIN {attendance_sessions} ats
                ON al.sessionid = ats.id
             WHERE ats.attendanceid = :aid AND
                   ats.sessdate >= :cstartdate AND
                   al.studentid = :uid
          GROUP BY al.statusid";
    }
    $params = array(
            'aid'           => $attid,
            'cstartdate'    => $coursestartdate,
            'uid'           => $userid);

    return $DB->get_records_sql($qry, $params);
}

function attendance_get_user_grade($userstatusesstat, $statuses) {
    $sum = 0;
    foreach ($userstatusesstat as $stat) {
        $sum += $stat->stcnt * $statuses[$stat->statusid]->grade;
    }

    return $sum;
}

function attendance_get_user_max_grade($sesscount, $statuses) {
    reset($statuses);
    return current($statuses)->grade * $sesscount;
}

function attendance_get_user_courses_attendances($userid) {
    global $DB;

    $usercourses = enrol_get_users_courses($userid);

    list($usql, $uparams) = $DB->get_in_or_equal(array_keys($usercourses), SQL_PARAMS_NAMED, 'cid0');

    $sql = "SELECT att.id as attid, att.course as courseid, course.fullname as coursefullname,
                   course.startdate as coursestartdate, att.name as attname, att.grade as attgrade
              FROM {attendance} att
              JOIN {course} course
                   ON att.course = course.id
             WHERE att.course $usql
          ORDER BY coursefullname ASC, attname ASC";

    $params = array_merge($uparams, array('uid' => $userid));

    return $DB->get_records_sql($sql, $params);
}

/**
 * Used to caclulate usergrade based on rawgrade and max grade.
 *
 * @param float $grade - raw grade for user
 * @param float $maxgrade - maxgrade for this session.
 * @return float the calculated grade.
 */
function attendance_calc_user_grade_fraction($grade, $maxgrade) {
    if ($maxgrade == 0) {
        return 0;
    } else {
        return $grade / $maxgrade;
    }
}

/**
 * Update all user grades - used when settings have changed.
 *
 * @param mod_attendance_structure $attendance - Full attendance class.
 * @param stdclass $coursemodule - full coursemodule record
 * @return float the calculated grade.
 */
function attendance_update_all_users_grades(mod_attendance_structure $attendance, $coursemodule) {
    global $DB;
    $grades = array();
    $course = $attendance->course;

    $userids = array_keys(get_enrolled_users($attendance->context, 'mod/attendance:canbelisted', 0, 'u.id'));
    $attgrades = grade_get_grades($course->id, 'mod', 'attendance', $attendance->id, $userids);

    $usergrades = [];
    if (!empty($attgrades->items[0]) and !empty($attgrades->items[0]->grades)) {
        $usergrades = $attgrades->items[0]->grades;
    }
    $statuses = attendance_get_statuses($attendance->id);
    if ($attendance->grade < 0) {
        $dbparams = array('id' => -($attendance->grade));
        $scale = $DB->get_record('scale', $dbparams);
        $scalearray = explode(',', $scale->scale);
        $gradebookmaxgrade = count($scalearray);
    } else {
        $gradebookmaxgrade = $attendance->grade;
    }
    foreach ($usergrades as $userid => $existinggrade) {
        if (is_null($existinggrade->grade)) {
            // Don't update grades where one doesn't exist yet.
            continue;
        }
        $grade = new stdClass;
        $grade->userid = $userid;
        $userstatusesstat = attendance_get_user_statuses_stat($attendance->id, $course->startdate, $userid, $coursemodule);
        $usertakensesscount = attendance_get_user_taken_sessions_count($attendance->id, $course->startdate, $userid, $coursemodule);
        $usergrade = attendance_get_user_grade($userstatusesstat, $statuses);
        $usermaxgrade = attendance_get_user_max_grade($usertakensesscount, $statuses);
        $grade->rawgrade = attendance_calc_user_grade_fraction($usergrade, $usermaxgrade) * $gradebookmaxgrade;
        $grades[$userid] = $grade;
    }

    if (!empty($grades)) {
        $result = grade_update('mod/attendance', $course->id, 'mod', 'attendance',
            $attendance->id, 0, $grades);
    } else {
        $result = true;
    }

    return $result;
}

/**
 * Check to see if statusid in use to help prevent deletion etc.
 *
 * @param integer $statusid
 */
function attendance_has_logs_for_status($statusid) {
    global $DB;
    return $DB->record_exists('attendance_log', array('statusid' => $statusid));
}

/**
 * Helper function to add sessiondate_selector to add/update forms.
 *
 * @param MoodleQuickForm $mform
 */
function attendance_form_sessiondate_selector (MoodleQuickForm $mform) {

    $mform->addElement('date_selector', 'sessiondate', get_string('sessiondate', 'attendance'));

    for ($i = 0; $i <= 23; $i++) {
        $hours[$i] = sprintf("%02d", $i);
    }
    for ($i = 0; $i < 60; $i += 5) {
        $minutes[$i] = sprintf("%02d", $i);
    }

    $sesendtime = array();
    $sesendtime[] =& $mform->createElement('static', 'from', '', get_string('from', 'attendance'));
    $sesendtime[] =& $mform->createElement('select', 'starthour', get_string('hour', 'form'), $hours, false, true);
    $sesendtime[] =& $mform->createElement('select', 'startminute', get_string('minute', 'form'), $minutes, false, true);
    $sesendtime[] =& $mform->createElement('static', 'to', '', get_string('to', 'attendance'));
    $sesendtime[] =& $mform->createElement('select', 'endhour', get_string('hour', 'form'), $hours, false, true);
    $sesendtime[] =& $mform->createElement('select', 'endminute', get_string('minute', 'form'), $minutes, false, true);
    $mform->addGroup($sesendtime, 'sestime', get_string('time', 'attendance'), array(' '), true);
}

/**
 * Count the number of status sets that exist for this instance.
 *
 * @param int $attendanceid
 * @return int
 */
function attendance_get_max_statusset($attendanceid) {
    global $DB;

    $max = $DB->get_field_sql('SELECT MAX(setnumber) FROM {attendance_statuses} WHERE attendanceid = ? AND deleted = 0',
        array($attendanceid));
    if ($max) {
        return $max;
    }
    return 0;
}
