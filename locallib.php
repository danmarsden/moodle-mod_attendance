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

class attendance_permissions {
    private $canview;
    private $canviewreports;
    private $cantake;
    private $canchange;
    private $canmanage;
    private $canchangepreferences;
    private $canexport;
    private $canbelisted;
    private $canaccessallgroups;

    private $cm;
    private $context;

    public function __construct($cm, $context) {
        $this->cm = $cm;
        $this->context = $context;
    }

    public function can_view() {
        if (is_null($this->canview)) {
            $this->canview = has_capability('mod/attendance:view', $this->context);
        }

        return $this->canview;
    }

    public function require_view_capability() {
        require_capability('mod/attendance:view', $this->context);
    }

    public function can_view_reports() {
        if (is_null($this->canviewreports)) {
            $this->canviewreports = has_capability('mod/attendance:viewreports', $this->context);
        }

        return $this->canviewreports;
    }

    public function require_view_reports_capability() {
        require_capability('mod/attendance:viewreports', $this->context);
    }

    public function can_take() {
        if (is_null($this->cantake)) {
            $this->cantake = has_capability('mod/attendance:takeattendances', $this->context);
        }

        return $this->cantake;
    }

    public function can_take_session($groupid) {
        if (!$this->can_take()) {
            return false;
        }

        if ($groupid == attendance::SESSION_COMMON
            || $this->can_access_all_groups()
            || array_key_exists($groupid, groups_get_activity_allowed_groups($this->cm))) {
            return true;
        }

        return false;
    }

    public function can_change() {
        if (is_null($this->canchange)) {
            $this->canchange = has_capability('mod/attendance:changeattendances', $this->context);
        }

        return $this->canchange;
    }

    public function can_manage() {
        if (is_null($this->canmanage)) {
            $this->canmanage = has_capability('mod/attendance:manageattendances', $this->context);
        }

        return $this->canmanage;
    }

    public function require_manage_capability() {
        require_capability('mod/attendance:manageattendances', $this->context);
    }

    public function can_change_preferences() {
        if (is_null($this->canchangepreferences)) {
            $this->canchangepreferences = has_capability('mod/attendance:changepreferences', $this->context);
        }

        return $this->canchangepreferences;
    }

    public function require_change_preferences_capability() {
        require_capability('mod/attendance:changepreferences', $this->context);
    }

    public function can_export() {
        if (is_null($this->canexport)) {
            $this->canexport = has_capability('mod/attendance:export', $this->context);
        }

        return $this->canexport;
    }

    public function require_export_capability() {
        require_capability('mod/attendance:export', $this->context);
    }

    public function can_be_listed() {
        if (is_null($this->canbelisted)) {
            $this->canbelisted = has_capability('mod/attendance:canbelisted', $this->context, null, false);
        }

        return $this->canbelisted;
    }

    public function can_access_all_groups() {
        if (is_null($this->canaccessallgroups)) {
            $this->canaccessallgroups = has_capability('moodle/site:accessallgroups', $this->context);
        }

        return $this->canaccessallgroups;
    }
}

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
            // Show Common groups always
            $this->sessgroupslist[self::SESSTYPE_COMMON] = get_string('commonsessions', 'attendance');
            foreach ($allowedgroups as $group) {
                $this->sessgroupslist[$group->id] = format_string($group->name);
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

    public function get_significant_params() {
        $params = array();

        if (isset($this->action)) {
            $params['action'] = $this->action;
        }
        if (isset($this->statusid)) {
            $params['statusid'] = $this->statusid;
        }

        return $params;
    }
}



class attendance {
    const SESSION_COMMON        = 0;
    const SESSION_GROUP         = 1;

    /** @var stdclass course module record */
    public $cm;

    /** @var stdclass course record */
    public $course;

    /** @var stdclass context object */
    public $context;

    /** @var int attendance instance identifier */
    public $id;

    /** @var string attendance activity name */
    public $name;

    /** @var float number (10, 5) unsigned, the maximum grade for attendance */
    public $grade;

    /** current page parameters */
    public $pageparams;

    /** @var attendance_permissions permission of current user for attendance instance*/
    public $perm;

    private $groupmode;

    private $statuses;

    // Array by sessionid.
    private $sessioninfo = array();

    // Arrays by userid.
    private $usertakensesscount = array();
    private $userstatusesstat = array();

    /**
     * Initializes the attendance API instance using the data from DB
     *
     * Makes deep copy of all passed records properties. Replaces integer $course attribute
     * with a full database record (course should not be stored in instances table anyway).
     *
     * @param stdClass $dbrecord Attandance instance data from {attendance} table
     * @param stdClass $cm       Course module record as returned by {@link get_coursemodule_from_id()}
     * @param stdClass $course   Course record from {course} table
     * @param stdClass $context  The context of the workshop instance
     */
    public function __construct(stdclass $dbrecord, stdclass $cm, stdclass $course, stdclass $context=null, $pageparams=null) {
        foreach ($dbrecord as $field => $value) {
            if (property_exists('attendance', $field)) {
                $this->{$field} = $value;
            } else {
                throw new coding_exception('The attendance table has a field with no property in the attendance class');
            }
        }
        $this->cm           = $cm;
        $this->course       = $course;
        if (is_null($context)) {
            $this->context = context_module::instance_by_id($this->cm->id);
        } else {
            $this->context = $context;
        }

        $this->pageparams = $pageparams;

        $this->perm = new attendance_permissions($this->cm, $this->context);
    }

    public function get_group_mode() {
        if (is_null($this->groupmode)) {
            $this->groupmode = groups_get_activity_groupmode($this->cm, $this->course);
        }
        return $this->groupmode;
    }

    /**
     * Returns current sessions for this attendance
     *
     * Fetches data from {attendance_sessions}
     *
     * @return array of records or an empty array
     */
    public function get_current_sessions() {
        global $DB;

        $today = time(); // Because we compare with database, we don't need to use usertime().

        $sql = "SELECT *
                  FROM {attendance_sessions}
                 WHERE :time BETWEEN sessdate AND (sessdate + duration)
                   AND attendanceid = :aid";
        $params = array(
                'time'  => $today,
                'aid'   => $this->id);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns today sessions for this attendance
     *
     * Fetches data from {attendance_sessions}
     *
     * @return array of records or an empty array
     */
    public function get_today_sessions() {
        global $DB;

        $start = usergetmidnight(time());
        $end = $start + DAYSECS;

        $sql = "SELECT *
                  FROM {attendance_sessions}
                 WHERE sessdate >= :start AND sessdate < :end
                   AND attendanceid = :aid";
        $params = array(
                'start' => $start,
                'end'   => $end,
                'aid'   => $this->id);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns today sessions suitable for copying attendance log
     *
     * Fetches data from {attendance_sessions}
     *
     * @return array of records or an empty array
     */
    public function get_today_sessions_for_copy($sess) {
        global $DB;

        $start = usergetmidnight($sess->sessdate);

        $sql = "SELECT *
                  FROM {attendance_sessions}
                 WHERE sessdate >= :start AND sessdate <= :end AND
                       (groupid = 0 OR groupid = :groupid) AND
                       lasttaken > 0 AND attendanceid = :aid";
        $params = array(
                'start'     => $start,
                'end'       => $sess->sessdate,
                'groupid'   => $sess->groupid,
                'aid'       => $this->id);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns count of hidden sessions for this attendance
     *
     * Fetches data from {attendance_sessions}
     *
     * @return count of hidden sessions
     */
    public function get_hidden_sessions_count() {
        global $DB;

        $where = "attendanceid = :aid AND sessdate < :csdate";
        $params = array(
                'aid'   => $this->id,
                'csdate'=> $this->course->startdate);

        return $DB->count_records_select('attendance_sessions', $where, $params);
    }

    public function get_filtered_sessions($filtergroups = true) {
        global $DB;

        if ($this->pageparams->startdate && $this->pageparams->enddate) {
            $where = "attendanceid = :aid AND sessdate >= :csdate AND sessdate >= :sdate AND sessdate < :edate";
        } else if ($this->pageparams->enddate) {
            $where = "attendanceid = :aid AND sessdate >= :csdate AND sessdate < :edate";
        } else {
            $where = "attendanceid = :aid AND sessdate >= :csdate";
        }
        if ($filtergroups && $this->pageparams->get_current_sesstype() > att_page_with_filter_controls::SESSTYPE_ALL) {
            $where .= " AND groupid=:cgroup";
        }
        $params = array(
                'aid'       => $this->id,
                'csdate'    => $this->course->startdate,
                'sdate'     => $this->pageparams->startdate,
                'edate'     => $this->pageparams->enddate,
                'cgroup'    => $this->pageparams->get_current_sesstype());
        $sessions = $DB->get_records_select('attendance_sessions', $where, $params, 'sessdate asc');
        foreach ($sessions as $sess) {
            if (empty($sess->description)) {
                $sess->description = get_string('nodescription', 'attendance');
            } else {
                $sess->description = file_rewrite_pluginfile_urls($sess->description,
                        'pluginfile.php', $this->context->id, 'mod_attendance', 'session', $sess->id);
            }
        }

        return $sessions;
    }

    /**
     * @return moodle_url of manage.php for attendance instance
     */
    public function url_manage($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/manage.php', $params);
    }

    /**
     * @return moodle_url of sessions.php for attendance instance
     */
    public function url_sessions($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/sessions.php', $params);
    }

    /**
     * @return moodle_url of report.php for attendance instance
     */
    public function url_report($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/report.php', $params);
    }

    /**
     * @return moodle_url of export.php for attendance instance
     */
    public function url_export() {
        $params = array('id' => $this->cm->id);
        return new moodle_url('/mod/attendance/export.php', $params);
    }

    /**
     * @return moodle_url of attsettings.php for attendance instance
     */
    public function url_preferences($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/preferences.php', $params);
    }

    /**
     * @return moodle_url of attendances.php for attendance instance
     */
    public function url_take($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/take.php', $params);
    }

    public function url_view($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/view.php', $params);
    }

    public function add_sessions($sessions) {
        global $DB;

        foreach ($sessions as $sess) {
            $sess->attendanceid = $this->id;

            $sess->id = $DB->insert_record('attendance_sessions', $sess);
            $description = file_save_draft_area_files($sess->descriptionitemid,
                        $this->context->id, 'mod_attendance', 'session', $sess->id,
                        array('subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0),
                        $sess->description);
            $DB->set_field('attendance_sessions', 'description', $description, array('id' => $sess->id));

            $info_array = array();
            $info_array[] = construct_session_full_date_time($sess->sessdate, $sess->duration);

            // Trigger a session added event.
            $event = \mod_attendance\event\session_added::create(array(
                        'objectid' => $this->id,
                        'context' => $this->context,
                        'other' => array('info' => implode(',', $info_array))
            ));
            $event->add_record_snapshot('course_modules', $this->cm);
            $sess->description = $description;
            $sess->lasttaken = 0;
            $sess->lasttakenby = 0;
            $sess->studentscanmark = 0;
            $event->add_record_snapshot('attendance_sessions', $sess);
            $event->trigger();
        }
    }

    public function update_session_from_form_data($formdata, $sessionid) {
        global $DB;

        if (!$sess = $DB->get_record('attendance_sessions', array('id' => $sessionid) )) {
            print_error('No such session in this course');
        }

        $sess->sessdate = $formdata->sessiondate;
        $sess->duration = $formdata->durtime['hours']*HOURSECS + $formdata->durtime['minutes']*MINSECS;
        $description = file_save_draft_area_files($formdata->sdescription['itemid'],
                                $this->context->id, 'mod_attendance', 'session', $sessionid,
                                array('subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0), $formdata->sdescription['text']);
        $sess->description = $description;
        $sess->descriptionformat = $formdata->sdescription['format'];
        $sess->timemodified = time();
        $DB->update_record('attendance_sessions', $sess);

        $info = construct_session_full_date_time($sess->sessdate, $sess->duration);
        $event = \mod_attendance\event\session_updated::create(array(
            'objectid' => $this->id,
            'context' => $this->context,
            'other' => array('info' => $info, 'sessionid' => $sessionid, 'action' => att_sessions_page_params::ACTION_UPDATE)));
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->add_record_snapshot('attendance_sessions', $sess);
        $event->trigger();
    }

    /**
     * Used to record attendance submitted by the student.
     *
     * @global type $DB
     * @global type $USER
     * @param type $mformdata
     * @return boolean
     */
    public function take_from_student($mformdata) {
        global $DB, $USER;

        $statuses = implode(',', array_keys( (array)$this->get_statuses() ));
        $now = time();

        $record = new stdClass();
        $record->studentid = $USER->id;
        $record->statusid = $mformdata->status;
        $record->statusset = $statuses;
        $record->remarks = get_string('set_by_student', 'mod_attendance');
        $record->sessionid = $mformdata->sessid;
        $record->timetaken = $now;
        $record->takenby = $USER->id;

        $dbsesslog = $this->get_session_log($mformdata->sessid);
        if (array_key_exists($record->studentid, $dbsesslog)) {
            // Already recorded do not save.
            return false;
        }
        else {
            $DB->insert_record('attendance_log', $record, false);
        }

        // Update the session to show that a register has been taken, or staff may overwrite records.
        $session = $this->get_session_info($mformdata->sessid);
        $session->lasttaken = $now;
        $session->lasttakenby = $USER->id;
        $DB->update_record('attendance_sessions', $session);

        // Update the users grade.
        $this->update_users_grade(array($USER->id));

        /* create url for link in log screen
         * need to set grouptype to 0 to allow take attendance page to be called
         * from report/log page */

        $params = array(
                'sessionid' => $this->pageparams->sessionid,
                'grouptype' => 0);

        // Log the change.
        $event = \mod_attendance\event\attendance_taken_by_student::create(array(
            'objectid' => $this->id,
            'context' => $this->context,
            'other' => $params));
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->add_record_snapshot('attendance_sessions', $session);
        $event->add_record_snapshot('attendance_log', $record);
        $event->trigger();

        return true;
    }

    public function take_from_form_data($formdata) {
        global $DB, $USER;
        // TODO: WARNING - $formdata is unclean - comes from direct $_POST - ideally needs a rewrite but we do some cleaning below.
        $statuses = implode(',', array_keys( (array)$this->get_statuses() ));
        $now = time();
        $sesslog = array();
        $formdata = (array)$formdata;
        foreach ($formdata as $key => $value) {
            if (substr($key, 0, 4) == 'user') {
                $sid = substr($key, 4);
                if (!(is_numeric($sid) && is_numeric($value))) { // Sanity check on $sid and $value.
                     print_error('nonnumericid', 'attendance');
                }
                $sesslog[$sid] = new stdClass();
                $sesslog[$sid]->studentid = $sid; // We check is_numeric on this above.
                $sesslog[$sid]->statusid = $value; // We check is_numeric on this above.
                $sesslog[$sid]->statusset = $statuses;
                $sesslog[$sid]->remarks = array_key_exists('remarks'.$sid, $formdata) ?
                                                      clean_param($formdata['remarks'.$sid], PARAM_TEXT) : '';
                $sesslog[$sid]->sessionid = $this->pageparams->sessionid;
                $sesslog[$sid]->timetaken = $now;
                $sesslog[$sid]->takenby = $USER->id;
            }
        }

        $dbsesslog = $this->get_session_log($this->pageparams->sessionid);
        foreach ($sesslog as $log) {
            if ($log->statusid) {
                if (array_key_exists($log->studentid, $dbsesslog)) {
                    $log->id = $dbsesslog[$log->studentid]->id;
                    $DB->update_record('attendance_log', $log);
                } else {
                    $DB->insert_record('attendance_log', $log, false);
                }
            }
        }

        $session = $this->get_session_info($this->pageparams->sessionid);
        $session->lasttaken = $now;
        $session->lasttakenby = $USER->id;
        $DB->update_record('attendance_sessions', $session);

        if ($this->grade != 0) {
            $this->update_users_grade(array_keys($sesslog));
        }

        // create url for link in log screen
        $params = array(
                'sessionid' => $this->pageparams->sessionid,
                'grouptype' => $this->pageparams->grouptype);
        $event = \mod_attendance\event\attendance_taken::create(array(
            'objectid' => $this->id,
            'context' => $this->context,
            'other' => $params));
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->add_record_snapshot('attendance_sessions', $session);
        $event->trigger();

        $group = 0;
        if ($this->pageparams->grouptype != attendance::SESSION_COMMON) {
            $group = $this->pageparams->grouptype;
        } else {
            if ($this->pageparams->group) {
                $group = $this->pageparams->group;
            }
        }

        $totalusers = count_enrolled_users(context_module::instance($this->cm->id), 'mod/attendance:canbelisted', $group);
        $usersperpage = $this->pageparams->perpage;

        if (!empty($this->pageparams->page) && $this->pageparams->page && $totalusers && $usersperpage) {
            $numberofpages = ceil($totalusers / $usersperpage);
            if ($this->pageparams->page < $numberofpages) {
                $params['page'] = $this->pageparams->page + 1;
                redirect($this->url_take($params), get_string('moreattendance', 'attendance'));
            }
        }

        redirect($this->url_manage(), get_string('attendancesuccess', 'attendance'));
    }

    /**
     * MDL-27591 made this method obsolete.
     */
    public function get_users($groupid = 0, $page = 1) {
        global $DB, $CFG;

        // Fields we need from the user table.
        $userfields = user_picture::fields('u', array('username' , 'idnumber' , 'institution' , 'department'));

        if (isset($this->pageparams->sort) and ($this->pageparams->sort == ATT_SORT_FIRSTNAME)) {
            $orderby = "u.firstname ASC, u.lastname ASC, u.idnumber ASC, u.institution ASC, u.department ASC";
        } else {
            $orderby = "u.lastname ASC, u.firstname ASC, u.idnumber ASC, u.institution ASC, u.department ASC";
        }

        if ($page) {
            $usersperpage = $this->pageparams->perpage;
            if (!empty($CFG->enablegroupmembersonly) and $this->cm->groupmembersonly) {
                $startusers = ($page - 1) * $usersperpage;
                if ($groupid == 0) {
                    $groups = array_keys(groups_get_all_groups($this->cm->course, 0, $this->cm->groupingid, 'g.id'));
                } else {
                    $groups = $groupid;
                }
                $users = get_users_by_capability($this->context, 'mod/attendance:canbelisted',
                                $userfields.',u.id, u.firstname, u.lastname, u.email',
                                $orderby, $startusers, $usersperpage, $groups,
                                '', false, true);
            } else {
                $startusers = ($page - 1) * $usersperpage;
                $users = get_enrolled_users($this->context, 'mod/attendance:canbelisted', $groupid, $userfields, $orderby, $startusers, $usersperpage);
            }
        } else {
            if (!empty($CFG->enablegroupmembersonly) and $this->cm->groupmembersonly) {
                if ($groupid == 0) {
                    $groups = array_keys(groups_get_all_groups($this->cm->course, 0, $this->cm->groupingid, 'g.id'));
                } else {
                    $groups = $groupid;
                }
                $users = get_users_by_capability($this->context, 'mod/attendance:canbelisted',
                                $userfields.',u.id, u.firstname, u.lastname, u.email',
                                $orderby, '', '', $groups,
                                '', false, true);
            } else {
                $users = get_enrolled_users($this->context, 'mod/attendance:canbelisted', $groupid, $userfields, $orderby);
            }
        }

        // Add a flag to each user indicating whether their enrolment is active.
        if (!empty($users)) {
            list($sql, $params) = $DB->get_in_or_equal(array_keys($users), SQL_PARAMS_NAMED, 'usid0');

            // CONTRIB-4868
            $mintime = 'MIN(CASE WHEN (ue.timestart > :zerotime) THEN ue.timestart ELSE ue.timecreated END)';
            $maxtime = 'MAX(ue.timeend)';

            // CONTRIB-3549
            $sql = "SELECT ue.userid, ue.status,
                           $mintime AS mintime,
                           $maxtime AS maxtime
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE ue.userid $sql
                           AND e.status = :estatus
                           AND e.courseid = :courseid
                  GROUP BY ue.userid, ue.status";
            $params += array('zerotime'=>0, 'estatus'=>ENROL_INSTANCE_ENABLED, 'courseid'=>$this->course->id);
            $enrolments = $DB->get_records_sql($sql, $params);

            foreach ($users as $user) {
                $users[$user->id]->enrolmentstatus = $enrolments[$user->id]->status;
                $users[$user->id]->enrolmentstart = $enrolments[$user->id]->mintime;
                $users[$user->id]->enrolmentend = $enrolments[$user->id]->maxtime;
            }
        }

        return $users;
    }

    public function get_user($userid) {
        global $DB;

        $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

        // CONTRIB-4868
        $mintime = 'MIN(CASE WHEN (ue.timestart > :zerotime) THEN ue.timestart ELSE ue.timecreated END)';
        $maxtime = 'MAX(ue.timeend)';

        $sql = "SELECT ue.userid, ue.status,
                       $mintime AS mintime,
                       $maxtime AS maxtime
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :uid
                       AND e.status = :estatus
                       AND e.courseid = :courseid
              GROUP BY ue.userid, ue.status";
        $params = array('zerotime'=>0, 'uid'=>$userid, 'estatus'=>ENROL_INSTANCE_ENABLED, 'courseid'=>$this->course->id);
        $enrolments = $DB->get_record_sql($sql, $params);

        $user->enrolmentstatus = $enrolments->status;
        $user->enrolmentstart = $enrolments->mintime;
        $user->enrolmentend = $enrolments->maxtime;

        return $user;
    }

    public function get_statuses($onlyvisible = true) {
        if (!isset($this->statuses)) {
            $this->statuses = att_get_statuses($this->id, $onlyvisible);
        }

        return $this->statuses;
    }

    public function get_session_info($sessionid) {
        global $DB;

        if (!array_key_exists($sessionid, $this->sessioninfo)) {
            $this->sessioninfo[$sessionid] = $DB->get_record('attendance_sessions', array('id' => $sessionid));
        }
        if (empty($this->sessioninfo[$sessionid]->description)) {
            $this->sessioninfo[$sessionid]->description = get_string('nodescription', 'attendance');
        } else {
            $this->sessioninfo[$sessionid]->description = file_rewrite_pluginfile_urls($this->sessioninfo[$sessionid]->description,
                        'pluginfile.php', $this->context->id, 'mod_attendance', 'session', $this->sessioninfo[$sessionid]->id);
        }
        return $this->sessioninfo[$sessionid];
    }

    public function get_sessions_info($sessionids) {
        global $DB;

        list($sql, $params) = $DB->get_in_or_equal($sessionids);
        $sessions = $DB->get_records_select('attendance_sessions', "id $sql", $params, 'sessdate asc');

        foreach ($sessions as $sess) {
            if (empty($sess->description)) {
                $sess->description = get_string('nodescription', 'attendance');
            } else {
                $sess->description = file_rewrite_pluginfile_urls($sess->description,
                            'pluginfile.php', $this->context->id, 'mod_attendance', 'session', $sess->id);
            }
        }

        return $sessions;
    }

    public function get_session_log($sessionid) {
        global $DB;

        return $DB->get_records('attendance_log', array('sessionid' => $sessionid), '', 'studentid,statusid,remarks,id');
    }

    public function get_user_stat($userid) {
        $ret = array();
        $ret['completed'] = $this->get_user_taken_sessions_count($userid);
        $ret['statuses'] = $this->get_user_statuses_stat($userid);

        return $ret;
    }

    public function get_user_taken_sessions_count($userid) {
        if (!array_key_exists($userid, $this->usertakensesscount)) {
            if (!empty($this->pageparams->startdate) && !empty($this->pageparams->enddate)) {
                $this->usertakensesscount[$userid] = att_get_user_taken_sessions_count($this->id, $this->course->startdate, $userid, $this->cm, $this->pageparams->startdate, $this->pageparams->enddate);
            } else {
                $this->usertakensesscount[$userid] = att_get_user_taken_sessions_count($this->id, $this->course->startdate, $userid, $this->cm);
            }
        }
        return $this->usertakensesscount[$userid];
    }

    /**
     *
     * @global type $DB
     * @param type $userid
     * @param type $filters - An array things to filter by. For now only enddate is valid.
     * @return type
     */
    public function get_user_statuses_stat($userid, array $filters = null) {
        global $DB;
        $params = array(
            'aid'           => $this->id,
            'cstartdate'    => $this->course->startdate,
            'uid'           => $userid);

        $processed_filters = array();

        // We test for any valid filters sent.
        if (isset($filters['enddate'])) {
            $processed_filters[] = 'ats.sessdate <= :enddate';
            $params['enddate'] = $filters['enddate'];
        }

        // Make the filter array into a SQL string.
        if (!empty($processed_filters)) {
            $processed_filters = ' AND '.implode(' AND ', $processed_filters);
        } else {
            $processed_filters = '';
        }


        $period = '';
        if (!empty($this->pageparams->startdate) && !empty($this->pageparams->enddate)) {
            $period = ' AND ats.sessdate >= :sdate AND ats.sessdate < :edate ';
            $params['sdate'] = $this->pageparams->startdate;
            $params['edate'] = $this->pageparams->enddate;
        }

        if ($this->get_group_mode()) {
            $qry = "SELECT al.statusid, count(al.statusid) AS stcnt
                  FROM {attendance_log} al
                  JOIN {attendance_sessions} ats ON al.sessionid = ats.id
                  LEFT JOIN {groups_members} gm ON gm.userid = al.studentid AND gm.groupid = ats.groupid
                 WHERE ats.attendanceid = :aid AND
                       ats.sessdate >= :cstartdate AND
                       al.studentid = :uid AND
                       (ats.groupid = 0 or gm.id is NOT NULL)".$period.$processed_filters."
              GROUP BY al.statusid";
        } else {
            $qry = "SELECT al.statusid, count(al.statusid) AS stcnt
                  FROM {attendance_log} al
                  JOIN {attendance_sessions} ats
                    ON al.sessionid = ats.id
                 WHERE ats.attendanceid = :aid AND
                       ats.sessdate >= :cstartdate AND
                       al.studentid = :uid".$period.$processed_filters."
              GROUP BY al.statusid";
        }

        // We do not want to cache, or use a cached version of the results when a filter is set.
        if ($filters !== null) {
            return $DB->get_records_sql($qry, $params);
        } else if (!array_key_exists($userid, $this->userstatusesstat)) {
            // Not filtered so if we do not already have them do the query.
            $this->userstatusesstat[$userid] = $DB->get_records_sql($qry, $params);
        }

        // Return the cached stats.
        return $this->userstatusesstat[$userid];
    }

    /**
     *
     * @param type $userid
     * @param type $filters - An array things to filter by. For now only enddate is valid.
     * @return type
     */
    public function get_user_grade($userid, array $filters = null) {
        return att_get_user_grade($this->get_user_statuses_stat($userid, $filters), $this->get_statuses());
    }

    // For getting sessions count implemented simplest method - taken sessions.
    // It can have error if users don't have attendance info for some sessions.
    // In the future we can implement another methods:
    // * all sessions between user start enrolment date and now;
    // * all sessions between user start and end enrolment date.
    // While implementing those methods we need recalculate grades of all users
    // on session adding.
    public function get_user_max_grade($userid) {
        return att_get_user_max_grade($this->get_user_taken_sessions_count($userid), $this->get_statuses());
    }

    public function update_users_grade($userids) {
        $grades = array();

        foreach ($userids as $userid) {
            $grades[$userid] = new stdClass();
            $grades[$userid]->userid = $userid;
            $grades[$userid]->rawgrade = att_calc_user_grade_fraction($this->get_user_grade($userid),
                                                                      $this->get_user_max_grade($userid)) * $this->grade;
        }

        return grade_update('mod/attendance', $this->course->id, 'mod', 'attendance',
                            $this->id, 0, $grades);
    }

    public function get_user_filtered_sessions_log($userid) {
        global $DB;

        if ($this->pageparams->startdate && $this->pageparams->enddate) {
            $where = "ats.attendanceid = :aid AND ats.sessdate >= :csdate AND
                      ats.sessdate >= :sdate AND ats.sessdate < :edate";
        } else {
            $where = "ats.attendanceid = :aid AND ats.sessdate >= :csdate";
        }
        if ($this->get_group_mode()) {
            $sql = "SELECT ats.id, ats.sessdate, ats.groupid, al.statusid, al.remarks
                  FROM {attendance_sessions} ats
                  JOIN {attendance_log} al ON ats.id = al.sessionid AND al.studentid = :uid
                  LEFT JOIN {groups_members} gm ON gm.userid = al.studentid AND gm.groupid = ats.groupid
                 WHERE $where AND (ats.groupid = 0 or gm.id is NOT NULL)
              ORDER BY ats.sessdate ASC";

            $params = array(
                'uid'       => $userid,
                'aid'       => $this->id,
                'csdate'    => $this->course->startdate,
                'sdate'     => $this->pageparams->startdate,
                'edate'     => $this->pageparams->enddate);

        } else {
            $sql = "SELECT ats.id, ats.sessdate, ats.groupid, al.statusid, al.remarks
                  FROM {attendance_sessions} ats
                  JOIN {attendance_log} al
                    ON ats.id = al.sessionid AND al.studentid = :uid
                 WHERE $where
              ORDER BY ats.sessdate ASC";

            $params = array(
                'uid'       => $userid,
                'aid'       => $this->id,
                'csdate'    => $this->course->startdate,
                'sdate'     => $this->pageparams->startdate,
                'edate'     => $this->pageparams->enddate);
        }
        $sessions = $DB->get_records_sql($sql, $params);

        return $sessions;
    }

    public function get_user_filtered_sessions_log_extended($userid) {
        global $DB;
        // All taked sessions (including previous groups).

        if ($this->pageparams->startdate && $this->pageparams->enddate) {
            $where = "ats.attendanceid = :aid AND ats.sessdate >= :csdate AND
                      ats.sessdate >= :sdate AND ats.sessdate < :edate";
        } else {
            $where = "ats.attendanceid = :aid AND ats.sessdate >= :csdate";
        }

        // We need to add this concatination so that moodle will use it as the array index that is a string.
        // If the array's index is a number it will not merge entries.
        // It would be better as a UNION query butunfortunatly MS SQL does not seem to support doing a DISTINCT on a the description field.
        $id = $DB->sql_concat(':value', 'ats.id');
        if ($this->get_group_mode()) {
            $sql = "SELECT $id, ats.id, ats.groupid, ats.sessdate, ats.duration, ats.description, al.statusid, al.remarks, ats.studentscanmark
                  FROM {attendance_sessions} ats
            RIGHT JOIN {attendance_log} al
                    ON ats.id = al.sessionid AND al.studentid = :uid
                    LEFT JOIN {groups_members} gm ON gm.userid = al.studentid AND gm.groupid = ats.groupid
                 WHERE $where AND (ats.groupid = 0 or gm.id is NOT NULL)
              ORDER BY ats.sessdate ASC";
        } else {
            $sql = "SELECT $id, ats.id, ats.groupid, ats.sessdate, ats.duration, ats.description, al.statusid, al.remarks, ats.studentscanmark
                  FROM {attendance_sessions} ats
            RIGHT JOIN {attendance_log} al
                    ON ats.id = al.sessionid AND al.studentid = :uid
                 WHERE $where
              ORDER BY ats.sessdate ASC";
        }

        $params = array(
                'uid'       => $userid,
                'aid'       => $this->id,
                'csdate'    => $this->course->startdate,
                'sdate'     => $this->pageparams->startdate,
                'edate'     => $this->pageparams->enddate,
                'value'     => 'c');
        $sessions = $DB->get_records_sql($sql, $params);

        // All sessions for current groups.

        $groups = array_keys(groups_get_all_groups($this->course->id, $userid));
        $groups[] = 0;
        list($gsql, $gparams) = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'gid0');

        if ($this->pageparams->startdate && $this->pageparams->enddate) {
            $where = "ats.attendanceid = :aid AND ats.sessdate >= :csdate AND
                      ats.sessdate >= :sdate AND ats.sessdate < :edate AND ats.groupid $gsql";
        } else {
            $where = "ats.attendanceid = :aid AND ats.sessdate >= :csdate AND ats.groupid $gsql";
        }

        $sql = "SELECT $id, ats.id, ats.groupid, ats.sessdate, ats.duration, ats.description, al.statusid, al.remarks, ats.studentscanmark
                  FROM {attendance_sessions} ats
             LEFT JOIN {attendance_log} al
                    ON ats.id = al.sessionid AND al.studentid = :uid
                 WHERE $where
              ORDER BY ats.sessdate ASC";

        $params = array_merge($params, $gparams);
        $sessions = array_merge($sessions, $DB->get_records_sql($sql, $params));

        foreach ($sessions as $sess) {
            if (empty($sess->description)) {
                $sess->description = get_string('nodescription', 'attendance');
            } else {
                $sess->description = file_rewrite_pluginfile_urls($sess->description,
                        'pluginfile.php', $this->context->id, 'mod_attendance', 'session', $sess->id);
            }
        }

        return $sessions;
    }

    public function delete_sessions($sessionsids) {
        global $DB;

        list($sql, $params) = $DB->get_in_or_equal($sessionsids);
        $DB->delete_records_select('attendance_log', "sessionid $sql", $params);
        $DB->delete_records_list('attendance_sessions', 'id', $sessionsids);
        $event = \mod_attendance\event\session_deleted::create(array(
            'objectid' => $this->id,
            'context' => $this->context,
            'other' => array('info' => implode(', ', $sessionsids))));
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->trigger();
    }

    public function update_sessions_duration($sessionsids, $duration) {
        global $DB;

        $now = time();
        $sessions = $DB->get_recordset_list('attendance_sessions', 'id', $sessionsids);
        foreach ($sessions as $sess) {
            $sess->duration = $duration;
            $sess->timemodified = $now;
            $DB->update_record('attendance_sessions', $sess);
            $event = \mod_attendance\event\session_duration_updated::create(array(
                'objectid' => $this->id,
                'context' => $this->context,
                'other' => array('info' => implode(', ', $sessionsids))));
            $event->add_record_snapshot('course_modules', $this->cm);
            $event->add_record_snapshot('attendance_sessions', $sess);
            $event->trigger();
        }
        $sessions->close();
    }

    /**
     * Remove a status variable from an attendance instance
     * 
     * @global moodle_database $DB
     * @param stdClass $status
     */
    public function remove_status($status) {
        global $DB;

        $DB->set_field('attendance_statuses', 'deleted', 1, array('id' => $status->id));
        $event = \mod_attendance\event\status_removed::create(array(
            'objectid' => $status->id,
            'context' => $this->context, 
            'other' => array(
                'acronym' => $status->acronym,
                'description' => $status->description
            )));
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->add_record_snapshot('attendance_statuses', $status);
        $event->trigger();
    }

    /**
     * Add an attendance status variable
     * 
     * @global moodle_database $DB
     * @param string $acronym
     * @param string $description
     * @param int $grade
     */
    public function add_status($acronym, $description, $grade) {
        global $DB;

        if ($acronym && $description) {
            $rec = new stdClass();
            $rec->courseid = $this->course->id;
            $rec->attendanceid = $this->id;
            $rec->acronym = $acronym;
            $rec->description = $description;
            $rec->grade = $grade;
            $rec->deleted = 0;
            $rec->visible = 1;
            $id = $DB->insert_record('attendance_statuses', $rec);
            $rec->id = $id;

            $event = \mod_attendance\event\status_added::create(array(
                'objectid' => $this->id,
                'context' => $this->context,
                'other' => array('acronym' => $acronym, 'description' => $description, 'grade' => $grade)));
            $event->add_record_snapshot('course_modules', $this->cm);
            $event->add_record_snapshot('attendance_statuses', $rec);
            $event->trigger();
        } else {
            print_error('cantaddstatus', 'attendance', $this->url_preferences());
        }
    }

    /**
     * Update status variable for a particular Attendance module instance
     * 
     * @global moodle_database $DB
     * @param stdClass $status
     * @param string $acronym
     * @param string $description
     * @param int $grade
     * @param bool $visible
     */
    public function update_status($status, $acronym, $description, $grade, $visible) {
        global $DB;

        $updated = array();

        if ($acronym) {
            $status->acronym = $acronym;
            $updated[] = $acronym;
        }
        if ($description) {
            $status->description = $description;
            $updated[] = $description;
        }
        if (isset($grade)) {
            $status->grade = $grade;
            $updated[] = $grade;
        }
        if (isset($visible)) {
            $status->visible = $visible;
            $updated[] = $visible ? get_string('show') : get_string('hide');
        }
        $DB->update_record('attendance_statuses', $status);

        $event = \mod_attendance\event\status_updated::create(array(
            'objectid' => $this->id,
            'context' => $this->context,
            'other' => array('acronym' => $acronym, 'description' => $description, 'grade' => $grade, 'updated' => implode(' ', $updated))));
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->add_record_snapshot('attendance_statuses', $status);
        $event->trigger();
    }

}

function att_get_statuses($attid, $onlyvisible=true) {
    global $DB;

    if ($onlyvisible) {
        $statuses = $DB->get_records_select('attendance_statuses', "attendanceid = :aid AND visible = 1 AND deleted = 0",
                                            array('aid' => $attid), 'grade DESC');
    } else {
        $statuses = $DB->get_records_select('attendance_statuses', "attendanceid = :aid AND deleted = 0",
                                            array('aid' => $attid), 'grade DESC');
    }

    return $statuses;
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

function att_calc_user_grade_fraction($grade, $maxgrade) {
    if ($maxgrade == 0) {
        return 0;
    } else {
        return $grade / $maxgrade;
    }
}

function att_get_gradebook_maxgrade($attid) {
    global $DB;

    return $DB->get_field('attendance', 'grade', array('id' => $attid));
}

function att_update_all_users_grades($attid, $course, $context, $coursemodule) {
    $grades = array();

    $userids = array_keys(get_enrolled_users($context, 'mod/attendance:canbelisted', 0, 'u.id'));

    $statuses = att_get_statuses($attid);
    $gradebook_maxgrade = att_get_gradebook_maxgrade($attid);
    foreach ($userids as $userid) {
        $grade = new stdClass;
        $grade->userid = $userid;
        $userstatusesstat = att_get_user_statuses_stat($attid, $course->startdate, $userid, $coursemodule);
        $usertakensesscount = att_get_user_taken_sessions_count($attid, $course->startdate, $userid, $coursemodule);
        $usergrade = att_get_user_grade($userstatusesstat, $statuses);
        $usermaxgrade = att_get_user_max_grade($usertakensesscount, $statuses);
        $grade->rawgrade = att_calc_user_grade_fraction($usergrade, $usermaxgrade) * $gradebook_maxgrade;
        $grades[$userid] = $grade;
    }

    return grade_update('mod/attendance', $course->id, 'mod', 'attendance',
                        $attid, 0, $grades);
}

function att_has_logs_for_status($statusid) {
    global $DB;

    return $DB->count_records('attendance_log', array('statusid'=> $statusid)) > 0;
}

function att_log_convert_url(moodle_url $fullurl) {
    static $baseurl;

    if (!isset($baseurl)) {
        $baseurl = new moodle_url('/mod/attendance/');
        $baseurl = $baseurl->out();
    }

    return substr($fullurl->out(), strlen($baseurl));
}

function attforblock_upgrade() {
    global $DB, $CFG;
    $module = $DB->get_record('modules', array('name' => 'attforblock'));

    // Deal with Moodle versions above 2013092001.02, where version is in config
    $versioninmodtable = true;
    if (!isset($module->version)) {
        $versioninmodtable = false;
        $module->version = get_config('mod_attforblock', 'version');
    }

    if ($module->version <= '2011061800') {
        print_error("noupgradefromthisversion", 'attendance');
    }
    if (file_exists($CFG->dirroot.'/mod/attforblock')) {
        print_error("attforblockdirstillexists", 'attendance');
    }

    // Now rename attforblock table and replace with attendance?
    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.
    $table = new xmldb_table('attforblock');
    $newtable = new xmldb_table('attendance'); // Sanity check to make sure 'attendance' table doesn't already exist.
    if ($dbman->table_exists($table) && !$dbman->table_exists($newtable)) {
        $dbman->rename_table($table, 'attendance');
    } else {
        print_error("tablerenamefailed", 'attendance');
    }
    // Now convert module record.
    $module->name = 'attendance';
    if (!$versioninmodtable) {
        set_config('version', $module->version, 'mod_attendance');
        unset($module->version);
    }
    $DB->update_record('modules', $module);

    // Now convert grade items to 'attendance'
    $sql = "UPDATE {grade_items}
            SET itemmodule = ?
            WHERE itemmodule = ?";
    $DB->execute($sql, array('attendance', 'attforblock'));

    $sql = "UPDATE {grade_items_history}
               SET itemmodule = 'attendance'
             WHERE itemmodule = 'attforblock'";
    $DB->execute($sql);

    /*
     * The user's custom capabilities need to be preserved due to the module renaming.
     * Capabilities with a modifierid = 0 value are installed by default.
     * Only update the user's custom capabilities where modifierid is not zero.
    */
    $sql = $DB->sql_like('capability', '?').' AND modifierid <> 0';
    $rs = $DB->get_recordset_select('role_capabilities', $sql, array('%mod/attforblock%'));
    foreach ($rs as $cap) {
        $renamedcapability = str_replace('mod/attforblock', 'mod/attendance', $cap->capability);
        $exists = $DB->record_exists('role_capabilities', array('roleid' => $cap->roleid, 'capability' => $renamedcapability));
        if (!$exists) {
            $DB->update_record('role_capabilities', array('id' => $cap->id, 'capability' => $renamedcapability));
        }
    }

    // Delete old role capabilities.
    $sql = $DB->sql_like('capability', '?');
    $DB->delete_records_select('role_capabilities', $sql, array('%mod/attforblock%'));

    // Delete old capabilities.
    $DB->delete_records_select('capabilities', 'component = ?', array('mod_attforblock'));

    // Clear cache for courses with attendances.
    $attendances = $DB->get_recordset('attendance', array(), '', 'course');
    foreach ($attendances as $attendance) {
        rebuild_course_cache($attendance->course, true);
    }
    $attendances->close();
}
