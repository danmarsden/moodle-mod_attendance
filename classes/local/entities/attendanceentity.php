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

namespace mod_attendance\local\entities;

use context_course;
use context_system;
use context_helper;
use core_course_category;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\course_selector;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\duration;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\custom_fields;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\local\entities\base;
use core_user\fields;
use core_reportbuilder\local\helpers\user_profile_fields;
use core_reportbuilder\local\entities\user;
use html_writer;
use lang_string;
use stdClass;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Attendance entity class implementation attendance
 *
 * This entity defines all the attendance columns and filters to be used in any report.
 *
 * @package     mod_attendance
 * @copyright   2022 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendanceentity extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
                'user' => 'attu',
                'context' => 'attctx',
                'course' => 'attc',
                'attendance' => 'att',
                'attendance_sessions' => 'attsess',
                'attendance_log' => 'attlog',
                'attendance_statuses' => 'attstat',
               ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('attendancereport', 'mod_attendance');
    }

    /**
     * Initialise the entity, add all user fields and all 'visible' user profile fields
     *
     * @return base
     */
    public function initialise(): base {

        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }

        // TODO: differentiate between filters and conditions (specifically the 'date' type: MDL-72662).
        $conditions = $this->get_all_filters();
        foreach ($conditions as $condition) {
            $this->add_condition($condition);
        }

        return $this;
    }

    /**
     * Returns list of all available columns
     *
     * These are all the columns available to use in any report that uses this entity.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {

        $columns = [];
        $usertablealias = $this->get_table_alias('user');
        $coursealias = $this->get_table_alias('course');
        $contexttablealias = $this->get_table_alias('context');
        $attendancealias = $this->get_table_alias('attendance');
        $attendancesessionalias = $this->get_table_alias('attendance_sessions');
        $attendancelogalias = $this->get_table_alias('attendance_log');
        $attendancestatusalias = $this->get_table_alias('attendance_statuses');

        $join = "
                    INNER JOIN {attendance_statuses} {$attendancestatusalias}
                    ON {$attendancestatusalias}.id = {$attendancelogalias}.statusid
                    INNER JOIN {attendance_sessions} {$attendancesessionalias}
                    ON {$attendancesessionalias}.id = {$attendancelogalias}.sessionid
                    INNER JOIN {attendance} {$attendancealias}
                    ON {$attendancealias}.id = {$attendancesessionalias}.attendanceid
                ";

        // Session name column.
        $columns[] = (new column(
            'name',
            new lang_string('sessionname', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancealias}.name");

        // Description column.
        $columns[] = (new column(
            'description',
            new lang_string('description', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancesessionalias}.description");

        // Session date column.
        $columns[] = (new column(
            'sessiondate',
            new lang_string('sessiondate', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancesessionalias}.sessdate")
            ->add_callback(static function ($value, $row): string {
                return userdate($value);
            });

        // Session duration column.
        $columns[] = (new column(
            'duration',
            new lang_string('duration', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancesessionalias}.duration");

        // Session last taken column.
        $columns[] = (new column(
            'lasttaken',
            new lang_string('lasttaken', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancesessionalias}.lasttaken")
            ->add_callback(static function ($value, $row): string {
                return userdate($value);
            });

        // Time modified column.
        $columns[] = (new column(
            'timemodified',
            new lang_string('timemodified', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancesessionalias}.timemodified")
            ->add_callback(static function ($value, $row): string {
                return userdate($value);
            });

        // Status column.
        $columns[] = (new column(
            'status',
            new lang_string('statuses', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancestatusalias}.description");

        // Grade column.
        $columns[] = (new column(
            'grade',
            new lang_string('attendancegrade', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancestatusalias}.grade");

        // Time taken column.
        $columns[] = (new column(
            'timetaken',
            new lang_string('timetaken', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancelogalias}.timetaken")
            ->add_callback(static function ($value, $row): string {
                return userdate($value);
            });

        // Remarks column.
        $columns[] = (new column(
            'remarks',
            new lang_string('remarks', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancelogalias}.remarks");

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {

        $filters = [];
        $usertablealias = $this->get_table_alias('user');
        $coursealias = $this->get_table_alias('course');
        $contexttablealias = $this->get_table_alias('context');
        $attendancealias = $this->get_table_alias('attendance');
        $attendancesessionalias = $this->get_table_alias('attendance_sessions');
        $attendancelogalias = $this->get_table_alias('attendance_log');
        $attendancestatusalias = $this->get_table_alias('attendance_statuses');

        $join = "
                    INNER JOIN {attendance_statuses} {$attendancestatusalias}
                    ON {$attendancestatusalias}.id = {$attendancelogalias}.statusid
                    INNER JOIN {attendance_sessions} {$attendancesessionalias}
                    ON {$attendancesessionalias}.id = {$attendancelogalias}.sessionid
                    INNER JOIN {attendance} {$attendancealias}
                    ON {$attendancealias}.id = {$attendancesessionalias}.attendanceid
                ";

        // Session name filter.
        $filters[] = (new filter(
            text::class,
            'nameselector',
            new lang_string('sessionname', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancealias}.name"
        ))
            ->add_join($join);

        // Description filter.
        $filters[] = (new filter(
            text::class,
            'description',
            new lang_string('description', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancesessionalias}.description"
        ))
            ->add_join($join);

        // Session date filter.
        $filters[] = (new filter(
            date::class,
            'sessiondate',
            new lang_string('sessiondate', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancesessionalias}.sessdate"
        ))
            ->add_join($join);

        // Duration filter.
        $filters[] = (new filter(
            duration::class,
            'duration',
            new lang_string('duration', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancesessionalias}.duration"
        ))
            ->add_join($join);

        // Last taken filter.
        $filters[] = (new filter(
            date::class,
            'lasttaken',
            new lang_string('lasttaken', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancesessionalias}.lasttaken"
        ))
            ->add_join($join);

        // Time modified filter.
        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('timemodified', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancesessionalias}.timemodified"
        ))
            ->add_join($join);

        // Status filter.
        $filters[] = (new filter(
            text::class,
            'status',
            new lang_string('statuses', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancestatusalias}.description"
        ))
            ->add_join($join);

        // Time taken filter.
        $filters[] = (new filter(
            date::class,
            'timetaken',
            new lang_string('timetaken', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancelogalias}.timetaken"
        ))
            ->add_join($join);

        // Remarks filter.
        $filters[] = (new filter(
            text::class,
            'remarks',
            new lang_string('remarks', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancelogalias}.remarks"
        ))
            ->add_join($join);

        return $filters;
    }
}
