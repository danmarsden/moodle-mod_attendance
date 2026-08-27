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
 * External function tests for get_session.
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
 * External function tests for get_session.
 *
 * @group mod_attendance
 */
final class get_session_test extends base_external_testcase {
    /**
     * Tests retrieving a session as a teacher.
     *
     * @covers \mod_attendance\external\get_session::execute
     */
    public function test_execute(): void {
        $this->resetAfterTest(true);

        $context = $this->create_attendance_context(4);
        $this->setUser($context['teacher']);
        $session = $this->create_session($context['attendance']);

        $result = get_session::execute($session->id);
        $result = external_api::clean_returnvalue(get_session::execute_returns(), $result);

        $this->assertSame((int)$session->id, (int)$result['id']);
        $this->assertSame((int)$context['attendance']->id, (int)$result['attendanceid']);
        $this->assertCount(4, $result['users']);
    }

    /**
     * Tests that students cannot retrieve session details.
     *
     * @covers \mod_attendance\external\get_session::execute
     */
    public function test_execute_denies_student(): void {
        $this->resetAfterTest(true);

        $context = $this->create_attendance_context(2);
        $this->setUser($context['teacher']);
        $session = $this->create_session($context['attendance']);
        $this->setUser($context['students'][0]);

        $this->expectException(\invalid_parameter_exception::class);
        get_session::execute($session->id);
    }
}
