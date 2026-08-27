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
 * Web service local plugin attendance external functions and service definitions.
 *
 * @package    mod_attendance
 * @copyright  2015 Caio Bressan Doneda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_attendance_add_attendance' => [
        'classname'    => 'mod_attendance\\external\\add_attendance',
        'methodname'   => 'execute',
        'description'  => 'Add attendance instance to course.',
        'type'         => 'write',
    ],
    'mod_attendance_remove_attendance' => [
        'classname'    => 'mod_attendance\\external\\remove_attendance',
        'methodname'   => 'execute',
        'description'  => 'Delete attendance instance.',
        'type'         => 'write',
    ],
    'mod_attendance_add_session' => [
        'classname'    => 'mod_attendance\\external\\add_session',
        'methodname'   => 'execute',
        'description'  => 'Add a new session.',
        'type'         => 'write',
    ],
    'mod_attendance_remove_session' => [
        'classname'    => 'mod_attendance\\external\\remove_session',
        'methodname'   => 'execute',
        'description'  => 'Delete a session.',
        'type'         => 'write',
    ],
    'mod_attendance_get_courses_with_today_sessions' => [
        'classname'   => 'mod_attendance\\external\\get_courses_with_today_sessions',
        'methodname'  => 'execute',
        'description' => 'Method that retrieves courses with today sessions of a teacher.',
        'type'        => 'read',
    ],
    'mod_attendance_get_session' => [
        'classname'   => 'mod_attendance\\external\\get_session',
        'methodname'  => 'execute',
        'description' => 'Method that retrieves the session data',
        'type'        => 'read',
    ],
    'mod_attendance_update_user_status' => [
        'classname'   => 'mod_attendance\\external\\update_user_status',
        'methodname'  => 'execute',
        'description' => 'Method that updates the user status in a session.',
        'type'        => 'write',
    ],
    'mod_attendance_get_sessions' => [
        'classname'   => 'mod_attendance\\external\\get_sessions',
        'methodname'  => 'execute',
        'description' => 'Method that retrieves the sessions in an attendance instance.',
        'type'        => 'read',
    ],
];


// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = [
    'Attendance' => [
        'functions' => [
            'mod_attendance_add_attendance',
            'mod_attendance_remove_attendance',
            'mod_attendance_add_session',
            'mod_attendance_remove_session',
            'mod_attendance_get_courses_with_today_sessions',
            'mod_attendance_get_session',
            'mod_attendance_update_user_status',
            'mod_attendance_get_sessions',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'mod_attendance',
    ],
];
