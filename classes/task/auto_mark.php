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
 * Attendance task - auto mark.
 *
 * @package    mod_attendance
 * @copyright  2017 onwards Dan Marsden http://danmarsden.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\task;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/attendance/locallib.php');
/**
 * get_scores class, used to get scores for submitted files.
 *
 * @package    mod_attendance
 * @copyright  2017 onwards Dan Marsden http://danmarsden.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auto_mark extends \core\task\scheduled_task {
    public function get_name() {
        // Shown in admin screens.
        return get_string('automarktask', 'mod_attendance');
    }
    public function execute() {
        global $DB;
        // Create some cache vars - might be nice to restructure this and make a smaller number of sql calls.
        $cachecm = array();
        $cacheatt = array();
        $cachecourse = array();
        $sessions = $DB->get_recordset_select('attendance_sessions',
            'automark = 1 AND automarkcompleted = 0 AND sessdate < ? ', array(time()));

        foreach ($sessions as $session) {
            // Would be nice to change duration field to a timestamp so we don't need this step.
            if ($session->sessdate + $session->duration < time()) {
                $donesomething = false; // Only trigger grades/events when an update actually occurs.

                // Store cm/att/course in cachefields so we don't make unnecessary db calls.
                // Would probably be nice to grab this stuff outside of the loop.
                // Make sure this status set has something to setunmarked.
                $setunmarked = $DB->get_field('attendance_statuses', 'id',
                    array('attendanceid' => $session->attendanceid, 'setnumber' => $session->statusset,
                          'setunmarked' => 1, 'deleted' => 0));
                if (empty($setunmarked)) {
                    mtrace("No unmarked status configured for session id: ".$session->id);
                    continue;
                }
                if (empty($cacheatt[$session->attendanceid])) {
                    $cacheatt[$session->attendanceid] = $DB->get_record('attendance', array('id' => $session->attendanceid));
                }
                if (empty($cachecm[$session->attendanceid])) {
                    $cachecm[$session->attendanceid] = get_coursemodule_from_instance('attendance',
                        $session->attendanceid, $cacheatt[$session->attendanceid]->course);
                }
                $courseid = $cacheatt[$session->attendanceid]->course;
                if (empty($cachecourse[$courseid])) {
                    $cachecourse[$courseid] = $DB->get_record('course', array('id' => $courseid));
                }
                $context = \context_module::instance($cachecm[$session->attendanceid]->id);

                $pageparams = new \mod_attendance_take_page_params();
                $pageparams->group = $session->groupid;
                if (empty($session->groupid)) {
                    $pageparams->grouptype  = 0;
                } else {
                    $pageparams->grouptype  = 1;
                }
                $pageparams->sessionid  = $session->id;

                // Get all unmarked students.
                $att = new \mod_attendance_structure($cacheatt[$session->attendanceid],
                    $cachecm[$session->attendanceid], $cachecourse[$courseid], $context, $pageparams);

                $users = $att->get_users($session->groupid, 0);

                $existinglog = $DB->get_recordset('attendance_log', array('sessionid' => $session->id));
                $updated = 0;

                foreach ($existinglog as $log) {
                    if (empty($log->statusid)) {
                        // Status needs updating.
                        $existinglog->statusid = $setunmarked;
                        $existinglog->timetaken = time();
                        $existinglog->takenby = 0;
                        $existinglog->remarks = get_string('autorecorded', 'attendance');

                        $DB->update_record('attendance_log', $existinglog);
                        $updated++;
                        $donesomething = true;
                    }
                    unset($users[$log->studentid]);
                }
                $existinglog->close();
                mtrace($updated . " session status updated");

                $newlog = new \stdClass();
                $newlog->statusid = $setunmarked;
                $newlog->timetaken = time();
                $newlog->takenby = 0;
                $newlog->sessionid = $session->id;
                $newlog->remarks = get_string('autorecorded', 'attendance');
                $newlog->statusset = implode(',', array_keys( (array)$att->get_statuses()));

                $added = 0;
                foreach ($users as $user) {
                    $newlog->studentid = $user->id;
                    $DB->insert_record('attendance_log', $newlog);
                    $added++;
                    $donesomething = true;
                }
                mtrace($added . " session status inserted");

                // Update lasttaken time and automarkcompleted for this session.
                $session->lasttaken = $newlog->timetaken;
                $session->lasttakenby = 0;
                $session->automarkcompleted = 1;
                $DB->update_record('attendance_sessions', $session);

                if ($donesomething) {
                    if ($att->grade != 0) {
                        $att->update_users_grade(array_keys($users));
                    }

                    $params = array(
                        'sessionid' => $att->pageparams->sessionid,
                        'grouptype' => $att->pageparams->grouptype);
                    $event = \mod_attendance\event\attendance_taken::create(array(
                        'objectid' => $att->id,
                        'context' => $att->context,
                        'other' => $params));
                    $event->add_record_snapshot('course_modules', $att->cm);
                    $event->add_record_snapshot('attendance_sessions', $session);
                    $event->trigger();
                }
            }
        }
    }
}