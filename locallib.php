<?php

global $CFG;
require_once($CFG->libdir . '/gradelib.php');

class attforblock_permissions {
    private $canview                = null;
    private $canviewreports         = null;
    private $cantake                = null;
    private $canchange              = null;
    private $canmanage              = null;
    private $canchangepreferences   = null;
    private $canexport              = null;
    private $canbelisted            = null;

    private $context;

    public function __construct($context) {
        $this->context = $context;
    }

    public function can_view() {
        if (is_null($this->canview))
            $this->canview = has_capability ('mod/attforblock:view', $this->context);

        return $this->canview;
    }

    public function can_viewreports() {
        if (is_null($this->canviewreports))
            $this->canviewreports = has_capability ('mod/attforblock:viewreports', $this->context);

        return $this->canviewreports;
    }

    public function can_take() {
        if (is_null($this->cantake))
            $this->cantake = has_capability ('mod/attforblock:takeattendances', $this->context);

        return $this->cantake;
    }

    public function can_change() {
        if (is_null($this->canchange))
            $this->canchange = has_capability ('mod/attforblock:changeattendances', $this->context);

        return $this->canchange;
    }

    public function can_manage() {
        if (is_null($this->canmanage))
            $this->canmanage = has_capability ('mod/attforblock:manageattendances', $this->context);

        return $this->canmanage;
    }

    public function can_change_preferences() {
        if (is_null($this->canchangepreferences))
            $this->canchangepreferences = has_capability ('mod/attforblock:changepreferences', $this->context);

        return $this->canchangepreferences;
    }

    public function can_export() {
        if (is_null($this->canexport))
            $this->canexport = has_capability ('mod/attforblock:export', $this->context);

        return $this->canexport;
    }

    public function can_be_listed() {
        if (is_null($this->canbelisted))
            $this->canbelisted = has_capability ('mod/attforblock:canbelisted', $this->context);

        return $this->canbelisted;
    }
}

class attforblock {
    const SESSION_COMMON        = 1;
    const SESSION_GROUP         = 2;

    const VIEW_DAYS             = 1;
    const VIEW_WEEKS            = 2;
    const VIEW_MONTHS           = 3;
    const VIEW_ALLTAKEN         = 4;
    const VIEW_ALL              = 5;

    const SELECTOR_NONE         = 1;
    const SELECTOR_GROUP        = 2;
    const SELECTOR_SESS_TYPE    = 3;

    const SORTED_LIST           = 1;
    const SORTED_GRID           = 2;

    const DEFAULT_VIEW          = attforblock::VIEW_WEEKS;
    const DEFAULT_CURDATE       = 0;
    const DEFAULT_VIEW_TAKE     = attforblock::SORTED_LIST;
    const DEFAULT_SHOWENDTIME   = 0;

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

    /** @var int current view mode */
    public $view = self::DEFAULT_VIEW;

    /** @var int $view and $curdate specify displaed date range */
    public $curdate = self::DEFAULT_CURDATE;

    /** @var int start date of displayed date range */
    public $startdate;

    /** @var int end date of displayed date range */
    public $enddate;

    /** @var int view mode of taking attendance page*/
    public $view_take = self::DEFAULT_VIEW_TAKE;

    /** @var int whether sessions end time will be displayed on manage.php */
    public $showendtime = self::DEFAULT_SHOWENDTIME;

    /** @var attforblock_permissions permission of current user for attendance instance*/
    public $perm;
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
    public function __construct(stdclass $dbrecord, stdclass $cm, stdclass $course, stdclass $context=null) {
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

        $this->perm = new attforblock_permissions($this->context);
    }

    public function init_view_params($view=NULL, $curdate=NULL, $view_take=NULL, $showendtime=NULL) {
        global $SESSION;

        if (isset($view)) {
            $SESSION->attcurrentattview[$this->course->id] = $view;
            $this->view = $view;
        }
        elseif (isset($SESSION->attcurrentattview[$this->course->id])) {
            $this->view = $SESSION->attcurrentattview[$this->course->id];
        }

        if ($curdate) {
            $SESSION->attcurrentattdate[$this->course->id] = $curdate;
            $this->curdate = $curdate;
        }
        elseif (isset($SESSION->attcurrentattdate[$this->course->id])) {
            $this->curdate = $SESSION->attcurrentattdate[$this->course->id];
        }
        else {
            $this->curdate = time();
        }

        if (isset($view_take)) {
            set_user_preference("attforblock_view_take", $view_take);
            $this->view_take = $view_take;
        }
        else {
            $this->view_take = get_user_preferences("attforblock_view_take", $this->view_take);
        }

        if (isset($showendtime)) {
            set_user_preference("attforblock_showendtime", $showendtime);
            $this->showendtime = $showendtime;
        }
        else {
            $this->showendtime = get_user_preferences("attforblock_showendtime", $this->showendtime);
        }

        $this->init_start_end_date();
    }

    private function init_start_end_date() {
        $date = usergetdate($this->curdate);
        $mday = $date['mday'];
        $wday = $date['wday'];
        $mon = $date['mon'];
        $year = $date['year'];

        switch ($this->view) {
            case self::VIEW_DAYS:
                $this->startdate = make_timestamp($year, $mon, $mday);
                $this->enddate = make_timestamp($year, $mon, $mday + 1);
                break;
            case self::VIEW_WEEKS:
                $this->startdate = make_timestamp($year, $mon, $mday - $wday + 1);
                $this->enddate = make_timestamp($year, $mon, $mday + 7 - $wday + 1) - 1;
                break;
            case self::VIEW_MONTHS:
                $this->startdate = make_timestamp($year, $mon);
                $this->enddate = make_timestamp($year, $mon + 1);
                break;
            case self::VIEW_ALLTAKEN:
                $this->startdate = 1;
                $this->enddate = time();
                break;
            case self::VIEW_ALL:
                $this->startdate = 0;
                $this->enddate = 0;
                break;
        }
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

		$today = time(); // because we compare with database, we don't need to use usertime()
        
        $sql = "SELECT id, groupid, lasttaken
                  FROM {attendance_sessions}
                 WHERE :time BETWEEN sessdate AND (sessdate + duration)
                   AND courseid = :cid AND attendanceid = :aid";
        $params = array(
                'time' => $today,
                'cid' => $this->course->id,
                'aid' => $this->id);

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

        $where = "courseid = :cid AND attendanceid = :aid AND sessdate < :csdate";
        $params = array(
                'cid'   => $this->course->id,
                'aid'   => $this->id,
                'csdate'=> $this->course->startdate);

        return $DB->count_records_select('attendance_sessions', $where, $params);
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
    public function url_sessions() {
        $params = array('id' => $this->cm->id);
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
        return new moodle_url('/mod/attforblock/attendances.php', $params);
    }
}


/**
 * Represents info about attendance tabs.
 *
 * Proxy class for security reasons (renderers must not have access to all attforblock methods)
 *
 */
class attforblock_tabs implements renderable {
    const TAB_SESSIONS  = 'sessions';
    const TAB_ADD       = 'add';
    const TAB_REPORT    = 'report';
    const TAB_EXPORT    = 'export';
    const TAB_SETTINGS  = 'settings';

    public $currenttab;

    /** @var attforblock */
    private $att;

    /**
     * Prepare info about sessions for attendance taking into account view parameters.
     *
     * @param attforblock $att instance
     * @param $currenttab - one of attforblock_tabs constants
     */
    public function  __construct(attforblock $att, $currenttab=self::TAB_SESSIONS) {
        $this->att = $att;
        $this->currenttab = $currenttab;
    }

    /**
     * Return array of rows where each row is an array of tab objects
     * taking into account permissions of current user
     */
    public function get_tabs() {
        $toprow = array();
        if ($this->att->perm->can_manage() or
                $this->att->perm->can_take() or
                $this->att->perm->can_change()) {
            $toprow[] = new tabobject(self::TAB_SESSIONS, $this->att->url_sessions()->out(),
                        get_string('sessions','attforblock'));
        }

        if ($this->att->perm->can_manage()) {
            $toprow[] = new tabobject(self::TAB_ADD, $this->att->url_sessions()->out(true, array('action' => 'add')),
                        get_string('add','attforblock'));
        }

        if ($this->att->perm->can_viewreports()) {
            $toprow[] = new tabobject(self::TAB_REPORT, $this->att->url_report()->out(),
                        get_string('report','attforblock'));
        }

        if ($this->att->perm->can_export()) {
            $toprow[] = new tabobject(self::TAB_EXPORT, $this->att->url_export()->out(),
                        get_string('export','quiz'));
        }

        if ($this->att->perm->can_change_preferences()) {
            $toprow[] = new tabobject(self::TAB_SETTINGS, $this->att->url_settings()->out(),
                        get_string('settings','attforblock'));
        }

        return array($toprow);
    }
}


class attforblock_filter_controls implements renderable {
    /** @var int current view mode */
    public $view;

    public $prevcur;
    public $nextcur;
    public $curdatetxt;

    private $att;

    public function __construct(attforblock $att) {
        $this->view = $att->view;

        $date = usergetdate($att->curdate);
        $mday = $date['mday'];
        $wday = $date['wday'];
        $mon = $date['mon'];
        $year = $date['year'];

        switch ($this->view) {
            case attforblock::VIEW_DAYS:
                $format = get_string('strftimedm', 'attforblock');
                $this->prevcur = make_timestamp($year, $mon, $mday - 1);
                $this->nextcur = make_timestamp($year, $mon, $mday + 1);
                $this->curdatetxt =  userdate($att->startdate, $format);
                break;
            case attforblock::VIEW_WEEKS:
                $format = get_string('strftimedm', 'attforblock');
                $this->prevcur = $att->startdate - WEEKSECS;
                $this->nextcur = $att->startdate + WEEKSECS;
                $this->curdatetxt = userdate($att->startdate, $format)." - ".userdate($att->enddate, $format);
                break;
            case attforblock::VIEW_MONTHS:
                $format = '%B';
                $this->prevcur = make_timestamp($year, $mon - 1);
                $this->nextcur = make_timestamp($year, $mon + 1);
                $this->curdatetxt = userdate($att->startdate, $format);
                break;
        }

        $this->att = $att;
    }

    public function url($params=NULL) {
        global $PAGE;
        
        return new moodle_url($PAGE->url, $params);
    }
}

/**
 * Represents info about attendance sessions taking into account view parameters.
 * 
 */
class attforblock_sessions_manage_data implements renderable {
    /** @var int start date of displayed date range */
    public $startdate;

    /** @var int end date of displayed date range */
    public $enddate;

    /** @var array of sessions*/
    public $sessions;

    /** @var int number of hidden sessions (sessions before $course->startdate)*/
    public $hiddensessionscount;

    /** @var attforblock_permissions permission of current user for attendance instance*/
    public $perm;

    /** @var int whether sessions end time will be displayed */
    public $showendtime;

    public $groups;

    public $hiddensesscount;

    /** @var attforblock */
    private $att;
    /**
     * Prepare info about attendance sessions taking into account view parameters.
     *
     * @param attforblock $att instance
     */
    public function __construct(attforblock $att) {
        global $DB;

        $this->perm = $att->perm;

        $this->showendtime = $att->showendtime;

        $this->startdate = $att->startdate;
        $this->enddate = $att->enddate;

        if ($this->startdate && $this->enddate) {
            $where = "courseid=:cid AND attendanceid = :aid AND sessdate >= :csdate AND sessdate >= :sdate AND sessdate < :edate";
        } else {
            $where = "courseid=:cid AND attendanceid = :aid AND sessdate >= :csdate";
        }
        $params = array(
                'cid'       => $att->course->id,
                'aid'       => $att->id,
                'csdate'    => $att->course->startdate,
                'sdate'     => $this->startdate,
                'edate'     => $this->enddate/*,
                'cgroup'    => $currentgroup*/);
        $this->sessions = $DB->get_records_select('attendance_sessions', $where, $params, 'sessdate asc');

        $where = "courseid = :cid AND attendanceid = :aid AND sessdate < :csdate";
        $params = array(
                'cid'       => $att->course->id,
                'aid'       => $att->id,
                'csdate'    => $att->course->startdate);
		$this->hiddensessionscount = $DB->count_records_select('attendance_sessions', $where, $params);

        $this->groups = groups_get_all_groups($att->course->id);

        $this->hiddensessionscount = $att->get_hidden_sessions_count();

        $this->att = $att;
    }

    public function url_take($sessionid, $grouptype=NULL) {
        $params = array('sessionid' => $sessionid);
        $url = new moodle_url($this->att->url_take(), $params);
        if (isset($grouptype))
            $url->param('grouptype', $grouptype);

        return $url;
    }

    /**
     * Must be called without or with both parameters
     */
    public function url_sessions($sessionid=NULL, $action=NULL) {
        $url = new moodle_url($this->att->url_sessions());
        if (isset($sessionid) && isset($action))
            $url->params(array('sessionid' => $sessionid, 'action' => $action));

        return $url;
    }
}

?>
