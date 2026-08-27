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
 * External method: update_user_status.
 *
 * @package    mod_attendance
 * @copyright  2026 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;

/**
 * External method: update_user_status.
 */
class update_user_status extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sessionid' => new external_value(PARAM_INT, 'Session id'),
            'studentid' => new external_value(PARAM_INT, 'Student id'),
            'takenbyid' => new external_value(PARAM_INT, 'Id of the user who took this session'),
            'statusid' => new external_value(PARAM_INT, 'Status id'),
            'statusset' => new external_value(
                PARAM_TEXT,
                'Status set of session'
            ),
        ]);
    }

    /**
     * Update user status.
     *
     * @param int $sessionid
     * @param int $studentid
     * @param int $takenbyid
     * @param int $statusid
     * @param int|string $statusset
     * @return mixed
     */
    public static function execute(int $sessionid, int $studentid, int $takenbyid, int $statusid, $statusset) {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'sessionid' => $sessionid,
            'studentid' => $studentid,
            'takenbyid' => $takenbyid,
            'statusid' => $statusid,
            'statusset' => $statusset,
        ]);

        $session = $DB->get_record('attendance_sessions', ['id' => $params['sessionid']], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('attendance', $session->attendanceid, 0, false, MUST_EXIST);

        // Check permissions.
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/attendance:view', $context);

        // If not a teacher, make sure session is open for self-marking.
        if (!has_capability('mod/attendance:takeattendances', $context)) {
            if ($params['studentid'] != $USER->id || $params['takenbyid'] != $USER->id) {
                throw new \invalid_parameter_exception('Invalid user id or no permissions.');
            }

            [$canmark, $reason] = attendance_can_student_mark($session);
            if (!$canmark) {
                throw new \invalid_parameter_exception($reason);
            }
        }

        // Check user id is valid.
        $DB->get_record('user', ['id' => $params['studentid']], '*', MUST_EXIST);
        $DB->get_record('user', ['id' => $params['takenbyid']], '*', MUST_EXIST);

        $attrecord = $DB->get_record('attendance', ['id' => $session->attendanceid], '*', MUST_EXIST);
        $course = get_course($cm->course);
        $att = new \mod_attendance_structure($attrecord, $cm, $course, $context);

        if ((int)$params['statusset'] !== (int)$session->statusset) {
            throw new \invalid_parameter_exception(get_string('invalidstatus', 'mod_attendance'));
        }

        if (!has_capability('mod/attendance:takeattendances', $context)) {
            [$allowedstatuses, $unused] = $att->get_student_statuses($session);
        } else {
            $allowedstatuses = attendance_get_statuses($session->attendanceid, true, (int)$session->statusset);
        }

        if (!isset($allowedstatuses[(int)$params['statusid']])) {
            throw new \invalid_parameter_exception(get_string('invalidstatus', 'mod_attendance'));
        }

        return \attendance_handler::update_user_status(
            $params['sessionid'],
            $params['studentid'],
            $params['takenbyid'],
            $params['statusid'],
            (int)$session->statusset
        );
    }

    /**
     * Return values.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_TEXT, 'Http code');
    }
}
