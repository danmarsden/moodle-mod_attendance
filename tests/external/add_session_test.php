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
 * External function tests for add_session.
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
 * External function tests for add_session.
 *
 * @group mod_attendance
 */
final class add_session_test extends base_external_testcase {
    /**
     * Tests adding a session as a teacher.
     *
     * @covers \mod_attendance\external\add_session::execute
     */
    public function test_execute(): void {
        global $DB;

        $this->resetAfterTest(true);
        $context = $this->create_attendance_context();
        $this->setUser($context['teacher']);

        $time = time();
        $result = add_session::execute($context['attendance']->id, 'Session A', $time, 1800, 0, true);
        $result = external_api::clean_returnvalue(add_session::execute_returns(), $result);

        $session = $DB->get_record('attendance_sessions', ['id' => $result['sessionid']], '*', MUST_EXIST);
        $this->assertSame('Session A', $session->description);
        $this->assertSame($time, (int)$session->sessdate);
    }

    /**
     * Tests that separate groups mode requires a non-zero group id.
     *
     * @covers \mod_attendance\external\add_session::execute
     */
    public function test_execute_requires_group_for_separate_groups(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $attendance = add_attendance::execute($course->id, 'Attendance A', 'Intro', SEPARATEGROUPS);
        $attendance = external_api::clean_returnvalue(add_attendance::execute_returns(), $attendance);

        $this->expectException(\invalid_parameter_exception::class);
        add_session::execute($attendance['attendanceid'], 'Session A', time(), 1800, 0, false);
    }

    /**
     * Tests that students cannot add sessions.
     *
     * @covers \mod_attendance\external\add_session::execute
     */
    public function test_execute_denies_student(): void {
        $this->resetAfterTest(true);
        $context = $this->create_attendance_context();
        $this->setUser($context['students'][0]);

        $this->expectException(\required_capability_exception::class);
        add_session::execute($context['attendance']->id, 'Session A', time(), 1800, 0, false);
    }
}
