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
 * External method: add_attendance.
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
 * External method: add_attendance.
 */
class add_attendance extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'course id'),
            'name' => new external_value(PARAM_TEXT, 'attendance name'),
            'intro' => new external_value(PARAM_RAW, 'attendance description', VALUE_DEFAULT, ''),
            'groupmode' => new external_value(
                PARAM_INT,
                'group mode (0 - no groups, 1 - separate groups, 2 - visible groups)',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    /**
     * Adds attendance instance to course.
     *
     * @param int $courseid
     * @param string $name
     * @param string $intro
     * @param int $groupmode
     * @return array
     */
    public static function execute(int $courseid, string $name, string $intro = '', int $groupmode = 0): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/modlib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'name' => $name,
            'intro' => $intro,
            'groupmode' => $groupmode,
        ]);

        // Get course.
        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);

        // Verify permissions.
        [$module, $context] = can_add_moduleinfo($course, 'attendance', 0);
        self::validate_context($context);
        require_capability('mod/attendance:addinstance', $context);

        // Verify group mode.
        if (!in_array($params['groupmode'], [NOGROUPS, SEPARATEGROUPS, VISIBLEGROUPS])) {
            throw new \invalid_parameter_exception('Group mode is invalid.');
        }

        // Populate modinfo object.
        $moduleinfo = new stdClass();
        $moduleinfo->modulename = 'attendance';
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

        return ['attendanceid' => $moduleinfo->instance];
    }

    /**
     * Return values.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'attendanceid' => new external_value(PARAM_INT, 'instance id of the created attendance'),
        ]);
    }
}
