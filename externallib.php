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

require_once("$CFG->libdir/externallib.php");
require_once(dirname(__FILE__).'/classes/attendance_webservices_handler.php');

/**
 * Class mod_wsattendance_external
 * @copyright  2015 Caio Bressan Doneda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_wsattendance_external extends external_api {

    /**
     * Describes the parameters for add_session.
     *
     * @return external_function_parameters
     */
    public static function add_session_parameters() {
        return new external_function_parameters(
            array(
                'attendanceid' => new external_value(PARAM_INT, 'attendance instance id'),
                'groupid' => new external_value(PARAM_INT, 'group id', VALUE_DEFAULT, 0),
                'sessiontime' => new external_value(PARAM_INT, 'session start timestamp'),
                'duration' => new external_value(PARAM_INT, 'session duration (seconds)', VALUE_DEFAULT, 0),
                'description' => new external_value(PARAM_RAW, 'field description', VALUE_DEFAULT, ''),
                'addcalendarevent' => new external_value(PARAM_BOOL, 'add calendar event', VALUE_DEFAULT, true),
            )
        );
    }

    /**
     * Adds session.
     *
     * @param int $userid
     * @return int $sessionid
     */
    public static function add_session(int $attendanceid, int $groupid, int $sessiontime, int $duration, $description, bool $addcalendarevent) {
        global $USER;

        $cm = get_coursemodule_from_id('attendance', $attendanceid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $attendance = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);

        // Check permissions.
        $context = context_module::instance($cm->id);
        require_capability('mod/attendance:manageattendances', $context);

        // Get attendance.
        $attendance = new mod_attendance_structure($attendance, $cm, $course, $context);

        // Create session.
        $sess = new stdClass();
        $sess->sessdate = $sessiontime;
        $sess->duration = $duration;
        $sess->descriptionitemid = 0;
        $sess->description = $description;
        $sess->descriptionformat = FORMAT_HTML;
        $sess->calendarevent = (int) $addcalendarevent;
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

        // Validate group.
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode === NOGROUPS && $groupid > 0) {
            throw new invalid_parameter_exception('Group id is specified, but group mode is disabled for activity');
        } else if ($groupmode === SEPARATEGROUPS && $groupid === 0) {
            throw new invalid_parameter_exception('Group id is not specified (or 0) in separate groups mode.');
        }
        if ($groupmode === SEPARATEGROUPS || ($groupmode === VISIBLEGROUPS && $groupid > 0)) {
            // Determine valid groups
            $userid = has_capability('moodle/site:accessallgroups', $context) ? 0 : $USER->id;
            $validgroupids = array_map(function($group) { return $group->id; },
                groups_get_all_groups($course->id, $userid, $cm->groupingid));
            if (!in_array($groupid, $valudgroupids)) {
                throw new invalid_parameter_exception('Invalid group id');
            }
        }

        $att->add_sessions(array($sess));
    }

    /**
     * Describes add_session return values.
     *
     * @return external_multiple_structure
     */
    public static function add_session_returns() {
        return new external_single_structure(array(
            'sessionid' => new external_value(PARAM_INT, 'ID of the created session'),
        ));
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
        return attendance_handler::get_courses_with_today_sessions($userid);
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
        $session['attendance_log'] = new external_multiple_structure(new external_single_structure($attendancelog));
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
        return attendance_handler::update_user_status($sessionid, $studentid, $takenbyid, $statusid, $statusset);
    }

    /**
     * Show return values.
     * @return external_value
     */
    public static function update_user_status_returns() {
        return new external_value(PARAM_TEXT, 'Http code');
    }
}
