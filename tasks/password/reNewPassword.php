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
 *
 * @copyright  2019 Aggelos Bellos
 * @package    mod_attendance
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 */

require_once(dirname(__FILE__).'/../../../../config.php');
require_once(dirname(__FILE__).'/../../locallib.php');

$session = required_param('session', PARAM_INT);
$session = $DB->get_record('attendance_sessions', array('id' => $session), '*', MUST_EXIST);

$cm = get_coursemodule_from_instance('attendance', $session->attendanceid);
require_login($cm->course, $cm);

// $context = context_module::instance($cm->id);
// disallow students to renew password
$context = context_module::instance($cm->id);
$capabilities = array('mod/attendance:manageattendances', 'mod/attendance:changeattendances');
if (!has_any_capability($capabilities, $context)) {
    exit;
}
// set a blank page
$PAGE->set_pagelayout('popup');
$PAGE->set_context(context_system::instance());
// change password
$newPassword = attendance_random_string();
$renewPassword = $DB->update_record('attendance_sessions', [
    'id' => $session->id,
    'studentpassword' => $newPassword,
    'last_changed_password' => time()
]);

if($renewPassword) {

    echo json_encode([
        'new_password' => $newPassword
    ]);

} else {
    // TODO: show a better error message
    echo json_encode([
        'error' => 'db error'
    ]);

}



?>