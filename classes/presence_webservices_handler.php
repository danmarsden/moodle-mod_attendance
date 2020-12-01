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
 * Web Services for presence plugin.
 *
 * @package    mod_presence
 * @copyright  2015 Caio Bressan Doneda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../locallib.php');
require_once(dirname(__FILE__).'/structure.php');
require_once(dirname(__FILE__).'/../../../lib/sessionlib.php');
require_once(dirname(__FILE__).'/../../../lib/datalib.php');

/**
 * Class presence_handler
 * @copyright  2015 Caio Bressan Doneda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presence_handler {
    /**
     * For this user, this method searches in all the courses that this user has permission to take presence,
     * looking for today sessions and returns the courses with the sessions.
     * @param int $userid
     * @return array
     */
    public static function get_courses_with_today_sessions($userid) {
        $usercourses = enrol_get_users_courses($userid);
        $presenceinstance = get_all_instances_in_courses('presence', $usercourses);

        $coursessessions = array();

        foreach ($presenceinstance as $presence) {
            $context = context_course::instance($presence->course);
            if (has_capability('mod/presence:takepresences', $context, $userid)) {
                $course = $usercourses[$presence->course];
                if (!isset($course->presence_instance)) {
                    $course->presence_instance = array();
                }

                $presence = new stdClass();
                $presence->id = $presence->id;
                $presence->course = $presence->course;
                $presence->name = $presence->name;
                $presence->grade = $presence->grade;

                $cm = new stdClass();
                $cm->id = $presence->coursemodule;

                $presence = new mod_presence_structure($presence, $cm, $course, $context);
                $course->presence_instance[$presence->id] = array();
                $course->presence_instance[$presence->id]['name'] = $presence->name;
                $todaysessions = $presence->get_today_sessions();

                if (!empty($todaysessions)) {
                    $course->presence_instance[$presence->id]['today_sessions'] = $todaysessions;
                    $coursessessions[$course->id] = $course;
                }
            }
        }

        return self::prepare_data($coursessessions);
    }

    /**
     * Prepare data.
     *
     * @param array $coursessessions
     * @return array
     */
    private static function prepare_data($coursessessions) {
        $courses = array();

        foreach ($coursessessions as $c) {
            $courses[$c->id] = new stdClass();
            $courses[$c->id]->shortname = $c->shortname;
            $courses[$c->id]->fullname = $c->fullname;
            $courses[$c->id]->presence_instances = $c->presence_instance;
        }

        return $courses;
    }

    /**
     * For this session, returns all the necessary data to take an presence.
     *
     * @param int $sessionid
     * @return mixed
     */
    public static function get_session($sessionid) {
        global $DB;

        $session = $DB->get_record('presence_sessions', array('id' => $sessionid));
        $session->courseid = $DB->get_field('presence', 'course', array('id' => $session->presenceid));
        $session->statuses = presence_get_statuses($session->presenceid, true, $session->statusset);
        $coursecontext = context_course::instance($session->courseid);
        $session->users = get_enrolled_users($coursecontext, 'mod/presence:canbelisted',
                                             $session->groupid, 'u.id, u.firstname, u.lastname');
        $session->presence_log = array();

        if ($presencelog = $DB->get_records('presence_evaluations', array('sessionid' => $sessionid),
                                              '', 'studentid, statusid, remarks, id')) {
            $session->presence_log = $presencelog;
        }

        return $session;
    }

    /**
     * Update user status
     *
     * @param int $sessionid
     * @param int $studentid
     * @param int $takenbyid
     * @param int $statusid
     * @param int $statusset
     */
    public static function update_user_status($sessionid, $studentid, $takenbyid, $statusid, $statusset) {
        global $DB;

        $record = new stdClass();
        $record->statusset = $statusset;
        $record->sessionid = $sessionid;
        $record->timetaken = time();
        $record->takenby = $takenbyid;
        $record->statusid = $statusid;
        $record->studentid = $studentid;

        if ($presencelog = $DB->get_record('presence_evaluations', array('sessionid' => $sessionid, 'studentid' => $studentid))) {
            $record->id = $presencelog->id;
            $DB->update_record('presence_evaluations', $record);
        } else {
            $DB->insert_record('presence_evaluations', $record);
        }

        if ($presencesession = $DB->get_record('presence_sessions', array('id' => $sessionid))) {
            $presencesession->lasttaken = time();
            $presencesession->lasttakenby = $takenbyid;
            $presencesession->timemodified = time();

            $DB->update_record('presence_sessions', $presencesession);
        }
    }
}
