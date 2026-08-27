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
 * External method: get_session.
 *
 * @package    mod_attendance
 * @copyright  2026 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../attendance_webservices_handler.php');

use core_external\external_api;
use core_external\external_multiple_structure;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External method: get_session.
 */
class get_session extends external_api {
    /**
     * Get structure of an attendance session.
     *
     * @return array
     */
    private static function get_session_structure(): array {
        return [
            'id' => new external_value(PARAM_INT, 'Session id.'),
            'attendanceid' => new external_value(PARAM_INT, 'Attendance id.'),
            'groupid' => new external_value(PARAM_INT, 'Group id.'),
            'sessdate' => new external_value(PARAM_INT, 'Session date.'),
            'duration' => new external_value(PARAM_INT, 'Session duration.'),
            'lasttaken' => new external_value(PARAM_INT, 'Session last taken time.'),
            'lasttakenby' => new external_value(PARAM_INT, 'ID of the last user that took this session.'),
            'timemodified' => new external_value(PARAM_INT, 'Time modified.'),
            'description' => new external_value(PARAM_RAW, 'Session description.'),
            'descriptionformat' => new external_value(PARAM_INT, 'Session description format.'),
            'studentscanmark' => new external_value(PARAM_INT, 'Students can mark their own presence.'),
            'absenteereport' => new external_value(PARAM_INT, 'Session included in absetee reports.'),
            'autoassignstatus' => new external_value(PARAM_INT, 'Automatically assign a status to students.'),
            'preventsharedip' => new external_value(PARAM_INT, 'Prevent students from sharing IP addresses.'),
            'preventsharediptime' => new external_value(PARAM_INT, 'Time delay before IP address is allowed again.'),
            'statusset' => new external_value(PARAM_INT, 'Session statusset.'),
            'includeqrcode' => new external_value(PARAM_INT, 'Include QR code when displaying password'),
            'studentsearlyopentime' => new external_value(PARAM_INT, 'Duration to allow session to opened early'),
        ];
    }

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'session id'),
        ]);
    }

    /**
     * Get session.
     *
     * @param int $sessionid
     * @return mixed
     */
    public static function execute(int $sessionid) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'sessionid' => $sessionid,
        ]);

        $session = $DB->get_record('attendance_sessions', ['id' => $params['sessionid']], '*', MUST_EXIST);
        $attendance = $DB->get_record('attendance', ['id' => $session->attendanceid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('attendance', $attendance->id, 0, false, MUST_EXIST);

        // Check permissions.
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        $capabilities = [
            'mod/attendance:manageattendances',
            'mod/attendance:takeattendances',
            'mod/attendance:changeattendances',
        ];
        if (!has_any_capability($capabilities, $context)) {
            throw new \invalid_parameter_exception('Invalid session id or no permissions.');
        }

        return \attendance_handler::get_session($sessionid);
    }

    /**
     * Return values.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $statuses = [
            'id' => new external_value(PARAM_INT, 'Status id.'),
            'attendanceid' => new external_value(PARAM_INT, 'Attendance id.'),
            'acronym' => new external_value(PARAM_TEXT, 'Status acronym.'),
            'description' => new external_value(PARAM_RAW, 'Status description.'),
            'grade' => new external_value(PARAM_FLOAT, 'Status grade.'),
            'visible' => new external_value(PARAM_INT, 'Status visibility.'),
            'deleted' => new external_value(PARAM_INT, 'informs if this session was deleted.'),
            'setnumber' => new external_value(PARAM_INT, 'Set number.'),
        ];

        $users = [
            'id' => new external_value(PARAM_INT, 'User id.'),
            'firstname' => new external_value(PARAM_TEXT, 'User first name.'),
            'lastname' => new external_value(PARAM_TEXT, 'User last name.'),
        ];

        $attendancelog = [
            'studentid' => new external_value(PARAM_INT, 'Student id.'),
            'statusid' => new external_value(PARAM_TEXT, 'Status id (last time).'),
            'remarks' => new external_value(PARAM_TEXT, 'Last remark.'),
            'id' => new external_value(PARAM_TEXT, 'log id.'),
        ];

        $session = self::get_session_structure();
        $session['courseid'] = new external_value(PARAM_INT, 'Course moodle id.');
        $session['statuses'] = new external_multiple_structure(new external_single_structure($statuses));
        $session['attendance_log'] = new external_multiple_structure(new external_single_structure($attendancelog));
        $session['users'] = new external_multiple_structure(new external_single_structure($users));

        return new external_single_structure($session);
    }
}
