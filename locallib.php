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

global $CFG;
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

class att_page_with_filter_controls {
    const SELECTOR_NONE         = 1;
    const SELECTOR_GROUP        = 2;
    const SELECTOR_SESS_TYPE    = 3;

    const SESSTYPE_COMMON       = 0;
    const SESSTYPE_ALL          = -1;
    const SESSTYPE_NO_VALUE     = -2;

    /** @var int current view mode */
    public $view;

    /** @var int $view and $curdate specify displaed date range */
    public $curdate;

    /** @var int start date of displayed date range */
    public $startdate;

    /** @var int end date of displayed date range */
    public $enddate;

    public $selectortype        = self::SELECTOR_NONE;

    protected $defaultview      = ATT_VIEW_WEEKS;

    private $cm;

    private $sessgroupslist;

    private $sesstype;

    public function init($cm) {
        $this->cm = $cm;
        $this->init_view();
        $this->init_curdate();
        $this->init_start_end_date();
    }

    private function init_view() {
        global $SESSION;

        if (isset($this->view)) {
            $SESSION->attcurrentattview[$this->cm->course] = $this->view;
        } else if (isset($SESSION->attcurrentattview[$this->cm->course])) {
            $this->view = $SESSION->attcurrentattview[$this->cm->course];
        } else {
            $this->view = $this->defaultview;
        }
    }

    private function init_curdate() {
        global $SESSION;

        if (isset($this->curdate)) {
            $SESSION->attcurrentattdate[$this->cm->course] = $this->curdate;
        } else if (isset($SESSION->attcurrentattdate[$this->cm->course])) {
            $this->curdate = $SESSION->attcurrentattdate[$this->cm->course];
        } else {
            $this->curdate = time();
        }
    }

    public function init_start_end_date() {
        global $CFG;

        // HOURSECS solves issue for weeks view with Daylight saving time and clocks adjusting by one hour backward.
        $date = usergetdate($this->curdate + HOURSECS);
        $mday = $date['mday'];
        $wday = $date['wday'] - $CFG->calendar_startwday;
        if ($wday < 0) {
            $wday += 7;
        }
        $mon = $date['mon'];
        $year = $date['year'];

        switch ($this->view) {
            case ATT_VIEW_DAYS:
                $this->startdate = make_timestamp($year, $mon, $mday);
                $this->enddate = make_timestamp($year, $mon, $mday + 1);
                break;
            case ATT_VIEW_WEEKS:
                $this->startdate = make_timestamp($year, $mon, $mday - $wday);
                $this->enddate = make_timestamp($year, $mon, $mday + 7 - $wday) - 1;
                break;
            case ATT_VIEW_MONTHS:
                $this->startdate = make_timestamp($year, $mon);
                $this->enddate = make_timestamp($year, $mon + 1);
                break;
            case ATT_VIEW_ALLPAST:
                $this->startdate = 1;
                $this->enddate = time();
                break;
            case ATT_VIEW_ALL:
                $this->startdate = 0;
                $this->enddate = 0;
                break;
        }
    }

    private function calc_sessgroupslist_sesstype() {
        global $SESSION;

        if (!array_key_exists('attsessiontype', $SESSION)) {
            $SESSION->attsessiontype = array($this->cm->course => self::SESSTYPE_ALL);
        } else if (!array_key_exists($this->cm->course, $SESSION->attsessiontype)) {
            $SESSION->attsessiontype[$this->cm->course] = self::SESSTYPE_ALL;
        }

        $group = optional_param('group', self::SESSTYPE_NO_VALUE, PARAM_INT);
        if ($this->selectortype == self::SELECTOR_SESS_TYPE) {
            if ($group > self::SESSTYPE_NO_VALUE) {
                $SESSION->attsessiontype[$this->cm->course] = $group;
                if ($group > self::SESSTYPE_ALL) {
                    // Set activegroup in $SESSION.
                    groups_get_activity_group($this->cm, true);
                } else {
                    // Reset activegroup in $SESSION.
                    unset($SESSION->activegroup[$this->cm->course][VISIBLEGROUPS][$this->cm->groupingid]);
                    unset($SESSION->activegroup[$this->cm->course]['aag'][$this->cm->groupingid]);
                    unset($SESSION->activegroup[$this->cm->course][SEPARATEGROUPS][$this->cm->groupingid]);
                }
                $this->sesstype = $group;
            } else {
                $this->sesstype = $SESSION->attsessiontype[$this->cm->course];
            }
        } else if ($this->selectortype == self::SELECTOR_GROUP) {
            if ($group == 0) {
                $SESSION->attsessiontype[$this->cm->course] = self::SESSTYPE_ALL;
                $this->sesstype = self::SESSTYPE_ALL;
            } else if ($group > 0) {
                $SESSION->attsessiontype[$this->cm->course] = $group;
                $this->sesstype = $group;
            } else {
                $this->sesstype = $SESSION->attsessiontype[$this->cm->course];
            }
        }

        if (is_null($this->sessgroupslist)) {
            $this->calc_sessgroupslist();
        }
        // For example, we set SESSTYPE_ALL but user can access only to limited set of groups.
        if (!array_key_exists($this->sesstype, $this->sessgroupslist)) {
            reset($this->sessgroupslist);
            $this->sesstype = key($this->sessgroupslist);
        }
    }

    private function calc_sessgroupslist() {
        global $USER, $PAGE;

        $this->sessgroupslist = array();
        $groupmode = groups_get_activity_groupmode($this->cm);
        if ($groupmode == NOGROUPS) {
            return;
        }

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $PAGE->context)) {
            $allowedgroups = groups_get_all_groups($this->cm->course, 0, $this->cm->groupingid);
        } else {
            $allowedgroups = groups_get_all_groups($this->cm->course, $USER->id, $this->cm->groupingid);
        }

        if ($allowedgroups) {
            if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $PAGE->context)) {
                $this->sessgroupslist[self::SESSTYPE_ALL] = get_string('all', 'attendance');
            }
            // Show Common groups always.
            $this->sessgroupslist[self::SESSTYPE_COMMON] = get_string('commonsessions', 'attendance');
            foreach ($allowedgroups as $group) {
                $this->sessgroupslist[$group->id] = get_string('group') . ': ' . format_string($group->name);
            }
        }
    }

    public function get_sess_groups_list() {
        if (is_null($this->sessgroupslist)) {
            $this->calc_sessgroupslist_sesstype();
        }

        return $this->sessgroupslist;
    }

    public function get_current_sesstype() {
        if (is_null($this->sesstype)) {
            $this->calc_sessgroupslist_sesstype();
        }

        return $this->sesstype;
    }

    public function set_current_sesstype($sesstype) {
        $this->sesstype = $sesstype;
    }
}

class att_view_page_params extends att_page_with_filter_controls {
    const MODE_THIS_COURSE  = 0;
    const MODE_ALL_COURSES  = 1;

    public $studentid;

    public $mode;

    public function  __construct() {
        $this->defaultview = ATT_VIEW_MONTHS;
    }

    public function get_significant_params() {
        $params = array();

        if (isset($this->studentid)) {
            $params['studentid'] = $this->studentid;
        }
        if ($this->mode != self::MODE_THIS_COURSE) {
            $params['mode'] = $this->mode;
        }

        return $params;
    }
}

class att_manage_page_params extends att_page_with_filter_controls {
    public function  __construct() {
        $this->selectortype = att_page_with_filter_controls::SELECTOR_SESS_TYPE;
    }

    public function get_significant_params() {
        return array();
    }
}

class att_sessions_page_params {
    const ACTION_ADD              = 1;
    const ACTION_UPDATE           = 2;
    const ACTION_DELETE           = 3;
    const ACTION_DELETE_SELECTED  = 4;
    const ACTION_CHANGE_DURATION  = 5;
    const ACTION_DELETE_HIDDEN    = 6;

    /** @var int view mode of taking attendance page*/
    public $action;
}

class att_take_page_params {
    const SORTED_LIST           = 1;
    const SORTED_GRID           = 2;

    const DEFAULT_VIEW_MODE     = self::SORTED_LIST;

    public $sessionid;
    public $grouptype;
    public $group;
    public $sort;
    public $copyfrom;

    /** @var int view mode of taking attendance page*/
    public $viewmode;

    public $gridcols;

    public function init() {
        if (!isset($this->group)) {
            $this->group = 0;
        }
        if (!isset($this->sort)) {
            $this->sort = ATT_SORT_LASTNAME;
        }
        $this->init_view_mode();
        $this->init_gridcols();
    }

    private function init_view_mode() {
        if (isset($this->viewmode)) {
            set_user_preference("attendance_take_view_mode", $this->viewmode);
        } else {
            $this->viewmode = get_user_preferences("attendance_take_view_mode", self::DEFAULT_VIEW_MODE);
        }
    }

    private function init_gridcols() {
        if (isset($this->gridcols)) {
            set_user_preference("attendance_gridcolumns", $this->gridcols);
        } else {
            $this->gridcols = get_user_preferences("attendance_gridcolumns", 5);
        }
    }

    public function get_significant_params() {
        $params = array();

        $params['sessionid'] = $this->sessionid;
        $params['grouptype'] = $this->grouptype;
        if ($this->group) {
            $params['group'] = $this->group;
        }
        if ($this->sort != ATT_SORT_LASTNAME) {
            $params['sort'] = $this->sort;
        }
        if (isset($this->copyfrom)) {
            $params['copyfrom'] = $this->copyfrom;
        }

        return $params;
    }
}

class att_report_page_params extends att_page_with_filter_controls {
    public $group;
    public $sort;

    public function  __construct() {
        $this->selectortype = self::SELECTOR_GROUP;
    }

    public function init($cm) {
        parent::init($cm);

        if (!isset($this->group)) {
            $this->group = $this->get_current_sesstype() > 0 ? $this->get_current_sesstype() : 0;
        }
        if (!isset($this->sort)) {
            $this->sort = ATT_SORT_LASTNAME;
        }
    }

    public function get_significant_params() {
        $params = array();

        if ($this->sort != ATT_SORT_LASTNAME) {
            $params['sort'] = $this->sort;
        }

        return $params;
    }
}

class att_preferences_page_params {
    const ACTION_ADD              = 1;
    const ACTION_DELETE           = 2;
    const ACTION_HIDE             = 3;
    const ACTION_SHOW             = 4;
    const ACTION_SAVE             = 5;

    /** @var int view mode of taking attendance page*/
    public $action;

    public $statusid;

    public $statusset;

    public function get_significant_params() {
        $params = array();

        if (isset($this->action)) {
            $params['action'] = $this->action;
        }
        if (isset($this->statusid)) {
            $params['statusid'] = $this->statusid;
        }
        if (isset($this->statusset)) {
            $params['statusset'] = $this->statusset;
        }

        return $params;
    }
}




function att_get_statuses($attid, $onlyvisible=true, $statusset = -1) {
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
function att_get_setname($attid, $statusset, $includevalues = true) {
    $statusname = get_string('statusset', 'mod_attendance', $statusset + 1);
    if ($includevalues) {
        $statuses = att_get_statuses($attid, true, $statusset);
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

function att_get_user_taken_sessions_count($attid, $coursestartdate, $userid, $coursemodule, $startdate = '', $enddate = '') {
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

function att_get_user_statuses_stat($attid, $coursestartdate, $userid, $coursemodule) {
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

function att_get_user_grade($userstatusesstat, $statuses) {
    $sum = 0;
    foreach ($userstatusesstat as $stat) {
        $sum += $stat->stcnt * $statuses[$stat->statusid]->grade;
    }

    return $sum;
}

function att_get_user_max_grade($sesscount, $statuses) {
    reset($statuses);
    return current($statuses)->grade * $sesscount;
}

function att_get_user_courses_attendances($userid) {
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
 * @param $grade - raw grade for user
 * @param $maxgrade - maxgrade for this session.
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
 * @param $attendance
 * @param $coursemodule
 */
function attendance_update_all_users_grades(mod_attendance_structure $attendance, $coursemodule) {
    $grades = array();
    $course = $attendance->course;

    $userids = array_keys(get_enrolled_users($attendance->context, 'mod/attendance:canbelisted', 0, 'u.id'));
    $attgrades = grade_get_grades($course->id, 'mod', 'attendance', $attendance->id, $userids);

    $usergrades = [];
    if (!empty($attgrades->items[0]) and !empty($attgrades->items[0]->grades)) {
        $usergrades = $attgrades->items[0]->grades;
    }
    $statuses = att_get_statuses($attendance->id);
    foreach ($usergrades as $userid => $existinggrade) {
        if (is_null($existinggrade->grade)) {
            // Don't update grades where one doesn't exist yet.
            continue;
        }
        $grade = new stdClass;
        $grade->userid = $userid;
        $userstatusesstat = att_get_user_statuses_stat($attendance->id, $course->startdate, $userid, $coursemodule);
        $usertakensesscount = att_get_user_taken_sessions_count($attendance->id, $course->startdate, $userid, $coursemodule);
        $usergrade = att_get_user_grade($userstatusesstat, $statuses);
        $usermaxgrade = att_get_user_max_grade($usertakensesscount, $statuses);
        $grade->rawgrade = attendance_calc_user_grade_fraction($usergrade, $usermaxgrade) * $attendance->grade;
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