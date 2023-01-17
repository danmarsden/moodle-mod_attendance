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

use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\duration;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\local\entities\base;
use core_user\fields;
use core_reportbuilder\local\helpers\user_profile_fields;
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\filters\number;
use lang_string;
use stdClass;

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
class attendance extends base {

    /** @var array  */
    private $acronyms = [];

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

        $attendancealias = $this->get_table_alias('attendance');
        $attendancesessionalias = $this->get_table_alias('attendance_sessions');
        $attendancelogalias = $this->get_table_alias('attendance_log');
        $attendancestatusalias = $this->get_table_alias('attendance_statuses');

        $join = $this->attendancejoin();

        // Attendance name column.
        $columns[] = (new column(
            'name',
            new lang_string('name', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancealias}.name");

        // Now handle session columns.

        // Description column.
        $columns[] = (new column(
            'sessiondescription',
            new lang_string('sessiondescription', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancesessionalias}.description");

        // Session date column.
        $columns[] = (new column(
            'sessiondate',
            new lang_string('reportsessiondate', 'mod_attendance'),
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
            new lang_string('reportsessionduration', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancesessionalias}.duration");

        // Session last taken column.
        $columns[] = (new column(
            'lasttaken',
            new lang_string('reportsessionlasttaken', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancesessionalias}.lasttaken")
            ->add_callback(static function ($value, $row): string {
                return userdate($value);
            });
        // Now add Log columns.

        // Time taken column.
        $columns[] = (new column(
            'timetaken',
            new lang_string('usersessiontaken', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancelogalias}.timetaken")
            ->add_callback(static function ($value, $row): string {
                return userdate($value);
            });

        // Now add Status columns.

        // Status column.
        $columns[] = (new column(
            'status',
            new lang_string('userstatus', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancestatusalias}.acronym");

        // Grade column.
        $columns[] = (new column(
            'grade',
            new lang_string('usersessiongrade', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancestatusalias}.grade");

        // Remarks column.
        $columns[] = (new column(
            'remarks',
            new lang_string('usersessionremarks', 'mod_attendance'),
            $this->get_entity_name()
        ))
            ->add_join($join)
            ->set_is_sortable(true)
            ->add_field("{$attendancelogalias}.remarks");

        // Attendance status totals.
        $thismonday = strtotime('monday this week');
        $lastmonday = strtotime('monday last week');
        foreach ($this->statusacronyms() as $acronym) {
            list($fieldname, $fieldnamecw, $fieldnamepw) = $this->acronymfieldnames($acronym);

            // Status total count column.
            $columns[] = (new column(
                $fieldname,
                new lang_string('statustotalcount', 'mod_attendance', $acronym),
                $this->get_entity_name()
            ))
                ->add_join($join)
                ->add_join("LEFT JOIN (
                    SELECT a.course, atst.id statusid, atlo.studentid, COUNT(atst.acronym) count
                    FROM {attendance_statuses} atst
                    JOIN {attendance_log} atlo ON atlo.statusid = atst.id
                    JOIN {attendance} a ON a.id = atst.attendanceid AND atst.acronym = '$acronym'
                    GROUP BY a.course, atst.id, atlo.studentid
                ) $fieldname
                ON $fieldname.course = $attendancealias.course
                AND $fieldname.statusid = $attendancestatusalias.id
                AND $fieldname.studentid = $attendancelogalias.studentid")
                ->set_is_sortable(true)
                ->add_field("$fieldname.count");

            // Status total count in the current week column.
            $columns[] = (new column(
                $fieldnamecw,
                new lang_string('statustotalcountcurrentweek', 'mod_attendance', $acronym),
                $this->get_entity_name()
            ))
                ->add_join($join)
                ->add_join("LEFT JOIN (
                    SELECT a.course, atst.id statusid, atlo.studentid, COUNT(atst.acronym) count
                    FROM {attendance_statuses} atst
                    JOIN {attendance_log} atlo ON atlo.statusid = atst.id
                    JOIN {attendance} a ON a.id = atst.attendanceid AND atst.acronym = '$acronym'
                    AND atlo.timetaken >= $thismonday
                    GROUP BY a.course, atst.id, atlo.studentid
                ) $fieldnamecw
                ON $fieldnamecw.course = $attendancealias.course
                AND $fieldnamecw.statusid = $attendancestatusalias.id
                AND $fieldnamecw.studentid = $attendancelogalias.studentid")
                ->set_is_sortable(true)
                ->add_field("{$fieldnamecw}.count");

            // Status total count in the previous week column.
            $columns[] = (new column(
                $fieldnamepw,
                new lang_string('statustotalcountpreviousweek', 'mod_attendance', $acronym),
                $this->get_entity_name()
            ))
                ->add_join($join)
                ->add_join("LEFT JOIN (
                    SELECT a.course, atst.id statusid, atlo.studentid, COUNT(atst.acronym) count
                    FROM {attendance_statuses} atst
                    JOIN {attendance_log} atlo ON atlo.statusid = atst.id
                    JOIN {attendance} a ON a.id = atst.attendanceid AND atst.acronym = '$acronym'
                    AND atlo.timetaken >= $lastmonday
                    AND atlo.timetaken < $thismonday
                    GROUP BY a.course, atst.id, atlo.studentid
                ) $fieldnamepw
                ON $fieldnamepw.course = $attendancealias.course
                AND $fieldnamepw.statusid = $attendancestatusalias.id
                AND $fieldnamepw.studentid = $attendancelogalias.studentid")
                ->set_is_sortable(true)
                ->add_field("{$fieldnamepw}.count");
        }

        return $columns;
    }

    /**
     * Return list of all available filters
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {

        $filters = [];
        $attendancealias = $this->get_table_alias('attendance');
        $attendancesessionalias = $this->get_table_alias('attendance_sessions');
        $attendancelogalias = $this->get_table_alias('attendance_log');
        $attendancestatusalias = $this->get_table_alias('attendance_statuses');

        $join = $this->attendancejoin();

        // Session name filter.
        $filters[] = (new filter(
            text::class,
            'nameselector',
            new lang_string('name', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancealias}.name"
        ))
            ->add_join($join);

        // Description filter.
        $filters[] = (new filter(
            text::class,
            'sessiondescription',
            new lang_string('sessiondescription', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancesessionalias}.description"
        ))
            ->add_join($join);

        // Session date filter.
        $filters[] = (new filter(
            date::class,
            'sessiondate',
            new lang_string('reportsessiondate', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancesessionalias}.sessdate"
        ))
            ->add_join($join);

        // Duration filter.
        $filters[] = (new filter(
            duration::class,
            'duration',
            new lang_string('reportsessionduration', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancesessionalias}.duration"
        ))
            ->add_join($join);

        // Last taken filter.
        $filters[] = (new filter(
            date::class,
            'lasttaken',
            new lang_string('reportsessionlasttaken', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancesessionalias}.lasttaken"
        ))
            ->add_join($join);

        // Status filter.
        $filters[] = (new filter(
            text::class,
            'status',
            new lang_string('userstatus', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancestatusalias}.acronym"
        ))
            ->add_join($join);

        // Time taken filter.
        $filters[] = (new filter(
            date::class,
            'timetaken',
            new lang_string('usersessiontaken', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancelogalias}.timetaken"
        ))
            ->add_join($join);

        // Remarks filter.
        $filters[] = (new filter(
            text::class,
            'remarks',
            new lang_string('usersessionremarks', 'mod_attendance'),
            $this->get_entity_name(),
            "{$attendancelogalias}.remarks"
        ))
            ->add_join($join);

        $thismonday = strtotime('monday this week');
        $lastmonday = strtotime('monday last week');
        foreach ($this->statusacronyms() as $acronym) {
            list($fieldname, $fieldnamecw, $fieldnamepw) = $this->acronymfieldnames($acronym);

            // Status total count filter.
            $filters[] = (new filter(
                number::class,
                $fieldname,
                new lang_string('statustotalcount', 'mod_attendance', $acronym),
                $this->get_entity_name(),
                "{$fieldname}.count"
            ))
                ->add_join($join)
                ->add_join("LEFT JOIN (
                    SELECT a.course, atst.id statusid, atlo.studentid, COUNT(atst.acronym) count
                    FROM {attendance_statuses} atst
                    JOIN {attendance_log} atlo ON atlo.statusid = atst.id
                    JOIN {attendance} a ON a.id = atst.attendanceid AND atst.acronym = '$acronym'
                    GROUP BY a.course, atst.id, atlo.studentid
                ) $fieldname
                ON $fieldname.course = $attendancealias.course
                AND $fieldname.statusid = $attendancestatusalias.id
                AND $fieldname.studentid = $attendancelogalias.studentid");

            // Status total count in the current week filter.
            $filters[] = (new filter(
                number::class,
                $fieldnamecw,
                new lang_string('statustotalcountcurrentweek', 'mod_attendance', $acronym),
                $this->get_entity_name(),
                "{$fieldnamecw}.count"
            ))
                ->add_join($join)
                ->add_join("LEFT JOIN (
                    SELECT a.course, atst.id statusid, atlo.studentid, COUNT(atst.acronym) count
                    FROM {attendance_statuses} atst
                    JOIN {attendance_log} atlo ON atlo.statusid = atst.id
                    JOIN {attendance} a ON a.id = atst.attendanceid AND atst.acronym = '$acronym'
                    AND atlo.timetaken >= $thismonday
                    GROUP BY a.course, atst.id, atlo.studentid
                ) $fieldnamecw
                ON $fieldnamecw.course = $attendancealias.course
                AND $fieldnamecw.statusid = $attendancestatusalias.id
                AND $fieldnamecw.studentid = $attendancelogalias.studentid");

            // Status total count in the previous week filter.
            $filters[] = (new filter(
                number::class,
                $fieldnamepw,
                new lang_string('statustotalcountpreviousweek', 'mod_attendance', $acronym),
                $this->get_entity_name(),
                "{$fieldnamepw}.count"
            ))
                ->add_join($join)
                ->add_join("LEFT JOIN (
                    SELECT a.course, atst.id statusid, atlo.studentid, COUNT(atst.acronym) count
                    FROM {attendance_statuses} atst
                    JOIN {attendance_log} atlo ON atlo.statusid = atst.id
                    JOIN {attendance} a ON a.id = atst.attendanceid AND atst.acronym = '$acronym'
                    AND atlo.timetaken >= $lastmonday
                    AND atlo.timetaken < $thismonday
                    GROUP BY a.course, atst.id, atlo.studentid
                ) $fieldnamepw
                ON $fieldnamepw.course = $attendancealias.course
                AND $fieldnamepw.statusid = $attendancestatusalias.id
                AND $fieldnamepw.studentid = $attendancelogalias.studentid");
        }

        return $filters;
    }

    /**
     * Helper function to get main join.
     *
     * @return string
     */
    public function attendancejoin() {
        $attendancealias = $this->get_table_alias('attendance');
        $attendancesessionalias = $this->get_table_alias('attendance_sessions');
        $attendancelogalias = $this->get_table_alias('attendance_log');
        $attendancestatusalias = $this->get_table_alias('attendance_statuses');

        return "JOIN {attendance_statuses} {$attendancestatusalias}
                    ON {$attendancestatusalias}.id = {$attendancelogalias}.statusid
                JOIN {attendance_sessions} {$attendancesessionalias}
                    ON {$attendancesessionalias}.id = {$attendancelogalias}.sessionid
                JOIN {attendance} {$attendancealias}
                    ON {$attendancealias}.id = {$attendancesessionalias}.attendanceid";
    }

    /**
     * Get a list of distinct status acronyms used across courses.
     *
     * @return array
     */
    private function statusacronyms(): array {
        if (!empty($this->acronyms)) {
            return $this->acronyms;
        }
        global $DB;
        $statuserecords = $DB->get_records_sql('SELECT DISTINCT acronym FROM {attendance_statuses} WHERE deleted = 0');
        foreach ($statuserecords as $statuserecord) {
            $acronyms[] = $statuserecord->acronym;
        }
        $this->acronyms = $acronyms;
        return $acronyms;
    }

    /**
     * Return a set of fieldnames using the acronym given.
     *
     * Index of fieldname values.
     * * [0] status_{$acronym}_total_count
     * * [1] status_{$acronym}_total_count_current_week
     * * [2] status_{$acronym}_total_count_previous_week
     *
     * @param string $acronym A status acronym.
     * @return array
     */
    private function acronymfieldnames(string $acronym): array {
        $fieldname = 'status_' . strtolower($acronym) . '_total_count';
        return [
            $fieldname,
            $fieldname . '_current_week',
            $fieldname . '_previous_week',
        ];
    }
}
