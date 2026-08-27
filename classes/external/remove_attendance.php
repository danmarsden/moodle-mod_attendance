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
 * External method: remove_attendance.
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
 * External method: remove_attendance.
 */
class remove_attendance extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attendanceid' => new external_value(PARAM_INT, 'attendance instance id'),
        ]);
    }

    /**
     * Remove attendance instance.
     *
     * @param int $attendanceid
     * @return bool
     */
    public static function execute(int $attendanceid): bool {
        require_once(__DIR__ . '/../../lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'attendanceid' => $attendanceid,
        ]);

        $cm = get_coursemodule_from_instance('attendance', $params['attendanceid'], 0, false, MUST_EXIST);

        // Check permissions.
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/attendance:manageattendances', $context);

        // Delete attendance instance.
        $result = attendance_delete_instance($params['attendanceid']);
        rebuild_course_cache($cm->course, true);
        return $result;
    }

    /**
     * Return values.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_BOOL, 'attendance deletion result');
    }
}
