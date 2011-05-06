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

    public $curdate;

    public $prevcur;
    public $nextcur;
    public $curdatetxt;

    private $url_path;
    private $url_params;

    private $att;

    public function __construct(attforblock $att) {
        global $PAGE;

        $this->view = $att->view_params->view;

        $this->curdate = $att->view_params->curdate;

        $date = usergetdate($att->view_params->curdate);
        $mday = $date['mday'];
        $wday = $date['wday'];
        $mon = $date['mon'];
        $year = $date['year'];

        switch ($this->view) {
            case attforblock_view_params::VIEW_DAYS:
                $format = get_string('strftimedm', 'attforblock');
                $this->prevcur = make_timestamp($year, $mon, $mday - 1);
                $this->nextcur = make_timestamp($year, $mon, $mday + 1);
                $this->curdatetxt =  userdate($att->view_params->startdate, $format);
                break;
            case attforblock_view_params::VIEW_WEEKS:
                $format = get_string('strftimedm', 'attforblock');
                $this->prevcur = $att->view_params->startdate - WEEKSECS;
                $this->nextcur = $att->view_params->startdate + WEEKSECS;
                $this->curdatetxt = userdate($att->view_params->startdate, $format)." - ".userdate($att->view_params->enddate, $format);
                break;
            case attforblock_view_params::VIEW_MONTHS:
                $format = '%B';
                $this->prevcur = make_timestamp($year, $mon - 1);
                $this->nextcur = make_timestamp($year, $mon + 1);
                $this->curdatetxt = userdate($att->view_params->startdate, $format);
                break;
        }

        $this->url_path = $PAGE->url->out_omit_querystring();
        $params = array('id' => $att->cm->id);
        if (isset($att->view_params->students_sort)) $params['sort'] = $att->view_params->students_sort;
        if (isset($att->view_params->student_id)) $params['studentid'] = $att->view_params->student_id;
        $this->url_params = $params;

        $this->att = $att;
    }

    public function url($params=array()) {
        $params = array_merge($this->url_params, $params);

        return new moodle_url($this->url_path, $params);
    }

    public function url_path() {
        return $this->url_path;
    }

    public function url_params($params=array()) {
        $params = array_merge($this->url_params, $params);

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

        $this->showendtime = $att->view_params->show_endtime;

        $this->startdate = $att->view_params->startdate;
        $this->enddate = $att->view_params->enddate;

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