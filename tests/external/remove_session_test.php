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
 * External function tests for remove_session.
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
 * External function tests for remove_session.
 *
 * @group mod_attendance
 */
final class remove_session_test extends base_external_testcase {
    /**
     * @covers \mod_attendance\external\remove_session::execute
     */
    public function test_execute(): void {
        global $DB;

        $this->resetAfterTest(true);
        $context = $this->create_attendance_context();
        $this->setUser($context['teacher']);
        $session = $this->create_session($context['attendance']);

        $result = remove_session::execute($session->id);
        $result = external_api::clean_returnvalue(remove_session::execute_returns(), $result);

        $this->assertTrue($result);
        $this->assertFalse($DB->record_exists('attendance_sessions', ['id' => $session->id]));
    }

    /**
     * @covers \mod_attendance\external\remove_session::execute
     */
    public function test_execute_invalid_sessionid(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->expectException(\dml_missing_record_exception::class);
        remove_session::execute(999999999);
    }

    /**
     * @covers \mod_attendance\external\remove_session::execute
     */
    public function test_execute_denies_student(): void {
        $this->resetAfterTest(true);
        $context = $this->create_attendance_context();
        $session = $this->create_session($context['attendance']);
        $this->setUser($context['students'][0]);

        $this->expectException(\required_capability_exception::class);
        remove_session::execute($session->id);
    }
}
