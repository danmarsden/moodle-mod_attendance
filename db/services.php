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
 * Web service local plugin presence external functions and service definitions.
 *
 * @package    mod_presence
 * @copyright  2015 Caio Bressan Doneda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_presence_add_presence' => array(
        'classname'    => 'mod_presence_external',
        'methodname'   => 'add_presence',
        'classpath'    => 'mod/presence/externallib.php',
        'description'  => 'Add presence instance to course.',
        'type'         => 'write',
    ),
    'mod_presence_remove_presence' => array(
        'classname'    => 'mod_presence_external',
        'methodname'   => 'remove_presence',
        'classpath'    => 'mod/presence/externallib.php',
        'description'  => 'Delete presence instance.',
        'type'         => 'write',
    ),
    'mod_presence_add_session' => array(
        'classname'    => 'mod_presence_external',
        'methodname'   => 'add_session',
        'classpath'    => 'mod/presence/externallib.php',
        'description'  => 'Add a new session.',
        'type'         => 'write',
    ),
    'mod_presence_remove_session' => array(
        'classname'    => 'mod_presence_external',
        'methodname'   => 'remove_session',
        'classpath'    => 'mod/presence/externallib.php',
        'description'  => 'Delete a session.',
        'type'         => 'write',
    ),
    'mod_presence_get_courses_with_today_sessions' => array(
        'classname'   => 'mod_presence_external',
        'methodname'  => 'get_courses_with_today_sessions',
        'classpath'   => 'mod/presence/externallib.php',
        'description' => 'Method that retrieves courses with today sessions of a teacher.',
        'type'        => 'read',
    ),
    'mod_presence_get_session' => array(
        'classname'   => 'mod_presence_external',
        'methodname'  => 'get_session',
        'classpath'   => 'mod/presence/externallib.php',
        'description' => 'Method that retrieves the session data',
        'type'        => 'read',
    ),

    'mod_presence_update_user_status' => array(
        'classname'   => 'mod_presence_external',
        'methodname'  => 'update_user_status',
        'classpath'   => 'mod/presence/externallib.php',
        'description' => 'Method that updates the user status in a session.',
        'type'        => 'write',
    ),

    'mod_presence_get_room_capacity' => array(
        'classname'   => 'mod_presence_external',
        'methodname'  => 'get_room_capacity',
        'classpath'   => 'mod/presence/externallib.php',
        'description' => 'Method that returns the capacity of a room',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
        'loginrequired' => true
    ),

    'mod_presence_book_session' => array(
        'classname'   => 'mod_presence_external',
        'methodname'  => 'book_session',
        'classpath'   => 'mod/presence/externallib.php',
        'description' => 'Method that books/unbooks a session for a user',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => '',
        'loginrequired' => true
    ),

    'mod_presence_update_evaluation' => [
        'classname'   => 'mod_presence_external',
        'methodname'  => 'update_evaluation',
        'classpath'   => 'mod/presence/externallib.php',
        'description' => 'Method to process input from evaluation take',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => '',
        'loginrequired' => true
    ],

    'mod_presence_update_user' => [
        'classname'   => 'mod_presence_external',
        'methodname'  => 'update_user',
        'classpath'   => 'mod/presence/externallib.php',
        'description' => 'Method to update status/strenghts/sws of a user',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => '',
        'loginrequired' => true
    ],

    'mod_presence_send_message' => [
        'classname'   => 'mod_presence_external',
        'methodname'  => 'send_message',
        'classpath'   => 'mod/presence/externallib.php',
        'description' => 'Send moodle message to a user',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => '',
        'loginrequired' => true
    ],

    'mod_presence_autocomplete_addstudent' => [
        'classname'   => 'mod_presence_external',
        'methodname'  => 'autocomplete_addstudent',
        'classpath'   => 'mod/presence/externallib.php',
        'description' => 'Method to get list of addable students',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
        'loginrequired' => true
    ],

    'mod_presence_magic_useradd' => [
        'classname'   => 'mod_presence_external',
        'methodname'  => 'magic_useradd',
        'classpath'   => 'mod/presence/externallib.php',
        'description' => 'Book, enrol and register users in one go.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => '',
        'loginrequired' => true
    ],

    'mod_presence_check_doublebooking' => [
        'classname'   => 'mod_presence_external',
        'methodname'  => 'check_doublebooking',
        'classpath'   => 'mod/presence/externallib.php',
        'description' => 'Check for room conflicts.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => '',
        'loginrequired' => true
    ],
];


// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = [
    'presence' => [
        'functions' => [
            'mod_presence_add_presence',
            'mod_presence_remove_presence',
            'mod_presence_add_session',
            'mod_presence_remove_session',
            'mod_presence_get_courses_with_today_sessions',
            'mod_presence_get_session',
            'mod_presence_update_user_status',
            'mod_presence_get_room_capacity',
            'mod_presence_book_session',
            'mod_presence_update_evaluation',
            'mod_presence_update_user',
            'mod_presence_send_message',
            'mod_presence_autocomplete_addstudent',
            'mod_presence_magic_useradd',
            'mod_presence_check_doublebooking',
        ],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'mod_presence',
    ]
];
