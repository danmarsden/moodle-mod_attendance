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
 * Class definition for mod_attendance_structure
 *
 * @package   mod_attendance
 * @copyright  2016 Dan Marsden http://danmarsden.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG; // This class is included inside existing functions.
require_once(dirname(__FILE__) . '/calendar_helpers.php');
require_once($CFG->libdir .'/filelib.php');

/**
 * Main class with all Attendance related info.
 *
 * @copyright  2016 Dan Marsden http://danmarsden.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_attendance_structure {
    /** Common sessions */
    const SESSION_COMMON        = 0;
    /** Group sessions */
    const SESSION_GROUP         = 1;

    /** @var stdclass course module record */
    public $cm;

    /** @var int cmid - needed for calendar internal tests (see Issue #473) */
    public $cmid;

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

    /** @var int last time attendance was modified - used for global search */
    public $timemodified;

    /** @var string required field for activity modules and searching */
    public $intro;

    /** @var int format of the intro (see above) */
    public $introformat;

    /** @var array current page parameters */
    public $pageparams;

    /** @var string subnets (IP range) for student self selection. */
    public $subnet;

    /** @var string subnets (IP range) for student self selection. */
    public $automark;

    /** @var boolean flag set when automarking is complete. */
    public $automarkcompleted;

    /** @var int Define if extra user details should be shown in reports */
    public $showextrauserdetails;

    /** @var int Define if session details should be shown in reports */
    public $showsessiondetails;

    /** @var int Position for the session detail columns related to summary columns.*/
    public $sessiondetailspos;

    /** @var int groupmode  */
    private $groupmode;

    /** @var  array */
    private $statuses;
    /** @var  array Cache list of all statuses (not just one used by current session). */
    private $allstatuses;

    /** @var array of sessionid. */
    private $sessioninfo = array();

    /** @var float number [0..1], the threshold for student to be shown at low grade report */
    private $lowgradethreshold;


    /**
     * Initializes the attendance API instance using the data from DB
     *
     * Makes deep copy of all passed records properties. Replaces integer $course attribute
     * with a full database record (course should not be stored in instances table anyway).
     *
     * @param stdClass $dbrecord Attandance instance data from {attendance} table
     * @param stdClass $cm       Course module record as returned by {@see get_coursemodule_from_id()}
     * @param stdClass $course   Course record from {course} table
     * @param stdClass $context  The context of the workshop instance
     * @param stdClass $pageparams
     */
    public function __construct(stdClass $dbrecord, stdClass $cm, stdClass $course, stdClass $context=null, $pageparams=null) {
        global $DB;

        foreach ($dbrecord as $field => $value) {
            if (property_exists('mod_attendance_structure', $field)) {
                $this->{$field} = $value;
            } else {
                throw new coding_exception('The attendance table has a field with no property in the attendance class');
            }
        }
        $this->cm           = $cm;
        if (empty($this->cmid)) {
            $this->cmid = $cm->id;
        }
        $this->course       = $course;
        if (is_null($context)) {
            $this->context = context_module::instance($this->cm->id);
        } else {
            $this->context = $context;
        }

        $this->pageparams = $pageparams;

        if (isset($pageparams->showextrauserdetails) && $pageparams->showextrauserdetails != $this->showextrauserdetails) {
            $DB->set_field('attendance', 'showextrauserdetails', $pageparams->showextrauserdetails, array('id' => $this->id));
        }
        if (isset($pageparams->showsessiondetails) && $pageparams->showsessiondetails != $this->showsessiondetails) {
            $DB->set_field('attendance', 'showsessiondetails', $pageparams->showsessiondetails, array('id' => $this->id));
        }
        if (isset($pageparams->sessiondetailspos) && $pageparams->sessiondetailspos != $this->sessiondetailspos) {
            $DB->set_field('attendance', 'sessiondetailspos', $pageparams->sessiondetailspos, array('id' => $this->id));
        }
    }



    /**
     * Returns current sessions for this attendance
     *
     * Fetches data from {attendance_sessions}
     *
     * @return array of records or an empty array
     */
    public function get_current_sessions() : array {
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
    public function get_today_sessions() : array {
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
     * Get filtered sessions.
     *
     * @return array
     */
    public function get_filtered_sessions() : array {
        global $DB;

        $where = "attendanceid = :aid";

        if ($this->pageparams->startdate) {
            $where .= " AND sessdate >= :sdate";
        }
        if ($this->pageparams->enddate) {
            $where .= " AND sessdate < :edate";
        }

        $params = array(
            'aid'       => $this->id,
            'csdate'    => $this->course->startdate,
            'sdate'     => $this->pageparams->startdate,
            'edate'     => $this->pageparams->enddate,
            'cgroup'    => $this->pageparams->get_current_sesstype());

        $sessions = $DB->get_records_sql(
            "SELECT atts.*, attr.name as roomname,
                        (SELECT COUNT(*) FROM {attendance_bookings} AS attb WHERE atts.id = attb.sessionid) as bookings
                   FROM {attendance_sessions} AS atts
              LEFT JOIN {attendance_rooms} AS attr ON atts.roomid = attr.id
                  WHERE $where
               ORDER BY sessdate ASC", $params);

        return $this->add_session_details($sessions);
    }


    /**
     * Add some details to a session object from the db
     *
     * @param $sessions
     * @return array the improved session objects
     * @throws coding_exception
     */
    private function add_session_details($sessions) : array {
        foreach ($sessions as $sess) {
            if (empty($sess->description)) {
                $sess->description = get_string('nodescription', 'attendance');
            } else {
                $sess->description = file_rewrite_pluginfile_urls($sess->description,
                    'pluginfile.php', $this->context->id, 'mod_attendance', 'session', $sess->id);
            }
            // TODO: localize time format
            $sess->maxattendants = intval($sess->maxattendants);
            $sess->timefrom = date("H:i", $sess->sessdate);
            $sess->timeto = date("H:i", $sess->sessdate + $sess->duration);

            if (has_capability('mod/attendance:manageattendances', $this->context)) {
                $sess->urledit = url_helpers::url_sessions($this, $sess->id, mod_attendance_sessions_page_params::ACTION_UPDATE);
                $sess->urldelete = url_helpers::url_sessions($this, $sess->id, mod_attendance_sessions_page_params::ACTION_DELETE);
                $sess->urlevaluate = $this->url_evaluation(['sessionid' => $sess->id, 'action' => mod_attendance_sessions_page_params::ACTION_EVALUATE ]);
            }
        }
        return $sessions;
    }

    /**
     * Get manage url.
     * @param array $params
     * @return moodle_url of manage.php for attendance instance
     */
    public function url_manage($params=array()) : moodle_url {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/manage.php', $params);
    }




    /**
     * Get url for sessions.
     * @param array $params
     * @return moodle_url of sessions.php for attendance instance
     */
    public function url_sessions($params=array()) : moodle_url {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/sessions.php', $params);
    }




    /**
     * Get evaluation url.
     * @param array $params
     * @return moodle_url of attsettings.php for attendance instance
     */
    public function url_evaluation($params=array()) : moodle_url {
        if((isset($params['sessionid']) && !$params['sessionid']) || (isset($params['action']) && !$params['action'])) {
            unset($params['sessionid']);
            unset($params['action']);
        }
        $params = array_merge( array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/evaluation.php', $params);
    }

    /**
     * Get user profile url.
     * @param array $params
     * @return moodle_url of attsettings.php for attendance instance
     */
    public function url_userprofile($params=array()) : moodle_url {
        if(!isset($params['userid']) || !$params['userid']) {
            throw new \coding_exception('User id needed to create user profile URL.');
        }
        $params = array_merge( array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/userprofile.php', $params);
    }



    /**
     * Get view url.
     * @param array $params
     * @return moodle_url
     */
    public function url_view($params=array()) : moodle_url {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/view.php', $params);
    }

    /**
     * Add sessions.
     *
     * @param array $sessions
     */
    public function add_sessions($sessions) {
        foreach ($sessions as $sess) {
            $this->add_session($sess);
        }
    }

    /**
     * Add single session.
     *
     * @param stdClass $sess
     * @return int $sessionid
     */
    public function add_session($sess) : int {
        global $DB;
        $config = get_config('attendance');

        $sess->attendanceid = $this->id;
        $sess->calendarevent = 1;
        $sess->statusset = 0;
        $sess->id = $DB->insert_record('attendance_sessions', $sess);
        $description = file_save_draft_area_files($sess->descriptionitemid,
            $this->context->id, 'mod_attendance', 'session', $sess->id,
            array('subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0),
            $sess->description);
        $DB->set_field('attendance_sessions', 'description', $description, array('id' => $sess->id));

        $sess->caleventid = 0;
        attendance_create_calendar_event($sess);

        $infoarray = array();
        $infoarray[] = construct_session_full_date_time($sess->sessdate, $sess->duration);

        // Trigger a session added event.
        $event = \mod_attendance\event\session_added::create(array(
            'objectid' => $this->id,
            'context' => $this->context,
            'other' => array('info' => implode(',', $infoarray))
        ));
        $event->add_record_snapshot('course_modules', $this->cm);
        $sess->description = $description;
        $sess->lasttaken = 0;
        $sess->lasttakenby = 0;
        $sess->roomid = 0;
        $sess->maxattendants = 0;

        $event->add_record_snapshot('attendance_sessions', $sess);
        $event->trigger();
        return $sess->id;
    }

    /**
     * Update session from form.
     *
     * @param stdClass $formdata
     * @param int $sessionid
     */
    public function update_session_from_form_data($formdata, $sessionid) {
        global $DB;

        if (!$sess = $DB->get_record('attendance_sessions', array('id' => $sessionid) )) {
            print_error('No such session in this course');
        }

        $sesstarttime = $formdata->sestime['starthour'] * HOURSECS + $formdata->sestime['startminute'] * MINSECS;
        $sesendtime = $formdata->sestime['endhour'] * HOURSECS + $formdata->sestime['endminute'] * MINSECS;

        $sess->sessdate = $formdata->sessiondate + $sesstarttime;
        $sess->duration = $sesendtime - $sesstarttime;

        $description = file_save_draft_area_files($formdata->sdescription['itemid'],
            $this->context->id, 'mod_attendance', 'session', $sessionid,
            array('subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0), $formdata->sdescription['text']);
        $sess->description = $description;
        $sess->descriptionformat = $formdata->sdescription['format'];
        $sess->calendarevent = empty($formdata->calendarevent) ? 0 : $formdata->calendarevent;

        $sess->roomid = $formdata->roomid;
        $sess->maxattendants = $formdata->maxattendants;

        $sess->timemodified = time();
        $DB->update_record('attendance_sessions', $sess);

        if (empty($sess->caleventid)) {
             // This shouldn't really happen, but just in case to prevent fatal error.
            attendance_create_calendar_event($sess);
        } else {
            attendance_update_calendar_event($sess);
        }

        $info = construct_session_full_date_time($sess->sessdate, $sess->duration);
        $event = \mod_attendance\event\session_updated::create(array(
            'objectid' => $this->id,
            'context' => $this->context,
            'other' => array('info' => $info, 'sessionid' => $sessionid,
                'action' => mod_attendance_sessions_page_params::ACTION_UPDATE)));
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->add_record_snapshot('attendance_sessions', $sess);
        $event->trigger();
    }



    /**
     * Helper function to save attendance and trigger events.
     *
     * @param array $sesslog
     * @throws coding_exception
     * @throws dml_exception
     */
    public function save_log($sesslog) {
        global $DB, $USER;
        // Get existing session log.
        $dbsesslog = $this->get_session_log($this->pageparams->sessionid);
        foreach ($sesslog as $log) {
            // Don't save a record if no statusid or remark.
            if (!empty($log->statusid) || !empty($log->remarks)) {
                if (array_key_exists($log->studentid, $dbsesslog)) {
                    // Check if anything important has changed before updating record.
                    // Don't update timetaken/takenby records if nothing has changed.
                    if ($dbsesslog[$log->studentid]->remarks <> $log->remarks ||
                        $dbsesslog[$log->studentid]->statusid <> $log->statusid ||
                        $dbsesslog[$log->studentid]->statusset <> $log->statusset) {

                        $log->id = $dbsesslog[$log->studentid]->id;
                        $DB->update_record('attendance_evaluations', $log);
                    }
                } else {
                    $DB->insert_record('attendance_evaluations', $log, false);
                }
            }
        }

        $session = $this->get_session_info($this->pageparams->sessionid);
        $session->lasttaken = time();
        $session->lasttakenby = $USER->id;

        $DB->update_record('attendance_sessions', $session);

        if ($this->grade != 0) {
            $this->update_users_grade(array_keys($sesslog));
        }

        // Create url for link in log screen.
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
    }

    /**
     * Get filtered list of users
     *
     * params:
     * - page (default: 0)
     * - sessionid (default: 0)
     * - evaluation (default: false) get evaluation for session
     * - enrolled (default: true)
     * - sort (ATT_SORT_DEFAULT | ATT_SORT_FIRSTNAME | ATT_SORT_LASTNAME)
     *
     * @param array $params
     * @return array
     */
    public function get_users($params) : array {
        global $DB;

        $page = isset($params['$page']) ? intval($params['$page']) : 0;
        $sessionid = isset($params['sessionid']) ? intval($params['sessionid']) : 0;
        $evaluation = isset($params['evaluation']) ? intval($params['evaluation']) : 0;
        $enrolled = isset($params['enrolled']) ? boolval($params['enrolled']) : true;
        $sort = isset($params['sort']) ?
            intval($params['sort'])
            : (empty($this->pageparams->sort) ?
                ATT_SORT_DEFAULT
                : intval($this->pageparams->sort));

        switch($sort) {
            case ATT_SORT_FIRSTNAME:
                $orderby = $DB->sql_fullname('u.firstname', 'u.lastname') . ', u.id';
                break;
            case ATT_SORT_LASTNAME:
                $orderby = 'u.lastname, u.firstname, u.id';
                break;
            default:
                list($orderby, $sortparams) = users_order_by_sql('u');
                break;
        }

        $fields = array('username' , 'idnumber' , 'institution' , 'department', 'city', 'country');
        // Get user identity fields if required - doesn't return original $fields array.
//        $extrafields = get_extra_user_fields($this->context, $fields);
//        $fields = array_merge($fields, $extrafields);
        $userfields = user_picture::fields('u', $fields);

        if ($page) {
            $usersperpage = $this->pageparams->perpage;
            $startusers = ($page - 1) * $usersperpage;
        } else {
            $usersperpage = 0;
            $startusers = 0;
        }
        $users = get_enrolled_users($this->context, 'mod/attendance:canbelisted',
            0, $userfields, $orderby, $startusers, $usersperpage);

        // Add a flag to each user indicating whether their enrolment is active.
        if (!empty($users)) {
            list($sql, $params) = $DB->get_in_or_equal(array_keys($users), SQL_PARAMS_NAMED, 'usid0');

            // See CONTRIB-4868.
            $mintime = 'MIN(CASE WHEN (ue.timestart > :zerotime) THEN ue.timestart ELSE ue.timecreated END)';
            $maxtime = 'CASE WHEN MIN(ue.timeend) = 0 THEN 0 ELSE MAX(ue.timeend) END';

            // See CONTRIB-3549.
            $sql = "SELECT ue.userid, MIN(ue.status) as status,
                           $mintime AS mintime,
                           $maxtime AS maxtime,
                           COUNT(attb.id) AS booked
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                 LEFT JOIN {attendance_bookings} attb ON ue.userid = attb.userid AND attb.sessionid = :sessionid
                     WHERE ue.userid $sql
                           AND e.status = :estatus
                           AND e.courseid = :courseid
                  GROUP BY ue.userid";
            $params += array(
                'zerotime' => 0,
                'estatus' => ENROL_INSTANCE_ENABLED,
                'courseid' => $this->course->id,
                'sessionid' => $sessionid
            );
            $enrolments = $DB->get_records_sql($sql, $params);

            if ($evaluation) {
                $sessionlogs = $DB->get_records("attendance_evaluations", ['sessionid' => $sessionid]);
                foreach ($sessionlogs as $log) {
                    if (array_key_exists($log->studentid, $users)) {
                        $users[$log->studentid]->duration = $log->duration;
                        $users[$log->studentid]->remarks_course = $log->remarks_course;
                        $users[$log->studentid]->remarks_personality = $log->remarks_personality;
                    }
                }
            }

            foreach ($users as $user) {
                $users[$user->id]->fullname = fullname($user);
                $users[$user->id]->enrolmentstatus = $enrolments[$user->id]->status;
                $users[$user->id]->enrolmentstart = $enrolments[$user->id]->mintime;
                $users[$user->id]->enrolmentend = $enrolments[$user->id]->maxtime;
                $users[$user->id]->type = 'standard'; // Mark as a standard (not a temporary) user.
                $users[$user->id]->booked = $enrolments[$user->id]->booked;
                $users[$user->id]->profileurl = $this->url_userprofile(['userid' => $user->id]);

            }
        }


        foreach ($users as $user) {
            $user->picturebigurl = new moodle_url("/user/pix.php/{$user->id}/f1.jpg", []);
            $user->picturesmallurl = new moodle_url("/user/pix.php/{$user->id}/f2", []);
        }

        return $users;
    }

    /**
     * Get user and include extra info.
     *
     * @param int $userid
     * @return mixed|object
     */
    public function get_user($userid) {
        global $DB;

        $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

        $user->type = 'standard';
        $user->sws = $this->get_user_sws($userid);
        $user->swspercent = round($user->sws / 7 * 100);
        $user->swslong = get_string('sws_level_'.$user->sws, 'attendance');
        $user->swsshort = get_string('sws_short', 'attendance', $user->sws);

        // See CONTRIB-4868.
        $mintime = 'MIN(CASE WHEN (ue.timestart > :zerotime) THEN ue.timestart ELSE ue.timecreated END)';
        $maxtime = 'CASE WHEN MIN(ue.timeend) = 0 THEN 0 ELSE MAX(ue.timeend) END';

        $sql = "SELECT ue.userid, ue.status,
                       $mintime AS mintime,
                       $maxtime AS maxtime
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :uid
                       AND e.status = :estatus
                       AND e.courseid = :courseid
              GROUP BY ue.userid, ue.status";
        $params = array('zerotime' => 0, 'uid' => $userid, 'estatus' => ENROL_INSTANCE_ENABLED, 'courseid' => $this->course->id);
        $enrolments = $DB->get_record_sql($sql, $params);
        if (!empty($enrolments)) {
            $user->enrolmentstatus = $enrolments->status;
            $user->enrolmentstart = $enrolments->mintime;
            $user->enrolmentend = $enrolments->maxtime;
        } else {
            $user->enrolmentstatus = '';
            $user->enrolmentstart = 0;
            $user->enrolmentend = 0;
        }
        $user->profileurl = $this->url_userprofile(['userid' => $user->id]);
        $user->picturebigurl = new moodle_url("/user/pix.php/{$user->id}/f1.jpg", []);
        $user->picturesmallurl = new moodle_url("/user/pix.php/{$user->id}/f2", []);
        return $user;
    }

    /**
     * Get SWS of give user.
     * @param $userid
     * @return false|mixed
     * @throws dml_exception
     */
    public function get_user_sws($userid) {
        global $DB;
        return intval($DB->get_field_sql(
            'SELECT sws FROM mdl_attendance_sws WHERE userid = :userid ORDER BY timemodified DESC LIMIT 1',
            ['userid' => $userid]));
    }



    /**
     * Get session info.
     * @param int $sessionid
     * @return mixed
     */
    public function get_session_info($sessionid) {
        global $DB;

        if (!array_key_exists($sessionid, $this->sessioninfo)) {
            $this->sessioninfo[$sessionid] = $DB->get_record('attendance_sessions', array('id' => $sessionid));
        }
        if (empty($this->sessioninfo[$sessionid]->description)) {
            $this->sessioninfo[$sessionid]->description = get_string('nodescription', 'attendance');
        } else {
            $this->sessioninfo[$sessionid]->description = file_rewrite_pluginfile_urls(strip_tags($this->sessioninfo[$sessionid]->description),
                'pluginfile.php', $this->context->id, 'mod_attendance', 'session', $this->sessioninfo[$sessionid]->id);
        }
        $blocklength = 30; // minutes per block (attendance selector will offer attendance time in blocks)
        $durationoptions = [];
        for ($i = $blocklength * 60; $i < $this->sessioninfo[$sessionid]->duration; $i += $blocklength * 60) {
            $durationoptions[] = ['caption' => gmdate("H:i", $i), 'value' => $i];
        }
        $durationoptions[] = [
            'caption' => gmdate("H:i", $this->sessioninfo[$sessionid]->duration),
            'value' => $this->sessioninfo[$sessionid]->duration,
            'selected' => true
        ];
        $this->sessioninfo[$sessionid]->durationoptions = $durationoptions;
        return $this->sessioninfo[$sessionid];
    }

    /**
     * Get sessions info
     *
     * @param array $sessionids
     * @return array
     */
    public function get_sessions_info($sessionids) : array {
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

    /**
     * Get log.
     *
     * @param int $sessionid
     * @return array
     */
    public function get_session_log($sessionid) : array {
        global $DB;

        return $DB->get_records('attendance_evaluations', array('sessionid' => $sessionid), '', 'studentid,statusid,remarks,id,statusset');
    }



    /**
     * Get filtered log.
     * @param int $userid
     * @return array
     */
    public function get_user_filtered_sessions_log($userid) : array {
        global $DB;

        if ($this->pageparams->startdate && $this->pageparams->enddate) {
            $where = "ats.attendanceid = :aid AND ats.sessdate >= :csdate AND
                      ats.sessdate >= :sdate AND ats.sessdate < :edate";
        } else {
            $where = "ats.attendanceid = :aid AND ats.sessdate >= :csdate";
        }
        if ($this->get_group_mode()) {
            $sql = "SELECT ats.id, ats.sessdate, ats.groupid, al.statusid, al.remarks,
                           ats.preventsharediptime, ats.preventsharedip
                  FROM {attendance_sessions} ats
                  JOIN {attendance_evaluations} al ON ats.id = al.sessionid AND al.studentid = :uid
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
            $sql = "SELECT ats.id, ats.sessdate, ats.groupid, al.statusid, al.remarks,
                           ats.preventsharediptime, ats.preventsharedip
                  FROM {attendance_sessions} ats
                  JOIN {attendance_evaluations} al
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

    /**
     * Get filtered log extended.
     * @param int $userid
     * @return array
     */
    public function get_user_filtered_sessions_log_extended($userid) : array {
        global $DB;
        // All taked sessions (including previous groups).

        if ($this->pageparams->startdate && $this->pageparams->enddate) {
            $where = "ats.attendanceid = :aid AND ats.sessdate >= :csdate AND
                      ats.sessdate >= :sdate AND ats.sessdate < :edate";
        } else {
            $where = "ats.attendanceid = :aid AND ats.sessdate >= :csdate";
        }

        // We need to add this concatenation so that moodle will use it as the array index that is a string.
        // If the array's index is a number it will not merge entries.
        // It would be better as a UNION query but unfortunatly MS SQL does not seem to support doing a
        // DISTINCT on a the description field.
        $id = $DB->sql_concat(':value', 'ats.id');
        if ($this->get_group_mode()) {
            $sql = "SELECT $id, ats.id, ats.sessdate, ats.duration, ats.description,
                           ats.roomid, ats.maxattendants
                      FROM {attendance_sessions} ats
                RIGHT JOIN {attendance_evaluations} al
                        ON ats.id = al.sessionid AND al.studentid = :uid
                     WHERE $where AND (ats.groupid = 0 or gm.id is NOT NULL)
                  ORDER BY ats.sessdate ASC";
        } else {
            $sql = "SELECT $id, ats.id, ats.sessdate, ats.duration, ats.description, 
                           ats.roomid, ats.maxattendants
                      FROM {attendance_sessions} ats
                RIGHT JOIN {attendance_evaluations} al
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
        $sql = "SELECT $id, ats.id, ats.groupid, ats.sessdate, ats.duration, ats.description,
                       ats.roomid, ats.maxattendants, atr.name AS roomname, atr.description AS roomdescription, atr.bookable,
                       (SELECT COUNT(*) FROM {attendance_bookings} as atb WHERE atb.sessionid = ats.id) as bookedspots
                  FROM {attendance_sessions} ats
             LEFT JOIN {attendance_evaluations} al
                    ON ats.id = al.sessionid AND al.studentid = :uid
             LEFT JOIN {attendance_rooms} atr
                    ON ats.roomid = atr.id
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

        // We have two merged arrays, each is sorted - but the merged array is not sorted. Let's do that now.
        usort($sessions, function($a, $b) {
            return $a->sessdate <=> $b->sessdate;
        });

        return $sessions;
    }

    /**
     * Delete sessions.
     * @param array $sessionsids
     */
    public function delete_sessions($sessionsids) {
        global $DB;
        if (attendance_existing_calendar_events_ids($sessionsids)) {
            attendance_delete_calendar_events($sessionsids);
        }

        list($sql, $params) = $DB->get_in_or_equal($sessionsids);
        $DB->delete_records_select('attendance_evaluations', "sessionid $sql", $params);
        $DB->delete_records_list('attendance_sessions', 'id', $sessionsids);

        $bookings = $DB->get_records_list('attendance_bookings', 'sessionid', $sessionsids);
        $caleventids = array_map(function($booking) {
            return $booking->caleventid;
        }, $bookings);
        $DB->delete_records_list('attendance_bookings', 'sessionid', $sessionsids);
        $DB->delete_records_list('event', 'id', $caleventids);

        $event = \mod_attendance\event\session_deleted::create(array(
            'objectid' => $this->id,
            'context' => $this->context,
            'other' => array('info' => implode(', ', $sessionsids))));
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->trigger();
    }

    /**
     * Update duration.
     *
     * @param array $sessionsids
     * @param int $duration
     */
    public function update_sessions_duration($sessionsids, $duration) {
        global $DB;

        $now = time();
        $sessions = $DB->get_recordset_list('attendance_sessions', 'id', $sessionsids);
        foreach ($sessions as $sess) {
            $sess->duration = $duration;
            $sess->timemodified = $now;
            $DB->update_record('attendance_sessions', $sess);
            if ($sess->caleventid) {
                attendance_update_calendar_event($sess);
            }
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
     * Gets an array of existing rooms
     * @param bool $onlybookable - return only rooms that are bookable (edit bookable flag in room editor)
     * @return array
     */
    public function get_rooms(bool $onlybookable = true) : array {
        global $DB;
        if ($onlybookable) {
            $rooms = array_values($DB->get_records('attendance_rooms', ["bookable" => true], 'name ASC'));
        } else {
            $rooms = array_values($DB->get_records('attendance_rooms', null, 'name ASC'));
        }
        return $rooms;
    }

    /**
     * Gets a hashed array of existing rooms with roomid as key
     * @param bool $onlybookable - return only rooms that are bookable (edit bookable flag in room editor)
     * @return array
     */
    public function get_rooms_hash(bool $onlybookable = true) : array {
        $roomsarray = $this->get_rooms($onlybookable);
        $roomshash = [];
        foreach ($roomsarray as $room) {
            $roomshash[$room->id] = $room;
        }
        return $roomshash;
    }

    /**
     * Gets a hashed array of existing rooms names with roomid as key
     * @param bool $onlybookable - return only rooms that are bookable (edit bookable flag in room editor)
     * @return array
     */
    public function get_room_names(bool $onlybookable = true) : array {
        $roomsarray = $this->get_rooms($onlybookable);
        $roomshash = [];
        foreach ($roomsarray as $room) {
            $roomshash[$room->id] = $room->name;
        }
        return $roomshash;
    }

    /**
     * Gets name of a room
     * @param int $roomid
     * @return string
     */
    public function get_room_name(int $roomid) : string {
        global $DB;
        $room = $DB->get_field('attendance_rooms', 'name', ["id" => $roomid]);
        if ($room === false) {
            $room = '';
        }
        return $room;
    }

    /**
     * Gets capacity of a room
     * @param int|null $roomid
     * @return int
     */
    public function get_room_capacity($roomid) : int {
        if (!$roomid) {
            return 0;
        }
        global $DB;
        $room = $DB->get_field('attendance_rooms', 'capacity', ["id" => $roomid]);
        if ($room === false) {
            $room = 0;
        }
        return $room;
    }
}
