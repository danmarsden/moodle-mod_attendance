<?php

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');

define('VIEW_DAYS', 1);
define('VIEW_WEEKS', 2);
define('VIEW_MONTHS', 3);
define('VIEW_ALLTAKEN', 4);
define('VIEW_ALL', 5);

class attforblock_permissions {
    private $canview;
    private $canviewreports;
    private $cantake;
    private $canchange;
    private $canmanage;
    private $canchangepreferences;
    private $canexport;
    private $canbelisted;
    private $canaccessallgroups;

    private $context;

    public function __construct($context) {
        $this->context = $context;
    }

    public function can_view() {
        if (is_null($this->canview))
            $this->canview = has_capability('mod/attforblock:view', $this->context);

        return $this->canview;
    }

    public function require_view_capability() {
        require_capability('mod/attforblock:view', $this->context);
    }

    public function can_view_reports() {
        if (is_null($this->canviewreports))
            $this->canviewreports = has_capability('mod/attforblock:viewreports', $this->context);

        return $this->canviewreports;
    }

    public function can_take() {
        if (is_null($this->cantake))
            $this->cantake = has_capability('mod/attforblock:takeattendances', $this->context);

        return $this->cantake;
    }

    public function can_change() {
        if (is_null($this->canchange))
            $this->canchange = has_capability('mod/attforblock:changeattendances', $this->context);

        return $this->canchange;
    }

    public function can_manage() {
        if (is_null($this->canmanage))
            $this->canmanage = has_capability('mod/attforblock:manageattendances', $this->context);

        return $this->canmanage;
    }

    public function require_manage_capability() {
        require_capability('mod/attforblock:manageattendances', $this->context);
    }

    public function can_change_preferences() {
        if (is_null($this->canchangepreferences))
            $this->canchangepreferences = has_capability('mod/attforblock:changepreferences', $this->context);

        return $this->canchangepreferences;
    }

    public function can_export() {
        if (is_null($this->canexport))
            $this->canexport = has_capability('mod/attforblock:export', $this->context);

        return $this->canexport;
    }

    public function can_be_listed() {
        if (is_null($this->canbelisted))
            $this->canbelisted = has_capability('mod/attforblock:canbelisted', $this->context);

        return $this->canbelisted;
    }

    public function can_access_all_groups() {
        if (is_null($this->canaccessallgroups))
            $this->canaccessallgroups = has_capability('moodle/site:accessallgroups', $this->context);

        return $this->canaccessallgroups;
    }
}

class att_page_with_filter_controls {
    const SELECTOR_NONE         = 1;
    const SELECTOR_GROUP        = 2;
    const SELECTOR_SESS_TYPE    = 3;

    /** @var int current view mode */
    public $view;

    /** @var int $view and $curdate specify displaed date range */
    public $curdate;

    /** @var int start date of displayed date range */
    public $startdate;

    /** @var int end date of displayed date range */
    public $enddate;

    public $selectortype        = self::SELECTOR_NONE;

    protected $defaultview      = VIEW_WEEKS;

    private $courseid;

    public function init($courseid) {
        $this->courseid = $courseid;
        $this->init_view();
        $this->init_curdate();
        $this->init_start_end_date();
    }

    private function init_view() {
        global $SESSION;

        if (isset($this->view)) {
            $SESSION->attcurrentattview[$this->courseid] = $this->view;
        }
        elseif (isset($SESSION->attcurrentattview[$this->courseid])) {
            $this->view = $SESSION->attcurrentattview[$this->courseid];
        }
        else {
            $this->view = $this->defaultview;
        }
    }

    private function init_curdate() {
        global $SESSION;

        if (isset($this->curdate)) {
            $SESSION->attcurrentattdate[$this->courseid] = $this->curdate;
        }
        elseif (isset($SESSION->attcurrentattdate[$this->courseid])) {
            $this->curdate = $SESSION->attcurrentattdate[$this->courseid];
        }
        else {
            $this->curdate = time();
        }
    }

    private function init_start_end_date() {
        $date = usergetdate($this->curdate);
        $mday = $date['mday'];
        $wday = $date['wday'];
        $mon = $date['mon'];
        $year = $date['year'];

        switch ($this->view) {
            case VIEW_DAYS:
                $this->startdate = make_timestamp($year, $mon, $mday);
                $this->enddate = make_timestamp($year, $mon, $mday + 1);
                break;
            case VIEW_WEEKS:
                $this->startdate = make_timestamp($year, $mon, $mday - $wday + 1);
                $this->enddate = make_timestamp($year, $mon, $mday + 7 - $wday + 1) - 1;
                break;
            case VIEW_MONTHS:
                $this->startdate = make_timestamp($year, $mon);
                $this->enddate = make_timestamp($year, $mon + 1);
                break;
            case VIEW_ALLTAKEN:
                $this->startdate = 1;
                $this->enddate = time();
                break;
            case VIEW_ALL:
                $this->startdate = 0;
                $this->enddate = 0;
                break;
        }
    }
}

class att_view_page_params extends att_page_with_filter_controls {
    const MODE_THIS_COURSE  = 0;
    const MODE_ALL_COURSES  = 1;

    public $student;

    public $mode;

    public function  __construct() {
        $this->defaultview = VIEW_MONTHS;
    }

    public function get_significant_params() {
        $params = array();

        if (isset($this->student)) $params['student'] = $this->student;
        if ($this->mode != self::MODE_THIS_COURSE) $params['mode'] = $this->mode;

        return $params;
    }
}

class att_manage_page_params extends att_page_with_filter_controls {
    public function  __construct() {
        $this->selectortype = self::SELECTOR_SESS_TYPE;
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

    const SORT_LASTNAME         = 1;
    const SORT_FIRSTNAME        = 2;

	public $sessionid;
    public $grouptype;
    public $group;
	public $sort;
    public $copyfrom;
    
    /** @var int view mode of taking attendance page*/
    public $viewmode;

    public $gridcols;

    public function init() {
        if (!isset($this->group)) $this->group = 0;
        if (!isset($this->sort)) $this->sort = self::SORT_LASTNAME;
        $this->init_view_mode();
        $this->init_gridcols();
    }

    private function init_view_mode() {
        if (isset($this->viewmode)) {
            set_user_preference("attforblock_take_view_mode", $this->viewmode);
        }
        else {
            $this->viewmode = get_user_preferences("attforblock_take_view_mode", self::DEFAULT_VIEW_MODE);
        }
    }

    private function init_gridcols() {
        if (isset($this->gridcols)) {
            set_user_preference("attforblock_gridcolumns", $this->gridcols);
        }
        else {
            $this->gridcols = get_user_preferences("attforblock_gridcolumns", 5);
        }
    }

    public function get_significant_params() {
        $params = array();

        $params['sessionid'] = $this->sessionid;
        $params['grouptype'] = $this->grouptype;
        if ($this->group) $params['group'] = $this->group;
        if ($this->sort != self::SORT_LASTNAME) $params['sort'] = $this->sort;
        if (isset($this->copyfrom)) $params['copyfrom'] = $this->copyfrom;

        return $params;
    }
}

class attforblock {
    const SESSION_COMMON        = 0;
    const SESSION_GROUP         = 1;

    const SELECTOR_COMMON       = 0;
    const SELECTOR_ALL          = -1;
    const SELECTOR_NOT_EXISTS   = -2;

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

    /** @var attforblock_permissions permission of current user for attendance instance*/
    public $perm;

    private $groupmode;

    private $sessgroupslist;

    private $currentgroup;

    private $sessioninfo;

    private $statuses;
    private $usertakensesscount;
    private $userstatusesstat;

    /**
     * Initializes the attendance API instance using the data from DB
     *
     * Makes deep copy of all passed records properties. Replaces integer $course attribute
     * with a full database record (course should not be stored in instances table anyway).
     *
     * @param stdClass $dbrecord Attandance instance data from {attforblock} table
     * @param stdClass $cm       Course module record as returned by {@link get_coursemodule_from_id()}
     * @param stdClass $course   Course record from {course} table
     * @param stdClass $context  The context of the workshop instance
     */
    public function __construct(stdclass $dbrecord, stdclass $cm, stdclass $course, stdclass $context=NULL, $view_params=NULL) {
        foreach ($dbrecord as $field => $value) {
            if (property_exists('attforblock', $field)) {
                $this->{$field} = $value;
            }
            else {
                throw new coding_exception('The attendance table has field for which there is no property in the attforblock class');
            }
        }
        $this->cm           = $cm;
        $this->course       = $course;
        if (is_null($context)) {
            $this->context = get_context_instance(CONTEXT_MODULE, $this->cm->id);
        } else {
            $this->context = $context;
        }

        $this->pageparams = $view_params;

        $this->perm = new attforblock_permissions($this->context);
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

		$today = time(); // because we compare with database, we don't need to use usertime()
        
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

    public function get_filtered_sessions() {
        global $DB;

        if ($this->pageparams->startdate && $this->pageparams->enddate) {
            $where = "attendanceid = :aid AND sessdate >= :csdate AND sessdate >= :sdate AND sessdate < :edate";
        } else {
            $where = "attendanceid = :aid AND sessdate >= :csdate";
        }
        if ($this->get_current_group() > attforblock::SELECTOR_ALL) {
            $where .= " AND groupid=:cgroup";
        }
        $params = array(
                'aid'       => $this->id,
                'csdate'    => $this->course->startdate,
                'sdate'     => $this->pageparams->startdate,
                'edate'     => $this->pageparams->enddate,
                'cgroup'    => $this->get_current_group());
        $sessions = $DB->get_records_select('attendance_sessions', $where, $params, 'sessdate asc');
        foreach ($sessions as $sess) {
            $sess->description = file_rewrite_pluginfile_urls($sess->description, 'pluginfile.php', $this->context->id, 'mod_attforblock', 'session', $sess->id);
        }

        return $sessions;
    }

    /**
     * @return moodle_url of manage.php for attendance instance
     */
    public function url_manage() {
        $params = array('id' => $this->cm->id);
        return new moodle_url('/mod/attforblock/manage.php', $params);
    }

    /**
     * @return moodle_url of sessions.php for attendance instance
     */
    public function url_sessions($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attforblock/sessions.php', $params);
    }

    /**
     * @return moodle_url of report.php for attendance instance
     */
    public function url_report() {
        $params = array('id' => $this->cm->id);
        return new moodle_url('/mod/attforblock/report.php', $params);
    }

    /**
     * @return moodle_url of export.php for attendance instance
     */
    public function url_export() {
        $params = array('id' => $this->cm->id);
        return new moodle_url('/mod/attforblock/export.php', $params);
    }

    /**
     * @return moodle_url of attsettings.php for attendance instance
     */
    public function url_settings() {
        $params = array('id' => $this->cm->id);
        return new moodle_url('/mod/attforblock/attsettings.php', $params);
    }

    /**
     * @return moodle_url of attendances.php for attendance instance
     */
    public function url_take() {
        $params = array('id' => $this->cm->id);
        return new moodle_url('/mod/attforblock/take.php', $params);
    }

    public function url_view($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attforblock/view.php', $params);
    }

    private function calc_groupmode_sessgroupslist_currentgroup(){
        global $USER, $SESSION;

        $cm = $this->cm;

        $this->get_group_mode();

        if ($this->groupmode == NOGROUPS)
            return;

        if ($this->groupmode == VISIBLEGROUPS or $this->perm->can_access_all_groups()) {
            $allowedgroups = groups_get_all_groups($cm->course, 0, $cm->groupingid); // any group in grouping (all if groupings not used)
            // detect changes related to groups and fix active group
            if (!empty($SESSION->activegroup[$cm->course][VISIBLEGROUPS][$cm->groupingid])) {
                if (!array_key_exists($SESSION->activegroup[$cm->course][VISIBLEGROUPS][$cm->groupingid], $allowedgroups)) {
                    // active group does not exist anymore
                    unset($SESSION->activegroup[$cm->course][VISIBLEGROUPS][$cm->groupingid]);
                }
            }
            if (!empty($SESSION->activegroup[$cm->course]['aag'][$cm->groupingid])) {
                if (!array_key_exists($SESSION->activegroup[$cm->course]['aag'][$cm->groupingid], $allowedgroups)) {
                    // active group does not exist anymore
                    unset($SESSION->activegroup[$cm->course]['aag'][$cm->groupingid]);
                }
            }

        } else {
            $allowedgroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid); // only assigned groups
            // detect changes related to groups and fix active group
            if (isset($SESSION->activegroup[$cm->course][SEPARATEGROUPS][$cm->groupingid])) {
                if ($SESSION->activegroup[$cm->course][SEPARATEGROUPS][$cm->groupingid] == 0) {
                    if ($allowedgroups) {
                        // somebody must have assigned at least one group, we can select it now - yay!
                        unset($SESSION->activegroup[$cm->course][SEPARATEGROUPS][$cm->groupingid]);
                    }
                } else {
                    if (!array_key_exists($SESSION->activegroup[$cm->course][SEPARATEGROUPS][$cm->groupingid], $allowedgroups)) {
                        // active group not allowed or does not exist anymore
                        unset($SESSION->activegroup[$cm->course][SEPARATEGROUPS][$cm->groupingid]);
                    }
                }
            }
        }

        $group = optional_param('group', self::SELECTOR_NOT_EXISTS, PARAM_INT);
        if (!array_key_exists('attsessiontype', $SESSION)) {
            $SESSION->attsessiontype = array();
        }
        if ($group > self::SELECTOR_NOT_EXISTS) {
            $SESSION->attsessiontype[$cm->course] = $group;
        } elseif (!array_key_exists($cm->course, $SESSION->attsessiontype)) {
            $SESSION->attsessiontype[$cm->course] = self::SELECTOR_ALL;
        }

        if ($group == self::SELECTOR_ALL) {
            $this->currentgroup = $group;
            unset($SESSION->activegroup[$cm->course][VISIBLEGROUPS][$cm->groupingid]);
            unset($SESSION->activegroup[$cm->course]['aag'][$cm->groupingid]);
            unset($SESSION->activegroup[$cm->course][SEPARATEGROUPS][$cm->groupingid]);
        } else {
            $this->currentgroup = groups_get_activity_group($cm, true);
            if ($this->currentgroup == 0 and $SESSION->attsessiontype[$cm->course] == self::SELECTOR_ALL) {
                $this->currentgroup = self::SELECTOR_ALL;
            }
        }

        $this->sessgroupslist = array();
        if ($allowedgroups or $this->groupmode == VISIBLEGROUPS or $this->perm->can_access_all_groups()) {
            $this->sessgroupslist[self::SELECTOR_ALL] = get_string('all', 'attforblock');
        }
        if ($this->groupmode == VISIBLEGROUPS) {
            $this->sessgroupslist[self::SELECTOR_COMMON] = get_string('commonsessions', 'attforblock');
        }        
        if ($allowedgroups) {
            foreach ($allowedgroups as $group) {
                $this->sessgroupslist[$group->id] = format_string($group->name);
            }
        }
    }

    public function get_group_mode() {
        if (is_null($this->groupmode))
            $this->groupmode = groups_get_activity_groupmode($this->cm);

        return $this->groupmode;
    }

    public function get_sess_groups_list() {
        if (is_null($this->sessgroupslist))
            $this->calc_groupmode_sessgroupslist_currentgroup();

        return $this->sessgroupslist;
    }

    public function get_current_group() {
        if (is_null($this->currentgroup))
            $this->calc_groupmode_sessgroupslist_currentgroup();

        return $this->currentgroup;
    }

    public function add_session_from_form_data($formdata) {
        global $DB;
        
        $duration = $formdata->durtime['hours']*HOURSECS + $formdata->durtime['minutes']*MINSECS;

        $rec->courseid = $this->course->id;
        $rec->attendanceid = $this->id;
        $rec->sessdate = $formdata->sessiondate;
        $rec->duration = $duration;
        $rec->description = $formdata->sdescription['text'];
        $rec->descriptionformat = $formdata->sdescription['format'];
        $rec->timemodified = time();
        
        if ($formdata->sessiontype == self::SESSION_COMMON) {
            $rec->id = $DB->insert_record('attendance_sessions', $rec);
            $description = file_save_draft_area_files($formdata->sdescription['itemid'],
                        $this->context->id, 'mod_attforblock', 'session', $rec->id,
                        array('subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0), $formdata->sdescription['text']);
            $DB->set_field('attendance_sessions', 'description', $description, array('id' => $rec->id));
        } else {
            foreach ($formdata->groups as $groupid) {
                $rec->groupid = $groupid;
                $rec->id = $DB->insert_record('attendance_sessions', $rec);
                $description = file_save_draft_area_files($formdata->sdescription['itemid'],
                            $this->context->id, 'mod_attforblock', 'session', $rec->id,
                            array('subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0), $formdata->sdescription['text']);
                $DB->set_field('attendance_sessions', 'description', $description, array('id' => $rec->id));
            }
        }
        // TODO: log
        //add_to_log($course->id, 'attendance', 'one session added', 'mod/attforblock/manage.php?id='.$id, $user->lastname.' '.$user->firstname);
    }

    public function update_session_from_form_data($formdata, $sessionid) {
        global $DB;

        if (!$sess = $DB->get_record('attendance_sessions', array('id' => $sessionid) )) {
            print_error('No such session in this course');
        }

        $sess->sessdate = $formdata->sessiondate;
        $sess->duration = $formdata->durtime['hours']*HOURSECS + $formdata->durtime['minutes']*MINSECS;
        $description = file_save_draft_area_files($formdata->sdescription['itemid'],
                                $this->context->id, 'mod_attforblock', 'session', $sessionid,
                                array('subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0), $formdata->sdescription['text']);
        $sess->description = $description;
        $sess->descriptionformat = $formdata->sdescription['format'];
        $sess->timemodified = time();
        $DB->update_record('attendance_sessions', $sess);
        // TODO: log
        // add_to_log($course->id, 'attendance', 'Session updated', 'mod/attforblock/manage.php?id='.$id, $user->lastname.' '.$user->firstname);
    }
    
    public function take_from_form_data($formdata) {
        global $DB, $USER;

        $statuses = implode(',', array_keys( (array)$this->get_statuses() ));
        $now = time();
        $sesslog = array();
        $formdata = (array)$formdata;
		foreach($formdata as $key => $value) {
			if(substr($key, 0, 4) == 'user' && $value !== '') {
				$sid = substr($key, 4);
				$sesslog[$sid] = new Object();
				$sesslog[$sid]->studentid = $sid;
				$sesslog[$sid]->statusid = $value;
				$sesslog[$sid]->statusset = $statuses;
				$sesslog[$sid]->remarks = array_key_exists('remarks'.$sid, $formdata) ? $formdata['remarks'.$sid] : '';
				$sesslog[$sid]->sessionid = $this->pageparams->sessionid;
				$sesslog[$sid]->timetaken = $now;
				$sesslog[$sid]->takenby = $USER->id;
			}
		}

        $dbsesslog = $this->get_session_log($this->pageparams->sessionid);
        foreach ($sesslog as $log) {
            if (array_key_exists($log->studentid, $dbsesslog)) {
                $log->id = $dbsesslog[$log->studentid]->id;
                $DB->update_record('attendance_log', $log);
            }
            else
                $DB->insert_record('attendance_log', $log, false);
        }

        $rec = new object();
        $rec->id = $this->pageparams->sessionid;
        $rec->lasttaken = $now;
        $rec->lasttakenby = $USER->id;
        $DB->update_record('attendance_sessions', $rec);

        // TODO: update_grades
        // TODO: log
        redirect($this->url_manage(), get_string('attendancesuccess','attforblock'));
    }

    /**
     * MDL-27591 made this method obsolete.
     */
    public function get_users($groupid = 0) {
        global $DB;

        //fields we need from the user table
        $userfields = user_picture::fields('u');

        if (isset($this->pageparams->sort) and ($this->pageparams->sort == att_take_page_params::SORT_FIRSTNAME)) {
            $orderby = "u.firstname ASC, u.lastname ASC";
        }
        else {
            $orderby = "u.lastname ASC, u.firstname ASC";
        }

        $users = get_enrolled_users($this->context, 'mod/attforblock:canbelisted', $groupid, $userfields, $orderby);

        //add a flag to each user indicating whether their enrolment is active
        if (!empty($users)) {
            list($usql, $uparams) = $DB->get_in_or_equal(array_keys($users), SQL_PARAMS_NAMED, 'usid0');

            $sql = "SELECT ue.userid, ue.status, ue.timestart, ue.timeend
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE ue.userid $usql
                           AND e.status = :estatus
                           AND e.courseid = :courseid
                  GROUP BY ue.userid";
            $params = array_merge($uparams, array('estatus'=>ENROL_INSTANCE_ENABLED, 'courseid'=>$this->course->id));
            $enrolmentsparams = $DB->get_records_sql($sql, $params);

            foreach ($users as $user) {
                $users[$user->id]->enrolmentstatus = $enrolmentsparams[$user->id]->status;
                $users[$user->id]->enrolmentstart = $enrolmentsparams[$user->id]->timestart;
                $users[$user->id]->enrolmentend = $enrolmentsparams[$user->id]->timeend;
            }
        }

        return $users;
    }

    public function get_user($userid) {
        global $DB;

        $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

        $sql = "SELECT ue.userid, ue.status, ue.timestart, ue.timeend
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :uid
                       AND e.status = :estatus
                       AND e.courseid = :courseid
              GROUP BY ue.userid";
        $params = array('uid' => $userid, 'estatus'=>ENROL_INSTANCE_ENABLED, 'courseid'=>$this->course->id);
        $enrolmentsparams = $DB->get_record_sql($sql, $params);

        $user->enrolmentstatus = $enrolmentsparams->status;
        $user->enrolmentstart = $enrolmentsparams->timestart;
        $user->enrolmentend = $enrolmentsparams->timeend;

        return $user;
    }

    public function get_statuses($onlyvisible = true) {
        global $DB;

        if (!isset($this->statuses)) {
            if ($onlyvisible) {
                $this->statuses = $DB->get_records_select('attendance_statuses', "attendanceid = :aid AND visible = 1 AND deleted = 0", array('aid' => $this->id), 'grade DESC');
            } else {
                $this->statuses = $DB->get_records_select('attendance_statuses', "attendanceid = :aid AND deleted = 0",  array('aid' => $this->id), 'grade DESC');
            }
        }
        
        return $this->statuses;
    }

    public function get_session_info($sessionid) {
        global $DB;

        if (!isset($this->sessioninfo))
            $this->sessioninfo = $DB->get_record('attendance_sessions', array('id' => $sessionid));
            $this->sessioninfo->description = file_rewrite_pluginfile_urls($this->sessioninfo->description,
                        'pluginfile.php', $this->context->id, 'mod_attforblock', 'session', $this->sessioninfo->id);

        return $this->sessioninfo;
    }

    public function get_session_log($sessionid) {
        global $DB;

        return $DB->get_records('attendance_log', array('sessionid' => $sessionid), '', 'studentid,statusid,remarks,id');
    }

    public function get_user_stat($userid) {
        global $DB;

        $ret = array();
        $ret['completed'] = $this->get_user_taken_sessions_count($userid);
        $ret['statuses'] = $this->get_user_statuses_stat($userid);

        return $ret;
    }

    public function get_user_taken_sessions_count($userid, $courseid=NULL) {
        global $DB;

        if (!isset($this->usertakensesscount)) {
            $qry = "SELECT count(*) as cnt
                      FROM {attendance_log} al
                      JOIN {attendance_sessions} ats
                        ON al.sessionid = ats.id
                     WHERE ats.attendanceid = :aid AND
                           ats.sessdate >= :cstartdate AND
                           al.studentid = :uid";
            $params = array(
                    'aid'           => $this->id,
                    'cstartdate'    => $this->course->startdate,
                    'uid'           => $userid);

            $this->usertakensesscount = $DB->count_records_sql($qry, $params);
        }

        return $this->usertakensesscount;
    }

    public function get_user_statuses_stat($userid) {
        global $DB;

        if (!isset($this->userstatusesstat)) {
            $qry = "SELECT al.statusid, count(al.statusid) AS stcnt
                      FROM {attendance_log} al
                      JOIN {attendance_sessions} ats
                        ON al.sessionid = ats.id
                     WHERE ats.attendanceid = :aid AND
                           ats.sessdate >= :cstartdate AND
                           al.studentid = :uid
                  GROUP BY al.statusid";
            $params = array(
                    'aid'           => $this->id,
                    'cstartdate'    => $this->course->startdate,
                    'uid'           => $userid);

            $this->userstatusesstat = $DB->get_records_sql($qry, $params);
        }
        
        return $this->userstatusesstat;
    }

    public function get_user_grade($userid, $courseid=NULL) {
        $statistics = $this->get_user_statuses_stat($userid, $courseid);
        $statuses = $this->get_statuses($courseid);

        $sum = 0;
        foreach ($statistics as $stat) {
            $sum += $stat->stcnt * $statuses[$stat->statusid]->grade;
        }

        return $sum;
    }

    // For getting sessions count implemented simplest method - taken sessions.
    // It can have error if users don't have attendance info for some sessions.
    // In the future we can implement another methods:
    // * all sessions between user start enrolment date and now;
    // * all sessions between user start and end enrolment date.
    public function get_user_max_grade($userid, $courseid=NULL) {
        $takensessions = $this->get_user_taken_sessions_count($userid, $courseid);
        $statuses = $this->get_statuses($courseid);

        return current($statuses)->grade * $takensessions;
    }

    public function get_user_filtered_sessions_log($userid) {
        global $DB;

        if ($this->pageparams->startdate && $this->pageparams->enddate) {
            $where = "ats.attendanceid = :aid AND ats.sessdate >= :csdate AND 
                      ats.sessdate >= :sdate AND ats.sessdate < :edate";
        } else {
            $where = "AND ats.attendanceid = :aid AND ats.sessdate >= :csdate";
        }

        $sql = "SELECT ats.id, ats.sessdate, ats.duration, ats.description, al.statusid, al.remarks
                  FROM {attendance_sessions} ats
             LEFT JOIN {attendance_log} al
                    ON ats.id = al.sessionid AND al.studentid = :uid
                 WHERE $where
              ORDER BY ats.sessdate ASC";

        $params = array(
                'uid'       => $userid,
                'aid'       => $this->id,
                'csdate'    => $this->course->startdate,
                'sdate'     => $this->pageparams->startdate,
                'edate'     => $this->pageparams->enddate);
        $sessions = $DB->get_records_sql($sql, $params);
        foreach ($sessions as $sess) {
            $sess->description = file_rewrite_pluginfile_urls($sess->description, 'pluginfile.php', $this->context->id, 'mod_attforblock', 'session', $sess->id);
        }

        return $sessions;
    }

    public function get_user_courses_with_attendance($userid) {
        global $DB;

        // we can't get user courses based only on attendance_log information
        // because of then users will see only courses with taked attendance
        $usercourses = enrol_get_users_courses($userid);

        list($usql, $uparams) = $DB->get_in_or_equal(array_keys($usercourses), SQL_PARAMS_NAMED, 'cid0');

        $sql = "SELECT ats.courseid, course.fullname
                  FROM {attendance_sessions} ats
                  JOIN {attendance_log} al
                       ON ats.id = al.sessionid AND al.studentid = :uid
                  JOIN {course} course
                       ON ats.courseid = course.id
                 WHERE ats.courseid $usql
              GROUP BY ats.courseid
              ORDER BY course.fullname ASC";

        $params = array_merge($uparams, array('uid' => $userid));

        return $DB->get_records_sql($sql, $params);
    }
}


?>
