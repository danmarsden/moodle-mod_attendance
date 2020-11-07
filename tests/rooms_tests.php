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
 * Test for attendance plugin related to the "rooms" feature
 *
 * @package    mod_attendance
 * @category   test
 * @copyright  2020 Florian Metzger-Noel (github.com/flocko-motion)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/mod/attendance/classes/attendance_webservices_handler.php');
require_once($CFG->dirroot . '/mod/attendance/classes/structure.php');
require_once($CFG->dirroot . '/mod/attendance/externallib.php');

class mod_attendance_rooms_tests extends externallib_advanced_testcase
{

    public function test_get_courses_with_today_sessions() {
        $options = mod_attendance_external::get_room_capacities();
    }
}