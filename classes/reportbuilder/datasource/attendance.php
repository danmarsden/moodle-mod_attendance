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

declare(strict_types=1);

namespace mod_attendance\reportbuilder\datasource;

use core_reportbuilder\datasource;
use mod_attendance\local\entities\attendanceentity;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;

/**
 * Attendance datasource
 *
 * @package   mod_attendance
 * @copyright 2022 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance extends datasource {

    /**
     * Return user friendly name of the datasource
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('attendancereport', 'mod_attendance');
    }

    /**
     * Initialise report
     */
    protected function initialise(): void {
        global $CFG;
        require_once($CFG->dirroot.'/mod/attendance/locallib.php');

        $attendanceentity = new attendanceentity();
        $attendancealias = $attendanceentity->get_table_alias('attendance');

        $attendancelogalias = $attendanceentity->get_table_alias('attendance_log');
        $this->set_main_table('attendance_log', $attendancelogalias);
        $this->add_entity($attendanceentity);

        // Add core user join.
        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $userjoin = "JOIN {user} {$useralias} ON {$useralias}.id = {$attendancelogalias}.studentid";
        $this->add_entity($userentity->add_join($userjoin));

        // Only show rows belonging to this course if there's a parameter for it.
        $course = attendace_get_course_helper();
        if (!empty($course) && can_access_course($course)) {
            $fieldvalueparam = database::generate_param_name();
            $this->add_base_condition_sql("{$attendancealias}.course = :{$fieldvalueparam}", [$fieldvalueparam => $course->id]);
        }

        $attendanceentityname = $attendanceentity->get_entity_name();
        $userentityname = $userentity->get_entity_name();

        $this->add_columns_from_entity($userentityname);
        $this->add_filters_from_entity($userentityname);
        $this->add_conditions_from_entity($userentityname);

        $this->add_columns_from_entity($attendanceentityname);
        $this->add_filters_from_entity($attendanceentityname);
        $this->add_conditions_from_entity($attendanceentityname);

    }

    /**
     * Return the columns that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_columns(): array {
        return [];
    }

    /**
     * Return the filters that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_filters(): array {
        return [];
    }

    /**
     * Return the conditions that will be added to the report once is created
     *
     * @return string[]
     */
    public function get_default_conditions(): array {
        return [];
    }
}
