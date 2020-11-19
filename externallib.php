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
 * Externallib.php file for attendance plugin.
 *
 * @package    mod_attendance
 * @copyright  2015 Caio Bressan Doneda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/filelib.php');
require_once(dirname(__FILE__).'/classes/attendance_webservices_handler.php');

/**
 * Class mod_attendance_external
 * @copyright  2015 Caio Bressan Doneda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_attendance_external extends external_api {

    /**
     * Describes the parameters for add_attendance.
     *
     * @return external_function_parameters
     */
    public static function add_attendance_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'course id'),
                'name' => new external_value(PARAM_TEXT, 'attendance name'),
                'intro' => new external_value(PARAM_RAW, 'attendance description', VALUE_DEFAULT, ''),
                'groupmode' => new external_value(PARAM_INT,
                    'group mode (0 - no groups, 1 - separate groups, 2 - visible groups)', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Adds attendance instance to course.
     *
     * @param int $courseid
     * @param string $name
     * @param string $intro
     * @param int $groupmode
     * @return array
     */
    public static function add_attendance(int $courseid, $name, $intro, int $groupmode) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/course/modlib.php');

        $params = self::validate_parameters(self::add_attendance_parameters(), array(
            'courseid' => $courseid,
            'name' => $name,
            'intro' => $intro,
            'groupmode' => $groupmode,
        ));

        // Get course.
        $course = $DB->get_record('course', array('id' => $params['courseid']), '*', MUST_EXIST);

        // Verify permissions.
        list($module, $context) = can_add_moduleinfo($course, 'attendance', 0);
        self::validate_context($context);
        require_capability('mod/attendance:addinstance', $context);

        // Verify group mode.
        if (!in_array($params['groupmode'], array(NOGROUPS, SEPARATEGROUPS, VISIBLEGROUPS))) {
            throw new invalid_parameter_exception('Group mode is invalid.');
        }

        // Populate modinfo object.
        $moduleinfo = new stdClass();
        $moduleinfo->modulename = 'attendance';
        $moduleinfo->module = $module->id;

        $moduleinfo->name = $params['name'];
        $moduleinfo->intro = $params['intro'];
        $moduleinfo->introformat = FORMAT_HTML;

        $moduleinfo->section = 0;
        $moduleinfo->visible = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->cmidnumber = '';
        $moduleinfo->groupmode = $params['groupmode'];
        $moduleinfo->groupingid = 0;

        // Add the module to the course.
        $moduleinfo = add_moduleinfo($moduleinfo, $course);

        return array('attendanceid' => $moduleinfo->instance);
    }

    /**
     * Describes add_attendance return values.
     *
     * @return external_multiple_structure
     */
    public static function add_attendance_returns() {
        return new external_single_structure(array(
            'attendanceid' => new external_value(PARAM_INT, 'instance id of the created attendance'),
        ));
    }

    /**
     * Describes the parameters for remove_attendance.
     *
     * @return external_function_parameters
     */
    public static function remove_attendance_parameters() {
        return new external_function_parameters(
            array(
                'attendanceid' => new external_value(PARAM_INT, 'attendance instance id'),
            )
        );
    }

    /**
     * Remove attendance instance.
     *
     * @param int $attendanceid
     */
    public static function remove_attendance(int $attendanceid) {
        $params = self::validate_parameters(self::remove_attendance_parameters(), array(
            'attendanceid' => $attendanceid,
        ));

        $cm = get_coursemodule_from_instance('attendance', $params['attendanceid'], 0, false, MUST_EXIST);

        // Check permissions.
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/attendance:manageattendances', $context);

        // Delete attendance instance.
        $result = attendance_delete_instance($params['attendanceid']);
        rebuild_course_cache($cm->course, true);
        return $result;
    }

    /**
     * Describes remove_attendance return values.
     *
     * @return external_value
     */
    public static function remove_attendance_returns() {
        return new external_value(PARAM_BOOL, 'attendance deletion result');
    }

    /**
     * Describes the parameters for add_session.
     *
     * @return external_function_parameters
     */
    public static function add_session_parameters() {
        return new external_function_parameters(
            array(
                'attendanceid' => new external_value(PARAM_INT, 'attendance instance id'),
                'description' => new external_value(PARAM_RAW, 'description', VALUE_DEFAULT, ''),
                'sessiontime' => new external_value(PARAM_INT, 'session start timestamp'),
                'duration' => new external_value(PARAM_INT, 'session duration (seconds)', VALUE_DEFAULT, 0),
                'groupid' => new external_value(PARAM_INT, 'group id', VALUE_DEFAULT, 0),
                'addcalendarevent' => new external_value(PARAM_BOOL, 'add calendar event', VALUE_DEFAULT, true),
            )
        );
    }

    /**
     * Adds session to attendance instance.
     *
     * @param int $attendanceid
     * @param string $description
     * @param int $sessiontime
     * @param int $duration
     * @param int $groupid
     * @param bool $addcalendarevent
     * @return array
     */
    public static function add_session(int $attendanceid, $description, int $sessiontime, int $duration, int $groupid,
                                       bool $addcalendarevent) {
        global $USER, $DB;

        $params = self::validate_parameters(self::add_session_parameters(), array(
            'attendanceid' => $attendanceid,
            'description' => $description,
            'sessiontime' => $sessiontime,
            'duration' => $duration,
            'groupid' => $groupid,
            'addcalendarevent' => $addcalendarevent,
        ));

        $cm = get_coursemodule_from_instance('attendance', $params['attendanceid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $attendance = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);

        // Check permissions.
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/attendance:manageattendances', $context);

        // Validate group.
        $groupid = $params['groupid'];
        $groupmode = (int)groups_get_activity_groupmode($cm);
        if ($groupmode === NOGROUPS && $groupid > 0) {
            throw new invalid_parameter_exception('Group id is specified, but group mode is disabled for activity');
        } else if ($groupmode === SEPARATEGROUPS && $groupid === 0) {
            throw new invalid_parameter_exception('Group id is not specified (or 0) in separate groups mode.');
        }
        if ($groupmode === SEPARATEGROUPS || ($groupmode === VISIBLEGROUPS && $groupid > 0)) {
            // Determine valid groups.
            $userid = has_capability('moodle/site:accessallgroups', $context) ? 0 : $USER->id;
            $validgroupids = array_map(function($group) {
                return $group->id;
            }, groups_get_all_groups($course->id, $userid, $cm->groupingid));
            if (!in_array($groupid, $validgroupids)) {
                throw new invalid_parameter_exception('Invalid group id');
            }
        }

        // Get attendance.
        $attendance = new mod_attendance_structure($attendance, $cm, $course, $context);

        // Create session.
        $sess = new stdClass();
        $sess->sessdate = $params['sessiontime'];
        $sess->duration = $params['duration'];
        $sess->descriptionitemid = 0;
        $sess->description = $params['description'];
        $sess->descriptionformat = FORMAT_HTML;
        $sess->calendarevent = (int) $params['addcalendarevent'];
        $sess->timemodified = time();
        $sess->studentscanmark = 0;
        $sess->autoassignstatus = 0;
        $sess->subnet = '';
        $sess->studentpassword = '';
        $sess->automark = 0;
        $sess->automarkcompleted = 0;
        $sess->absenteereport = get_config('attendance', 'absenteereport_default');
        $sess->includeqrcode = 0;
        $sess->subnet = $attendance->subnet;
        $sess->statusset = 0;
        $sess->groupid = $groupid;

        $sessionid = $attendance->add_session($sess);
        return array('sessionid' => $sessionid);
    }

    /**
     * Describes add_session return values.
     *
     * @return external_multiple_structure
     */
    public static function add_session_returns() {
        return new external_single_structure(array(
            'sessionid' => new external_value(PARAM_INT, 'id of the created session'),
        ));
    }

    /**
     * Describes the parameters for remove_session.
     *
     * @return external_function_parameters
     */
    public static function remove_session_parameters() {
        return new external_function_parameters(
            array(
                'sessionid' => new external_value(PARAM_INT, 'session id'),
            )
        );
    }

    /**
     * Delete session from attendance instance.
     *
     * @param int $sessionid
     * @return bool
     */
    public static function remove_session(int $sessionid) {
        global $DB;

        $params = self::validate_parameters(self::remove_session_parameters(),
            array('sessionid' => $sessionid));

        $session = $DB->get_record('attendance_sessions', array('id' => $params['sessionid']), '*', MUST_EXIST);
        $attendance = $DB->get_record('attendance', array('id' => $session->attendanceid), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('attendance', $attendance->id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

        // Check permissions.
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/attendance:manageattendances', $context);

        // Get attendance.
        $attendance = new mod_attendance_structure($attendance, $cm, $course, $context);

        // Delete session.
        $attendance->delete_sessions(array($sessionid));
        attendance_update_users_grade($attendance);

        return true;
    }

    /**
     * Describes remove_session return values.
     *
     * @return external_value
     */
    public static function remove_session_returns() {
        return new external_value(PARAM_BOOL, 'attendance session deletion result');
    }

    /**
     * Get parameter list.
     * @return external_function_parameters
     */
    public static function get_courses_with_today_sessions_parameters() {
        return new external_function_parameters (
                    array('userid' => new external_value(PARAM_INT, 'User id.',  VALUE_DEFAULT, 0)));
    }

    /**
     * Get list of courses with active sessions for today.
     * @param int $userid
     * @return array
     */
    public static function get_courses_with_today_sessions($userid) {
        global $DB;

        $params = self::validate_parameters(self::get_courses_with_today_sessions_parameters(), array(
            'userid' => $userid,
        ));

        // Check user id is valid.
        $user = $DB->get_record('user', array('id' => $params['userid']), '*', MUST_EXIST);

        // Capability check is done in get_courses_with_today_sessions
        // as it switches contexts in loop for each course.
        return attendance_handler::get_courses_with_today_sessions($params['userid']);
    }

    /**
     * Get structure of an attendance session.
     *
     * @return array
     */
    private static function get_session_structure() {
        $session = array('id' => new external_value(PARAM_INT, 'Session id.'),
                         'attendanceid' => new external_value(PARAM_INT, 'Attendance id.'),
                         'groupid' => new external_value(PARAM_INT, 'Group id.'),
                         'sessdate' => new external_value(PARAM_INT, 'Session date.'),
                         'duration' => new external_value(PARAM_INT, 'Session duration.'),
                         'lasttaken' => new external_value(PARAM_INT, 'Session last taken time.'),
                         'lasttakenby' => new external_value(PARAM_INT, 'ID of the last user that took this session.'),
                         'timemodified' => new external_value(PARAM_INT, 'Time modified.'),
                         'description' => new external_value(PARAM_TEXT, 'Session description.'),
                         'descriptionformat' => new external_value(PARAM_INT, 'Session description format.'),
                         'studentscanmark' => new external_value(PARAM_INT, 'Students can mark their own presence.'),
                         'absenteereport' => new external_value(PARAM_INT, 'Session included in absetee reports.'),
                         'autoassignstatus' => new external_value(PARAM_INT, 'Automatically assign a status to students.'),
                         'preventsharedip' => new external_value(PARAM_INT, 'Prevent students from sharing IP addresses.'),
                         'preventsharediptime' => new external_value(PARAM_INT, 'Time delay before IP address is allowed again.'),
                         'statusset' => new external_value(PARAM_INT, 'Session statusset.'),
                         'includeqrcode' => new external_value(PARAM_INT, 'Include QR code when displaying password'));

        return $session;
    }

    /**
     * Show structure of return.
     * @return external_multiple_structure
     */
    public static function get_courses_with_today_sessions_returns() {
        $todaysessions = self::get_session_structure();

        $attendanceinstances = array('name' => new external_value(PARAM_TEXT, 'Attendance name.'),
                                      'today_sessions' => new external_multiple_structure(
                                                          new external_single_structure($todaysessions)));

        $courses = array('shortname' => new external_value(PARAM_TEXT, 'short name of a moodle course.'),
                         'fullname' => new external_value(PARAM_TEXT, 'full name of a moodle course.'),
                         'attendance_instances' => new external_multiple_structure(
                                                   new external_single_structure($attendanceinstances)));

        return new external_multiple_structure(new external_single_structure(($courses)));
    }

    /**
     * Get session params.
     *
     * @return external_function_parameters
     */
    public static function get_session_parameters() {
        return new external_function_parameters (
                    array('sessionid' => new external_value(PARAM_INT, 'session id')));
    }

    /**
     * Get session.
     *
     * @param int $sessionid
     * @return mixed
     */
    public static function get_session($sessionid) {
        global $DB;

        $params = self::validate_parameters(self::get_session_parameters(), array(
            'sessionid' => $sessionid,
        ));

        $session = $DB->get_record('attendance_sessions', array('id' => $params['sessionid']), '*', MUST_EXIST);
        $attendance = $DB->get_record('attendance', array('id' => $session->attendanceid), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('attendance', $attendance->id, 0, false, MUST_EXIST);

        // Check permissions.
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        $capabilities = array(
            'mod/attendance:manageattendances',
            'mod/attendance:takeattendances',
            'mod/attendance:changeattendances'
        );
        if (!has_any_capability($capabilities, $context)) {
            throw new invalid_parameter_exception('Invalid session id or no permissions.');
        }

        return attendance_handler::get_session($sessionid);
    }

    /**
     * Show return values of get_session.
     *
     * @return external_single_structure
     */
    public static function get_session_returns() {
        $statuses = array('id' => new external_value(PARAM_INT, 'Status id.'),
                          'attendanceid' => new external_value(PARAM_INT, 'Attendance id.'),
                          'acronym' => new external_value(PARAM_TEXT, 'Status acronym.'),
                          'description' => new external_value(PARAM_TEXT, 'Status description.'),
                          'grade' => new external_value(PARAM_FLOAT, 'Status grade.'),
                          'visible' => new external_value(PARAM_INT, 'Status visibility.'),
                          'deleted' => new external_value(PARAM_INT, 'informs if this session was deleted.'),
                          'setnumber' => new external_value(PARAM_INT, 'Set number.'));

        $users = array('id' => new external_value(PARAM_INT, 'User id.'),
                       'firstname' => new external_value(PARAM_TEXT, 'User first name.'),
                       'lastname' => new external_value(PARAM_TEXT, 'User last name.'));

        $attendancelog = array('studentid' => new external_value(PARAM_INT, 'Student id.'),
                                'statusid' => new external_value(PARAM_TEXT, 'Status id (last time).'),
                                'remarks' => new external_value(PARAM_TEXT, 'Last remark.'),
                                'id' => new external_value(PARAM_TEXT, 'log id.'));

        $session = self::get_session_structure();
        $session['courseid'] = new external_value(PARAM_INT, 'Course moodle id.');
        $session['statuses'] = new external_multiple_structure(new external_single_structure($statuses));
        $session['attendance_evaluations'] = new external_multiple_structure(new external_single_structure($attendancelog));
        $session['users'] = new external_multiple_structure(new external_single_structure($users));

        return new external_single_structure($session);
    }

    /**
     * Update user status params.
     *
     * @return external_function_parameters
     */
    public static function update_user_status_parameters() {
        return new external_function_parameters(
                    array('sessionid' => new external_value(PARAM_INT, 'Session id'),
                          'studentid' => new external_value(PARAM_INT, 'Student id'),
                          'takenbyid' => new external_value(PARAM_INT, 'Id of the user who took this session'),
                          'statusid' => new external_value(PARAM_INT, 'Status id'),
                          'statusset' => new external_value(PARAM_TEXT, 'Status set of session')));
    }

    /**
     * Update user status.
     *
     * @param int $sessionid
     * @param int $studentid
     * @param int $takenbyid
     * @param int $statusid
     * @param int $statusset
     */
    public static function update_user_status($sessionid, $studentid, $takenbyid, $statusid, $statusset) {
        global $DB;

        $params = self::validate_parameters(self::update_user_status_parameters(), array(
            'sessionid' => $sessionid,
            'studentid' => $studentid,
            'takenbyid' => $takenbyid,
            'statusid' => $statusid,
            'statusset' => $statusset,
        ));

        $session = $DB->get_record('attendance_sessions', array('id' => $params['sessionid']), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('attendance', $session->attendanceid, 0, false, MUST_EXIST);

        // Check permissions.
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/attendance:view', $context);

        // If not a teacher, make sure session is open for self-marking.
        if (!has_capability('mod/attendance:takeattendances', $context)) {
            list($canmark, $reason) = attendance_can_student_mark($session);
            if (!$canmark) {
                throw new invalid_parameter_exception($reason);
            }
        }

        // Check user id is valid.
        $student = $DB->get_record('user', array('id' => $params['studentid']), '*', MUST_EXIST);
        $takenby = $DB->get_record('user', array('id' => $params['takenbyid']), '*', MUST_EXIST);

        // TODO: Verify statusset and statusid.

        return attendance_handler::update_user_status($params['sessionid'], $params['studentid'], $params['takenbyid'],
            $params['statusid'], $params['statusset']);
    }

    /**
     * Show return values.
     * @return external_value
     */
    public static function update_user_status_returns() {
        return new external_value(PARAM_TEXT, 'Http code');
    }

    /**
     * Get room capacity.
     * @param int $roomid
     * @return int capacity
     */
    public static function get_room_capacity(int $roomid) {
        global $DB;
        $capacity = $DB->get_field('attendance_rooms', 'capacity', ["id" => $roomid]);
        return intval($capacity);
    }

    /**
     * Get room capacity params.
     *
     * @return external_function_parameters
     */
    public static function get_room_capacity_parameters() {
        return new external_function_parameters(
            array('roomid' => new external_value(PARAM_INT, 'Room id')));
    }

    /**
     * Returns description of method result value.
     * @return external_description
     */
    public static function get_room_capacity_returns() {
        return new external_value(PARAM_INT, 'The capacity of the room with the given id');
    }

    /**
     * Get booking object and session.
     *
     * @param int $bookingid
     * @return stdClass booking object with session
     */
    public static function get_booking(int $bookingid) {
        global $DB;

        $booking = $DB->get_record('attendance_bookings', array('id' => $bookingid));
        if (!$booking) {
            throw new invalid_parameter_exception('Invalid booking id or no permissions.');
        }
        $booking->session = $DB->get_record('attendance_sessions', array('id' => $booking->sessionid));
        if (!$booking->session) {
            throw new invalid_parameter_exception('Invalid session id or no permissions.');
        }
        return $booking;
    }

    /**
     * Book/unbook session for current user.
     * @param int $sessionid
     * @param int $book -1: unbook, 0: toggle booking, 1: book
     * @return array new status of booking
     */
    public static function book_session(int $sessionid, int $book = 0) : array {
        global $DB, $USER;

        $bookedspots = $DB->count_records('attendance_bookings', array('sessionid' => $sessionid));
        $booking = $DB->get_record('attendance_bookings', array('sessionid' => $sessionid, 'userid' => $USER->id));
        $maxattendants = intval($DB->get_field('attendance_sessions', 'maxattendants', array('id' => $sessionid)));
        $result = intval(boolval($booking));

        $errortitle = '';
        $errormessage = '';
        $errorconfirm = '';

        if ($booking && $book <= 0) {
            attendance_delete_calendar_event_booking($booking);
            $DB->delete_records('attendance_bookings', ['id' => $booking->id, 'userid' => $USER->id]);
            $bookedspots--;
            $result = 0;
        } else if (!$booking && $book >= 0) {
            if ($maxattendants > 0 && $bookedspots + 1 >= $maxattendants) {
                $errortitle = get_string('sessionfulltitle', 'attendance');
                $errormessage = get_string('sessionfull', 'attendance');
                $errorconfirm = get_string('ok');
            } else {
                $bookingid = $DB->insert_record('attendance_bookings',
                    array('sessionid' => $sessionid, 'userid' => $USER->id, 'caleventid' => 0));
                $bookedspots++;
                attendance_create_calendar_event_booking(self::get_booking($bookingid));
                $result = 1;
            }
        }

        return array(
            'sessionid' => $sessionid,
            'bookingstatus' => $result,
            'bookedspots' => $bookedspots,
            'errortitle' => $errortitle,
            'errormessage' => $errormessage,
            'errorconfirm' => $errorconfirm
        );
    }

    /**
     * Describes book_session user parameters.
     *
     * @return external_function_parameters
     */
    public static function book_session_parameters() {
        return new external_function_parameters(
            array('sessionid' => new external_value(PARAM_INT, 'Session id'),
                'book' => new external_value(PARAM_INT, '-1: unbook, 0(default): toggle, 1: book')));
    }

    /**
     * Describes book_session return values.
     *
     * @return external_single_structure
     */
    public static function book_session_returns() {
        return new external_single_structure(array(
            'sessionid' => new external_value(PARAM_INT, 'id of the manipulated session'),
            'bookingstatus' => new external_value(PARAM_INT, 'new status of the booking (1: booked, 0: not booked)'),
            'bookedspots' => new external_value(PARAM_INT, 'bookedspots'),
            'errortitle' => new external_value(PARAM_TEXT, 'title for error message'),
            'errormessage' => new external_value(PARAM_TEXT, 'text for error message'),
            'errorconfirm' => new external_value(PARAM_TEXT, 'error message confirm button caption')
        ));
    }


    /**
     * Update evaluation.
     * @param int $sessionid
     * @param array $updates
     * @return array new status of booking
     * @throws coding_exception
     * @throws dml_exception
     */
    public static function update_evaluation(
        int $sessionid,
        array $updates
    ) : array {
        global $DB, $USER;

        foreach ($updates as $update) {
            $remarkscourse = trim(strip_tags($remarkscourse));
            $remarkspersonality = trim(strip_tags($remarkspersonality));

            $params = [
                'sessionid' => $sessionid,
                'studentid' => $update['userid'],
                'duration' => boolval($update['attendance']) ? intval($update['duration']) : 0,
                'remarks_course' => trim(strip_tags($update['remarks_course'])),
                'remarks_personality' => trim(strip_tags($update['remarks_personality'])),
                'timetaken' => time(),
                'takenby' => $USER->id,
            ];


            $id = $DB->get_field('attendance_evaluations', 'id', ['sessionid' => $sessionid, 'studentid' => $update['userid']]);
            if ($id) {
                $DB->update_record('attendance_evaluations', array_merge(['id' => $id], $params));
            } else {
                $id = $DB->insert_record('attendance_evaluations', $params);
                if (!$id) {
                    throw new coding_exception('Error putting attendance into db.');
                }
            }
        }

        return array(
            'sessionid' => $sessionid,
        );

    }

    /**
     * Describes update_evaluation user parameters.
     *
     * @return external_function_parameters
     */
    public static function update_evaluation_parameters() {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session id'),
            'updates' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'user id'),
                    'attendance' => new external_value(PARAM_TEXT, 'attendet this session?'),
                    'duration' => new external_value(PARAM_TEXT, 'attention duration in seconds'),
                    'remarks_course' => new external_value(PARAM_TEXT, 'remark for course wide view'),
                    'remarks_personality' => new external_value(PARAM_TEXT, 'remark on personality can only be seen by school managers'),
                ], 'list of updates', VALUE_REQUIRED)
            ),
        ]);
    }

    /**
     * Describes update_evaluation return values.
     *
     * @return external_single_structure
     */
    public static function update_evaluation_returns() {
        return new external_single_structure(array(
            'sessionid' => new external_value(PARAM_INT, 'sessionid of the manipulated log'),
        ));
    }

}
