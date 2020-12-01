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
 * Allows editing a room
 *
 * @package   mod_presence
 * @copyright    2020 Florian Metzger-Noel (github.com/flocko-motion)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(__DIR__.'/../../config.php');

require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/mod/presence/lib.php');
require_once($CFG->dirroot.'/mod/presence/locallib.php');
require_once($CFG->dirroot.'/mod/presence/classes/form/editroom.php');

admin_externalpage_setup('managemodules');
$url = new moodle_url('/mod/presence/editroom.php', array('roomid' => 0));

$id = optional_param('id', 0, PARAM_INT);
if ($id) {
    $room = $DB->get_record_select('presence_rooms', "id = :id", ['id' => $id]);
} else {
    $room = (object)['id' => $id, 'name' => '', 'description' => '', 'capacity' => '', 'bookable' => 1];
}
$room->capacities = presence_room_capacities();

$mform = new editroom(null, $room);

if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot . '/mod/presence/rooms.php');
} else if ($mform->is_submitted()) {
    try {
        $room = $mform->get_data();
        $room->id = $id;
        $room->name = trim($room->name);
        if (!$room->name) {
            throw new InvalidArgumentException('invalid argument!');
        }
        if ($room->id) {
            $DB->update_record('presence_rooms', $room);
            $message = get_string("roomeditsuccess", "mod_presence", $room->name);
        } else {
            $DB->insert_record('presence_rooms', $room);
            $message = get_string("roomaddsuccess", "mod_presence", $room->name);
        }
        redirect($CFG->wwwroot . '/mod/presence/rooms.php', $message, null, \core\notification::SUCCESS);
    } catch (dml_exception $e) {
        // Room name already existing?
        if (strpos($e->debuginfo, 'duplicate key value') !== false) {
            \core\notification::error(get_string('error:roomnameexists', 'mod_presence', $room->name));
        }
    }
}

echo $OUTPUT->header();

$title = $id ? get_string('roomedit', 'mod_presence')
    : get_string('roomadd', 'mod_presence');
echo $OUTPUT->heading($title);
echo presence_print_settings_tabs('rooms');

$mform->display();
echo $OUTPUT->footer();