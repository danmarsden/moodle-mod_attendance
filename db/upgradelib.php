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
 * Helper functions to keep upgrade.php clean.
 *
 * @package   mod_presence
 * @copyright 2016 Vyacheslav Strelkov <strelkov.vo@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Function to help upgrade old presence records and create calendar events.
 */
function presence_upgrade_create_calendar_events() {
    global $DB;

    $presences = $DB->get_records('presence', null, null, 'id, name, course');
    foreach ($presences as $presence) {
        $sessionsdata = $DB->get_records('presence_sessions', array('presenceid' => $presence->id), null,
            'id, groupid, sessdate, duration, description, descriptionformat');
        foreach ($sessionsdata as $session) {
            $calevent = new stdClass();
            $calevent->name           = $presence->name;
            $calevent->courseid       = $presence->course;
            $calevent->groupid        = $session->groupid;
            $calevent->instance       = $presence->id;
            $calevent->timestart      = $session->sessdate;
            $calevent->timeduration   = $session->duration;
            $calevent->eventtype      = 'presence';
            $calevent->timemodified   = time();
            $calevent->modulename     = 'presence';
            $calevent->description    = $session->description;
            $calevent->format         = $session->descriptionformat;

            $caleventid = $DB->insert_record('event', $calevent);
            $DB->set_field('presence_sessions', 'caleventid', $caleventid, array('id' => $session->id));
        }
    }
}
