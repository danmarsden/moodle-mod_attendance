<?php

/**
 * Attendance module renderable components are defined here
 *
 * @package    mod
 * @subpackage attforblock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/locallib.php');


/**
 * Represents info about attendance tabs.
 *
 * Proxy class for security reasons (renderers must not have access to all attforblock methods)
 *
 */
class attforblock_tabs implements renderable {
    const TAB_SESSIONS  = 1;
    const TAB_ADD       = 2;
    const TAB_REPORT    = 3;
    const TAB_EXPORT    = 4;
    const TAB_SETTINGS  = 5;

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
            $toprow[] = new tabobject(self::TAB_SESSIONS, $this->att->url_manage()->out(),
                        get_string('sessions','attforblock'));
        }

        if ($this->att->perm->can_manage()) {
            $toprow[] = new tabobject(self::TAB_ADD, $this->att->url_sessions()->out(true, array('action' => att_sessions_page_params::ACTION_ADD)),
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

    public $curdate;

    public $prevcur;
    public $nextcur;
    public $curdatetxt;

    private $urlpath;
    private $urlparams;

    private $att;

    public function __construct(attforblock $att) {
        global $PAGE;

        $this->view = $att->pageparams->view;

        $this->curdate = $att->pageparams->curdate;

        $date = usergetdate($att->pageparams->curdate);
        $mday = $date['mday'];
        $wday = $date['wday'];
        $mon = $date['mon'];
        $year = $date['year'];

        switch ($this->view) {
            case att_manage_page_params::VIEW_DAYS:
                $format = get_string('strftimedm', 'attforblock');
                $this->prevcur = make_timestamp($year, $mon, $mday - 1);
                $this->nextcur = make_timestamp($year, $mon, $mday + 1);
                $this->curdatetxt =  userdate($att->pageparams->startdate, $format);
                break;
            case att_manage_page_params::VIEW_WEEKS:
                $format = get_string('strftimedm', 'attforblock');
                $this->prevcur = $att->pageparams->startdate - WEEKSECS;
                $this->nextcur = $att->pageparams->startdate + WEEKSECS;
                $this->curdatetxt = userdate($att->pageparams->startdate, $format)." - ".userdate($att->pageparams->enddate, $format);
                break;
            case att_manage_page_params::VIEW_MONTHS:
                $format = '%B';
                $this->prevcur = make_timestamp($year, $mon - 1);
                $this->nextcur = make_timestamp($year, $mon + 1);
                $this->curdatetxt = userdate($att->pageparams->startdate, $format);
                break;
        }

        $this->urlpath = $PAGE->url->out_omit_querystring();
        $params = array('id' => $att->cm->id);
        if (isset($att->pageparams->studentssort)) $params['sort'] = $att->pageparams->studentssort;
        if (isset($att->pageparams->studentid)) $params['studentid'] = $att->pageparams->studentid;
        $this->urlparams = $params;

        $this->att = $att;
    }

    public function url($params=array()) {
        $params = array_merge($this->urlparams, $params);

        return new moodle_url($this->urlpath, $params);
    }

    public function url_path() {
        return $this->urlpath;
    }

    public function url_params($params=array()) {
        $params = array_merge($this->urlparams, $params);

        return $params;
    }

    public function get_group_mode() {
        return $this->att->get_group_mode();
    }

    public function get_sess_groups_list() {
        return $this->att->get_sess_groups_list();
    }

    public function get_current_group() {
        return $this->att->get_current_group();
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

        $this->showendtime = $att->pageparams->showendtime;

        $this->startdate = $att->pageparams->startdate;
        $this->enddate = $att->pageparams->enddate;

        if ($this->startdate && $this->enddate) {
            $where = "courseid=:cid AND attendanceid = :aid AND sessdate >= :csdate AND sessdate >= :sdate AND sessdate < :edate";
        } else {
            $where = "courseid=:cid AND attendanceid = :aid AND sessdate >= :csdate";
        }
        if ($att->get_current_group() > attforblock::SELECTOR_ALL) {
            $where .= " AND groupid=:cgroup";
        }
        $params = array(
                'cid'       => $att->course->id,
                'aid'       => $att->id,
                'csdate'    => $att->course->startdate,
                'sdate'     => $this->startdate,
                'edate'     => $this->enddate,
                'cgroup'    => $att->get_current_group());
        $this->sessions = $DB->get_records_select('attendance_sessions', $where, $params, 'sessdate asc');
        foreach ($this->sessions as $sess) {
            $sess->description = file_rewrite_pluginfile_urls($sess->description, 'pluginfile.php', $att->context->id, 'mod_attforblock', 'session', $sess->id);
        }

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