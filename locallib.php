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
 * local functions and constants for module presence
 *
 * @package   mod_presence
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');
require_once(dirname(__FILE__).'/renderhelpers.php');

define('PRESENCE_VIEW_DAYS', 1);
define('PRESENCE_VIEW_WEEKS', 2);
define('PRESENCE_VIEW_MONTHS', 3);
define('PRESENCE_VIEW_ALLPAST', 4);
define('PRESENCE_VIEW_ALL', 5);
define('PRESENCE_VIEW_NOTPRESENT', 6);
define('PRESENCE_VIEW_SUMMARY', 7);
define('PRESENCE_VIEW_ALLFUTURE', 8);

define('PRESENCE_SORT_DEFAULT', 0);
define('PRESENCE_SORT_LASTNAME', 1);
define('PRESENCE_SORT_FIRSTNAME', 2);

define('PRESENCE_ROOMS_MAX_CAPACITY', 1000);

/**
 * Boilerplate code for an presence page
 * @param array p
 */

function presence_init_page($p) {
    global $id, $presence, $capabilities, $cm, $context, $course, $output, $pageparams, $DB, $PAGE, $tabs, $header;
    $id             = required_param('id', PARAM_INT);
    $cm             = get_coursemodule_from_id('presence', $id, 0, false, MUST_EXIST);
    $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $presence            = $DB->get_record('presence', array('id' => $cm->instance), '*', MUST_EXIST);

    require_login($course, true, $cm);

    $context = context_module::instance($cm->id);

    if (!has_any_capability($capabilities, $context)) {
        $url = new moodle_url('/mod/presence/view.php', array('id' => $cm->id));
        redirect($url);
    }

    $pageparams->init($cm);
    $presence = new mod_presence_structure($presence, $cm, $course, $context, $pageparams);

    $PAGE->set_title($course->shortname. ": ".$presence->name);
    $PAGE->set_heading($course->fullname);
    $PAGE->set_cacheable(true);
    $PAGE->force_settings_menu(true);
    $PAGE->set_url($p['url']);
    // $PAGE->navbar->add($presence->name);

    $tabs = new presence_tabs($presence, $p['tab']);
    $title = get_string('presenceforthecourse', 'presence'); // .' :: ' .format_string($course->fullname);
    $header = new mod_presence_header($presence, $title);

    if (!isset($p['printheader']) || $p['printheader']) {
        presence_print_header();
    }
}

function presence_print_header() {
    global $PAGE, $tabs, $header, $output;
    $output = $PAGE->get_renderer('mod_presence');
    echo $output->header();
    echo $output->render($header);
    mod_presence_notifyqueue::show();
    echo $output->render($tabs);

}

/**
 * Get users courses and the relevant presences.
 *
 * @param int $userid
 * @return array
 */
function presence_get_user_courses_presences($userid) {
    global $DB;

    $usercourses = enrol_get_users_courses($userid);

    list($usql, $uparams) = $DB->get_in_or_equal(array_keys($usercourses), SQL_PARAMS_NAMED, 'cid0');

    $sql = "SELECT att.id as attid, att.course as courseid, course.fullname as coursefullname,
                   course.startdate as coursestartdate, att.name as attname, att.grade as attgrade
              FROM {presence} att
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
function presence_calc_fraction($part, $total) {
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
function presence_has_logs_for_status($statusid) {
    global $DB;
    return $DB->record_exists('presence_evaluations', array('statusid' => $statusid));
}

/**
 * Helper function to add sessiondate_selector to add/update forms.
 *
 * @param MoodleQuickForm $mform
 */
function presence_form_sessiondate_selector (MoodleQuickForm $mform, $dateselector = true, $sess = null) {

    $mform->addElement('date_selector', 'sessiondate', get_string('sessiondate', 'presence'));

    for ($i = 0; $i <= 23; $i++) {
        $hours[$i] = sprintf("%02d", $i);
    }
    for ($i = 0; $i < 60; $i += 5) {
        $minutes[$i] = sprintf("%02d", $i);
    }

    $sesendtime = array();
    $sesendtime[] =& $mform->createElement('static', 'from', '', get_string('from', 'presence'));
    $sesendtime[] =& $mform->createElement('select', 'starthour', get_string('hour', 'form'), $hours, false, true);
    $sesendtime[] =& $mform->createElement('select', 'startminute', get_string('minute', 'form'), $minutes, false, true);
    $sesendtime[] =& $mform->createElement('static', 'to', '', get_string('to', 'presence'));
    $sesendtime[] =& $mform->createElement('select', 'endhour', get_string('hour', 'form'), $hours, false, true);
    $sesendtime[] =& $mform->createElement('select', 'endminute', get_string('minute', 'form'), $minutes, false, true);

    $mform->addGroup($sesendtime, 'sestime', get_string('time', 'presence'), array(' '), true);
}

/**
 * Helper function to add room options to add/update forms.
 *
 * @param MoodleQuickForm $mform
 * @param mod_presence_structure $presence
 * @param stdClass $sess
 */
function presence_form_session_room (MoodleQuickForm $mform, mod_presence_structure $presence, $sess = null) {
    if (!$sess) {
        $sess = new stdClass();
        $sess->roomid = 0;
        $sess->maxattendants = 0;
        $sess->bookings = 0;

    }


    $options = [0 => ''] + $presence->get_room_names(true, true);
    $mform->addElement('select', 'roomid',
        get_string('roomselect', 'presence'), $options);
    $mform->setType('roomid', PARAM_INT);

    $mform->addElement('select', 'maxattendants',
        get_string('roomattendantsmax', 'presence'), presence_room_capacities());
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
function presence_random_string($length=6) {
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
 * Generate worksheet for presence export
 *
 * @param stdclass $data The data for the report
 * @param string $filename The name of the file
 * @param string $format excel|ods
 *
 */
function presence_exporttotableed($data, $filename, $format) {
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
    $myxls = $workbook->add_worksheet(get_string('modulenameplural', 'presence'));
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
 * Generate csv for presence export
 *
 * @param stdclass $data The data for the report
 * @param string $filename The name of the file
 *
 */
function presence_exporttocsv($data, $filename) {
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
 * @param stdClass $formdata moodleform - presence form.
 * @param mod_presence_structure $presence - used to get presence level subnet.
 * @return array.
 */
function presence_construct_sessions_data_for_add($formdata, mod_presence_structure $presence) {
    global $CFG, $DB;

    $sesstarttime = $formdata->sestime['starthour'] * HOURSECS + $formdata->sestime['startminute'] * MINSECS;
    $sesendtime = $formdata->sestime['endhour'] * HOURSECS + $formdata->sestime['endminute'] * MINSECS;
    $sessiondate = $formdata->sessiondate + $sesstarttime;
    $duration = $sesendtime - $sesstarttime;
    $now = time();

    $sessions = [];
    if (isset($formdata->addmultiply)) {

        $calgroup = $DB->get_field_sql('SELECT MAX(calgroup) FROM {presence_sessions}') + 1;

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
                    $sess->description = $formdata->sdescription;
                    $sess->timemodified = $now;
                    $sess->roomid = intval($formdata->roomid);
                    $sess->maxattendants = intval($formdata->maxattendants);
                    $sess->calgroup = $calgroup;
                    $sessions[] = $sess;
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
        $sess->description = $formdata->sdescription;
        $sess->timemodified = $now;
        $sess->roomid = intval($formdata->roomid);
        $sess->maxattendants = intval($formdata->maxattendants);
        $sess->calgroup = 0;
        $sessions[] = $sess;
    }

    return $sessions;
}

/**
 * Helper function for presence_construct_sessions_data_for_add().
 *
 * @param stdClass $formdata
 * @param stdClass $sessions
 * @param stdClass $sess
 */
function presence_fill_groupid($formdata, &$sessions, $sess) {
    if ($formdata->sessiontype == mod_presence_structure::SESSION_COMMON) {
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
function presence_course_users_points($courseids = array(), $orderby = '') {
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
                   FROM {presence_sessions} ats
                   JOIN {presence} a ON a.id = ats.presenceid
                   JOIN {course} c ON c.id = a.course
                   JOIN {presence_evaluations} atl ON (atl.sessionid = ats.id)
                   JOIN {presence_statuses} stg ON (stg.id = atl.statusid AND stg.deleted = 0 AND stg.visible = 1)
                   JOIN (SELECT presenceid, setnumber, MAX(grade) AS maxgrade
                           FROM {presence_statuses}
                          WHERE deleted = 0
                            AND visible = 1
                         GROUP BY presenceid, setnumber) stm
                     ON (stm.setnumber = ats.statusset AND stm.presenceid = ats.presenceid)
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
function presence_template_variables($record) {
    $templatevars = array(
        '/%coursename%/' => $record->coursename,
        '/%courseid%/' => $record->courseid,
        '/%userfirstname%/' => $record->firstname,
        '/%userlastname%/' => $record->lastname,
        '/%userid%/' => $record->userid,
        '/%warningpercent%/' => $record->warningpercent,
        '/%presencename%/' => $record->aname,
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
function presence_strftimehm($time) {
//    $mins = userdate($time, '%M');
//
//    if ($mins == '00') {
//        $format = get_string('strftimeh', 'presence');
//    } else {
//        $format = get_string('strftimehm', 'presence');
//    }

    $userdate = userdate($time, get_string('strftimetime', 'langconfig'));


    return $userdate;
}

/**
 * Used to print simple time - 1am instead of 1:00am.
 *
 * @param int $datetime - unix timestamp.
 * @param int $duration - number of seconds.
 */
function presence_construct_session_time($datetime, $duration) {
    $starttime = presence_strftimehm($datetime);
    $endtime = presence_strftimehm($datetime + $duration);

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
    $sessinfo = userdate($datetime, get_string('strftimedate', 'langconfig'));
    $sessinfo .= ', '.presence_construct_session_time($datetime, $duration);

    return $sessinfo;
}

/**
 * Returns description of method result value.
 * @return array of room capacity options
 */
function presence_room_capacities() {
    $options = [];
    $options[0] = '';
    $i = 1;
    for ($n = 1; $n <= PRESENCE_ROOMS_MAX_CAPACITY; $n += $n < 20 ? 1 : ($n < 50 ? 5 : ($n < 200 ? 10 : ($n < 500 ? 50 : 100)))) {
        $options[$i++] = $n;
    }
    return $options;
}

/**
 * Returns array of session ids that the user has booked.
 */
function presence_sessionsbooked() {
    global $DB, $USER;
    return $DB->get_fieldset_select('presence_bookings', 'sessionid', 'userid = ?', array($USER->id));
}


/**
 * Returns int how many bookings exist for given session.
 * @param int $sessionid
 * @return int
 */
function presence_sessionbookings(int $sessionid) : int {
    global $DB;
    return intval($DB->get_field_sql('SELECT COUNT(*) FROM {presence_bookings} WHERE sessionid = :sessionid',
        array('sessionid' => $sessionid)));
}

/**
 * Finish evaluation of given session.
 * @param mod_presence_structure $presence
 * @param int $sessionid
 */
function presence_finish_evaluation($presence, $sessionid) {
    global $DB, $USER;
    $DB->update_record('presence_sessions', (object)[
        'id' => $sessionid,
        'presenceid' => $presence->id,
        'lastevaluated' => time(),
        'lastevaluatedby' => $USER->id,
    ]);
}

/**
 * Finish evaluation of given session.
 * @param mod_presence_structure $presence
 */
function presence_finish_all_evaluations($presence) {
    global $DB, $USER;
    $evaluations = $DB->get_records_select('presence_sessions',
        'sessdate < :now AND presenceid = :presenceid AND lastevaluatedby = 0', [
            'now' => time(),
            'presenceid' => $presence->id,
        ]);
    foreach ($evaluations as $evaluation) {
        $DB->update_record('presence_sessions', (object)[
            'id' => $evaluation->id,
            'lastevaluated' => time(),
            'lastevaluatedby' => $USER->id,
        ]);
    }
}