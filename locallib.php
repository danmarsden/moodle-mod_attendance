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
define('ATT_VIEW_SUMMARY', 7);
define('ATT_VIEW_ALLFUTURE', 8);

define('ATT_SORT_DEFAULT', 0);
define('ATT_SORT_LASTNAME', 1);
define('ATT_SORT_FIRSTNAME', 2);

define('ATT_ROOMS_MAX_CAPACITY', 1000);

define('ATTENDANCE_AUTOMARK_DISABLED', 0);
define('ATTENDANCE_AUTOMARK_ALL', 1);
define('ATTENDANCE_AUTOMARK_CLOSE', 2);

define('ATTENDANCE_SHAREDIP_DISABLED', 0);
define('ATTENDANCE_SHAREDIP_MINUTES', 1);
define('ATTENDANCE_SHAREDIP_FORCE', 2);

// Max number of sessions available in the warnings set form to trigger warnings.
define('ATTENDANCE_MAXWARNAFTER', 100);


/**
 * Get users courses and the relevant attendances.
 *
 * @param int $userid
 * @return array
 */
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
 * Check to see if statusid in use to help prevent deletion etc.
 *
 * @param integer $statusid
 */
function attendance_has_logs_for_status($statusid) {
    global $DB;
    return $DB->record_exists('attendance_evaluations', array('statusid' => $statusid));
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
    if (!right_to_left()) {
        $sesendtime[] =& $mform->createElement('static', 'from', '', get_string('from', 'attendance'));
        $sesendtime[] =& $mform->createElement('select', 'starthour', get_string('hour', 'form'), $hours, false, true);
        $sesendtime[] =& $mform->createElement('select', 'startminute', get_string('minute', 'form'), $minutes, false, true);
        $sesendtime[] =& $mform->createElement('static', 'to', '', get_string('to', 'attendance'));
        $sesendtime[] =& $mform->createElement('select', 'endhour', get_string('hour', 'form'), $hours, false, true);
        $sesendtime[] =& $mform->createElement('select', 'endminute', get_string('minute', 'form'), $minutes, false, true);
    } else {
        $sesendtime[] =& $mform->createElement('static', 'from', '', get_string('from', 'attendance'));
        $sesendtime[] =& $mform->createElement('select', 'startminute', get_string('minute', 'form'), $minutes, false, true);
        $sesendtime[] =& $mform->createElement('select', 'starthour', get_string('hour', 'form'), $hours, false, true);
        $sesendtime[] =& $mform->createElement('static', 'to', '', get_string('to', 'attendance'));
        $sesendtime[] =& $mform->createElement('select', 'endminute', get_string('minute', 'form'), $minutes, false, true);
        $sesendtime[] =& $mform->createElement('select', 'endhour', get_string('hour', 'form'), $hours, false, true);
    }
    $mform->addGroup($sesendtime, 'sestime', get_string('time', 'attendance'), array(' '), true);
}

/**
 * Helper function to add room options to add/update forms.
 *
 * @param MoodleQuickForm $mform
 * @param mod_attendance_structure $att
 * @param stdClass $sess
 */
function attendance_form_session_room (MoodleQuickForm $mform, mod_attendance_structure $att, $sess = null) {
    if (!$sess) {
        $sess = new stdClass();
        $sess->roomid = 0;
        $sess->maxattendants = 0;
        $sess->bookings = 0;

    }

    $mform->addElement('header', 'headerrooms', get_string('roombooking', 'attendance'));
    $mform->setExpanded('headerrooms');

    $options = [0 => ''] + $att->get_room_names(true, true);
    $mform->addElement('select', 'roomid',
        get_string('roomselect', 'attendance'), $options);
    $mform->setType('roomid', PARAM_INT);

    $mform->addElement('select', 'maxattendants',
        get_string('roomattendantsmax', 'attendance'), attendance_room_capacities());
    $mform->setType('maxattendants', PARAM_INT);

    $mform->addElement('hidden', 'bookings', $sess->bookings);
    $mform->settype('bookings', PARAM_INT);
}




/**
 * Similar to core random_string function but only lowercase letters.
 * designed to make it relatively easy to provide a simple password in class.
 *
 * @param int $length The length of the string to be created.
 * @return string
 */
function attendance_random_string($length=6) {
    $randombytes = random_bytes_emulate($length);
    $pool = 'abcdefghijklmnopqrstuvwxyz';
    $pool .= '0123456789';
    $poollen = strlen($pool);
    $string = '';
    for ($i = 0; $i < $length; $i++) {
        $rand = ord($randombytes[$i]);
        $string .= substr($pool, ($rand % ($poollen)), 1);
    }
    return $string;
}

/**
 * Generate worksheet for Attendance export
 *
 * @param stdclass $data The data for the report
 * @param string $filename The name of the file
 * @param string $format excel|ods
 *
 */
function attendance_exporttotableed($data, $filename, $format) {
    global $CFG;

    if ($format === 'excel') {
        require_once("$CFG->libdir/excellib.class.php");
        $filename .= ".xls";
        $workbook = new MoodleExcelWorkbook("-");
    } else {
        require_once("$CFG->libdir/odslib.class.php");
        $filename .= ".ods";
        $workbook = new MoodleODSWorkbook("-");
    }
    // Sending HTTP headers.
    $workbook->send($filename);
    // Creating the first worksheet.
    $myxls = $workbook->add_worksheet(get_string('modulenameplural', 'attendance'));
    // Format types.
    $formatbc = $workbook->add_format();
    $formatbc->set_bold(1);

    $myxls->write(0, 0, get_string('course'), $formatbc);
    $myxls->write(0, 1, $data->course);
    $myxls->write(1, 0, get_string('group'), $formatbc);
    $myxls->write(1, 1, $data->group);

    $i = 3;
    $j = 0;
    foreach ($data->tabhead as $cell) {
        // Merge cells if the heading would be empty (remarks column).
        if (empty($cell)) {
            $myxls->merge_cells($i, $j - 1, $i, $j);
        } else {
            $myxls->write($i, $j, $cell, $formatbc);
        }
        $j++;
    }
    $i++;
    $j = 0;
    foreach ($data->table as $row) {
        foreach ($row as $cell) {
            $myxls->write($i, $j++, $cell);
        }
        $i++;
        $j = 0;
    }
    $workbook->close();
}

/**
 * Generate csv for Attendance export
 *
 * @param stdclass $data The data for the report
 * @param string $filename The name of the file
 *
 */
function attendance_exporttocsv($data, $filename) {
    $filename .= ".txt";

    header("Content-Type: application/download\n");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Expires: 0");
    header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
    header("Pragma: public");

    echo get_string('course')."\t".$data->course."\n";
    echo get_string('group')."\t".$data->group."\n\n";

    echo implode("\t", $data->tabhead)."\n";
    foreach ($data->table as $row) {
        echo implode("\t", $row)."\n";
    }
}

/**
 * Get session data for form.
 * @param stdClass $formdata moodleform - attendance form.
 * @param mod_attendance_structure $att - used to get attendance level subnet.
 * @return array.
 */
function attendance_construct_sessions_data_for_add($formdata, mod_attendance_structure $att) {
    global $CFG;

    $sesstarttime = $formdata->sestime['starthour'] * HOURSECS + $formdata->sestime['startminute'] * MINSECS;
    $sesendtime = $formdata->sestime['endhour'] * HOURSECS + $formdata->sestime['endminute'] * MINSECS;
    $sessiondate = $formdata->sessiondate + $sesstarttime;
    $duration = $sesendtime - $sesstarttime;
    if (empty(get_config('attendance', 'enablewarnings'))) {
        $absenteereport = get_config('attendance', 'absenteereport_default');
    } else {
        $absenteereport = empty($formdata->absenteereport) ? 0 : 1;
    }

    $now = time();

    if (empty(get_config('attendance', 'studentscanmark'))) {
        $formdata->studentscanmark = 0;
    }

    $calendarevent = 0;
    if (isset($formdata->calendarevent)) { // Calendar event should be created.
        $calendarevent = 1;
    }

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
                    $sess->calendarevent = $calendarevent;
                    $sess->timemodified = $now;
                    $sess->roomid = intval($formdata->roomid);
                    $sess->absenteereport = $absenteereport;
                    $sess->studentpassword = '';
                    $sess->includeqrcode = 0;
                    $sess->rotateqrcode = 0;
                    $sess->rotateqrcodesecret = '';

                    if (!empty($formdata->usedefaultsubnet)) {
                        $sess->subnet = $att->subnet;
                    } else {
                        $sess->subnet = $formdata->subnet;
                    }
                    $sess->automark = $formdata->automark;
                    $sess->automarkcompleted = 0;
                    if (!empty($formdata->preventsharedip)) {
                        $sess->preventsharedip = $formdata->preventsharedip;
                    }
                    if (!empty($formdata->preventsharediptime)) {
                        $sess->preventsharediptime = $formdata->preventsharediptime;
                    }

                    if (isset($formdata->studentscanmark)) { // Students will be able to mark their own attendance.
                        $sess->studentscanmark = 1;
                        if (isset($formdata->autoassignstatus)) {
                            $sess->autoassignstatus = 1;
                        }

                        if (!empty($formdata->randompassword)) {
                            $sess->studentpassword = attendance_random_string();
                        } else if (!empty($formdata->studentpassword)) {
                            $sess->studentpassword = $formdata->studentpassword;
                        }
                        if (!empty($formdata->includeqrcode)) {
                            $sess->includeqrcode = $formdata->includeqrcode;
                        }
                        if (!empty($formdata->rotateqrcode)) {
                            $sess->rotateqrcode = $formdata->rotateqrcode;
                            $sess->studentpassword = attendance_random_string();
                            $sess->rotateqrcodesecret = attendance_random_string();
                        }
                        if (!empty($formdata->preventsharedip)) {
                            $sess->preventsharedip = $formdata->preventsharedip;
                        }
                        if (!empty($formdata->preventsharediptime)) {
                            $sess->preventsharediptime = $formdata->preventsharediptime;
                        }
                    } else {
                        $sess->subnet = '';
                        $sess->automark = 0;
                        $sess->automarkcompleted = 0;
                        $sess->preventsharedip = 0;
                        $sess->preventsharediptime = '';
                    }
                    $sess->statusset = $formdata->statusset;

                    attendance_fill_groupid($formdata, $sessions, $sess);
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
        $sess->calendarevent = $calendarevent;
        $sess->timemodified = $now;
        $sess->roomid = intval($formdata->roomid);
        $sess->studentscanmark = 0;
        $sess->autoassignstatus = 0;
        $sess->subnet = '';
        $sess->studentpassword = '';
        $sess->automark = 0;
        $sess->automarkcompleted = 0;
        $sess->absenteereport = $absenteereport;
        $sess->includeqrcode = 0;
        $sess->rotateqrcode = 0;
        $sess->rotateqrcodesecret = '';

        if (!empty($formdata->usedefaultsubnet)) {
            $sess->subnet = $att->subnet;
        } else {
            $sess->subnet = $formdata->subnet;
        }

        if (!empty($formdata->automark)) {
            $sess->automark = $formdata->automark;
        }
        if (!empty($formdata->preventsharedip)) {
            $sess->preventsharedip = $formdata->preventsharedip;
        }
        if (!empty($formdata->preventsharediptime)) {
            $sess->preventsharediptime = $formdata->preventsharediptime;
        }

        if (isset($formdata->studentscanmark) && !empty($formdata->studentscanmark)) {
            // Students will be able to mark their own attendance.
            $sess->studentscanmark = 1;
            if (isset($formdata->autoassignstatus) && !empty($formdata->autoassignstatus)) {
                $sess->autoassignstatus = 1;
            }
            if (!empty($formdata->randompassword)) {
                $sess->studentpassword = attendance_random_string();
            } else if (!empty($formdata->studentpassword)) {
                $sess->studentpassword = $formdata->studentpassword;
            }
            if (!empty($formdata->includeqrcode)) {
                $sess->includeqrcode = $formdata->includeqrcode;
            }
            if (!empty($formdata->rotateqrcode)) {
                $sess->rotateqrcode = $formdata->rotateqrcode;
                $sess->studentpassword = attendance_random_string();
                $sess->rotateqrcodesecret = attendance_random_string();
            }
            if (!empty($formdata->usedefaultsubnet)) {
                $sess->subnet = $att->subnet;
            } else {
                $sess->subnet = $formdata->subnet;
            }

            if (!empty($formdata->automark)) {
                $sess->automark = $formdata->automark;
            }
            if (!empty($formdata->preventsharedip)) {
                $sess->preventsharedip = $formdata->preventsharedip;
            }
            if (!empty($formdata->preventsharediptime)) {
                $sess->preventsharediptime = $formdata->preventsharediptime;
            }
        }
        $sess->statusset = $formdata->statusset;

        attendance_fill_groupid($formdata, $sessions, $sess);
    }

    return $sessions;
}

/**
 * Helper function for attendance_construct_sessions_data_for_add().
 *
 * @param stdClass $formdata
 * @param stdClass $sessions
 * @param stdClass $sess
 */
function attendance_fill_groupid($formdata, &$sessions, $sess) {
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

/**
 * Generates a summary of points for the courses selected.
 *
 * @param array $courseids optional list of courses to return
 * @param string $orderby - optional order by param
 * @return stdClass
 */
function attendance_course_users_points($courseids = array(), $orderby = '') {
    global $DB;

    $where = '';
    $params = array();
    $where .= ' AND ats.sessdate < :enddate ';
    $params['enddate'] = time();

    $joingroup = 'LEFT JOIN {groups_members} gm ON (gm.userid = atl.studentid AND gm.groupid = ats.groupid)';
    $where .= ' AND (ats.groupid = 0 or gm.id is NOT NULL)';

    if (!empty($courseids)) {
        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $where .= ' AND c.id ' . $insql;
        $params = array_merge($params, $inparams);
    }

    $sql = "SELECT courseid, coursename, sum(points) / sum(maxpoints) as percentage FROM (
SELECT a.id, a.course as courseid, c.fullname as coursename, atl.studentid AS userid, COUNT(DISTINCT ats.id) AS numtakensessions,
                        SUM(stg.grade) AS points, SUM(stm.maxgrade) AS maxpoints
                   FROM {attendance_sessions} ats
                   JOIN {attendance} a ON a.id = ats.attendanceid
                   JOIN {course} c ON c.id = a.course
                   JOIN {attendance_evaluations} atl ON (atl.sessionid = ats.id)
                   JOIN {attendance_statuses} stg ON (stg.id = atl.statusid AND stg.deleted = 0 AND stg.visible = 1)
                   JOIN (SELECT attendanceid, setnumber, MAX(grade) AS maxgrade
                           FROM {attendance_statuses}
                          WHERE deleted = 0
                            AND visible = 1
                         GROUP BY attendanceid, setnumber) stm
                     ON (stm.setnumber = ats.statusset AND stm.attendanceid = ats.attendanceid)
                  {$joingroup}
                  WHERE ats.sessdate >= c.startdate
                    AND ats.lasttaken != 0
                    {$where}
                GROUP BY a.id, a.course, c.fullname, atl.studentid
                ) p GROUP by courseid, coursename {$orderby}";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Template variables into place in supplied email content.
 *
 * @param object $record db record of details
 * @return array - the content of the fields after templating.
 */
function attendance_template_variables($record) {
    $templatevars = array(
        '/%coursename%/' => $record->coursename,
        '/%courseid%/' => $record->courseid,
        '/%userfirstname%/' => $record->firstname,
        '/%userlastname%/' => $record->lastname,
        '/%userid%/' => $record->userid,
        '/%warningpercent%/' => $record->warningpercent,
        '/%attendancename%/' => $record->aname,
        '/%cmid%/' => $record->cmid,
        '/%numtakensessions%/' => $record->numtakensessions,
        '/%points%/' => $record->points,
        '/%maxpoints%/' => $record->maxpoints,
        '/%percent%/' => $record->percent,
    );
    $extrauserfields = get_all_user_name_fields();
    foreach ($extrauserfields as $extra) {
        $templatevars['/%'.$extra.'%/'] = $record->$extra;
    }
    $patterns = array_keys($templatevars); // The placeholders which are to be replaced.
    $replacements = array_values($templatevars); // The values which are to be templated in for the placeholders.
    // Array to describe which fields in reengagement object should have a template replacement.
    $replacementfields = array('emailsubject', 'emailcontent');

    // Replace %variable% with relevant value everywhere it occurs in reengagement->field.
    foreach ($replacementfields as $field) {
        $record->$field = preg_replace($patterns, $replacements, $record->$field);
    }
    return $record;
}


/**
 * Used to print simple time - 1am instead of 1:00am.
 *
 * @param int $time - unix timestamp.
 */
function attendance_strftimehm($time) {
    $mins = userdate($time, '%M');

    if ($mins == '00') {
        $format = get_string('strftimeh', 'attendance');
    } else {
        $format = get_string('strftimehm', 'attendance');
    }

    $userdate = userdate($time, $format);

    // Some Lang packs use %p to suffix with AM/PM but not all strftime support this.
    // Check if %p is in use and make sure it's being respected.
    if (stripos($format, '%p')) {
        // Check if $userdate did something with %p by checking userdate against the same format without %p.
        $formatwithoutp = str_ireplace('%p', '', $format);
        if (userdate($time, $formatwithoutp) == $userdate) {
            // The date is the same with and without %p - we have a problem.
            if (userdate($time, '%H') > 11) {
                $userdate .= 'pm';
            } else {
                $userdate .= 'am';
            }
        }
        // Some locales and O/S don't respect correct intended case of %p vs %P
        // This can cause problems with behat which expects AM vs am.
        if (strpos($format, '%p')) { // Should be upper case according to PHP spec.
            $userdate = str_replace('am', 'AM', $userdate);
            $userdate = str_replace('pm', 'PM', $userdate);
        }
    }

    return $userdate;
}

/**
 * Used to print simple time - 1am instead of 1:00am.
 *
 * @param int $datetime - unix timestamp.
 * @param int $duration - number of seconds.
 */
function attendance_construct_session_time($datetime, $duration) {
    $starttime = attendance_strftimehm($datetime);
    $endtime = attendance_strftimehm($datetime + $duration);

    return $starttime . ($duration > 0 ? ' - ' . $endtime : '');
}

/**
 * Used to print session time.
 *
 * @param int $datetime - unix timestamp.
 * @param int $duration - number of seconds duration.
 * @return string.
 */
function construct_session_full_date_time($datetime, $duration) {
    $sessinfo = userdate($datetime, get_string('strftimedmyw', 'attendance'));
    $sessinfo .= ' '.attendance_construct_session_time($datetime, $duration);

    return $sessinfo;
}

/**
 * Returns description of method result value.
 * @return array of room capacity options
 */
function attendance_room_capacities() {
    $options = [];
    $options[0] = '';
    $i = 1;
    for ($n = 1; $n <= ATT_ROOMS_MAX_CAPACITY; $n += $n < 20 ? 1 : ($n < 50 ? 5 : ($n < 200 ? 10 : ($n < 500 ? 50 : 100)))) {
        $options[$i++] = $n;
    }
    return $options;
}

/**
 * Returns array of session ids that the user has booked.
 */
function attendance_sessionsbooked() {
    global $DB, $USER;
    return $DB->get_fieldset_select('attendance_bookings', 'sessionid', 'userid = ?', array($USER->id));
}


/**
 * Returns int how many bookings exist for given session.
 * @param int $sessionid
 */
function attendance_sessionbookings($sessionid) {
    global $DB;
    return intval($DB->get_field_sql('SELECT COUNT(*) FROM {attendance_bookings} WHERE sessionid = :sessionid',
        array('sessionid' => $sessionid)));
}
