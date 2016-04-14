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
 * Used to calculate a fraction based on the part and total values
 *
 * @param float $part - part of the total value
 * @param float $total - total value.
 * @return float the calculated fraction.
 */
function attendance_calc_fraction($part, $total) {
    if ($total == 0) {
        return 0;
    } else {
        return $part / $total;
    }
}

/**
 * Update all user grades - used when settings have changed.
 *
 * @param mixed mod_attendance_structure|stdClass $attendance
 * @return float the calculated grade.
 */
function attendance_update_all_users_grades($attendance) {
    return attendance_update_users_grade($attendance, 0);
}

/**
 * Update user grades
 *
 * @param mixed mod_attendance_structure|stdClass $attendance
 * @param mixed array|int $userids
 * @return float the calculated grade.
 */
function attendance_update_users_grade($attendance, $userids=0) {
    global $DB;

    if ($attendance instanceof mod_attendance_structure) {
        $attendanceid = $attendance->id;
        $course = $attendance->course;
        $cm = $attendance->cm;
        $context = $attendance->context;
        $grade = $attendance->grade;
    } else {
        $attendanceid = $attendance->id;
        list($course, $cm) = get_course_and_cm_from_instance($attendanceid, 'attendance');
        $context = context_module::instance($cm->id);
        $grade = $attendance->grade;
    }

    if (empty($userids)) {
        $userids = array_keys(get_enrolled_users($context, 'mod/attendance:canbelisted', 0, 'u.id'));
        $userspoints = attendance_get_users_points($attendance);
    } else {
        $userspoints = attendance_get_users_points($attendance, $userids);
    }

    if ($grade < 0) {
        $dbparams = array('id' => -($grade));
        $scale = $DB->get_record('scale', $dbparams);
        $scalearray = explode(',', $scale->scale);
        $attendancegrade = count($scalearray);
    } else {
        $attendancegrade = $grade;
    }

    $grades = array();
    foreach ($userids as $userid) {
        $grades[$userid] = new stdClass();
        $grades[$userid]->userid = $userid;

        if (isset($userspoints[$userid])) {
            $points = $userspoints[$userid]->points;
            $maxpoints = $userspoints[$userid]->maxpoints;
            $grades[$userid]->rawgrade = attendance_calc_fraction($points, $maxpoints) * $attendancegrade;
        } else {
            $grades[$userid]->rawgrade = null;
        }
    }

    return grade_update('mod/attendance', $course->id, 'mod', 'attendance', $attendanceid, 0, $grades);
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

/**
 * Returns the maxpoints for each statusset
 *
 * @param array statuses
 * @return array
 */
function attendance_get_statusset_maxpoints($statuses) {
    $statusset_maxpoints = array();
    foreach ($statuses AS $st) {
        if (!isset($statusset_maxpoints[$st->setnumber])) {
            $statusset_maxpoints[$st->setnumber] = $st->grade;
        }
    }
    return $statusset_maxpoints;
}

/**
 * Compute the points of the users that has some taken session
 *
 * @param mixed mod_attendance_structure|stdClass|int $attendance
 * @param mixed int|array $userids one or more userids (zero means all)
 * @param int $startdate Attendance sessions startdate
 * @param int $enddate Attendance sessions enddate
 * @return array of objects (userid, numtakensessions, points, maxpoints)
 */
function attendance_get_users_points($attendance, $userids=0, $startdate = '', $enddate = '') {
    global $DB;

    if (is_object($attendance)) {
        $attendanceid = $attendance->id;
    } else {
        $attendanceid = $attendance;
    }
    list($course, $cm) = get_course_and_cm_from_instance($attendanceid, 'attendance');

    $params = array(
        'attid'      => $attendanceid,
        'attid2'     => $attendanceid,
        'cstartdate' => $course->startdate,
        );

    $where = '';

    if (!empty($userid)) {
        if (is_array($userids)) {
            list($in_sql, $in_params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $where .= ' AND atl.studentid ' . $in_sql;
            $params = array_merge($params, $in_params);
        } else {
            $where .= ' AND atl.studentid = :userid';
            $params['userid'] = $userids;
        }
    }
    if (!empty($startdate)) {
        $where .= ' AND ats.sessdate >= :startdate';
        $params['startdate'] = $startdate;
    }
    if (!empty($enddate)) {
        $where .= ' AND ats.sessdate < :enddate ';
        $params['enddate'] = $enddate;
    }

    $join_group = '';
    if (!empty($cm->effectivegroupmode)) {
        $join_group = 'LEFT JOIN {groups_members} gm ON (gm.userid = atl.studentid AND gm.groupid = ats.groupid)';
        $where .= ' AND (ats.groupid = 0 or gm.id is NOT NULL)';
    }

    $sql = "SELECT userid, COUNT(*) AS numtakensessions, SUM(grade) AS points, SUM(maxgrade) AS maxpoints
             FROM (SELECT atl.studentid AS userid, ats.id AS sessionid, stg.grade, stm.maxgrade
                     FROM {attendance_sessions} ats
                     JOIN {attendance_log} atl ON (atl.sessionid = ats.id)
                     JOIN {attendance_statuses} stg ON (stg.id = atl.statusid AND stg.deleted = 0 AND stg.visible = 1)
                     JOIN (SELECT setnumber, MAX(grade) AS maxgrade
                             FROM {attendance_statuses}
                            WHERE attendanceid = :attid2
                              AND deleted = 0
                              AND visible = 1
                           GROUP BY setnumber) stm
                       ON (stm.setnumber = ats.statusset)
                     {$join_group}
                    WHERE ats.attendanceid = :attid
                      AND ats.sessdate >= :cstartdate
                      AND ats.lasttakenby != 0
                      {$where}
                  ) sess
            GROUP BY userid";
    return $DB->get_records_sql($sql, $params);
}

/**
 * Given a float, prints it nicely.
 *
 * @param float $float The float to print
 * @param bool $stripzeros If true, removes final zeros after decimal point
 * @return string locale float
 */
function attendance_format_float($float, $stripzeros=true) {
    return format_float($float, 1, true, $stripzeros);
}
