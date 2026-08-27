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
 * External method: add_session.
 *
 * @package    mod_attendance
 * @copyright  2026 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use stdClass;

/**
 * External method: add_session.
 */
class add_session extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attendanceid' => new external_value(PARAM_INT, 'attendance instance id'),
            'description' => new external_value(PARAM_RAW, 'description', VALUE_DEFAULT, ''),
            'sessiontime' => new external_value(PARAM_INT, 'session start timestamp'),
            'duration' => new external_value(PARAM_INT, 'session duration (seconds)', VALUE_DEFAULT, 0),
            'groupid' => new external_value(PARAM_INT, 'group id', VALUE_DEFAULT, 0),
            'addcalendarevent' => new external_value(PARAM_BOOL, 'add calendar event', VALUE_DEFAULT, true),
        ]);
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
    public static function execute(
        int $attendanceid,
        string $description = '',
        int $sessiontime = 0,
        int $duration = 0,
        int $groupid = 0,
        bool $addcalendarevent = true
    ): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'attendanceid' => $attendanceid,
            'description' => $description,
            'sessiontime' => $sessiontime,
            'duration' => $duration,
            'groupid' => $groupid,
            'addcalendarevent' => $addcalendarevent,
        ]);

        $cm = get_coursemodule_from_instance('attendance', $params['attendanceid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $attendance = $DB->get_record('attendance', ['id' => $cm->instance], '*', MUST_EXIST);

        // Check permissions.
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/attendance:manageattendances', $context);

        // Validate group.
        $groupid = $params['groupid'];
        $groupmode = (int)groups_get_activity_groupmode($cm);
        if ($groupmode === NOGROUPS && $groupid > 0) {
            throw new \invalid_parameter_exception('Group id is specified, but group mode is disabled for activity');
        } else if ($groupmode === SEPARATEGROUPS && $groupid === 0) {
            throw new \invalid_parameter_exception('Group id is not specified (or 0) in separate groups mode.');
        }
        if ($groupmode === SEPARATEGROUPS || ($groupmode === VISIBLEGROUPS && $groupid > 0)) {
            // Determine valid groups.
            $userid = has_capability('moodle/site:accessallgroups', $context) ? 0 : $USER->id;
            $validgroupids = array_map(function ($group) {
                return $group->id;
            }, groups_get_all_groups($course->id, $userid, $cm->groupingid));
            if (!in_array($groupid, $validgroupids)) {
                throw new \invalid_parameter_exception('Invalid group id');
            }
        }

        // Get attendance.
        $attendance = new \mod_attendance_structure($attendance, $cm, $course, $context);

        // Create session.
        $sess = new stdClass();
        $sess->sessdate = $params['sessiontime'];
        $sess->duration = $params['duration'];
        $sess->descriptionitemid = 0;
        $sess->description = $params['description'];
        $sess->descriptionformat = FORMAT_HTML;
        $sess->calendarevent = (int)$params['addcalendarevent'];
        $sess->timemodified = time();
        $sess->studentscanmark = 0;
        $sess->allowupdatestatus = 0;
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
        $sess->automarkcmid = 0;
        $sess->studentsearlyopentime = get_config('attendance', 'studentsearlyopentime');

        $sessionid = $attendance->add_session($sess);
        return ['sessionid' => $sessionid];
    }

    /**
     * Return values.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sessionid' => new external_value(PARAM_INT, 'id of the created session'),
        ]);
    }
}
