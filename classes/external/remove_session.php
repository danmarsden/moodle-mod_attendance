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
 * External method: remove_session.
 *
 * @package    mod_attendance
 * @copyright  2026 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;

/**
 * External method: remove_session.
 */
class remove_session extends external_api {
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
     * Delete session from attendance instance.
     *
     * @param int $sessionid
     * @return bool
     */
    public static function execute(int $sessionid): bool {
        global $DB;

        require_once(__DIR__ . '/../../lib.php');

        $params = self::validate_parameters(
            self::execute_parameters(),
            ['sessionid' => $sessionid]
        );

        $session = $DB->get_record('attendance_sessions', ['id' => $params['sessionid']], '*', MUST_EXIST);
        $attendance = $DB->get_record('attendance', ['id' => $session->attendanceid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('attendance', $attendance->id, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        // Check permissions.
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/attendance:manageattendances', $context);

        // Get attendance.
        $attendance = new \mod_attendance_structure($attendance, $cm, $course, $context);

        // Delete session.
        $attendance->delete_sessions([$sessionid]);
        attendance_update_users_grade($attendance);

        return true;
    }

    /**
     * Return values.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'attendance session deletion result');
    }
}
