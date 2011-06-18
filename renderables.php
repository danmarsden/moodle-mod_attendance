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
    public function  __construct(attforblock $att, $currenttab=NULL) {
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

        if ($this->att->perm->can_view_reports()) {
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
    public $pageparams;

    public $curdate;

    public $prevcur;
    public $nextcur;
    public $curdatetxt;

    private $urlpath;
    private $urlparams;

    private $att;

    public function __construct(attforblock $att) {
        global $PAGE;

        $this->pageparams = $att->pageparams;

        $this->curdate = $att->pageparams->curdate;

        $date = usergetdate($att->pageparams->curdate);
        $mday = $date['mday'];
        $wday = $date['wday'];
        $mon = $date['mon'];
        $year = $date['year'];

        switch ($this->pageparams->view) {
            case VIEW_DAYS:
                $format = get_string('strftimedm', 'attforblock');
                $this->prevcur = make_timestamp($year, $mon, $mday - 1);
                $this->nextcur = make_timestamp($year, $mon, $mday + 1);
                $this->curdatetxt =  userdate($att->pageparams->startdate, $format);
                break;
            case VIEW_WEEKS:
                $format = get_string('strftimedm', 'attforblock');
                $this->prevcur = $att->pageparams->startdate - WEEKSECS;
                $this->nextcur = $att->pageparams->startdate + WEEKSECS;
                $this->curdatetxt = userdate($att->pageparams->startdate, $format)." - ".userdate($att->pageparams->enddate, $format);
                break;
            case VIEW_MONTHS:
                $format = '%B';
                $this->prevcur = make_timestamp($year, $mon - 1);
                $this->nextcur = make_timestamp($year, $mon + 1);
                $this->curdatetxt = userdate($att->pageparams->startdate, $format);
                break;
        }

        $this->urlpath = $PAGE->url->out_omit_querystring();
        $params = $att->pageparams->get_significant_params();
        $params['id'] = $att->cm->id;
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
    /** @var array of sessions*/
    public $sessions;

    /** @var int number of hidden sessions (sessions before $course->startdate)*/
    public $hiddensessionscount;

    /** @var attforblock_permissions permission of current user for attendance instance*/
    public $perm;

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

        $this->sessions = $att->get_filtered_sessions();

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

class attforblock_take_data implements renderable {
    public $users;

    public $pageparams;
    public $perm;

    public $groupmode;
    public $cm;

    public $statuses;

    public $sessioninfo;

    public $sessionlog;

    public $sessions4copy;

    public $updatemode;

    private $urlpath;
    private $urlparams;
    private $att;

    public function  __construct(attforblock $att) {
        if ($att->pageparams->grouptype)
            $this->users = $att->get_users($att->pageparams->grouptype);
        else
            $this->users = $att->get_users($att->pageparams->group);

        $this->pageparams = $att->pageparams;
        $this->perm = $att->perm;

        $this->groupmode = $att->get_group_mode();
        $this->cm = $att->cm;

        $this->statuses = $att->get_statuses();

        $this->sessioninfo = $att->get_session_info($att->pageparams->sessionid);
        $this->updatemode = $this->sessioninfo->lasttaken > 0;

        if (isset($att->pageparams->copyfrom))
            $this->sessionlog = $att->get_session_log($att->pageparams->copyfrom);
        elseif ($this->updatemode)
            $this->sessionlog = $att->get_session_log($att->pageparams->sessionid);
        else
            $this->sessionlog = array();


        if (!$this->updatemode)
            $this->sessions4copy = $att->get_today_sessions_for_copy($this->sessioninfo);

        $this->urlpath = $att->url_take()->out_omit_querystring();
        $params = $att->pageparams->get_significant_params();
        $params['id'] = $att->cm->id;
        $this->urlparams = $params;

        $this->att = $att;
    }
    
    public function url($params=array(), $excludeparams=array()) {
        $params = array_merge($this->urlparams, $params);

        foreach ($excludeparams as $paramkey)
            unset($params[$paramkey]);

        return new moodle_url($this->urlpath, $params);
    }

    public function url_view($params=array()) {
        return new moodle_url($this->att->url_view($params), $params);
    }

    public function url_path() {
        return $this->urlpath;
    }
}

class attforblock_user_data implements renderable {
    public $user;

    public $pageparams;

    public $stat;

    public $statuses;

    public $gradable;

    public $grade;

    public $maxgrade;

    public $decimalpoints;

    public $filtercontrols;

    public $sessionslog;

    public $coursesatts;

    private $urlpath;
    private $urlparams;

    public function  __construct(attforblock $att, $userid) {
        global $CFG;

        $this->user = $att->get_user($userid);

        $this->pageparams = $att->pageparams;

        if (!$this->decimalpoints = grade_get_setting($att->course->id, 'decimalpoints')) {
            $this->decimalpoints = $CFG->grade_decimalpoints;
        }

        if ($this->pageparams->mode == att_view_page_params::MODE_THIS_COURSE) {
            $this->statuses = $att->get_statuses();

            $this->stat = $att->get_user_stat($userid);

            $this->gradable = $att->grade > 0;
            if ($this->gradable) {
                $this->grade = $att->get_user_grade($userid);
                $this->maxgrade = $att->get_user_max_grade($userid);
            }


            $this->filtercontrols = new attforblock_filter_controls($att);

            $this->sessionslog = $att->get_user_filtered_sessions_log($userid);
        }
        else {
            $this->coursesatts = get_user_courses_attendances($userid);

            $this->statuses = array();
            $this->stat = array();
            $this->gradable = array();
            $this->grade = array();
            $this->maxgrade = array();
            foreach ($this->coursesatts as $ca) {
                $statuses = get_statuses($ca->attid);
                $user_taken_sessions_count = get_user_taken_sessions_count($ca->attid, $ca->coursestartdate, $userid);
                $user_statuses_stat = get_user_statuses_stat($ca->attid, $ca->coursestartdate, $userid);

                $this->statuses[$ca->attid] = $statuses;

                $this->stat[$ca->attid]['completed'] = $user_taken_sessions_count;
                $this->stat[$ca->attid]['statuses'] = $user_statuses_stat;

                $this->gradable[$ca->attid] = $ca->attgrade > 0;

                if ($this->gradable[$ca->attid]) {
                    $this->grade[$ca->attid] = get_user_grade($user_statuses_stat, $statuses);
                    // For getting sessions count implemented simplest method - taken sessions.
                    // It can have error if users don't have attendance info for some sessions.
                    // In the future we can implement another methods:
                    // * all sessions between user start enrolment date and now;
                    // * all sessions between user start and end enrolment date.
                    $this->maxgrade[$ca->attid] = get_user_max_grade($user_taken_sessions_count, $statuses);
                }
                else {
                    //for more comfortable and universal work with arrays
                    $this->grade[$ca->attid] = NULL;
                    $this->maxgrade[$ca->attid] = NULL;
                }
            }
        }
        
        $this->urlpath = $att->url_view()->out_omit_querystring();
        $params = $att->pageparams->get_significant_params();
        $params['id'] = $att->cm->id;
        $this->urlparams = $params;
    }

    public function url() {
        return new moodle_url($this->urlpath, $this->urlparams);
    }
}

?>