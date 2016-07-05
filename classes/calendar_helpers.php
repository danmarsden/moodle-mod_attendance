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
 * @package    mod_attendance
 * @copyright  2016 Vyacheslav Strelkov
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../../calendar/lib.php');

/**
 * Create single calendar event bases on session data.
 *
 * @param stdClass $session initial sessions to take data from
 * @return bool result of calendar event creation
 */
function create_calendar_event(&$session) {
    // We don't want to create multiple calendar events for 1 session.
    if ($session->caleventid) {
        return $session->caleventid;
    }

    global $DB;
    $attendance = $DB->get_record('attendance', array('id' => $session->attendanceid));

    $caleventdata = new stdClass();
    $caleventdata->name           = $attendance->name;
    $caleventdata->courseid       = $attendance->course;
    $caleventdata->groupid        = $session->groupid;
    $caleventdata->userid         = 0;
    $caleventdata->instance       = $session->attendanceid;
    $caleventdata->timestart      = $session->sessdate;
    $caleventdata->timeduration   = $session->duration;
    $caleventdata->eventtype      = 'attendance';
    $caleventdata->timemodified   = time();
    $caleventdata->modulename     = 'attendance';

    $calevent = new stdClass();
    if ($calevent = calendar_event::create($caleventdata, false)) {
        $session->caleventid = $calevent->id;
        $DB->set_field('attendance_sessions', 'caleventid', $session->caleventid, array('id' => $session->id));
        return true;
    } else {
        return false;
    }
}

/**
 * Create multiple calendar events based on sessions data.
 *
 * @param array %sessionsids array of sessions ids
 */
function create_calendar_events($sessionsids) {
    global $DB;
    $sessions = $DB->get_recordset_list('attendance_sessions', 'id', $sessionsids);

    foreach ($sessions as $session) {
        create_calendar_event($session);
        if ($session->caleventid) {
            $DB->update_record('attendance_sessions', $session);
        }
    }
}

/**
 * Update calendar event duration and date
 *
 * @param $caleventid int calendar event id
 * @param $timeduration int duration of the event
 * @param $timestart int start time of the event
 * @return bool result of updating
 */
function update_calendar_event($caleventid, $timeduration, $timestart = null) {
    $caleventdata = new stdClass();
    $caleventdata->timeduration   = $timeduration;
    $caleventdata->timestart = $timestart;
    $caleventdata->timemodified   = time();

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
 * @param array %sessionsids array of sessions ids
 * @return bool result of updating
 */
function delete_calendar_events($sessionsids) {
    global $DB;
    $caleventsids = existing_calendar_events_ids($sessionsids);
    if ($caleventsids) {
        $DB->delete_records_list('event', 'id', $caleventsids);
    }

    $sessions = $DB->get_recordset_list('attendance_sessions', 'id', $sessionsids);
    foreach ($sessions as $session) {
        $session->caleventid = 0;
        $DB->update_record('attendance_sessions', $session);
    }
}

/**
 * Check if calendar events are created for given sessions
 *
 * @param array $sessionsids of sessions ids
 * @return array | bool array of existing calendar events or false if none found
 */
function existing_calendar_events_ids($sessionsids) {
    global $DB;
    $caleventsids = array_keys($DB->get_records_list('attendance_sessions', 'id', $sessionsids, '', 'caleventid'));
    $existingcaleventsids = array_filter($caleventsids);
    if (! empty($existingcaleventsids)) {
        return $existingcaleventsids;
    } else {
        return false;
    }
}