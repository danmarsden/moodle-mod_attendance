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
 * Unit tests for mod_attendance_structure.
 *
 * @package    mod_attendance
 * @category   test
 * @copyright  2026 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/attendance/classes/structure.php');

/**
 * Unit tests for mod_attendance_structure.
 *
 * @coversDefaultClass \mod_attendance_structure
 * @group mod_attendance
 */
final class structure_test extends advanced_testcase {
    /**
     * Tests low grade threshold defaults to 1 when attendance has no positive grade.
     *
     * @covers ::get_lowgrade_threshold
     */
    public function test_get_lowgrade_threshold_defaults_to_one_when_grade_is_zero(): void {
        [$attendance] = $this->create_attendance_structure(0);

        $this->assertSame(1, $attendance->get_lowgrade_threshold());
    }

    /**
     * Tests low grade threshold uses gradepass ratio from the grade item.
     *
     * @covers ::get_lowgrade_threshold
     */
    public function test_get_lowgrade_threshold_uses_gradepass_ratio(): void {
        global $DB;

        [$attendance] = $this->create_attendance_structure(100);

        $gradeitem = $DB->get_record('grade_items', [
            'courseid' => $attendance->course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'attendance',
            'iteminstance' => $attendance->id,
            'itemnumber' => 0,
        ], '*', MUST_EXIST);
        $gradeitem->gradepass = 60;
        $DB->update_record('grade_items', $gradeitem);

        $this->assertEqualsWithDelta(0.6, $attendance->get_lowgrade_threshold(), 0.0001);
    }

    /**
     * Tests low grade threshold remains safe when no grade item exists.
     *
     * @covers ::get_lowgrade_threshold
     */
    public function test_get_lowgrade_threshold_when_grade_item_missing(): void {
        global $DB;

        [$attendance] = $this->create_attendance_structure(100);

        $DB->delete_records('grade_items', [
            'courseid' => $attendance->course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'attendance',
            'iteminstance' => $attendance->id,
            'itemnumber' => 0,
        ]);

        $this->assertSame(1, $attendance->get_lowgrade_threshold());
    }

    /**
     * Create a course attendance activity and return structure with base records.
     *
     * @param int $grade
     * @return array
     */
    private function create_attendance_structure(int $grade): array {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $attrecord = $this->getDataGenerator()->create_module('attendance', [
            'course' => $course->id,
            'grade' => $grade,
        ]);

        $cm = $DB->get_record('course_modules', ['id' => $attrecord->cmid], '*', MUST_EXIST);
        $courserecord = $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
        $attendance = new \mod_attendance_structure($attrecord, $cm, $courserecord);

        return [$attendance, $course, $cm, $attrecord];
    }
}
