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
 * External function tests for get_courses_with_today_sessions.
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
 * External function tests for get_courses_with_today_sessions.
 *
 * @group mod_attendance
 */
final class get_courses_with_today_sessions_test extends base_external_testcase {
    /**
     * @covers \mod_attendance\external\get_courses_with_today_sessions::execute
     */
    public function test_execute(): void {
        $this->resetAfterTest(true);

        $context = $this->create_attendance_context(5);
        $this->setUser($context['teacher']);
        $this->create_session($context['attendance']);

        $result = get_courses_with_today_sessions::execute($context['teacher']->id);
        $result = external_api::clean_returnvalue(get_courses_with_today_sessions::execute_returns(), $result);

        $this->assertCount(1, $result);
        $course = array_pop($result);
        $this->assertSame($context['course']->fullname, $course['fullname']);
        $attendanceinstance = array_pop($course['attendance_instances']);
        $this->assertNotEmpty($attendanceinstance['today_sessions']);
    }

    /**
     * @covers \mod_attendance\external\get_courses_with_today_sessions::execute
     */
    public function test_execute_invalid_userid(): void {
        $this->resetAfterTest(true);

        $this->expectException(\dml_missing_record_exception::class);
        get_courses_with_today_sessions::execute(999999999);
    }
}
