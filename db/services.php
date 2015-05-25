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
 * Web service for mod attendance
 * @package    mod_attendance
 * @copyright  2015 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(

    'mod_attendance_take_attendance' => array(
            'classname'   => 'mod_attendance_external',
            'methodname'  => 'take_attendance',
            'classpath'   => 'mod/attendance/externallib.php',
            'description' => 'Register (add/update) attendance logs',
            'type'        => 'write'
    ),

    'mod_attendance_add_sessions' => array(
            'classname'   => 'mod_attendance_external',
            'methodname'  => 'add_sessions',
            'classpath'   => 'mod/attendance/externallib.php',
            'description' => 'Add sessions to an attendance',
            'type'        => 'write'
    ),
);
