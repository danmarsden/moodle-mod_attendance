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
 * presence task - auto mark.
 *
 * @package    mod_presence
 * @copyright  2017 onwards Dan Marsden http://danmarsden.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_presence\task;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/presence/locallib.php');
/**
 * get_scores class, used to get scores for submitted files.
 *
 * @package    mod_presence
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
        return get_string('automarktask', 'mod_presence');
    }

    /**
     * Execte the task.
     */
    public function execute() {
        global $DB;
        // Create some cache vars - might be nice to restructure this and make a smaller number of sql calls.
        $cachecm = array();
        $cachepresence = array();
        $cachecourse = array();
        $now = time(); // Store current time to use in queries so they all match nicely.

        $sessions = $DB->get_recordset_select('presence_sessions',
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


                if (empty($cachepresence[$session->presenceid])) {
                    $cachepresence[$session->presenceid] = $DB->get_record('presence', array('id' => $session->presenceid));
                }
                if (empty($cachecm[$session->presenceid])) {
                    $cachecm[$session->presenceid] = get_coursemodule_from_instance('presence',
                        $session->presenceid, $cachepresence[$session->presenceid]->course);
                }
                $courseid = $cachepresence[$session->presenceid]->course;
                if (empty($cachecourse[$courseid])) {
                    $cachecourse[$courseid] = $DB->get_record('course', array('id' => $courseid));
                }
                $context = \context_module::instance($cachecm[$session->presenceid]->id);

                $pageparams = new \mod_presence_take_page_params();
                $pageparams->group = $session->groupid;
                if (empty($session->groupid)) {
                    $pageparams->grouptype  = 0;
                } else {
                    $pageparams->grouptype  = 1;
                }
                $pageparams->sessionid  = $session->id;

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
                        $duration = get_config('presence', 'studentscanmarksessiontimeend') * 60;
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
                }

                // Get all unmarked students.
                $presence = new \mod_presence_structure($cachepresence[$session->presenceid],
                    $cachecm[$session->presenceid], $cachecourse[$courseid], $context, $pageparams);

                $users = $presence->get_users(['groupid' => $session->groupid, 'page'=> 0]);

                $existinglog = $DB->get_recordset('presence_evaluations', array('sessionid' => $session->id));
                $updated = 0;

                foreach ($existinglog as $log) {
                    if (empty($log->statusid)) {
                        if ($sessionover || !empty($userfirstaccess[$log->studentid])) {
                            // Status needs updating.
                            if ($sessionover) {
                                $log->statusid = $setunmarked;
                            } else if (!empty($userfirstaccess[$log->studentid])) {
                                $log->statusid = $presence->get_automark_status($userfirstaccess[$log->studentid], $session->id);
                            }
                            if (!empty($log->statusid)) {
                                $log->timetaken = $now;
                                $log->takenby = 0;
                                $log->remarks = get_string('autorecorded', 'presence');

                                $DB->update_record('presence_evaluations', $log);
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
                $newlog->remarks = get_string('autorecorded', 'presence');
                $newlog->statusset = implode(',', array_keys( (array)$presence->get_statuses()));

                $added = 0;
                foreach ($users as $user) {
                    if ($sessionover || !empty($userfirstaccess[$user->id])) {
                        if ($sessionover) {
                            $newlog->statusid = $setunmarked;
                        } else if (!empty($userfirstaccess[$user->id])) {
                            $newlog->statusid = $presence->get_automark_status($userfirstaccess[$user->id], $session->id);
                        }
                        if (!empty($newlog->statusid)) {
                            $newlog->studentid = $user->id;
                            $DB->insert_record('presence_evaluations', $newlog);
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

                $DB->update_record('presence_sessions', $session);

                if ($donesomething) {
                    if ($presence->grade != 0) {
                        $presence->update_users_grade(array_keys($users));
                    }

                    $params = array(
                        'sessionid' => $presence->pageparams->sessionid,
                        'grouptype' => $presence->pageparams->grouptype);
                    $event = \mod_presence\event\presence_taken::create(array(
                        'objectid' => $presence->id,
                        'context' => $presence->context,
                        'other' => $params));
                    $event->add_record_snapshot('course_modules', $presence->cm);
                    $event->add_record_snapshot('presence_sessions', $session);
                    $event->trigger();
                }
            }
        }
    }
}