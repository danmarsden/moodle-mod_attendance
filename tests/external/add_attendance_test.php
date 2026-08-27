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
 * External function tests for add_attendance.
 *
 * @package    mod_attendance
 * @category   test
 * @copyright  2026 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/base_external_testcase.php');

use core_external\external_api;

/**
 * External function tests for add_attendance.
 *
 * @group mod_attendance
 */
final class add_attendance_test extends base_external_testcase {
    /**
     * Tests adding an attendance instance as a teacher.
     *
     * @covers \mod_attendance\external\add_attendance::execute
     */
    public function test_execute(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $result = add_attendance::execute($course->id, 'Attendance A', 'Intro', NOGROUPS);
        $result = external_api::clean_returnvalue(add_attendance::execute_returns(), $result);

        $record = $DB->get_record('attendance', ['id' => $result['attendanceid']], '*', MUST_EXIST);
        $this->assertSame('Attendance A', $record->name);
    }

    /**
     * Tests that an invalid group mode is rejected.
     *
     * @covers \mod_attendance\external\add_attendance::execute
     */
    public function test_execute_invalid_groupmode(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\invalid_parameter_exception::class);
        add_attendance::execute($course->id, 'Attendance A', 'Intro', 999);
    }

    /**
     * Tests that students cannot add attendance instances.
     *
     * @covers \mod_attendance\external\add_attendance::execute
     */
    public function test_execute_denies_student(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        add_attendance::execute($course->id, 'Attendance A', 'Intro', NOGROUPS);
    }
}
