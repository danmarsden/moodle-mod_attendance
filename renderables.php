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
 * Attendance module renderable components are defined here
 *
 * @package    mod_attendance
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/locallib.php');


/**
 * Represents info about attendance tabs.
 *
 * Proxy class for security reasons (renderers must not have access to all attendance methods)
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class attendance_tabs implements renderable {
    const TAB_SESSIONS      = 1;
    const TAB_ADD           = 2;
    const TAB_REPORT        = 3;
    const TAB_EXPORT        = 4;
    const TAB_PREFERENCES   = 5;
    const TAB_TEMPORARYUSERS = 6; // Tab for managing temporary users.

    public $currenttab;

    /** @var attendance */
    private $att;

    /**
     * Prepare info about sessions for attendance taking into account view parameters.
     *
     * @param attendance $att instance
     * @param $currenttab - one of attendance_tabs constants
     */
    public function  __construct(attendance $att, $currenttab=null) {
        $this->att = $att;
        $this->currenttab = $currenttab;
    }

    /**
     * Return array of rows where each row is an array of tab objects
     * taking into account permissions of current user
     */
    public function get_tabs() {
        $toprow = array();
        $context = $this->att->context;
        $capabilities = array(
            'mod/attendance:manageattendances',
            'mod/attendance:takeattendances',
            'mod/attendance:changeattendances'
        );
        if (has_any_capability($capabilities, $context)) {
            $toprow[] = new tabobject(self::TAB_SESSIONS, $this->att->url_manage()->out(),
                get_string('sessions', 'attendance'));
        }

        if (has_capability('mod/attendance:manageattendances', $context)) {
            $toprow[] = new tabobject(self::TAB_ADD,
                                     $this->att->url_sessions()->out(true, array('action' => att_sessions_page_params::ACTION_ADD)),
                        get_string('add', 'attendance'));
        }
        if (has_capability('mod/attendance:viewreports', $context)) {
            $toprow[] = new tabobject(self::TAB_REPORT, $this->att->url_report()->out(),
                        get_string('report', 'attendance'));
        }

        if (has_capability('mod/attendance:export', $context)) {
            $toprow[] = new tabobject(self::TAB_EXPORT, $this->att->url_export()->out(),
                        get_string('export', 'attendance'));
        }

        if (has_capability('mod/attendance:changepreferences', $context)) {
            $toprow[] = new tabobject(self::TAB_PREFERENCES, $this->att->url_preferences()->out(),
                        get_string('settings', 'attendance'));
        }
        if (has_capability('mod/attendance:managetemporaryusers', $context)) {
            $toprow[] = new tabobject(self::TAB_TEMPORARYUSERS, $this->att->url_managetemp()->out(),
                                      get_string('tempusers', 'attendance'));
        }

        return array($toprow);
    }
}


class attendance_filter_controls implements renderable {
    /** @var int current view mode */
    public $pageparams;

    public $cm;

    public $curdate;

    public $prevcur;
    public $nextcur;
    public $curdatetxt;
    public $reportcontrol;

    private $urlpath;
    private $urlparams;

    private $att;

    public function __construct(attendance $att, $report = false) {
        global $PAGE;

        $this->pageparams = $att->pageparams;

        $this->cm = $att->cm;

        // This is a report control only if $reports is true and the attendance block can be graded.
        $this->reportcontrol = $report && ($att->grade > 0);

        $this->curdate = $att->pageparams->curdate;

        $date = usergetdate($att->pageparams->curdate);
        $mday = $date['mday'];
        $wday = $date['wday'];
        $mon = $date['mon'];
        $year = $date['year'];

        switch ($this->pageparams->view) {
            case ATT_VIEW_DAYS:
                $format = get_string('strftimedm', 'attendance');
                $this->prevcur = make_timestamp($year, $mon, $mday - 1);
                $this->nextcur = make_timestamp($year, $mon, $mday + 1);
                $this->curdatetxt =  userdate($att->pageparams->startdate, $format);
                break;
            case ATT_VIEW_WEEKS:
                $format = get_string('strftimedm', 'attendance');
                $this->prevcur = $att->pageparams->startdate - WEEKSECS;
                $this->nextcur = $att->pageparams->startdate + WEEKSECS;
                $this->curdatetxt = userdate($att->pageparams->startdate, $format).
                                    " - ".userdate($att->pageparams->enddate, $format);
                break;
            case ATT_VIEW_MONTHS:
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
        return $this->att->pageparams->get_sess_groups_list();
    }

    public function get_current_sesstype() {
        return $this->att->pageparams->get_current_sesstype();
    }
}

/**
 * Represents info about attendance sessions taking into account view parameters.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance_manage_data implements renderable {
    /** @var array of sessions*/
    public $sessions;

    /** @var int number of hidden sessions (sessions before $course->startdate)*/
    public $hiddensessionscount;

    public $groups;

    public $hiddensesscount;

    /** @var attendance */
    public $att;
    /**
     * Prepare info about attendance sessions taking into account view parameters.
     *
     * @param attendance $att instance
     */
    public function __construct(attendance $att) {

        $this->sessions = $att->get_filtered_sessions();

        $this->groups = groups_get_all_groups($att->course->id);

        $this->hiddensessionscount = $att->get_hidden_sessions_count();

        $this->att = $att;
    }

    public function url_take($sessionid) {
        return url_helpers::url_take($this->att, $sessionid);
    }

    /**
     * Must be called without or with both parameters
     */
    public function url_sessions($sessionid=null, $action=null) {
        return url_helpers::url_sessions($this->att, $sessionid, $action);
    }
}

class attendance_take_data implements renderable {
    public $users;

    public $pageparams;

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

    public function  __construct(attendance $att) {
        $this->users = $att->get_users($att->pageparams->groupid, $att->pageparams->page);

        $this->pageparams = $att->pageparams;

        $this->groupmode = $att->get_group_mode();
        $this->cm = $att->cm;

        $this->statuses = $att->get_statuses();

        $this->sessioninfo = $att->get_session_info($att->pageparams->sessionid);
        $this->updatemode = $this->sessioninfo->lasttaken > 0;

        if (isset($att->pageparams->copyfrom)) {
            $this->sessionlog = $att->get_session_log($att->pageparams->copyfrom);
        } else if ($this->updatemode) {
            $this->sessionlog = $att->get_session_log($att->pageparams->sessionid);
        } else {
            $this->sessionlog = array();
        }

        if (!$this->updatemode) {
            $this->sessions4copy = $att->get_today_sessions_for_copy($this->sessioninfo);
        }

        $this->urlpath = $att->url_take()->out_omit_querystring();
        $params = $att->pageparams->get_significant_params();
        $params['id'] = $att->cm->id;
        $this->urlparams = $params;

        $this->att = $att;
    }

    public function url($params=array(), $excludeparams=array()) {
        $params = array_merge($this->urlparams, $params);

        foreach ($excludeparams as $paramkey) {
            unset($params[$paramkey]);
        }

        return new moodle_url($this->urlpath, $params);
    }

    public function url_view($params=array()) {
        return url_helpers::url_view($this->att, $params);
    }

    public function url_path() {
        return $this->urlpath;
    }
}

class attendance_user_data implements renderable {
    public $user;

    public $pageparams;

    public $statuses;

    public $grade;

    public $maxgrade;

    public $numtakensessions;

    public $filtercontrols;

    public $sessionslog;

    public $groups;

    public $usergroups;

    public $coursesatts;

    private $urlpath;
    private $urlparams;

    public function  __construct(attendance $att, $userid) {
        global $CFG, $USER;

        $this->user = $att->get_user($userid);

        $this->pageparams = $att->pageparams;

        if ($this->pageparams->mode == att_view_page_params::MODE_THIS_COURSE) {
            $this->statuses = $att->get_statuses(true, true);

            $userpoints = att_get_user_points($att->get_users_points($userid, $att->pageparams->startdate, $att->pageparams->enddate), $userid);
            $this->grade = $userpoints->points;
            $this->maxgrade = $userpoints->maxpoints;
            $this->numtakensessions = $userpoints->numtakensessions;

            $this->filtercontrols = new attendance_filter_controls($att);

            $this->sessionslog = $att->get_user_filtered_sessions_log_extended($userid);

            $this->groups = groups_get_all_groups($att->course->id);
            $this->usergroups = groups_get_all_groups($att->course->id, $userid);
        } else {
            $this->coursesatts = att_get_user_courses_attendances($userid);
            $this->statuses = array();
            $this->grade = array();
            $this->maxgrade = array();
            $this->numtakensessions = array();
            foreach ($this->coursesatts as $atid => $ca) {
                // Check to make sure the user can view this cm.
                if (!get_fast_modinfo($ca->courseid)->instances['attendance'][$ca->attid]->uservisible) {
                    unset($this->courseatts[$atid]);
                    continue;
                }

                $statuses = att_get_statuses($ca->attid);
                $this->statuses[$ca->attid] = $statuses;

                $userpoints = att_get_user_points(att_get_users_points($ca->attid, $userid), $userid);
                $this->grade[$ca->attid] = $userpoints->points;
                $this->maxgrade[$ca->attid] = $userpoints->maxpoints;
                $this->numtakensessions[$ca->attid] = $userpoints->numtakensessions;
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

class attendance_report_data implements renderable {
    public $pageparams;

    public $users;

    public $groups;

    public $sessions;

    public $statuses;
    // Includes disablrd/deleted statuses.
    public $allstatuses;

    public $usersgroups = array();

    public $sessionslog = array();

    public $usersstats = array();

    public $grades = array();

    public $maxgrades = array();

    public $att;

    public function  __construct(attendance $att) {
        global $CFG;

        $currenttime = time();
        if ($att->pageparams->view == ATT_VIEW_NOTPRESENT) {
            $att->pageparams->enddate = $currenttime;
        }

        $this->pageparams = $att->pageparams;

        $this->users = $att->get_users($att->pageparams->group, $att->pageparams->page);

        if (isset($att->pageparams->userids)) {
            foreach ($this->users as $key => $user) {
                if (!in_array($user->id, $att->pageparams->userids)) {
                    unset($this->users[$key]);
                }
            }
        }

        $this->groups = groups_get_all_groups($att->course->id);

        $this->sessions = $att->get_filtered_sessions();

        $this->statuses = $att->get_statuses(true, true);
        $this->allstatuses = $att->get_statuses(false, true);

        $points = $att->get_users_points(array_keys($this->users), $att->pageparams->startdate, $att->pageparams->enddate);

        foreach ($this->users as $key => $user) {
            $userpoints = att_get_user_points($points, $user->id);
            $grade = $userpoints->points;
            $maxgrade = $userpoints->maxpoints;

            if ($att->pageparams->view != ATT_VIEW_NOTPRESENT || $grade < $maxgrade) {
                $this->usersgroups[$user->id] = groups_get_all_groups($att->course->id, $user->id);

                $this->sessionslog[$user->id] = $att->get_user_filtered_sessions_log($user->id);

                $this->grades[$user->id] = $grade;
                $this->maxgrades[$user->id] = $maxgrade;
            } else {
                unset($this->users[$key]);
            }
        }

        $this->att = $att;
    }

    public function url_take($sessionid) {
        return url_helpers::url_take($this->att, $sessionid);
    }

    public function url_view($params=array()) {
        return url_helpers::url_view($this->att, $params);
    }

    public function url($params=array()) {
        $params = array_merge($params, $this->pageparams->get_significant_params());

        return $this->att->url_report($params);
    }

}

class attendance_preferences_data implements renderable {
    public $statuses;

    private $att;

    public $errors;

    public function __construct(attendance $att, $errors) {
        $this->statuses = $att->get_statuses(false);
        $this->errors = $errors;

        foreach ($this->statuses as $st) {
            $st->haslogs = att_has_logs_for_status ($st->id);
        }

        $this->att = $att;
    }

    public function url($params=array(), $significant_params=true) {
        if ($significant_params) {
            $params = array_merge($this->att->pageparams->get_significant_params(), $params);
        }

        return $this->att->url_preferences($params);
    }
}

// Output a selector to change between status sets.
class attendance_set_selector implements renderable {
    public $maxstatusset;

    private $att;

    public function __construct(attendance $att, $maxstatusset) {
        $this->att = $att;
        $this->maxstatusset = $maxstatusset;
    }

    public function url($statusset) {
        $params = array();
        $params['statusset'] = $statusset;

        return $this->att->url_preferences($params);
    }

    public function get_current_statusset() {
        if (isset($this->att->pageparams->statusset)) {
            return $this->att->pageparams->statusset;
        }
        return 0;
    }

    public function get_status_name($statusset) {
        return att_get_setname($this->att->id, $statusset, true);
    }
}

class url_helpers {
    public static function url_take($att, $sessionid) {
        $params = array('sessionid' => $sessionid);
        return $att->url_take($params);
    }

    /**
     * Must be called without or with both parameters
     */
    public static function url_sessions($att, $sessionid=null, $action=null) {
        if (isset($sessionid) && isset($action)) {
            $params = array('sessionid' => $sessionid, 'action' => $action);
        } else {
            $params = array();
        }

        return $att->url_sessions($params);
    }

    public static function url_view($att, $params=array()) {
        return $att->url_view($params);
    }
}
