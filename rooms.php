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
 * Allows default status set to be modified.
 *
 * @package   mod_attendance
 * @author    2020 Florian Metzger-Noel (github.com/flocko-motion)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__.'/../../config.php');
global $CFG, $DB, $PAGE, $USER, $OUTPUT;

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/mod/attendance/lib.php');
require_once($CFG->dirroot.'/mod/attendance/locallib.php');
require_once($CFG->dirroot.'/mod/attendance/classes/form/editroom.php');

admin_externalpage_setup('managemodules');
$url = new moodle_url('/mod/attendance/rooms.php');
$delete_room_id = optional_param('del', null, PARAM_INT);
$delete_room_confirm = optional_param('confirm', null, PARAM_INT);


// do deletion
if($delete_room_id && $delete_room_confirm)
{
    try {
        $DB->delete_records('attendance_rooms', ['id'=>$delete_room_id]);
        redirect($CFG->wwwroot . '/mod/attendance/rooms.php',
            get_string('roomdeleted', 'mod_attendance'),
            null,
            \core\notification::SUCCESS);
    }
    catch(dml_exception $d) {
        redirect($CFG->wwwroot . '/mod/attendance/rooms.php',
            get_string('error:deleteroomerror', 'mod_attendance'),
            null,
            \core\notification::ERROR);
    }
}

// print page header
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('rooms', 'mod_attendance'));
echo attendance_print_settings_tabs('rooms');


// show deletion confirmation dialog
if($delete_room_id && !$delete_room_confirm) {
    try {
        $room = $DB->get_record_select('attendance_rooms', "id = :id", ['id' => $delete_room_id]);
        echo $OUTPUT->confirm(get_string('deleteroomconfirm', 'mod_attendance', $room->name),
            new moodle_url('/mod/attendance/rooms.php', ['del' => $delete_room_id, 'confirm' => 1]),
            new moodle_url('/mod/attendance/rooms.php')
        );
    } catch (dml_exception $d) {
        \core\notification::error(get_string('error:deleteroomerror', 'mod_attendance'));
    }
}
// show list of room
else
{
    // get data
    try {
        $rooms = array_values($DB->get_records('attendance_rooms', null, 'name ASC'));
        foreach($rooms as $room) {
            $room->url_edit = new moodle_url('/mod/attendance/editroom.php', ['id'=>$room->id]);
            $room->url_delete = new moodle_url('/mod/attendance/rooms.php', ['del'=>$room->id]);
            $room->is_bookable = $room->bookable ? get_string('yes') : get_string('no');
        }
    } catch (dml_exception $e) {
        $rooms = array();
    }

    $templatecontext = (object)[
        'addurl'=> new moodle_url('/mod/attendance/editroom.php'),
        'rooms' => $rooms,
        'button_addroom' => get_string('addroom', 'mod_attendance'),
        'name' => get_string('roomname', 'mod_attendance'),
        'description' => get_string('description'),
        'capacity' => get_string('roomcapacity', 'mod_attendance'),
        'bookable' => get_string('roombookable', 'mod_attendance'),
    ];
    echo $OUTPUT->render_from_template('mod_attendance/rooms', $templatecontext);
}
echo $OUTPUT->footer();