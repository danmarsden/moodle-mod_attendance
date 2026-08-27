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
 * Shared external function test helpers for attendance.
 *
 * @package    mod_attendance
 * @category   test
 * @copyright  2026 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/attendance/classes/structure.php');

use externallib_advanced_testcase;
use mod_attendance_structure;
use stdClass;

/**
 * Shared setup helpers for attendance external API tests.
 */
abstract class base_external_testcase extends externallib_advanced_testcase {
    /**
     * Create a course with a teacher, enrolled students, and an attendance activity.
     *
     * @param int $studentcount Number of students to enrol.
     * @return array
     */
    protected function create_attendance_context(int $studentcount = 2): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $students = [];
        for ($i = 0; $i < $studentcount; $i++) {
            $students[] = $this->getDataGenerator()->create_and_enrol($course, 'student');
        }

        $att = $this->getDataGenerator()->create_module('attendance', ['course' => $course->id]);
        $cm = $DB->get_record('course_modules', ['id' => $att->cmid], '*', MUST_EXIST);
        $courserecord = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $attendance = new mod_attendance_structure($att, $cm, $courserecord);

        return [
            'course' => $course,
            'teacher' => $teacher,
            'students' => $students,
            'attendance' => $attendance,
        ];
    }

    /**
     * Add one attendance session and return the stored record.
     *
     * @param mod_attendance_structure $attendance
     * @param array $overrides
     * @return stdClass
     */
    protected function create_session(mod_attendance_structure $attendance, array $overrides = []): stdClass {
        global $DB;

        $session = new stdClass();
        $session->sessdate = time();
        $session->duration = 3600;
        $session->description = 'test session';
        $session->descriptionformat = FORMAT_HTML;
        $session->descriptionitemid = 0;
        $session->timemodified = time();
        $session->statusset = 0;
        $session->groupid = 0;
        $session->absenteereport = 1;
        $session->calendarevent = 0;

        foreach ($overrides as $field => $value) {
            $session->{$field} = $value;
        }

        $attendance->add_sessions([$session]);

        return $DB->get_record_sql(
            'SELECT *
               FROM {attendance_sessions}
              WHERE attendanceid = :attendanceid
           ORDER BY id DESC',
            ['attendanceid' => $attendance->id],
            MUST_EXIST
        );
    }
}
