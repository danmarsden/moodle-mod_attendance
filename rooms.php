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
 * Shows a list of all rooms
 *
 * @package   mod_attendance
 * @copyright 2020 Florian Metzger-Noel (github.com/flocko-motion)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__.'/../../config.php');

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/mod/attendance/lib.php');
require_once($CFG->dirroot.'/mod/attendance/locallib.php');
require_once($CFG->dirroot.'/mod/attendance/classes/form/editroom.php');

admin_externalpage_setup('managemodules');

$url = new moodle_url('/mod/attendance/rooms.php');
$deleteroomid = optional_param('del', null, PARAM_INT);
$deleteroomconfirm = optional_param('confirm', null, PARAM_INT);

if ($deleteroomid && $deleteroomconfirm) {
    try {
        $DB->set_field('attendance_sessions', 'roomid', 0, ['roomid' => $deleteroomid]);
        $DB->delete_records('attendance_rooms', ['id' => $deleteroomid]);
        redirect($CFG->wwwroot . '/mod/attendance/rooms.php',
            get_string('roomdeletesuccess', 'mod_attendance'),
            null,
            \core\notification::SUCCESS);
    } catch (dml_exception $d) {
        redirect($CFG->wwwroot . '/mod/attendance/rooms.php',
            get_string('roomdeleteerror', 'mod_attendance'),
            null,
            \core\notification::ERROR);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('rooms', 'mod_attendance'));
echo  attendance_print_settings_tabs('rooms');

if ($deleteroomid && !$deleteroomconfirm) {
    try {
        $room = $DB->get_record_select('attendance_rooms', "id = :id", ['id' => $deleteroomid]);
        echo $OUTPUT->confirm(get_string('roomdeleteconfirm', 'mod_attendance', $room->name),
            new moodle_url('/mod/attendance/rooms.php', ['del' => $deleteroomid, 'confirm' => 1]),
            new moodle_url('/mod/attendance/rooms.php')
        );
    } catch (dml_exception $d) {
        \core\notification::error(get_string('error:roomdelete', 'mod_attendance'));
    }
} else {
    try {
        $rooms = array_values($DB->get_records('attendance_rooms', null, 'name ASC'));
        foreach ($rooms as $room) {
            $room->url_edit = new moodle_url('/mod/attendance/editroom.php', ['id' => $room->id]);
            $room->url_delete = new moodle_url('/mod/attendance/rooms.php', ['del' => $room->id]);
            $room->is_bookable = $room->bookable ? get_string('yes') : get_string('no');
        }
    } catch (dml_exception $e) {
        $rooms = array();
    }

    $templatecontext = (object)[
        'rooms' => $rooms,
        'name' => get_string('roomname', 'mod_attendance'),
        'description' => get_string('description'),
        'capacity' => get_string('roomcapacity', 'mod_attendance'),
        'bookable' => get_string('roombookable', 'mod_attendance'),
        'url_add' => new moodle_url('/mod/attendance/editroom.php'),
        'button_addroom' => get_string('roomadd', 'mod_attendance'),
    ];
    echo $OUTPUT->render_from_template('mod_attendance/rooms', $templatecontext);
}
echo $OUTPUT->footer();