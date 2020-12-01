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
 * presence module renderable components are defined here
 *
 * @package    mod_presence
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/locallib.php');


/**
 * Represents info about presence tabs.
 *
 * Proxy class for security reasons (renderers must not have access to all presence methods)
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class presence_tabs implements renderable {
    /** Sessions tab */
    const TAB_SESSIONS      = 1;
    /** Add tab */
    const TAB_ADD           = 2;
    /** Rerort tab */
    const TAB_REPORT        = 3;
    /** Export tab */
    const TAB_EXPORT        = 4;
    /** Preferences tab */
    const TAB_EVALUATION   = 5;
    /** Temp users tab */
    const TAB_TEMPORARYUSERS = 6; // Tab for managing temporary users.
    /** Update tab */
    const TAB_UPDATE        = 7;
    /** Warnings tab */
    const TAB_WARNINGS = 8;
    /** Absentee tab */
    const TAB_ABSENTEE      = 9;
    /** Booking tab  */
    const TAB_BOOKING   = 10;
    /** Room planner tab */
    const TAB_ROOMPLANNER = 11;
    /** @var int current tab */
    public $currenttab;

    /** @var stdClass presence */
    private $presence;

    /**
     * Prepare info about sessions for presence taking into account view parameters.
     *
     * @param mod_presence_structure $presence
     * @param int $currenttab - one of presence_tabs constants
     */
    public function  __construct(mod_presence_structure $presence, $currenttab=null) {
        $this->presence = $presence;
        $this->currenttab = $currenttab;
    }

    /**
     * Return array of rows where each row is an array of tab objects
     * taking into account permissions of current user
     */
    public function get_tabs() {
        $toprow = array();
        $context = $this->presence->context;
        $capabilities = array(
            'mod/presence:managepresences',
            'mod/presence:takepresences',
            'mod/presence:changepresences'
        );

        if (has_capability('mod/presence:view', $context)) {
            $toprow[] = new tabobject(self::TAB_BOOKING, $this->presence->url_view()->out(),
                get_string('book', 'presence'));
        }

        if (has_capability('mod/presence:managepresences', $context)) {
            $toprow[] = new tabobject(self::TAB_EVALUATION, $this->presence->url_evaluation()->out(),
                get_string('evaluation', 'presence'));
        }

        if (has_any_capability($capabilities, $context)) {
            $toprow[] = new tabobject(self::TAB_SESSIONS, $this->presence->url_manage()->out(),
                            get_string('sessions', 'presence'));
        }

        if ($this->currenttab == self::TAB_ADD && has_capability('mod/presence:managepresences', $context)) {
            $toprow[] = new tabobject(self::TAB_ADD,
                            $this->presence->url_sessions()->out(true,
                                array('action' => mod_presence_sessions_page_params::ACTION_ADD)),
                                get_string('addsession', 'presence'));
        }

        if ($this->currenttab == self::TAB_UPDATE && has_capability('mod/presence:managepresences', $context)) {
            $toprow[] = new tabobject(self::TAB_UPDATE,
                            $this->presence->url_sessions()->out(true,
                                array('action' => mod_presence_sessions_page_params::ACTION_UPDATE)),
                                get_string('changesession', 'presence'));
        }

        if (has_capability('mod/presence:view', $context)) {
            $toprow[] = new tabobject(self::TAB_ROOMPLANNER, $this->presence->url_roomplanner()->out(),
                get_string('roomplanner', 'presence'));
        }

        return array($toprow);
    }
}

/**
 * Class presence_filter_controls
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presence_filter_controls implements renderable {
    /** @var int current view mode */
    public $pageparams;
    /** @var stdclass  */
    public $cm;
    /** @var int  */
    public $curdate;
    /** @var int  */
    public $prevcur;
    /** @var int  */
    public $nextcur;
    /** @var string  */
    public $curdatetxt;
    /** @var boolean  */
    public $reportcontrol;
    /** @var string  */
    private $urlpath;
    /** @var array  */
    private $urlparams;
    /** @var mod_presence_structure */
    public $presence;

    /**
     * presence_filter_controls constructor.
     * @param mod_presence_structure $presence
     * @param bool $report
     */
    public function __construct(mod_presence_structure $presence, $report = false) {
        global $PAGE;

        $this->pageparams = $presence->pageparams;

        $this->cm = $presence->cm;

        // This is a report control only if $reports is true and the presence block can be graded.
        $this->reportcontrol = $report;

        $this->curdate = $presence->pageparams->curdate;

        $date = usergetdate($presence->pageparams->curdate);
        $mday = $date['mday'];
        $mon = $date['mon'];
        $year = $date['year'];

        switch ($this->pageparams->view) {
            case PRESENCE_VIEW_DAYS:
                $format = get_string('strftimedm', 'presence');
                $this->prevcur = make_timestamp($year, $mon, $mday - 1);
                $this->nextcur = make_timestamp($year, $mon, $mday + 1);
                $this->curdatetxt = userdate($presence->pageparams->startdate, $format);
                break;
            case PRESENCE_VIEW_WEEKS:
                $format = get_string('strftimedm', 'presence');
                $this->prevcur = $presence->pageparams->startdate - WEEKSECS;
                $this->nextcur = $presence->pageparams->startdate + WEEKSECS;
                $this->curdatetxt = userdate($presence->pageparams->startdate, $format).
                                    " - ".userdate($presence->pageparams->enddate, $format);
                break;
            case PRESENCE_VIEW_MONTHS:
                $format = '%B';
                $this->prevcur = make_timestamp($year, $mon - 1);
                $this->nextcur = make_timestamp($year, $mon + 1);
                $this->curdatetxt = userdate($presence->pageparams->startdate, $format);
                break;
        }

        $this->urlpath = $PAGE->url->out_omit_querystring();
        $params = $presence->pageparams->get_significant_params();
        $params['curdate'] = time();
        $params['id'] = $presence->cm->id;
        $this->urlparams = $params;

        $this->presence = $presence;
    }

    /**
     * Helper function for url.
     *
     * @param array $params
     * @return moodle_url
     */
    public function url($params=array()) {
        $params = array_merge($this->urlparams, $params);

        return new moodle_url($this->urlpath, $params);
    }

    /**
     * Helper function for url path.
     * @return string
     */
    public function url_path() {
        return $this->urlpath;
    }

    /**
     * Helper function for url_params.
     * @param array $params
     * @return array
     */
    public function url_params($params=array()) {
        $params = array_merge($this->urlparams, $params);

        return $params;
    }

    /**
     * Return groupmode.
     * @return int
     */
    public function get_group_mode() {
        return $this->presence->get_group_mode();
    }

    /**
     * Return groupslist.
     * @return mixed
     */
    public function get_sess_groups_list() {
        return $this->presence->pageparams->get_sess_groups_list();
    }

    /**
     * Get current session type.
     * @return mixed
     */
    public function get_current_sesstype() {
        return $this->presence->pageparams->get_current_sesstype();
    }
}

/**
 * Represents info about presence sessions
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presence_sessions_data implements renderable {
    /** @var array of sessions*/
    public $sessions;

    /** @var int number of hidden sessions (sessions before $course->startdate)*/
    public $hiddensessionscount;
    /** @var array  */
    public $groups;
    /** @var  int */
    public $hiddensesscount;

    /** @var mod_presence_structure */
    public $presence;
    /** @var array */
    public $sessionsbydate;

    /**
     * Prepare info about presence sessions taking into account view parameters.
     *
     * @param mod_presence_structure $presence instance
     */
    public function __construct(mod_presence_structure $presence) {

        $this->sessions = $presence->get_filtered_sessions();

        $this->sessionsbydate = array();
        $olddate = null;
        $dateid = -1;
        foreach ($this->sessions as $session) {
            $date = userdate($session->sessdate, get_string('strftimedatefullshort', 'langconfig'));

            if ($date != $olddate) {
                $this->sessionsbydate[++$dateid] = array("date" => $date, "sessions" => array());
                $olddate = $date;
            }
            $this->sessionsbydate[$dateid]["sessions"][] = $session;
        }
        $this->presence = $presence;
    }

    /**
     * Must be called without or with both parameters
     *
     * @param int $sessionid
     * @param null $action
     * @return mixed
     */
    public function url_sessions($sessionid=null, $action=null) {
        die("depreceated");
        return url_helpers::url_sessions($this->presence, $sessionid, $action);
    }
}

/**
 * class take data.
 *
 * @copyright  2020 Florian Metzger-Noel (github.com/flocko-motion)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presence_evaluation_data implements renderable {
    /** @var array  */
    public $users;
    /** @var array|null|stdClass  */
    public $pageparams;
    /** @var stdclass  */
    public $cm;
    /** @var mod_presence_structure  */
    public $presence;
    /** @var stdClass */
    public $session;
    /** @var string */
    public $urlfinish;

    /**
     * presence_take_data constructor.
     * @param mod_presence_structure $presence
     */
    public function  __construct(mod_presence_structure $presence) {
        $this->users = $presence->get_users([
            'page' => $presence->pageparams->page,
            'sessionid' => $presence->pageparams->sessionid,
            'evaluation' => 1,
        ]);
        $bookings = 0;
        foreach ($this->users as $user) {
            if (intval($user->booked)) {
                $bookings++;
            }
        }

        if ($bookings > 0) {
            foreach ($this->users as $k => $user) {
                if (!$user->booked) {
                    unset ($this->users[$k]);
                }
            }
        }


        $this->cm = $presence->cm;
        $this->session = $presence->get_session_info($presence->pageparams->sessionid);
        $this->pageparams = $presence->pageparams;
        $this->urlfinish = $presence->url_evaluation([
            'sessionid' => $presence->pageparams->sessionid,
            'action' => mod_presence_sessions_page_params::ACTION_EVALUATE_FINISH ,
        ]);
        $this->presence = $presence;
    }
}

/**
 * Class user data.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presence_user_data implements renderable {
    /** @var mixed|object  */
    public $user;
    /** @var array|null|stdClass  */
    public $pageparams;
    /** @var array  */
    public $statuses;
    /** @var presence_filter_controls  */
    public $filtercontrols;
    /** @var array  */
    public $sessionslog;
    /** @var array  */
    public $groups;
    /** @var array  */
    public $coursespresences;
    /** @var string  */
    private $urlpath;
    /** @var array */
    private $urlparams;

    /**
     * presence_user_data constructor.
     * @param mod_presence_structure $presence
     * @param int $userid
     * @param boolean $mobile - this is called by the mobile code, don't generate everything.
     */
    public function  __construct(mod_presence_structure $presence, $userid, $mobile = false) {
        $this->user = $presence->get_user($userid);

        $this->pageparams = $presence->pageparams;

        if ($this->pageparams->mode == mod_presence_view_page_params::MODE_THIS_COURSE
                || $this->pageparams->mode == mod_presence_view_page_params::MODE_THIS_BOOKING) {

            $this->filtercontrols = new presence_filter_controls($presence);
            $this->sessionslog = $presence->get_user_filtered_sessions_log_extended($userid);

            $this->sessionsbydate = [];
            $olddate = null;
            $dateid = -1;
            foreach ($this->sessionslog as $session) {
                $session->timefrom = userdate($session->sessdat, get_string('strftimetime', 'langconfig'));
                $session->timeto = userdate($session->sessdat + $session->duration, get_string('strftimetime', 'langconfig'));
                $date = userdate($session->sessdate, get_string('strftimedatefullshort', 'langconfig'));

                if ($date != $olddate) {
                    $this->sessionsbydate[++$dateid] = array("date" => $date, "sessions" => array());
                    $olddate = $date;
                }
                $this->sessionsbydate[$dateid]["sessions"][] = $session;
            }

        } else {
            die('depreceated:'.__LINE__.'@'.__FILE__);
            $this->coursespresences = presence_get_user_courses_presences($userid);
            foreach ($this->coursespresences as $atid => $ca) {
                // Check to make sure the user can view this cm.
                $modinfo = get_fast_modinfo($ca->courseid);
                if (!$modinfo->instances['presence'][$ca->presenceid]->uservisible) {
                    unset($this->coursespresences[$atid]);
                    continue;
                } else {
                    $this->coursespresences[$atid]->cmid = $modinfo->instances['presence'][$ca->presenceid]->get_course_module_record()->id;
                }
                $this->statuses[$ca->presenceid] = presence_get_statuses($ca->presenceid);
            }
        }
        $this->urlpath = $presence->url_view()->out_omit_querystring();
        $params = $presence->pageparams->get_significant_params();
        $params['id'] = $presence->cm->id;
        $this->urlparams = $params;
    }

    /**
     * url helper.
     * @return moodle_url
     */
    public function url() {
        return new moodle_url($this->urlpath, $this->urlparams);
    }
}

/**
 * Class report data.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presence_report_data implements renderable {
    /** @var array|null|stdClass  */
    public $pageparams;
    /** @var array  */
    public $users;
    /** @var array  */
    public $groups;
    /** @var array  */
    public $sessions;
    /** @var array  */
    public $statuses;
    /** @var array includes disablrd/deleted statuses. */
    public $allstatuses;
    /** @var array  */
    public $usersgroups = array();
    /** @var array  */
    public $sessionslog = array();
    /** @var mod_presence_structure  */
    public $presence;

    /**
     * presence_report_data constructor.
     * @param mod_presence_structure $presence
     */
    public function  __construct(mod_presence_structure $presence) {
        $this->pageparams = $presence->pageparams;

        $this->users = $presence->get_users(['groupid' => $presence->pageparams->group, 'page' => $presence->pageparams->page]);

        if (isset($presence->pageparams->userids)) {
            foreach ($this->users as $key => $user) {
                if (!in_array($user->id, $presence->pageparams->userids)) {
                    unset($this->users[$key]);
                }
            }
        }

        $this->groups = groups_get_all_groups($presence->course->id);

        $this->sessions = $presence->get_filtered_sessions();



        $this->presence = $presence;
    }

    /**
     * url take helper.
     * @param int $sessionid
     * @param int $grouptype
     * @return mixed
     */
    public function url_take($sessionid, $grouptype) {
        return url_helpers::url_take($this->presence, $sessionid, $grouptype);
    }

    /**
     * url view helper.
     * @param array $params
     * @return mixed
     */
    public function url_view($params=array()) {
        return url_helpers::url_view($this->presence, $params);
    }

    /**
     * url helper.
     * @param array $params
     * @return moodle_url
     */
    public function url($params=array()) {
        $params = array_merge($params, $this->pageparams->get_significant_params());

        return $this->presence->url_report($params);
    }

}

/**
 * Class preferences data.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presence_preferences_data implements renderable {
    /** @var array  */
    public $statuses;
    /** @var mod_presence_structure  */
    private $presence;
    /** @var array  */
    public $errors;

    /**
     * presence_preferences_data constructor.
     * @param mod_presence_structure $presence
     * @param array $errors
     */
    public function __construct(mod_presence_structure $presence, $errors) {
        $this->statuses = $presence->get_statuses(false);
        $this->errors = $errors;

        foreach ($this->statuses as $st) {
            $st->haslogs = presence_has_logs_for_status($st->id);
        }

        $this->presence = $presence;
    }

    /**
     * url helper function
     * @param array $params
     * @param bool $significantparams
     * @return moodle_url
     */
    public function url($params=array(), $significantparams=true) {
        if ($significantparams) {
            $params = array_merge($this->presence->pageparams->get_significant_params(), $params);
        }

        return $this->presence->url_preferences($params);
    }
}

/**
 * Default status set
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presence_default_statusset implements renderable {
    /** @var array  */
    public $statuses;
    /** @var array  */
    public $errors;

    /**
     * presence_default_statusset constructor.
     * @param array $statuses
     * @param array $errors
     */
    public function __construct($statuses, $errors) {
        $this->statuses = $statuses;
        $this->errors = $errors;
    }

    /**
     * url helper.
     * @param stdClass $params
     * @return moodle_url
     */
    public function url($params) {
        return new moodle_url('/mod/presence/defaultstatus.php', $params);
    }
}

/**
 * Output a selector to change between status sets.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presence_set_selector implements renderable {
    /** @var int  */
    public $maxstatusset;
    /** @var mod_presence_structure  */
    private $presence;

    /**
     * presence_set_selector constructor.
     * @param mod_presence_structure $presence
     * @param int $maxstatusset
     */
    public function __construct(mod_presence_structure $presence, $maxstatusset) {
        $this->presence = $presence;
        $this->maxstatusset = $maxstatusset;
    }

    /**
     * url helper
     * @param array $statusset
     * @return moodle_url
     */
    public function url($statusset) {
        $params = array();
        $params['statusset'] = $statusset;

        return $this->presence->url_preferences($params);
    }

    /**
     * get current statusset.
     * @return int
     */
    public function get_current_statusset() {
        if (isset($this->presence->pageparams->statusset)) {
            return $this->presence->pageparams->statusset;
        }
        return 0;
    }

    /**
     * get statusset name.
     * @param int $statusset
     * @return string
     */
    public function get_status_name($statusset) {
        return presence_get_setname($this->presence->id, $statusset, true);
    }
}

/**
 * Url helpers
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class url_helpers {
    /**
     * Url take.
     * @param stdClass $presence
     * @param int $sessionid
     * @param int $grouptype
     * @return mixed
     */
    public static function url_take($presence, $sessionid, $grouptype) {
        $params = array('sessionid' => $sessionid);
        if (isset($grouptype)) {
            $params['grouptype'] = $grouptype;
        }

        return $presence->url_take($params);
    }

    /**
     * Must be called without or with both parameters
     * @param stdClass $presence
     * @param null $sessionid
     * @param null $action
     * @return mixed
     */
    public static function url_sessions($presence, $sessionid=null, $action=null) {
        if (isset($sessionid) && isset($action)) {
            $params = array('sessionid' => $sessionid, 'action' => $action);
        } else {
            $params = array();
        }

        return $presence->url_sessions($params);
    }

    /**
     * Must be called without or with both parameters
     * @param stdClass $presence
     * @param null $sessionid
     * @param null $action
     * @return mixed
     */
    public static function url_evaluation($presence, $sessionid=null, $action=null) {
        if (isset($sessionid) && isset($action)) {
            $params = array('sessionid' => $sessionid, 'action' => $action);
        } else {
            $params = array();
        }

        return $presence->url_evaluation($params);
    }

    /**
     * Url view helper.
     * @param stdClass $presence
     * @param array $params
     * @return mixed
     */
    public static function url_view($presence, $params=array()) {
        return $presence->url_view($params);
    }
}

/**
 * Data structure representing an presence password icon.
 *
 * @copyright 2017 Dan Marsden
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presence_password_icon implements renderable, templatable {

    /**
     * @var string text to show
     */
    public $text;

    /**
     * @var string Extra descriptive text next to the icon
     */
    public $linktext = null;

    /**
     * Constructor
     *
     * @param string $text string for help page title,
     *  string with _help suffix is used for the actual help text.
     *  string with _link suffix is used to create a link to further info (if it exists)
     * @param string $sessionid
     */
    public function __construct($text, $sessionid) {
        $this->text  = $text;
        $this->sessionid = $sessionid;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return array
     */
    public function export_for_template(renderer_base $output) {

        $title = get_string('password', 'presence');

        $data = new stdClass();
        $data->heading = '';
        $data->text = $this->text;

        if ($this->includeqrcode == 1) {
            $pix = 'qrcode';
        } else {
            $pix = 'key';
        }

        $data->alt = $title;
        $data->icon = (new pix_icon($pix, '', 'presence'))->export_for_template($output);
        $data->linktext = '';
        $data->title = $title;
        $data->url = (new moodle_url('/mod/presence/password.php', [
            'session' => $this->sessionid]))->out(false);

        $data->ltr = !right_to_left();
        return $data;
    }
}
