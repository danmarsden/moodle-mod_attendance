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
 * Class user data.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance_user_data implements renderable {
    /** @var mixed|object  */
    public $user;
    /** @var array|null|stdClass  */
    public $pageparams;
    /** @var array  */
    public $statuses;
    /** @var array  */
    public $summary;
    /** @var mod_attendance\output\filter_controls  */
    public $filtercontrols;
    /** @var array  */
    public $sessionslog;
    /** @var array  */
    public $groups;
    /** @var array  */
    public $coursesatts;
    /** @var string  */
    private $urlpath;
    /** @var array */
    private $urlparams;

    /**
     * attendance_user_data constructor.
     * @param mod_attendance_structure $att
     * @param int $userid
     * @param boolean $mobile - this is called by the mobile code, don't generate everything.
     */
    public function  __construct(mod_attendance_structure $att, $userid, $mobile = false) {
        $this->user = $att->get_user($userid);

        $this->pageparams = $att->pageparams;

        if ($this->pageparams->mode == mod_attendance_view_page_params::MODE_THIS_COURSE) {
            $this->statuses = $att->get_statuses(true, true);

            if (!$mobile) {
                $this->summary = new mod_attendance_summary($att->id, array($userid), $att->pageparams->startdate,
                    $att->pageparams->enddate);

                $this->filtercontrols = new mod_attendance\output\filter_controls($att);
            }

            $this->sessionslog = $att->get_user_filtered_sessions_log_extended($userid);

            $this->groups = groups_get_all_groups($att->course->id);
        } else if ($this->pageparams->mode == mod_attendance_view_page_params::MODE_ALL_SESSIONS) {
            $this->coursesatts = attendance_get_user_courses_attendances($userid);
            $this->statuses = array();
            $this->summaries = array();
            $this->groups = array();

            foreach ($this->coursesatts as $atid => $ca) {
                // Check to make sure the user can view this cm.
                $modinfo = get_fast_modinfo($ca->courseid);
                if (!$modinfo->instances['attendance'][$ca->attid]->uservisible) {
                    unset($this->coursesatts[$atid]);
                    continue;
                } else {
                    $this->coursesatts[$atid]->cmid = $modinfo->instances['attendance'][$ca->attid]->get_course_module_record()->id;
                }
                $this->statuses[$ca->attid] = attendance_get_statuses($ca->attid);
                $this->summaries[$ca->attid] = new mod_attendance_summary($ca->attid, array($userid));

                if (!array_key_exists($ca->courseid, $this->groups)) {
                    $this->groups[$ca->courseid] = groups_get_all_groups($ca->courseid);
                }
            }

            if (!$mobile) {
                $this->summary = new mod_attendance_summary($att->id, array($userid), $att->pageparams->startdate,
                    $att->pageparams->enddate);

                $this->filtercontrols = new mod_attendance\output\filter_controls($att);
            }

            $this->sessionslog = attendance_get_user_sessions_log_full($userid, $this->pageparams);

            foreach ($this->sessionslog as $sessid => $sess) {
                $this->sessionslog[$sessid]->cmid = $this->coursesatts[$sess->attendanceid]->cmid;
            }

        } else {
            $this->coursesatts = attendance_get_user_courses_attendances($userid);
            $this->statuses = array();
            $this->summary = array();
            foreach ($this->coursesatts as $atid => $ca) {
                // Check to make sure the user can view this cm.
                $modinfo = get_fast_modinfo($ca->courseid);
                if (!$modinfo->instances['attendance'][$ca->attid]->uservisible) {
                    unset($this->coursesatts[$atid]);
                    continue;
                } else {
                    $this->coursesatts[$atid]->cmid = $modinfo->instances['attendance'][$ca->attid]->get_course_module_record()->id;
                }
                $this->statuses[$ca->attid] = attendance_get_statuses($ca->attid);
                $this->summary[$ca->attid] = new mod_attendance_summary($ca->attid, array($userid));
            }
        }
        $this->urlpath = $att->url_view()->out_omit_querystring();
        $params = $att->pageparams->get_significant_params();
        $params['id'] = $att->cm->id;
        $this->urlparams = $params;
    }

    /**
     * Url function
     * @param array $params
     * @param array $excludeparams
     * @return moodle_url
     */
    public function url($params=array(), $excludeparams=array()) {
        $params = array_merge($this->urlparams, $params);

        foreach ($excludeparams as $paramkey) {
            unset($params[$paramkey]);
        }

        return new moodle_url($this->urlpath, $params);
    }

    /**
     * Take multiple sessions attendance from form data.
     *
     * @param stdClass $formdata
     */
    public function take_sessions_from_form_data($formdata) {
        global $DB, $USER;
        // TODO: WARNING - $formdata is unclean - comes from direct $_POST - ideally needs a rewrite but we do some cleaning below.
        // This whole function could do with a nice clean up.

        $now = time();
        $sesslog = array();
        $formdata = (array)$formdata;
        $updatedsessions = array();
        $sessionatt = array();

        foreach ($formdata as $key => $value) {
            // Look at Remarks field because the user options may not be passed if empty.
            if (substr($key, 0, 7) == 'remarks') {
                $parts = explode('sess', substr($key, 7));
                $stid = $parts[0];
                if (!(is_numeric($stid))) { // Sanity check on $stid.
                    throw new moodle_exception('nonnumericid', 'attendance');
                }
                $sessid = $parts[1];
                if (!(is_numeric($sessid))) { // Sanity check on $sessid.
                    throw new moodle_exception('nonnumericid', 'attendance');
                }
                $dbsession = $this->sessionslog[$sessid];

                $context = context_module::instance($dbsession->cmid);
                if (!has_capability('mod/attendance:takeattendances', $context)) {
                    // How do we tell user about this?
                    \core\notification::warning(get_string("nocapabilitytotakethisattendance", "attendance", $dbsession->cmid));
                    continue;
                }

                $formkey = 'user'.$stid.'sess'.$sessid;
                $attid = $dbsession->attendanceid;
                $statusset = array_filter($this->statuses[$attid],
                    function($x) use($dbsession) {
                        return $x->setnumber === $dbsession->statusset;
                    });
                $sessionatt[$sessid] = $attid;
                $formlog = new stdClass();
                if (array_key_exists($formkey, $formdata) && is_numeric($formdata[$formkey])) {
                    $formlog->statusid = $formdata[$formkey];
                }
                $formlog->studentid = $stid; // We check is_numeric on this above.
                $formlog->statusset = implode(',', array_keys($statusset));
                $formlog->remarks = $value;
                $formlog->sessionid = $sessid;
                $formlog->timetaken = $now;
                $formlog->takenby = $USER->id;

                if (!array_key_exists($stid, $sesslog)) {
                    $sesslog[$stid] = array();
                }
                $sesslog[$stid][$sessid] = $formlog;
            }
        }

        $updateatts = array();
        foreach ($sesslog as $stid => $userlog) {
            $dbstudlog = $DB->get_records('attendance_log', array('studentid' => $stid), '',
                'sessionid,statusid,remarks,id,statusset');
            foreach ($userlog as $log) {
                if (array_key_exists($log->sessionid, $dbstudlog)) {
                    $attid = $sessionatt[$log->sessionid];
                    // Check if anything important has changed before updating record.
                    // Don't update timetaken/takenby records if nothing has changed.
                    if ($dbstudlog[$log->sessionid]->remarks != $log->remarks ||
                        $dbstudlog[$log->sessionid]->statusid != $log->statusid ||
                        $dbstudlog[$log->sessionid]->statusset != $log->statusset) {

                        $log->id = $dbstudlog[$log->sessionid]->id;
                        $DB->update_record('attendance_log', $log);

                        $updatedsessions[$log->sessionid] = $log->sessionid;
                        if (!array_key_exists($attid, $updateatts)) {
                            $updateatts[$attid] = array();
                        }
                        array_push($updateatts[$attid], $log->studentid);
                    }
                } else {
                    $DB->insert_record('attendance_log', $log, false);
                    $updatedsessions[$log->sessionid] = $log->sessionid;
                    if (!array_key_exists($attid, $updateatts)) {
                        $updateatts[$attid] = array();
                    }
                    array_push($updateatts[$attid], $log->studentid);
                }
            }
        }

        foreach ($updatedsessions as $sessionid) {
            $session = $this->sessionslog[$sessionid];
            $session->lasttaken = $now;
            $session->lasttakenby = $USER->id;
            $DB->update_record('attendance_sessions', $session);
        }

        if (!empty($updateatts)) {
            $attendancegrade = $DB->get_records_list('attendance', 'id', array_keys($updateatts), '', 'id, grade');
            foreach ($updateatts as $attid => $updateusers) {
                if ($attendancegrade[$attid] != 0) {
                    attendance_update_users_grades_by_id($attid, $grade, $updateusers);
                }
            }
        }
    }
}

/**
 * Class report data.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance_report_data implements renderable {
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
    /** @var array|mod_attendance_summary  */
    public $summary = array();
    /** @var mod_attendance_structure  */
    public $att;

    /**
     * attendance_report_data constructor.
     * @param mod_attendance_structure $att
     */
    public function  __construct(mod_attendance_structure $att) {
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
        $this->allstatuses = attendance_get_statuses($att->id, false);

        if ($att->pageparams->view == ATT_VIEW_SUMMARY) {
            $this->summary = new mod_attendance_summary($att->id);
        } else {
            $this->summary = new mod_attendance_summary($att->id, array_keys($this->users),
                                                        $att->pageparams->startdate, $att->pageparams->enddate);
        }

        foreach ($this->users as $key => $user) {
            $usersummary = $this->summary->get_taken_sessions_summary_for($user->id);
            if ($att->pageparams->view != ATT_VIEW_NOTPRESENT ||
                attendance_calc_fraction($usersummary->takensessionspoints, $usersummary->takensessionsmaxpoints) <
                $att->get_lowgrade_threshold()) {

                $this->usersgroups[$user->id] = groups_get_all_groups($att->course->id, $user->id);

                $this->sessionslog[$user->id] = $att->get_user_filtered_sessions_log($user->id);
            } else {
                unset($this->users[$key]);
            }
        }

        $this->att = $att;
    }

    /**
     * url take helper.
     * @param int $sessionid
     * @param int $grouptype
     * @return mixed
     */
    public function url_take($sessionid, $grouptype) {
        return url_helpers::url_take($this->att, $sessionid, $grouptype);
    }

    /**
     * url view helper.
     * @param array $params
     * @return mixed
     */
    public function url_view($params=array()) {
        return url_helpers::url_view($this->att, $params);
    }

    /**
     * url helper.
     * @param array $params
     * @return moodle_url
     */
    public function url($params=array()) {
        $params = array_merge($params, $this->pageparams->get_significant_params());

        return $this->att->url_report($params);
    }

}

/**
 * Class preferences data.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance_preferences_data implements renderable {
    /** @var array  */
    public $statuses;
    /** @var mod_attendance_structure  */
    private $att;
    /** @var array  */
    public $errors;

    /**
     * attendance_preferences_data constructor.
     * @param mod_attendance_structure $att
     * @param array $errors
     */
    public function __construct(mod_attendance_structure $att, $errors) {
        $this->statuses = $att->get_statuses(false);
        $this->errors = $errors;

        foreach ($this->statuses as $st) {
            $st->haslogs = attendance_has_logs_for_status($st->id);
        }

        $this->att = $att;
    }

    /**
     * url helper function
     * @param array $params
     * @param bool $significantparams
     * @return moodle_url
     */
    public function url($params=array(), $significantparams=true) {
        if ($significantparams) {
            $params = array_merge($this->att->pageparams->get_significant_params(), $params);
        }

        return $this->att->url_preferences($params);
    }
}

/**
 * Default status set
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance_default_statusset implements renderable {
    /** @var array  */
    public $statuses;
    /** @var array  */
    public $errors;

    /**
     * attendance_default_statusset constructor.
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
        return new moodle_url('/mod/attendance/defaultstatus.php', $params);
    }
}

/**
 * Output a selector to change between status sets.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance_set_selector implements renderable {
    /** @var int  */
    public $maxstatusset;
    /** @var mod_attendance_structure  */
    private $att;

    /**
     * attendance_set_selector constructor.
     * @param mod_attendance_structure $att
     * @param int $maxstatusset
     */
    public function __construct(mod_attendance_structure $att, $maxstatusset) {
        $this->att = $att;
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

        return $this->att->url_preferences($params);
    }

    /**
     * get current statusset.
     * @return int
     */
    public function get_current_statusset() {
        if (isset($this->att->pageparams->statusset)) {
            return $this->att->pageparams->statusset;
        }
        return 0;
    }

    /**
     * get statusset name.
     * @param int $statusset
     * @return string
     */
    public function get_status_name($statusset) {
        return attendance_get_setname($this->att->id, $statusset, true);
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
     * @param stdClass $att
     * @param int $sessionid
     * @param int $grouptype
     * @return mixed
     */
    public static function url_take($att, $sessionid, $grouptype) {
        $params = array('sessionid' => $sessionid);
        if (isset($grouptype)) {
            $params['grouptype'] = $grouptype;
        }

        return $att->url_take($params);
    }

    /**
     * Must be called without or with both parameters
     * @param stdClass $att
     * @param null $sessionid
     * @param null $action
     * @return mixed
     */
    public static function url_sessions($att, $sessionid=null, $action=null) {
        if (isset($sessionid) && isset($action)) {
            $params = array('sessionid' => $sessionid, 'action' => $action);
        } else {
            $params = array();
        }

        return $att->url_sessions($params);
    }

    /**
     * Url view helper.
     * @param stdClass $att
     * @param array $params
     * @return mixed
     */
    public static function url_view($att, $params=array()) {
        return $att->url_view($params);
    }
}

/**
 * Data structure representing an attendance password icon.
 *
 * @copyright 2017 Dan Marsden
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance_password_icon implements renderable, templatable {

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

        $title = get_string('password', 'attendance');

        $data = new stdClass();
        $data->heading = '';
        $data->text = $this->text;

        if ($this->includeqrcode == 1) {
            $pix = 'qrcode';
        } else {
            $pix = 'key';
        }

        $data->alt = $title;
        $data->icon = (new pix_icon($pix, '', 'attendance'))->export_for_template($output);
        $data->linktext = '';
        $data->title = $title;
        $data->url = (new moodle_url('/mod/attendance/password.php', [
            'session' => $this->sessionid]))->out(false);

        $data->ltr = !right_to_left();
        return $data;
    }
}
