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
require_once($CFG->libdir . '/grouplib.php');
/**
 * get_scores class, used to get scores for submitted files.
 *
 * @package    mod_attendance
 * @copyright  2017 onwards Dan Marsden http://danmarsden.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auto_mark extends \core\task\scheduled_task {

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public function get_name() {
        // Shown in admin screens.
        return get_string('automarktask', 'mod_attendance');
    }

    /**
     * Execte the task.
     */
    public function execute() {
        global $DB, $CFG;
        // Create some cache vars - might be nice to restructure this and make a smaller number of sql calls.
        $cachecm = array();
        $cacheatt = array();
        $cachecourse = array();
        $now = time(); // Store current time to use in queries so they all match nicely.

        $sessions = $DB->get_recordset_select('attendance_sessions',
            'automark > 0 AND automarkcompleted < 2 AND sessdate < ? ', array($now));

        foreach ($sessions as $session) {
            if ($session->sessdate + $session->duration < $now || // If session is over.
                // OR if session is currently open and automark is set to do all.
                ($session->sessdate < $now && $session->automark == 1)) {

                $userfirstaccess = array();
                $donesomething = false; // Only trigger grades/events when an update actually occurs.
                $sessionover = false; // Is this session over?
                if ($session->sessdate + $session->duration < $now) {
                    $sessionover = true;
                }

                // Store cm/att/course in cachefields so we don't make unnecessary db calls.
                // Would probably be nice to grab this stuff outside of the loop.
                // Make sure this status set has something to setunmarked.
                $setunmarked = $DB->get_field('attendance_statuses', 'id',
                    array('attendanceid' => $session->attendanceid, 'setnumber' => $session->statusset,
                          'setunmarked' => 1, 'deleted' => 0));

                if (empty($setunmarked)) {
                    $coursemodule = get_coursemodule_from_instance('attendance', $session->attendanceid);
                    $url = $CFG->wwwroot.'/mod/attendance/preferences.php?id='.$coursemodule->id;
                    mtrace("No unmarked status configured for session id: ".$session->id. " to fix, go to: ".$url);
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

                $att = new \mod_attendance_structure($cacheatt[$session->attendanceid],
                $cachecm[$session->attendanceid], $cachecourse[$courseid], $context, $pageparams);

                if ($session->automark == 1) {
                    $userfirstacess = array();
                    // If set to do full automarking, get all users that have accessed course during session open.
                    $id = $DB->sql_concat('userid', 'ip'); // Users may access from multiple ip, make the first field unique.
                    $sql = "SELECT $id, userid, ip, min(timecreated) as timecreated
                             FROM {logstore_standard_log}
                            WHERE courseid = ? AND timecreated > ? AND timecreated < ?
                         GROUP BY userid, ip";

                    $timestart = $session->sessdate;
                    if (empty($session->lasttakenby) && $session->lasttaken > $timestart) {
                        // If the last time session was taken it was done automatically, use the last time taken
                        // as the start time for the logs we are interested in to help with performance.
                        $timestart = $session->lasttaken;
                    }
                    $duration = $session->duration;
                    if (empty($duration)) {
                        $duration = get_config('attendance', 'studentscanmarksessiontimeend') * 60;
                    }
                    $timeend = $timestart + $duration;
                    $logusers = $DB->get_recordset_sql($sql, array($courseid, $timestart, $timeend));
                    // Check if user access is in allowed subnet.
                    foreach ($logusers as $loguser) {
                        if (!empty($session->subnet) && !address_in_subnet($loguser->ip, $session->subnet)) {
                            // This record isn't in the right subnet.
                            continue;
                        }
                        if (empty($userfirstaccess[$loguser->userid]) ||
                            $userfirstaccess[$loguser->userid] > $loguser->timecreated) {
                            // Users may have accessed from mulitple ip addresses, find the earliest access.
                            $userfirstaccess[$loguser->userid] = $loguser->timecreated;
                        }
                    }
                    $logusers->close();

                } else if ($session->automark == 3) {
                    $existinglog = $DB->get_records_menu('attendance_log',
                        ['sessionid' => $session->id], '', 'studentid, statusid');

                    $newlog = new \stdClass();
                    $newlog->timetaken = $now;
                    $newlog->takenby = 0;
                    $newlog->sessionid = $session->id;
                    $newlog->remarks = get_string('autorecorded', 'attendance');
                    $newlog->statusset = implode(',', array_keys( (array)$att->get_statuses()));

                    // Get users who have completed the course in this session.
                    $completedusers = $DB->get_records_select('course_modules_completion',
                        'coursemoduleid = ? AND completionstate > 0', [$session->automarkcmid]);

                    // Get automark status the users and update the attendance log.
                    foreach ($completedusers as $completionuser) {
                        if (empty($completionuser->timemodified) || (empty($completionuser->userid))) {
                            // Time modified or userid not set - we can't calculate for this record.
                            continue;
                        }
                        if (!empty($existinglog[$completionuser->userid])) {
                            // Status already set for this user.
                            continue;
                        }
                        if (!has_capability('mod/attendance:canbelisted', $context, $completionuser->userid)) {
                            // This user can't be listed in this attendance - skip them.
                            continue;
                        }
                        if (!empty($session->groupid) && !groups_is_member($session->groupid, $completionuser->userid)) {
                            // This is a group session, and the user is not a member of the group.
                            continue;
                        }
                        $newlog->studentid = $completionuser->userid;
                        $newlog->statusid = $att->get_automark_status($completionuser->timemodified, $session->id);
                        if (!empty($newlog->statusid)) {
                            $DB->insert_record('attendance_log', $newlog);
                        }
                    }
                }

                // Get all unmarked students.
                $users = $att->get_users($session->groupid, 0);

                $existinglog = $DB->get_recordset('attendance_log', array('sessionid' => $session->id));
                $updated = 0;

                foreach ($existinglog as $log) {
                    if (empty($log->statusid)) {
                        if ($sessionover || !empty($userfirstaccess[$log->studentid])) {
                            // Status needs updating.
                            if ($sessionover) {
                                $log->statusid = $setunmarked;
                            } else if (!empty($userfirstaccess[$log->studentid])) {
                                $log->statusid = $att->get_automark_status($userfirstaccess[$log->studentid], $session->id);
                            }
                            if (!empty($log->statusid)) {
                                $log->timetaken = $now;
                                $log->takenby = 0;
                                $log->remarks = get_string('autorecorded', 'attendance');

                                $DB->update_record('attendance_log', $log);
                                $updated++;
                                $donesomething = true;
                            }
                        }
                    }
                    unset($users[$log->studentid]);
                }
                $existinglog->close();
                mtrace($updated . " session status updated");

                $newlog = new \stdClass();
                $newlog->timetaken = $now;
                $newlog->takenby = 0;
                $newlog->sessionid = $session->id;
                $newlog->remarks = get_string('autorecorded', 'attendance');
                $newlog->statusset = implode(',', array_keys( (array)$att->get_statuses()));

                $added = 0;
                foreach ($users as $user) {
                    if ($sessionover || !empty($userfirstaccess[$user->id])) {
                        if ($sessionover) {
                            $newlog->statusid = $setunmarked;
                        } else if (!empty($userfirstaccess[$user->id])) {
                            $newlog->statusid = $att->get_automark_status($userfirstaccess[$user->id], $session->id);
                        }
                        if (!empty($newlog->statusid)) {
                            $newlog->studentid = $user->id;
                            $DB->insert_record('attendance_log', $newlog);
                            $added++;
                            $donesomething = true;
                        }
                    }
                }
                mtrace($added . " session status inserted");

                // Update lasttaken time and automarkcompleted for this session.
                $session->lasttaken = $now;
                $session->lasttakenby = 0;
                if ($sessionover) {
                    $session->automarkcompleted = 2;
                } else {
                    $session->automarkcompleted = 1;
                }

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
