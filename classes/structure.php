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

/**
 * Main class with all Attendance related info.
 *
 * @copyright  2016 Dan Marsden http://danmarsden.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_attendance_structure {
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

    private $groupmode;

    private $statuses;
    private $allstatuses; // Cache list of all statuses (not just one used by current session).

    // Array by sessionid.
    private $sessioninfo = array();

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
            if (property_exists('mod_attendance_structure', $field)) {
                $this->{$field} = $value;
            } else {
                throw new coding_exception('The attendance table has a field with no property in the attendance class');
            }
        }
        $this->cm           = $cm;
        $this->course       = $course;
        if (is_null($context)) {
            $this->context = context_module::instance($this->cm->id);
        } else {
            $this->context = $context;
        }

        $this->pageparams = $pageparams;
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
            'csdate' => $this->course->startdate);

        return $DB->count_records_select('attendance_sessions', $where, $params);
    }

    /**
     * Returns the hidden sessions for this attendance
     *
     * Fetches data from {attendance_sessions}
     *
     * @return hidden sessions
     */
    public function get_hidden_sessions() {
        global $DB;

        $where = "attendanceid = :aid AND sessdate < :csdate";
        $params = array(
            'aid'   => $this->id,
            'csdate' => $this->course->startdate);

        return $DB->get_records_select('attendance_sessions', $where, $params);
    }

    public function get_filtered_sessions() {
        global $DB;

        if ($this->pageparams->startdate && $this->pageparams->enddate) {
            $where = "attendanceid = :aid AND sessdate >= :csdate AND sessdate >= :sdate AND sessdate < :edate";
        } else if ($this->pageparams->enddate) {
            $where = "attendanceid = :aid AND sessdate >= :csdate AND sessdate < :edate";
        } else {
            $where = "attendanceid = :aid AND sessdate >= :csdate";
        }

        if ($this->pageparams->get_current_sesstype() > mod_attendance_page_with_filter_controls::SESSTYPE_ALL) {
            $where .= " AND (groupid = :cgroup OR groupid = 0)";
        }
        $params = array(
            'aid'       => $this->id,
            'csdate'    => $this->course->startdate,
            'sdate'     => $this->pageparams->startdate,
            'edate'     => $this->pageparams->enddate,
            'cgroup'    => $this->pageparams->get_current_sesstype());
        $sessions = $DB->get_records_select('attendance_sessions', $where, $params, 'sessdate asc');
        $statussetmaxpoints = attendance_get_statusset_maxpoints($this->get_statuses(true, true));
        foreach ($sessions as $sess) {
            if (empty($sess->description)) {
                $sess->description = get_string('nodescription', 'attendance');
            } else {
                $sess->description = file_rewrite_pluginfile_urls($sess->description,
                    'pluginfile.php', $this->context->id, 'mod_attendance', 'session', $sess->id);
            }
            $sess->maxpoints = $statussetmaxpoints[$sess->statusset];
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
     * @param array $params optional
     * @return moodle_url of tempusers.php for attendance instance
     */
    public function url_managetemp($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/tempusers.php', $params);
    }

    /**
     * @param array $params optional
     * @return moodle_url of tempdelete.php for attendance instance
     */
    public function url_tempdelete($params=array()) {
        $params = array_merge(array('id' => $this->cm->id, 'action' => 'delete'), $params);
        return new moodle_url('/mod/attendance/tempedit.php', $params);
    }

    /**
     * @param array $params optional
     * @return moodle_url of tempedit.php for attendance instance
     */
    public function url_tempedit($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/tempedit.php', $params);
    }

    /**
     * @param array $params optional
     * @return moodle_url of tempedit.php for attendance instance
     */
    public function url_tempmerge($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/tempmerge.php', $params);
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
        // Add the statusset params.
        if (isset($this->pageparams->statusset) && !isset($params['statusset'])) {
            $params['statusset'] = $this->pageparams->statusset;
        }
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

        $sesstarttime = $formdata->sestime['starthour'] * HOURSECS + $formdata->sestime['startminute'] * MINSECS;
        $sesendtime = $formdata->sestime['endhour'] * HOURSECS + $formdata->sestime['endminute'] * MINSECS;

        $sess->sessdate = $formdata->sessiondate + $sesstarttime;
        $sess->duration = $sesendtime - $sesstarttime;

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
            'other' => array('info' => $info, 'sessionid' => $sessionid, 'action' => mod_attendance_sessions_page_params::ACTION_UPDATE)));
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->add_record_snapshot('attendance_sessions', $sess);
        $event->trigger();
    }

    /**
     * Used to record attendance submitted by the student.
     *
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

        $logid = $DB->insert_record('attendance_log', $record, false);
        $record->id = $logid;

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
                $sesslog[$sid]->remarks = array_key_exists('remarks'.$sid, $formdata) ? clean_param($formdata['remarks'.$sid], PARAM_TEXT) : '';
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

        $group = 0;
        if ($this->pageparams->grouptype != self::SESSION_COMMON) {
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
                $users = get_enrolled_users($this->context, 'mod/attendance:canbelisted', $groupid, $userfields,
                    $orderby, $startusers, $usersperpage);
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

            // See CONTRIB-4868.
            $mintime = 'MIN(CASE WHEN (ue.timestart > :zerotime) THEN ue.timestart ELSE ue.timecreated END)';
            $maxtime = 'CASE WHEN MIN(ue.timeend) = 0 THEN 0 ELSE MAX(ue.timeend) END';

            // See CONTRIB-3549.
            $sql = "SELECT ue.userid, MIN(ue.status) as status,
                           $mintime AS mintime,
                           $maxtime AS maxtime
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE ue.userid $sql
                           AND e.status = :estatus
                           AND e.courseid = :courseid
                  GROUP BY ue.userid";
            $params += array('zerotime' => 0, 'estatus' => ENROL_INSTANCE_ENABLED, 'courseid' => $this->course->id);
            $enrolments = $DB->get_records_sql($sql, $params);

            foreach ($users as $user) {
                $users[$user->id]->enrolmentstatus = $enrolments[$user->id]->status;
                $users[$user->id]->enrolmentstart = $enrolments[$user->id]->mintime;
                $users[$user->id]->enrolmentend = $enrolments[$user->id]->maxtime;
                $users[$user->id]->type = 'standard'; // Mark as a standard (not a temporary) user.
            }
        }

        // Add the 'temporary' users to this list.
        $tempusers = $DB->get_records('attendance_tempusers', array('courseid' => $this->course->id));
        foreach ($tempusers as $tempuser) {
            $users[] = self::tempuser_to_user($tempuser);
        }

        return $users;
    }

    // Convert a tempuser record into a user object.
    protected static function tempuser_to_user($tempuser) {
        $ret = (object)array(
            'id' => $tempuser->studentid,
            'firstname' => $tempuser->fullname,
            'email' => $tempuser->email,
            'username' => '',
            'enrolmentstatus' => 0,
            'enrolmentstart' => 0,
            'enrolmentend' => 0,
            'picture' => 0,
            'type' => 'temporary',
        );
        foreach (get_all_user_name_fields() as $namefield) {
            if (!isset($ret->$namefield)) {
                $ret->$namefield = '';
            }
        }
        return $ret;
    }

    public function get_user($userid) {
        global $DB;

        $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

        // Look for 'temporary' users and return their details from the attendance_tempusers table.
        if ($user->idnumber == 'tempghost') {
            $tempuser = $DB->get_record('attendance_tempusers', array('studentid' => $userid), '*', MUST_EXIST);
            return self::tempuser_to_user($tempuser);
        }

        $user->type = 'standard';

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

        $user->enrolmentstatus = $enrolments->status;
        $user->enrolmentstart = $enrolments->mintime;
        $user->enrolmentend = $enrolments->maxtime;

        return $user;
    }

    public function get_statuses($onlyvisible = true, $allsets = false) {
        if (!isset($this->statuses)) {
            // Get the statuses for the current set only.
            $statusset = 0;
            if (isset($this->pageparams->statusset)) {
                $statusset = $this->pageparams->statusset;
            } else if (isset($this->pageparams->sessionid)) {
                $sessioninfo = $this->get_session_info($this->pageparams->sessionid);
                $statusset = $sessioninfo->statusset;
            }
            $this->statuses = attendance_get_statuses($this->id, $onlyvisible, $statusset);
            $this->allstatuses = attendance_get_statuses($this->id, $onlyvisible);
        }

        // Return all sets, if requested.
        if ($allsets) {
            return $this->allstatuses;
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

    public function update_users_grade($userids) {
        attendance_update_users_grade($this, $userids);
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
        // It would be better as a UNION query but unfortunatly MS SQL does not seem to support doing a
        // DISTINCT on a the description field.
        $id = $DB->sql_concat(':value', 'ats.id');
        if ($this->get_group_mode()) {
            $sql = "SELECT $id, ats.id, ats.groupid, ats.sessdate, ats.duration, ats.description,
                           al.statusid, al.remarks, ats.studentscanmark
                      FROM {attendance_sessions} ats
                RIGHT JOIN {attendance_log} al
                        ON ats.id = al.sessionid AND al.studentid = :uid
                 LEFT JOIN {groups_members} gm ON gm.userid = al.studentid AND gm.groupid = ats.groupid
                     WHERE $where AND (ats.groupid = 0 or gm.id is NOT NULL)
                  ORDER BY ats.sessdate ASC";
        } else {
            $sql = "SELECT $id, ats.id, ats.groupid, ats.sessdate, ats.duration, ats.description, ats.statusset,
                           al.statusid, al.remarks, ats.studentscanmark
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

        $sql = "SELECT $id, ats.id, ats.groupid, ats.sessdate, ats.duration, ats.description, ats.statusset,
                       al.statusid, al.remarks, ats.studentscanmark
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
            $rec->setnumber = $this->pageparams->statusset; // Save which set it is part of.
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
     * @param stdClass $status
     * @param string $acronym
     * @param string $description
     * @param int $grade
     * @param bool $visible
     */
    public function update_status($status, $acronym, $description, $grade, $visible) {
        global $DB;

        if (isset($visible)) {
            $status->visible = $visible;
            $updated[] = $visible ? get_string('show') : get_string('hide');
        } else if (empty($acronym) || empty($description)) {
            return array('acronym' => $acronym, 'description' => $description);
        }

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
        $DB->update_record('attendance_statuses', $status);

        $event = \mod_attendance\event\status_updated::create(array(
            'objectid' => $this->id,
            'context' => $this->context,
            'other' => array('acronym' => $acronym, 'description' => $description, 'grade' => $grade,
                'updated' => implode(' ', $updated))));
        $event->add_record_snapshot('course_modules', $this->cm);
        $event->add_record_snapshot('attendance_statuses', $status);
        $event->trigger();
    }

    /**
     * Check if the email address is already in use by either another temporary user,
     * or a real user.
     *
     * @param string $email the address to check for
     * @param int $tempuserid optional the ID of the temporary user (to avoid matching against themself)
     * @return null|string the error message to display, null if there is no error
     */
    public static function check_existing_email($email, $tempuserid = 0) {
        global $DB;

        if (empty($email)) {
            return null; // Fine to create temporary users without an email address.
        }
        if ($tempuser = $DB->get_record('attendance_tempusers', array('email' => $email), 'id')) {
            if ($tempuser->id != $tempuserid) {
                return get_string('tempexists', 'attendance');
            }
        }
        if ($DB->record_exists('user', array('email' => $email))) {
            return get_string('userexists', 'attendance');
        }

        return null;
    }
}
