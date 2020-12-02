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
 * Externallib.php file for presence plugin.
 *
 * @package    mod_presence
 * @copyright  2015 Caio Bressan Doneda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot.'/enrol/locallib.php');
require_once(dirname(__FILE__).'/classes/presence_webservices_handler.php');
require_once(dirname(__FILE__).'/classes/calendar.php');


/**
 * Class mod_presence_external
 * @copyright  2015 Caio Bressan Doneda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_presence_external extends external_api {

    /**
     * Describes the parameters for add_presence.
     *
     * @return external_function_parameters
     */
    public static function add_presence_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'course id'),
                'name' => new external_value(PARAM_TEXT, 'presence name'),
                'intro' => new external_value(PARAM_RAW, 'presence description', VALUE_DEFAULT, ''),
                'groupmode' => new external_value(PARAM_INT,
                    'group mode (0 - no groups, 1 - separate groups, 2 - visible groups)', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Adds presence instance to course.
     *
     * @param int $courseid
     * @param string $name
     * @param string $intro
     * @param int $groupmode
     * @return array
     */
    public static function add_presence(int $courseid, $name, $intro, int $groupmode) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/course/modlib.php');

        $params = self::validate_parameters(self::add_presence_parameters(), array(
            'courseid' => $courseid,
            'name' => $name,
            'intro' => $intro,
            'groupmode' => $groupmode,
        ));

        // Get course.
        $course = $DB->get_record('course', array('id' => $params['courseid']), '*', MUST_EXIST);

        // Verify permissions.
        list($module, $context) = can_add_moduleinfo($course, 'presence', 0);
        self::validate_context($context);
        require_capability('mod/presence:addinstance', $context);

        // Verify group mode.
        if (!in_array($params['groupmode'], array(NOGROUPS, SEPARATEGROUPS, VISIBLEGROUPS))) {
            throw new invalid_parameter_exception('Group mode is invalid.');
        }

        // Populate modinfo object.
        $moduleinfo = new stdClass();
        $moduleinfo->modulename = 'presence';
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

        return array('presenceid' => $moduleinfo->instance);
    }

    /**
     * Describes add_presence return values.
     *
     * @return external_multiple_structure
     */
    public static function add_presence_returns() {
        return new external_single_structure(array(
            'presenceid' => new external_value(PARAM_INT, 'instance id of the created presence'),
        ));
    }

    /**
     * Describes the parameters for remove_presence.
     *
     * @return external_function_parameters
     */
    public static function remove_presence_parameters() {
        return new external_function_parameters(
            array(
                'presenceid' => new external_value(PARAM_INT, 'presence instance id'),
            )
        );
    }

    /**
     * Remove presence instance.
     *
     * @param int $presenceid
     */
    public static function remove_presence(int $presenceid) {
        $params = self::validate_parameters(self::remove_presence_parameters(), array(
            'presenceid' => $presenceid,
        ));

        $cm = get_coursemodule_from_instance('presence', $params['presenceid'], 0, false, MUST_EXIST);

        // Check permissions.
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/presence:managepresences', $context);

        // Delete presence instance.
        $result = presence_delete_instance($params['presenceid']);
        rebuild_course_cache($cm->course, true);
        return $result;
    }

    /**
     * Describes remove_presence return values.
     *
     * @return external_value
     */
    public static function remove_presence_returns() {
        return new external_value(PARAM_BOOL, 'presence deletion result');
    }

    /**
     * Describes the parameters for add_session.
     *
     * @return external_function_parameters
     */
    public static function add_session_parameters() {
        return new external_function_parameters(
            array(
                'presenceid' => new external_value(PARAM_INT, 'presence instance id'),
                'description' => new external_value(PARAM_RAW, 'description', VALUE_DEFAULT, ''),
                'sessiontime' => new external_value(PARAM_INT, 'session start timestamp'),
                'duration' => new external_value(PARAM_INT, 'session duration (seconds)', VALUE_DEFAULT, 0),
                'groupid' => new external_value(PARAM_INT, 'group id', VALUE_DEFAULT, 0),
                'addcalendarevent' => new external_value(PARAM_BOOL, 'add calendar event', VALUE_DEFAULT, true),
            )
        );
    }

    /**
     * Adds session to presence instance.
     *
     * @param int $presenceid
     * @param string $description
     * @param int $sessiontime
     * @param int $duration
     * @param int $groupid
     * @param bool $addcalendarevent
     * @return array
     */
    public static function add_session(int $presenceid, $description, int $sessiontime, int $duration, int $groupid,
                                       bool $addcalendarevent) {
        global $USER, $DB;

        $params = self::validate_parameters(self::add_session_parameters(), array(
            'presenceid' => $presenceid,
            'description' => $description,
            'sessiontime' => $sessiontime,
            'duration' => $duration,
            'groupid' => $groupid,
            'addcalendarevent' => $addcalendarevent,
        ));

        $cm = get_coursemodule_from_instance('presence', $params['presenceid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $presence = $DB->get_record('presence', array('id' => $cm->instance), '*', MUST_EXIST);

        // Check permissions.
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/presence:managepresences', $context);

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

        // Get presence.
        $presence = new mod_presence_structure($presence, $cm, $course, $context);

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
        $sess->absenteereport = get_config('presence', 'absenteereport_default');
        $sess->includeqrcode = 0;
        $sess->subnet = $presence->subnet;
        $sess->statusset = 0;
        $sess->groupid = $groupid;

        $sessionid = $presence->add_session($sess);
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
     * Delete session from presence instance.
     *
     * @param int $sessionid
     * @return bool
     */
    public static function remove_session(int $sessionid) {
        global $DB;

        $params = self::validate_parameters(self::remove_session_parameters(),
            array('sessionid' => $sessionid));

        $session = $DB->get_record('presence_sessions', array('id' => $params['sessionid']), '*', MUST_EXIST);
        $presence = $DB->get_record('presence', array('id' => $session->presenceid), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('presence', $presence->id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

        // Check permissions.
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/presence:managepresences', $context);

        // Get presence.
        $presence = new mod_presence_structure($presence, $cm, $course, $context);

        // Delete session.
        $presence->delete_sessions(array($sessionid));
        presence_update_users_grade($presence);

        return true;
    }

    /**
     * Describes remove_session return values.
     *
     * @return external_value
     */
    public static function remove_session_returns() {
        return new external_value(PARAM_BOOL, 'presence session deletion result');
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
        return presence_handler::get_courses_with_today_sessions($params['userid']);
    }

    /**
     * Get structure of an presence session.
     *
     * @return array
     */
    private static function get_session_structure() {
        $session = array('id' => new external_value(PARAM_INT, 'Session id.'),
                         'presenceid' => new external_value(PARAM_INT, 'presence id.'),
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

        $presenceinstances = array('name' => new external_value(PARAM_TEXT, 'presence name.'),
                                      'today_sessions' => new external_multiple_structure(
                                                          new external_single_structure($todaysessions)));

        $courses = array('shortname' => new external_value(PARAM_TEXT, 'short name of a moodle course.'),
                         'fullname' => new external_value(PARAM_TEXT, 'full name of a moodle course.'),
                         'presence_instances' => new external_multiple_structure(
                                                   new external_single_structure($presenceinstances)));

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

        $session = $DB->get_record('presence_sessions', array('id' => $params['sessionid']), '*', MUST_EXIST);
        $presence = $DB->get_record('presence', array('id' => $session->presenceid), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('presence', $presence->id, 0, false, MUST_EXIST);

        // Check permissions.
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        $capabilities = array(
            'mod/presence:managepresences',
            'mod/presence:takepresences',
            'mod/presence:changepresences'
        );
        if (!has_any_capability($capabilities, $context)) {
            throw new invalid_parameter_exception('Invalid session id or no permissions.');
        }

        return presence_handler::get_session($sessionid);
    }

    /**
     * Show return values of get_session.
     *
     * @return external_single_structure
     */
    public static function get_session_returns() {
        $statuses = array('id' => new external_value(PARAM_INT, 'Status id.'),
                          'presenceid' => new external_value(PARAM_INT, 'presence id.'),
                          'acronym' => new external_value(PARAM_TEXT, 'Status acronym.'),
                          'description' => new external_value(PARAM_TEXT, 'Status description.'),
                          'grade' => new external_value(PARAM_FLOAT, 'Status grade.'),
                          'visible' => new external_value(PARAM_INT, 'Status visibility.'),
                          'deleted' => new external_value(PARAM_INT, 'informs if this session was deleted.'),
                          'setnumber' => new external_value(PARAM_INT, 'Set number.'));

        $users = array('id' => new external_value(PARAM_INT, 'User id.'),
                       'firstname' => new external_value(PARAM_TEXT, 'User first name.'),
                       'lastname' => new external_value(PARAM_TEXT, 'User last name.'));

        $presencelog = array('studentid' => new external_value(PARAM_INT, 'Student id.'),
                                'statusid' => new external_value(PARAM_TEXT, 'Status id (last time).'),
                                'remarks' => new external_value(PARAM_TEXT, 'Last remark.'),
                                'id' => new external_value(PARAM_TEXT, 'log id.'));

        $session = self::get_session_structure();
        $session['courseid'] = new external_value(PARAM_INT, 'Course moodle id.');
        $session['statuses'] = new external_multiple_structure(new external_single_structure($statuses));
        $session['presence_evaluations'] = new external_multiple_structure(new external_single_structure($presencelog));
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

        $session = $DB->get_record('presence_sessions', array('id' => $params['sessionid']), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('presence', $session->presenceid, 0, false, MUST_EXIST);

        // Check permissions.
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/presence:view', $context);

        // If not a teacher, make sure session is open for self-marking.
        if (!has_capability('mod/presence:takepresences', $context)) {
            list($canmark, $reason) = presence_can_student_mark($session);
            if (!$canmark) {
                throw new invalid_parameter_exception($reason);
            }
        }

        // Check user id is valid.
        $student = $DB->get_record('user', array('id' => $params['studentid']), '*', MUST_EXIST);
        $takenby = $DB->get_record('user', array('id' => $params['takenbyid']), '*', MUST_EXIST);

        return presence_handler::update_user_status($params['sessionid'], $params['studentid'], $params['takenbyid'],
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
        $capacity = $DB->get_field('presence_rooms', 'capacity', ["id" => $roomid]);
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

        $booking = $DB->get_record('presence_bookings', array('id' => $bookingid));
        if (!$booking) {
            throw new invalid_parameter_exception('Invalid booking id or no permissions.');
        }
        $booking->session = $DB->get_record('presence_sessions', array('id' => $booking->sessionid));
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

        $bookedspots = $DB->count_records('presence_bookings', array('sessionid' => $sessionid));
        $booking = $DB->get_record('presence_bookings', array('sessionid' => $sessionid, 'userid' => $USER->id));
        $maxattendants = intval($DB->get_field('presence_sessions', 'maxattendants', array('id' => $sessionid)));
        $result = intval(boolval($booking));

        $errortitle = '';
        $errormessage = '';
        $errorconfirm = '';

        if ($booking && $book <= 0) {
            presence_delete_calendar_event_booking($booking);
            $DB->delete_records('presence_bookings', ['id' => $booking->id, 'userid' => $USER->id]);
            $bookedspots--;
            $result = 0;
        } else if (!$booking && $book >= 0) {
            if ($maxattendants > 0 && $bookedspots + 1 >= $maxattendants) {
                $errortitle = get_string('sessionfulltitle', 'presence');
                $errormessage = get_string('sessionfull', 'presence');
                $errorconfirm = get_string('ok');
            } else {
                $bookingid = $DB->insert_record('presence_bookings',
                    array('sessionid' => $sessionid, 'userid' => $USER->id, 'caleventid' => 0));
                $bookedspots++;
                presence_create_calendar_event_booking(self::get_booking($bookingid));
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
                'duration' => boolval($update['presence']) ? intval($update['duration']) : 0,
                'remarks_course' => trim(strip_tags($update['remarks_course'])),
                'remarks_personality' => trim(strip_tags($update['remarks_personality'])),
                'timetaken' => time(),
                'takenby' => $USER->id,
            ];


            $id = $DB->get_field('presence_evaluations', 'id', ['sessionid' => $sessionid, 'studentid' => $update['userid']]);
            if ($id) {
                $DB->update_record('presence_evaluations', array_merge(['id' => $id], $params));
            } else {
                $id = $DB->insert_record('presence_evaluations', $params);
                if (!$id) {
                    throw new coding_exception('Error putting presence into db.');
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
                    'presence' => new external_value(PARAM_TEXT, 'attendet this session?'),
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

    /**
     * Set new sws level for a given user.
     * @param int $userid
     * @param int $courseid
     * @param string $status
     * @param string $strengths
     * @param int $sws
     * @return array new status of sws
     */
    public static function update_user(int $userid, int $courseid, string $status, string $strengths, int $sws) : array {
        global $DB, $USER;
        if (!$userid) {
            throw new invalid_parameter_exception('Illegal parameters.');
        }

        $id = $DB->get_field('presence_user', 'id', ['userid' => $userid]);
        if (!$id) $id = $DB->insert_record('presence_user', [
            'userid' => $userid,
            'statusremark' => '',
            'strengths' => '',
            'timecreated' => time(),
        ]);
        $DB->update_record('presence_user', [
           'id' => $id,
           'statusremark' => trim($status),
           'strengths' => trim($strengths),
            'timemodified' => time(),
            'usermodified' => $USER->id,
        ]);

        if ($sws > 0) {
            $DB->delete_records_select('presence_sws', 'userid = :userid AND timemodified >= :timefrom AND timemodified < :timeto AND courseid = :courseid', [
                'userid' => $userid,
                'timefrom' => strtotime(date('Y-m-d')),
                'timeto' => strtotime(date('Y-m-d')) + (3600 * 24),
                'courseid' => $courseid,
            ]);

            $DB->insert_record('presence_sws', (object)[
                'userid' => $userid,
                'sws' => max(1, min(7, $sws)),
                'timemodified' => time(),
                'modifiedby' => $USER->id,
                'courseid' => $courseid,
            ]);
        }
        return [
            'status' => $status,
            'strengths' => $strengths,
            'sws' => $sws,
            'swspercent' => round($sws / 7 * 100),
            'swstext' => get_string('sws_level_' . $sws, 'presence'),
            'swstextshort' => get_string('sws_short', 'presence', $sws),
        ];
    }

    /**
     * Describes update_sws user parameters.
     *
     * @return external_function_parameters
     */
    public static function update_user_parameters() {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User id'),
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'status' => new external_value(PARAM_RAW, 'New status'),
            'strengths' => new external_value(PARAM_RAW, 'New strenghts'),
            'sws' => new external_value(PARAM_INT, 'New SWS level'),
        ]);
    }

    /**
     * Describes update_sws return values.
     *
     * @return external_single_structure
     */
    public static function update_user_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_RAW, 'new status of user'),
            'strengths' => new external_value(PARAM_RAW, 'new strengths of user'),
            'sws' => new external_value(PARAM_INT, 'new sws of user'),
            'swspercent' => new external_value(PARAM_INT, 'new sws in percent (rounded)'),
            'swstext' => new external_value(PARAM_TEXT, 'verbose description of new sws'),
            'swstextshort' => new external_value(PARAM_TEXT, 'short description of new sws'),
        ]);
    }


    /**
     * Send message to a user
     * @param int $userid
     * @param string $text
     * @return array result
     */
    public static function send_message(int $userid, string $text) : array {
        global $DB, $USER, $COURSE;
        if (!$userid) {
            throw new invalid_parameter_exception('Illegal parameters.');
        }
        // Ensure the current user is allowed to run this function
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:sendmessage', $context);

        $text = strip_tags($text);
        $message = new \core\message\message();
        $message->component = 'moodle';
        $message->name = 'instantmessage';
        $message->userfrom = intval($USER->id);
        $message->userto = $userid;
        $message->subject = trim('Message from '.$USER->firstname.' '.$USER->lastname);
        $message->fullmessage = $text;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $text;
        $message->smallmessage = $text;
        $message->notification = '0';
        $message->courseid = $COURSE->id;
        // $PAGE->set_context(CONTEXT_USER.$USER->id);

        // $content = array('*' => array('header' => ' test ', 'footer' => ' test ')); // Extra content for specific processor
        // $message->set_additional_content('email', $content);
        // $message->courseid = $course->id; // This is required in recent versions, use it from 3.2 on https://tracker.moodle.org/browse/MDL-47162
        // Actually send the message
        $messageid = message_send($message);

        return [
            'success' => true,
        ];
    }

    /**
     * Describes send_message user parameters.
     *
     * @return external_function_parameters
     */
    public static function send_message_parameters() {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'recipient user id'),
            'message' => new external_value(PARAM_RAW, 'message'),

        ]);
    }

    /**
     * Describes send_message return values.
     *
     * @return external_single_structure
     */
    public static function send_message_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_INT, 'success'),
        ]);
    }


    /**
     * Set new sws level for a given user.
     * @param int $sessionid
     * @param int $queryid
     * @param string $query
     * @return array new status of sws
     */
    public static function autocomplete_addstudent(int $sessionid, int $queryid, string $query) : array {
        global $DB, $USER;
        $presenceid = $DB->get_field('presence_sessions', 'presenceid', ['id' => $sessionid]);
        if (!$presenceid) {
            throw new invalid_parameter_exception('Invalid session.');
        }
        $courseid = $DB->get_field('presence', 'course', ['id' => $presenceid]);
        $context = context_course::instance($courseid, MUST_EXIST);
        $contextid = $context->id;
        $sql = "SELECT u.id, u.firstname, u.lastname, MIN(ue.status) as status, e.courseid
                  FROM {user} u
             LEFT JOIN {user_enrolments} ue ON u.id = ue.userid
             LEFT JOIN {enrol} e ON e.id = ue.enrolid
             LEFT JOIN {presence_bookings} attb ON ue.userid = attb.userid AND attb.sessionid = :sessionid
             LEFT JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = :contextid AND ra.roleid = :roleid
                 WHERE (e.courseid IS NULL OR e.courseid = :courseid)
                   AND (u.firstname <> '' OR u.lastname <> '')
                   AND u.id > 1
                   AND attb.id IS NULL
                   AND (LOWER(CONCAT(u.firstname, ' ', u.lastname)) LIKE :query1 OR LOWER(u.lastname) LIKE :query2)
              GROUP BY u.id, u.firstname, u.lastname, e.courseid
              ORDER BY e.courseid ASC, u.firstname ASC, u.lastname ASC, u.id ASC
                 LIMIT 10";
        $enrolments = $DB->get_records_sql($sql, [
            'query1' => strtolower($query).'%',
            'query2' => strtolower($query).'%',
            'courseid' => $courseid,
            'sessionid' => $sessionid,
            'contextid' => $contextid,
            'roleid' => $roleid,
        ]);

        $results = [];
        foreach ($enrolments as $enrolment) {
            $results[] = [
                'userid' => $enrolment->id,
                'name' => trim($enrolment->firstname . ' ' . $enrolment->lastname),
                'tag' => $enrolment->status == "0" ?
                    get_string('enrolled', 'presence') : get_string('notentrolled', 'presence'),
                'action' => $enrolment->status == "0" ? 1 : 2,
                'actiontext' => $enrolment->status == "0" ?
                    get_string('book', 'presence') : get_string('enrollandbook', 'presence'),
            ];
        }

        if (!count($results)) {
            $results[] = [
                'userid' => 0,
                'name' => ucfirst($query),
                'tag' => get_string('newuser', 'presence'),
                'action' => 3,
                'actiontext' => get_string('signupenrollandbook', 'presence'),
            ];
        }

        return [
            'query' => $query,
            'queryid' => $queryid,
            'results' => $results,
        ];
    }

    /**
     * Describes update_sws user parameters.
     *
     * @return external_function_parameters
     */
    public static function autocomplete_addstudent_parameters() {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session id'),
            'queryid' => new external_value(PARAM_INT, 'Query id'),
            'query' => new external_value(PARAM_TEXT, 'Search query for students'),
        ]);
    }

    /**
     * Describes update_sws return values.
     *
     * @return external_single_structure
     */
    public static function autocomplete_addstudent_returns() {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Query'),
            'queryid' => new external_value(PARAM_INT, 'Query id'),
            'results' => new external_multiple_structure(
                new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'user id'),
                    'name' => new external_value(PARAM_TEXT, 'name of user'),
                    'tag' => new external_value(PARAM_TEXT, 'result type'),
                    'action' => new external_value(PARAM_INT, 'action for this user'),
                    'actiontext' => new external_value(PARAM_TEXT, 'action text for this user'),
                ], 'list of results', VALUE_REQUIRED)
            ),
        ]);
    }


    /**
     * Book, enrol and sign up a list of users in one go.
     * @param int $sessionid
     * @param array $userdata
     * @return array result
     */
    public static function magic_useradd(int $sessionid, array $userdata) : array {
        global $DB, $USER, $PAGE;
        $presenceid = $DB->get_field('presence_sessions', 'presenceid', ['id' => $sessionid]);
        if (!$presenceid) {
            throw new invalid_parameter_exception('Invalid session.');
        }
        $courseid = $DB->get_field('presence', 'course', ['id' => $presenceid]);
        $enrolid = $DB->get_field('enrol', 'id', ['courseid' => $courseid, 'enrol' => 'manual']);
        if (!$enrolid) {
            throw new coding_exception("Error getting enrollment");
        }
        $studentroleid = $DB->get_field('role', 'id', ['archetype' => 'student']);
        $course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
        $context = context_course::instance($course->id, MUST_EXIST);

        if ($course->id == SITEID) {
            throw new moodle_exception('invalidcourse');
        }
        $manager = new course_enrolment_manager($PAGE, $course);
        if (!has_capability('moodle/role:assign', $context)) {
            throw new enrol_ajax_exception('assignnotpermitted');
        }
        if (!array_key_exists($studentroleid, get_assignable_roles($context, ROLENAME_ALIAS, false))) {
            throw new enrol_ajax_exception('invalidrole');
        }
        $instances = $manager->get_enrolment_instances();
        $plugins = $manager->get_enrolment_plugins(true); // Do not a
        if (!array_key_exists($enrolid, $instances)) {
            throw new enrol_ajax_exception('invalidenrolinstance');
        }
        $instance = $instances[$enrolid];
        if (!isset($plugins[$instance->enrol])) {
            throw new enrol_ajax_exception('enrolnotpermitted');
        }
        $plugin = $plugins[$instance->enrol];
        // We mimic get_enrolled_sql round(time(), -2) but always floor as we want users to always access their
        // courses once they are enrolled.
        $timestart = intval(substr(time(), 0, 8) . '00') - 1;
        $timeend = 0;

        foreach ($userdata as $user) {
            // sign up for moodle
            if ($user['action'] == 3) {
                $uname = strtolower(preg_replace('/[\W]/','', $user['name']));
                $names = explode(" ", ucfirst($user['name']));
                $user['lastname'] = array_pop($names);
                $user['firstname'] = implode(" ", $names);
                $user['email'] = trim($user['email']);
                $user['phone'] = trim($user['phone']);
                $found = false;
                for ($i = 0; $i < 1000; $i++) {
                    $user['username'] = $uname . ($i > 0 ? $i : '');
                    $unameexists = $DB->get_field('user', 'id', ['username' => $user['username']]);
                    if (!$unameexists) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    return ['result' => false];
                }
                $user['id'] = $DB->insert_record('user', [
                    'username' => $user['username'],
                    'firstname' => $user['firstname'],
                    'lastname' => $user['lastname'],
                    'email' => $user['email'],
                    'phone1' => $user['phone'],
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'lang' => 'de',
                    'mnethostid' => 1,
                ]);
                if (!$user['id']) {
                    throw new coding_exception("Error creating new user");
                }
                $sql = "SELECT MAX(CASE WHEN idnumber~E'^\\\\d+$' THEN CAST (idnumber AS INTEGER) ELSE 0 END) as num
                    FROM {user};";
                $res = $DB->get_record_sql($sql);
                $DB->update_record('user', ['id' => $user['id'], 'idnumber' => intval($res->num) + 1]);
            }
            if ($user['action'] >= 2) {
                if ($plugin->allow_enrol($instance) && has_capability('enrol/'.$plugin->get_name().':enrol', $context)) {
                    $plugin->enrol_user($instance, $user['id'], $studentroleid, $timestart, $timeend, null);
                } else {
                    throw new enrol_ajax_exception('enrolnotpermitted');
                }
            }
            // book into session
            if ($user['action'] >= 1) {
                $bookingid = $DB->get_field('presence_bookings', 'id', ['sessionid' => $sessionid, 'userid' => $user['id']]);
                $caleventid = $DB->get_field('presence_sessions', 'caleventid', ['id' => $sessionid]);
                if (!$bookingid) {
                    $DB->insert_record('presence_bookings', ['sessionid' => $sessionid, 'userid' => $user['id'], 'caleventid' => $caleventid]);
                }
            }
        }
        return [
            'result' => true,
        ];
    }

    /**
     * Describes update_sws user parameters.
     *
     * @return external_function_parameters
     */
    public static function magic_useradd_parameters() {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session id'),
            'userdata' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'user id'),
                    'action' => new external_value(PARAM_INT, 'action: 1=book, 2=enrol&book, 3=signup,enrol,book'),
                    'name' => new external_value(PARAM_TEXT, 'new user name'),
                    'email' => new external_value(PARAM_TEXT, 'new user email'),
                    'phone' => new external_value(PARAM_TEXT, 'new user phone'),
                ], 'list of updates', VALUE_REQUIRED)
            ),
        ]);
    }

    /**
     * Describes update_sws return values.
     *
     * @return external_single_structure
     */
    public static function magic_useradd_returns() {
        return new external_function_parameters([
            'result' => new external_value(PARAM_BOOL, 'Result'),
        ]);
    }

    /**
     * Check if any doublebookings occour.
     * @return array result
     */
    public static function check_doublebooking(int $sessionid, int $roomid, int $from, int $to,
                                         int $repeat, string $repeatdays, int $repeatperiod, int $repeatuntil) : array {
        global $DB;

        $duration = $to - $from;
        if ($duration < 0) return ['result' =>
            '<div class="alert alert-danger" role="alert">'.get_string('invalidsessionendtime', 'presence').'</div>'];
        else if ($duration == 0) return ['result' => ''];

        $cal = new mod_presence\calendar();
        if ($repeat) {
            $dates = $cal->get_series_dates($from, $repeatuntil, explode(',', $repeatdays), $repeatperiod);
        } else {
            $dates = [$from, ];
        }

        $html = '';

        foreach ($dates as $date) {
            if($roomid > 0) {
                $collisions = $DB->get_records_sql("
            SELECT ps.id, ps.sessdate, ps.duration, c.fullname
            FROM {presence_sessions} ps
            JOIN {presence} p ON p.id = ps.presenceid
            JOIN {course} c ON c.id = p.course
            WHERE roomid=".$roomid." 
            AND  (ps.sessdate < ".($date + $duration)." AND ps.sessdate + ps.duration > ".$date.")");

                if (count($collisions)) {
                    $html .= '<div class="alert alert-danger" role="alert">'
                        .userdate($date, get_string('strftimedaydatetime', 'langconfig'))
                        .' - '.userdate($date + $duration, get_string('strftimetime', 'langconfig'))
                        .': '
                        .$cal->rooms[$roomid]->name.' '
                        .get_string('roomoccupied', 'presence')
                        .'</div>';
                } else {
                    $html .= '<div class="alert alert-success" role="alert">'
                        .userdate($date, get_string('strftimedaydatetime', 'langconfig'))
                        .' - '.userdate($date + $duration, get_string('strftimetime', 'langconfig'))
                        .': '
                        .$cal->rooms[$roomid]->name.' '
                        .get_string('roomavailable', 'presence')
                        .'</div>';
                }
            } else {
                $html .= '<div class="alert alert-info" role="alert">'
                    .userdate($date, get_string('strftimedaydatetime', 'langconfig'))
                    .' - '.userdate($date + $duration, get_string('strftimetime', 'langconfig'))
                    .'</div>';
            }

        }

        return [
            'result' => $html,
        ];
    }

    /**
     * Describes check_doublebookings user parameters.
     *
     * @return external_function_parameters
     */
    public static function check_doublebooking_parameters() {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session id'),
            'roomid' => new external_value(PARAM_INT, 'roomid id'),
            'from' => new external_value(PARAM_INT, 'unix time from'),
            'to' => new external_value(PARAM_INT, 'unix time to'),
            'repeat' => new external_value(PARAM_INT, 'bool repeat?'),
            'repeatdays' => new external_value(PARAM_RAW, 'csv mo-su 1/0 if repeat that day'),
            'repeatperiod' => new external_value(PARAM_INT, 'repeat every n weeks'),
            'repeatuntil' => new external_value(PARAM_INT, 'repeat until unix timestamp'),
        ]);
    }

    /**
     * Describes check_doublebookings return values.
     *
     * @return external_single_structure
     */
    public static function check_doublebooking_returns() {
        return new external_function_parameters([
            'result' => new external_value(PARAM_RAW, 'course name'),
        ]);
    }
}
