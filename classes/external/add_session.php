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
            'studentscanmark' => new external_value(
                PARAM_BOOL,
                'students can record their own attendance (ignored if disabled for the site)',
                VALUE_DEFAULT,
                false
            ),
            'allowupdatestatus' => new external_value(
                PARAM_BOOL,
                'students can change their own status (needs studentscanmark, ignored if disabled for the site)',
                VALUE_DEFAULT,
                false
            ),
            'studentpassword' => new external_value(
                PARAM_TEXT,
                'password students must enter (needs studentscanmark)',
                VALUE_DEFAULT,
                ''
            ),
            'randompassword' => new external_value(
                PARAM_BOOL,
                'generate a random password instead of studentpassword (needs studentscanmark)',
                VALUE_DEFAULT,
                false
            ),
            'includeqrcode' => new external_value(
                PARAM_BOOL,
                'show a QR code with the password (needs studentscanmark)',
                VALUE_DEFAULT,
                false
            ),
            'rotateqrcode' => new external_value(
                PARAM_BOOL,
                'use a rotating QR code with a generated password instead of includeqrcode (needs studentscanmark)',
                VALUE_DEFAULT,
                false
            ),
            'autoassignstatus' => new external_value(
                PARAM_BOOL,
                'automatically select the highest available status (needs studentscanmark)',
                VALUE_DEFAULT,
                false
            ),
            'automark' => new external_value(
                PARAM_INT,
                'automatic marking: 0 = no, 1 = yes, 2 = set unmarked at end of session, 3 = on activity completion',
                VALUE_DEFAULT,
                0
            ),
            'automarkcmid' => new external_value(
                PARAM_INT,
                'course module id of the activity to use when automark is 3',
                VALUE_DEFAULT,
                0
            ),
            'preventsharedip' => new external_value(
                PARAM_INT,
                'prevent students sharing IP address: 0 = no, 1 = for preventsharediptime, 2 = yes (needs studentscanmark)',
                VALUE_DEFAULT,
                0
            ),
            'preventsharediptime' => new external_value(
                PARAM_INT,
                'minutes until an IP address can be re-used, required when preventsharedip is 1',
                VALUE_DEFAULT,
                0
            ),
            'absenteereport' => new external_value(
                PARAM_BOOL,
                'include in absentee report if warnings are enabled (default: absenteereport_default setting)',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED
            ),
            'studentsearlyopentime' => new external_value(
                PARAM_INT,
                'seconds before the start that students can record attendance (default: studentsearlyopentime setting)',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED
            ),
            'subnet' => new external_value(
                PARAM_TEXT,
                'subnet students must record attendance from (default: the subnet of the activity)',
                VALUE_DEFAULT,
                ''
            ),
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
     * @param bool $studentscanmark
     * @param bool $allowupdatestatus
     * @param string $studentpassword
     * @param bool $randompassword
     * @param bool $includeqrcode
     * @param bool $rotateqrcode
     * @param bool $autoassignstatus
     * @param int $automark
     * @param int $automarkcmid
     * @param int $preventsharedip
     * @param int $preventsharediptime
     * @param bool|null $absenteereport
     * @param int|null $studentsearlyopentime
     * @param string $subnet
     * @return array
     */
    public static function execute(
        int $attendanceid,
        string $description = '',
        int $sessiontime = 0,
        int $duration = 0,
        int $groupid = 0,
        bool $addcalendarevent = true,
        bool $studentscanmark = false,
        bool $allowupdatestatus = false,
        string $studentpassword = '',
        bool $randompassword = false,
        bool $includeqrcode = false,
        bool $rotateqrcode = false,
        bool $autoassignstatus = false,
        int $automark = 0,
        int $automarkcmid = 0,
        int $preventsharedip = 0,
        int $preventsharediptime = 0,
        ?bool $absenteereport = null,
        ?int $studentsearlyopentime = null,
        string $subnet = ''
    ): array {
        global $CFG, $USER, $DB;

        require_once(__DIR__ . '/../../locallib.php');
        // For the completion constants used by attendance_get_automarkoptions().
        require_once($CFG->libdir . '/completionlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'attendanceid' => $attendanceid,
            'description' => $description,
            'sessiontime' => $sessiontime,
            'duration' => $duration,
            'groupid' => $groupid,
            'addcalendarevent' => $addcalendarevent,
            'studentscanmark' => $studentscanmark,
            'allowupdatestatus' => $allowupdatestatus,
            'studentpassword' => $studentpassword,
            'randompassword' => $randompassword,
            'includeqrcode' => $includeqrcode,
            'rotateqrcode' => $rotateqrcode,
            'autoassignstatus' => $autoassignstatus,
            'automark' => $automark,
            'automarkcmid' => $automarkcmid,
            'preventsharedip' => $preventsharedip,
            'preventsharediptime' => $preventsharediptime,
            'absenteereport' => $absenteereport,
            'studentsearlyopentime' => $studentsearlyopentime,
            'subnet' => $subnet,
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

        // Self-marking options are only used when self-marking is enabled for the site and the session.
        $config = get_config('attendance');
        $selfmarking = $params['studentscanmark'] && !empty($config->studentscanmark);

        // Validate session options.
        self::validate_session_options($params, $cm, $course, $selfmarking);

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
        $sess->studentpassword = '';
        $sess->automark = $params['automark'];
        $sess->automarkcompleted = 0;
        $sess->automarkcmid = 0;
        if ($params['automark'] == ATTENDANCE_AUTOMARK_ACTIVITYCOMPLETION) {
            $sess->automarkcmid = $params['automarkcmid'];
        }
        $sess->includeqrcode = 0;
        $subnet = trim($params['subnet']);
        $sess->subnet = $subnet !== '' ? $subnet : $attendance->subnet;
        $sess->statusset = 0;
        $sess->groupid = $groupid;
        if ($params['studentsearlyopentime'] !== null) {
            $sess->studentsearlyopentime = $params['studentsearlyopentime'];
        } else {
            $sess->studentsearlyopentime = get_config('attendance', 'studentsearlyopentime');
        }

        // Like in the add session form, the absentee report option is only used when warnings are enabled.
        if (!empty($config->enablewarnings) && $params['absenteereport'] !== null) {
            $sess->absenteereport = (int)$params['absenteereport'];
        } else {
            $sess->absenteereport = get_config('attendance', 'absenteereport_default');
        }

        // Self-marking options.
        if ($selfmarking) {
            $sess->studentscanmark = 1;
            if ($params['allowupdatestatus'] && !empty($config->allowupdatestatus)) {
                $sess->allowupdatestatus = 1;
            }
            $sess->autoassignstatus = (int)$params['autoassignstatus'];
            $sess->preventsharedip = $params['preventsharedip'];
            if ($params['preventsharedip'] == ATTENDANCE_SHAREDIP_MINUTES) {
                $sess->preventsharediptime = $params['preventsharediptime'];
            }
            if ($params['rotateqrcode']) {
                // A rotating QR code comes with a generated password and replaces the static QR code.
                $sess->rotateqrcode = 1;
                $sess->rotateqrcodesecret = attendance_random_string();
                $sess->studentpassword = attendance_random_string();
            } else {
                $sess->includeqrcode = (int)$params['includeqrcode'];
                if ($params['randompassword']) {
                    $sess->studentpassword = attendance_random_string();
                } else {
                    $sess->studentpassword = $params['studentpassword'];
                }
            }
        }

        $sessionid = $attendance->add_session($sess);
        return ['sessionid' => $sessionid];
    }

    /**
     * Checks the session options with the same rules as the add session form and the CSV session import.
     *
     * @param array $params validated parameters of execute()
     * @param stdClass $cm course module of the attendance activity
     * @param stdClass $course course of the attendance activity
     * @param bool $selfmarking whether the self-marking options are used (requested and enabled for the site)
     */
    private static function validate_session_options(array $params, stdClass $cm, stdClass $course, bool $selfmarking): void {
        global $DB;

        // The available automark options depend on the course: on activity completion needs completion tracking.
        if (!array_key_exists($params['automark'], attendance_get_automarkoptions($course))) {
            throw new \invalid_parameter_exception('Invalid automark value: ' . $params['automark']);
        }
        if ($params['automark'] != ATTENDANCE_AUTOMARK_DISABLED) {
            // Automatic marking sets a status for unmarked students, which the status set must have.
            $conditions = ['attendanceid' => $cm->instance, 'setnumber' => 0, 'setunmarked' => 1, 'deleted' => 0];
            if (!$DB->record_exists('attendance_statuses', $conditions)) {
                throw new \invalid_parameter_exception('automark needs a status set with a status to use when unmarked');
            }
        }
        if (
            $params['automark'] == ATTENDANCE_AUTOMARK_ACTIVITYCOMPLETION &&
            !array_key_exists($params['automarkcmid'], attendance_get_coursemodulenames($course->id))
        ) {
            throw new \invalid_parameter_exception('automarkcmid must be an activity with completion tracking in this course');
        }
        if (!array_key_exists($params['preventsharedip'], attendance_get_sharedipoptions())) {
            throw new \invalid_parameter_exception('Invalid preventsharedip value: ' . $params['preventsharedip']);
        }
        if ($params['preventsharediptime'] < 0) {
            throw new \invalid_parameter_exception('preventsharediptime must not be negative');
        }
        if ($params['studentsearlyopentime'] !== null && $params['studentsearlyopentime'] < 0) {
            throw new \invalid_parameter_exception('studentsearlyopentime must not be negative');
        }
        if (\core_text::strlen($params['studentpassword']) > 50) {
            throw new \invalid_parameter_exception('studentpassword must not be longer than 50 characters');
        }
        if (\core_text::strlen(trim($params['subnet'])) > 255) {
            throw new \invalid_parameter_exception('subnet must not be longer than 255 characters');
        }

        // Same check as the CSV session import, which looks at the requested value, not at the site setting.
        if (!$params['studentscanmark'] && $params['includeqrcode']) {
            throw new \invalid_parameter_exception('includeqrcode needs studentscanmark');
        }

        // Same check as the add session form, which only offers self-marking when it is enabled for the site.
        if (
            $selfmarking &&
            $params['preventsharedip'] == ATTENDANCE_SHAREDIP_MINUTES &&
            empty($params['preventsharediptime'])
        ) {
            throw new \invalid_parameter_exception('preventsharediptime is required when preventsharedip is 1');
        }
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
