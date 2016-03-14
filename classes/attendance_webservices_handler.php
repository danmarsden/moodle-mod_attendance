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
 *
 * @package    local_attendance
 * @copyright  2015 Caio Bressan Doneda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(dirname(__FILE__).'/../../../config.php');
require_once(dirname(__FILE__).'/../locallib.php');
require_once(dirname(__FILE__).'/structure.php');
require_once(dirname(__FILE__).'/../../../lib/sessionlib.php');

class attendance_handler {
    /* For this user, this method searches in all the courses that this user has permission to take attendance, 
    ** looking for today sessions and returns the courses with the sessions.
    */
    public static function get_courses_with_today_sessions($userid, $period = 1) {
        global $DB;
      
        $moduleid = $DB->get_field('modules', 'id', array('name'=>'attendance'));
        $user_courses = enrol_get_users_courses($userid);
        $courses_with_today_sessions = array();
        foreach ($user_courses as $course) {
            $context = context_course::instance($course->id);
            if (has_capability('mod/attendance:takeattendances', $context, $userid)) {
                if ($cms = $DB->get_records('course_modules', array('course'=>$course->id, 'module'=>$moduleid))) {
                    $course->cms = $cms;
                    $courses_with_today_sessions[$course->id] = $course;
                }
            }
        }

        foreach ($courses_with_today_sessions as $course) {
            $course->attendance_instance = array();
            foreach ($course->cms as $cm) {
                $att = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);
                $context = context_course::instance($course->id);
                $att = new mod_attendance_structure($att, $cm, $course, $context);
                $course->attendance_instance[$att->id] = array();
                $course->attendance_instance[$att->id]['name'] = $att->name;
        		$today_sessions = $att->get_today_sessions();
                        
        		if (empty($today_sessions)) {
        		    unset($course->cms[$cm->id]);	
        		} else {
                    $course->attendance_instance[$att->id]['today_sessions'] = $today_sessions;
        		}
            }
        }

        return attendance_handler::prepare_data($courses_with_today_sessions);
    }

    private static function prepare_data($courses_with_today_sessions) {
	
        $courses = array();
        
        foreach ($courses_with_today_sessions as $c) {
            $courses[$c->id] = new stdClass();
            $courses[$c->id]->shortname = $c->shortname;
            $courses[$c->id]->fullname = $c->fullname;
            $courses[$c->id]->attendance_instances = $c->attendance_instance;
        }

        return $courses;
    }

    /* 
    ** For this session, returns all the necessary data to take an attendance 
    */
    public static function get_session($sessionid) {
        global $DB;
            
        $session = $DB->get_record('attendance_sessions', array('id' => $sessionid));
        $session->courseid = $DB->get_field('attendance', 'course', array('id' => $session->attendanceid));
	$session->statuses = attendance_get_statuses($session->attendanceid, true, $session->statusset);
        $coursecontext = context_course::instance($session->courseid);
        $session->users = get_enrolled_users($coursecontext, 'mod/attendance:canbelisted', 0, 'u.id, u.firstname, u.lastname');
	$session->attendance_log = array();
    	
    	$sql = "SELECT uid.data
                  FROM {user_info_data} uid
                  JOIN {user_info_field} uif 
                    ON (uid.fieldid = uif.id)
                 WHERE uif.shortname = 'rfid' AND uid.userid = :userid";

    	foreach ($session->users as $key => $user) {
    	    $session->users[$key]->rfid = $DB->get_field_sql($sql, array('userid'=>$user->id));
    	}

        if ($attendance_log = $DB->get_records('attendance_log', array('sessionid' => $sessionid), '', 'studentid,statusid,remarks,id')) {
    	    $session->attendance_log = $attendance_log;
    	}

        return $session;
    }
	

    public static function update_user_status($sessionid, $studentid, $takenbyid, $statusid, $statusset) {
    	global $DB;

    	$record = new stdClass();
    	$record->statusset = $statusset;
    	$record->sessionid = $sessionid;
    	$record->timetaken = time();
    	$record->takenby = $takenbyid;
	$record->statusid = $statusid;
	$record->studentid = $studentid;
 
        if ($attendance_log = $DB->get_record('attendance_log', array('sessionid'=>$sessionid, 'studentid'=>$studentid))) {
            $record->id = $attendance_log->id;
            $DB->update_record('attendance_log', $record);
        } else {
            $DB->insert_record('attendance_log', $record);
        }
            
    	if ($attendance_session = $DB->get_record('attendance_sessions', array('id'=>$sessionid))) {
    	    $attendance_session->lasttaken = time();
    	    $attendance_session->lasttakenby = $takenbyid;
    	    $attendance_session->timemodified = time();
            
            $DB->update_record('attendance_sessions', $attendance_session);
    	}
    }	
}
