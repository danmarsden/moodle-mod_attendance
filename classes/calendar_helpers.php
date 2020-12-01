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
 * Calendar related functions
 *
 * @package    mod_presence
 * @copyright  2016 Vyacheslav Strelkov
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../../../calendar/lib.php');

/**
 * Create single calendar event bases on session data.
 *
 * @param stdClass $session initial sessions to take data from
 * @return bool result of calendar event creation
 */
function presence_create_calendar_event(&$session) {
    global $DB;

    // We don't want to create multiple calendar events for 1 session.
    if ($session->caleventid) {
        return $session->caleventid;
    }


    $presence = $DB->get_record('presence', array('id' => $session->presenceid));
    $course = $DB->get_record('course', ['id' => $presence->course]);



    $caleventdata = new stdClass();
    $caleventdata->name           = $course->shortname ? $course->shortname : $course->fullname;
    $caleventdata->courseid       = $presence->course;
    $caleventdata->groupid        = 0;
    $caleventdata->instance       = $session->presenceid;
    $caleventdata->timestart      = $session->sessdate;
    $caleventdata->timeduration   = $session->duration;
    $caleventdata->description    = $session->description;
    $caleventdata->format         = 0;
    $caleventdata->eventtype      = 'presence';
    $caleventdata->timemodified   = time();
    $caleventdata->modulename     = 'presence';
    if ($session->roomid) {
        $room = $DB->get_record('presence_rooms', ['id' => $session->roomid]);
        $caleventdata->location   = $room->name;
    }

    $calevent = new stdClass();
    if ($calevent = calendar_event::create($caleventdata, false)) {
        $session->caleventid = $calevent->id;
        $DB->set_field('presence_sessions', 'caleventid', $session->caleventid, array('id' => $session->id));
        return true;
    } else {
        return false;
    }
}



/**
 * Create multiple calendar events based on sessions data.
 *
 * @param array $sessionsids array of sessions ids
 */
function presence_create_calendar_events($sessionsids) {
    global $DB;


    $sessions = $DB->get_recordset_list('presence_sessions', 'id', $sessionsids);

    foreach ($sessions as $session) {
        presence_create_calendar_event($session);
        if ($session->caleventid) {
            $DB->update_record('presence_sessions', $session);
        }
    }
}

/**
 * Update calendar event duration and date
 *
 * @param stdClass $session Session data
 * @return bool result of updating
 */
function presence_update_calendar_event($session) {
    global $DB;

    $caleventid = $session->caleventid;
    $timeduration = $session->duration;
    $timestart = $session->sessdate;


    // Boring update.
    $caleventdata = new stdClass();
    $caleventdata->timeduration   = $timeduration;
    $caleventdata->timestart      = $timestart;
    $caleventdata->timemodified   = time();
    $caleventdata->description    = $session->description;
    $caleventdata->location       = $session->location;

    $calendarevent = calendar_event::load($caleventid);
    if ($calendarevent) {
        return $calendarevent->update($caleventdata) ? true : false;
    } else {
        return false;
    }
}

/**
 * Delete calendar events for sessions
 *
 * @param array $sessionsids array of sessions ids
 * @return bool result of updating
 */
function presence_delete_calendar_events($sessionsids) {
    global $DB;
    $caleventsids = presence_existing_calendar_events_ids($sessionsids);
    if ($caleventsids) {
        $DB->delete_records_list('event', 'id', $caleventsids);
    }

    $sessions = $DB->get_recordset_list('presence_sessions', 'id', $sessionsids);
    foreach ($sessions as $session) {
        $session->caleventid = 0;
        $DB->update_record('presence_sessions', $session);
    }
}

/**
 * Check if calendar events are created for given sessions
 *
 * @param array $sessionsids of sessions ids
 * @return array | bool array of existing calendar events or false if none found
 */
function presence_existing_calendar_events_ids($sessionsids) {
    global $DB;
    $caleventsids = array_keys($DB->get_records_list('presence_sessions', 'id', $sessionsids, '', 'caleventid'));
    $existingcaleventsids = array_filter($caleventsids);
    if (! empty($existingcaleventsids)) {
        return $existingcaleventsids;
    } else {
        return false;
    }
}

/**
 * Create/update single personal calendar event for booking
 *
 * @param stdClass $booking id of the booking
 * @return bool result of calendar event creation
 */
function presence_create_calendar_event_booking($booking) {
    global $DB, $USER;

    $presence = $DB->get_record('presence', array('id' => $booking->session->presenceid));
    $room = $DB->get_record('presence_rooms', array('id' => $booking->session->roomid));
    $course = $DB->get_record('course', array('id' => $presence->course));
    $caleventdata = new stdClass();
    $caleventdata->name           = // get_string("bookedcalprefix" , "presence") .
        ($course->shortname ? $course->shortname : $course->fullname);
    $caleventdata->type           = CALENDAR_EVENT_TYPE_STANDARD;
    $caleventdata->courseid       = 0; // $presence->course;
    $caleventdata->groupid        = 0;
    $caleventdata->userid         = $USER->id;
    $caleventdata->instance       = $booking->session->presenceid;
    $caleventdata->timestart      = $booking->session->sessdate;
    $caleventdata->timeduration   = $booking->session->duration;
    $caleventdata->description    = '<p>'. get_string("bookedcaldescription", "presence") .'</p>'
        .'<p>'.$course->fullname.'</p>'
        . $booking->session->description;
    $caleventdata->format         = $booking->session->descriptionformat;
    $caleventdata->eventtype      = 'user'; // 'presencebooking';
    $caleventdata->timemodified   = time();
    $caleventdata->modulename     = ''; //'presence';
    if ($room) {
        $caleventdata->location   = $room->name;
    }

    if ($calevent = calendar_event::create($caleventdata, false)) {
        $DB->set_field('presence_bookings', 'caleventid', $calevent->id, array('id' => $booking->id));
        return true;
    }
    return false;
}

/**
 * Delete single personal calendar event for booking
 *
 * @param stdClass $booking booking to delete cal event for
 * @return bool result of calendar event creation
 */
function presence_delete_calendar_event_booking($booking) {
    global $DB, $USER;
    if (intval($booking->caleventid)) {
        $DB->delete_records('event', ['id' => $booking->caleventid, 'userid' => $USER->id]);
    }
    return true;
}