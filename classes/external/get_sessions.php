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
 * External method: get_sessions.
 *
 * @package    mod_attendance
 * @copyright  2026 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * External method: get_sessions.
 */
class get_sessions extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attendanceid' => new external_value(PARAM_INT, 'Attendance id.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Get sessions.
     *
     * @param int $attendanceid
     * @return mixed
     */
    public static function execute(int $attendanceid) {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attendanceid' => $attendanceid,
        ]);

        // Check permissions.
        $cm = get_coursemodule_from_instance('attendance', $attendanceid, 0, false, MUST_EXIST);
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

        $sessions = $DB->get_records('attendance_sessions', ['attendanceid' => $params['attendanceid']], 'id ASC');

        $sessionsinfo = [];
        foreach ($sessions as $session) {
            $sessionsinfo[$session->id] = get_session::execute((int)$session->id);
        }

        return $sessionsinfo;
    }

    /**
     * Return values.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(get_session::execute_returns());
    }
}
