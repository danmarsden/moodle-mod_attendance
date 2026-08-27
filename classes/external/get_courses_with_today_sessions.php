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
 * External method: get_courses_with_today_sessions.
 *
 * @package    mod_attendance
 * @copyright  2026 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External method: get_courses_with_today_sessions.
 */
class get_courses_with_today_sessions extends external_api {
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
            'userid' => new external_value(PARAM_INT, 'User id.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Get list of courses with active sessions for today.
     *
     * @param int $userid
     * @return array
     */
    public static function execute(int $userid = 0): array {
        global $DB, $USER;

        require_once(__DIR__ . '/../structure.php');
        require_once(__DIR__ . '/../../../../lib/datalib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
        ]);

        $targetuserid = (int)$params['userid'];
        if ($targetuserid === 0) {
            $targetuserid = $USER->id;
        }

        // Check user id is valid.
        $DB->get_record('user', ['id' => $targetuserid], '*', MUST_EXIST);

        $usercourses = enrol_get_users_courses($targetuserid);
        $attendanceinstance = get_all_instances_in_courses('attendance', $usercourses);

        $coursessessions = [];
        foreach ($attendanceinstance as $attendance) {
            $context = \context_course::instance($attendance->course);
            if (!$context) {
                continue;
            }

            // Capability check is done per-course as the external method spans multiple course contexts.
            if (has_capability('mod/attendance:takeattendances', $context)) {
                $course = $usercourses[$attendance->course];
                if (!isset($course->attendance_instance)) {
                    $course->attendance_instance = [];
                }

                $att = new \stdClass();
                $att->id = $attendance->id;
                $att->course = $attendance->course;
                $att->name = $attendance->name;
                $att->grade = $attendance->grade;

                $cm = new \stdClass();
                $cm->id = $attendance->coursemodule;

                $att = new \mod_attendance_structure($att, $cm, $course, $context);
                $todaysessions = $att->get_today_sessions();

                if (!empty($todaysessions)) {
                    $course->attendance_instance[$att->id] = [];
                    $course->attendance_instance[$att->id]['name'] = $att->name;
                    $course->attendance_instance[$att->id]['today_sessions'] = $todaysessions;
                    $coursessessions[$course->id] = $course;
                }
            }
        }

        return self::prepare_data($coursessessions);
    }

    /**
     * Prepare webservice data payload.
     *
     * @param array $coursessessions
     * @return array
     */
    private static function prepare_data(array $coursessessions): array {
        $courses = [];

        foreach ($coursessessions as $course) {
            $courses[$course->id] = new \stdClass();
            $courses[$course->id]->shortname = $course->shortname;
            $courses[$course->id]->fullname = $course->fullname;
            $courses[$course->id]->attendance_instances = $course->attendance_instance;
        }

        return $courses;
    }

    /**
     * Return values.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        $todaysessions = self::get_session_structure();

        $attendanceinstances = [
            'name' => new external_value(PARAM_TEXT, 'Attendance name.'),
            'today_sessions' => new external_multiple_structure(
                new external_single_structure($todaysessions)
            ),
        ];

        $courses = [
            'shortname' => new external_value(PARAM_TEXT, 'short name of a moodle course.'),
            'fullname' => new external_value(PARAM_TEXT, 'full name of a moodle course.'),
            'attendance_instances' => new external_multiple_structure(
                new external_single_structure($attendanceinstances)
            ),
        ];

        return new external_multiple_structure(new external_single_structure($courses));
    }
}
