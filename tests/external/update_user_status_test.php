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
 * External function tests for attendance update_user_status.
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
 * External function tests for attendance update_user_status.
 *
 * @group mod_attendance
 */
final class update_user_status_test extends base_external_testcase {
    /**
     * Test update_user_status::execute updates a student's attendance status.
     *
     * @covers \mod_attendance\external\update_user_status::execute
     */
    public function test_execute_updates_status(): void {
        $this->resetAfterTest(true);

        $context = $this->create_attendance_context(4);
        $this->setUser($context['teacher']);
        $session = $this->create_session($context['attendance']);

        $sessioninfo = get_session::execute($session->id);
        $sessioninfo = external_api::clean_returnvalue(get_session::execute_returns(), $sessioninfo);

        $student = reset($sessioninfo['users']);
        $status = reset($sessioninfo['statuses']);

        $result = update_user_status::execute(
            $session->id,
            $student['id'],
            $context['teacher']->id,
            $status['id'],
            $sessioninfo['statusset']
        );
        $result = external_api::clean_returnvalue(update_user_status::execute_returns(), $result);

        $this->assertIsString($result);
        $this->assertNotSame('', trim($result));
    }

    /**
     * Test update_user_status::execute rejects updates to another student.
     *
     * @covers \mod_attendance\external\update_user_status::execute
     */
    public function test_execute_disallows_other_students(): void {
        global $DB;

        $this->resetAfterTest(true);
        set_config('studentscanmark', 1, 'attendance');

        $context = $this->create_attendance_context(3);
        $this->setUser($context['teacher']);
        $session = $this->create_session($context['attendance']);
        $DB->set_field('attendance_sessions', 'studentscanmark', 1, ['id' => $session->id]);

        $sessioninfo = get_session::execute($session->id);
        $sessioninfo = external_api::clean_returnvalue(get_session::execute_returns(), $sessioninfo);
        $status = reset($sessioninfo['statuses']);

        $attacker = $context['students'][0];
        $victim = $context['students'][1];

        $this->setUser($attacker);
        $this->expectException(\invalid_parameter_exception::class);
        update_user_status::execute($session->id, $victim->id, $victim->id, $status['id'], $sessioninfo['statusset']);
    }

    /**
     * Test update_user_status::execute validates statusset.
     *
     * @covers \mod_attendance\external\update_user_status::execute
     */
    public function test_execute_invalid_statusset(): void {
        $this->resetAfterTest(true);

        $context = $this->create_attendance_context(2);
        $this->setUser($context['teacher']);
        $session = $this->create_session($context['attendance']);

        $sessioninfo = get_session::execute($session->id);
        $sessioninfo = external_api::clean_returnvalue(get_session::execute_returns(), $sessioninfo);

        $student = reset($sessioninfo['users']);
        $status = reset($sessioninfo['statuses']);

        $this->expectException(\invalid_parameter_exception::class);
        update_user_status::execute(
            $session->id,
            $student['id'],
            $context['teacher']->id,
            $status['id'],
            $sessioninfo['statusset'] + 1
        );
    }

    /**
     * Test update_user_status::execute validates status id.
     *
     * @covers \mod_attendance\external\update_user_status::execute
     */
    public function test_execute_invalid_statusid(): void {
        $this->resetAfterTest(true);

        $context = $this->create_attendance_context(2);
        $this->setUser($context['teacher']);
        $session = $this->create_session($context['attendance']);

        $sessioninfo = get_session::execute($session->id);
        $sessioninfo = external_api::clean_returnvalue(get_session::execute_returns(), $sessioninfo);
        $student = reset($sessioninfo['users']);

        $this->expectException(\invalid_parameter_exception::class);
        update_user_status::execute(
            $session->id,
            $student['id'],
            $context['teacher']->id,
            999999999,
            $sessioninfo['statusset']
        );
    }
}
