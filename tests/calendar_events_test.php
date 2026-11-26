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

namespace mod_attendance;

use advanced_testcase;
use backup;
use backup_controller;
use restore_controller;
use restore_dbops;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/attendance/classes/structure.php');
require_once($CFG->dirroot . '/calendar/lib.php');

/**
 * Tests for attendance session calendar events.
 *
 * @package    mod_attendance
 * @category   test
 * @copyright  2026 taki-0105a
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_attendance
 */
final class calendar_events_test extends advanced_testcase {
    /**
     * Load backup and restore classes.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;

        parent::setUpBeforeClass();

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
    }

    /**
     * Group sessions create events with eventtype 'group', other sessions keep eventtype 'attendance'.
     *
     * @covers ::attendance_create_calendar_event
     */
    public function test_create_calendar_event_eventtype(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablecalendar', 1, 'attendance');

        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $attendance = $this->create_attendance($course);

        $coursesession = $this->add_session($attendance, 0, time() + DAYSECS);
        $groupsession = $this->add_session($attendance, $group->id, time() + 2 * DAYSECS);

        $courseevent = $DB->get_record('event', ['id' => $coursesession->caleventid], '*', MUST_EXIST);
        $this->assertEquals('attendance', $courseevent->eventtype);
        $this->assertEquals(0, $courseevent->groupid);

        $groupevent = $DB->get_record('event', ['id' => $groupsession->caleventid], '*', MUST_EXIST);
        $this->assertEquals('group', $groupevent->eventtype);
        $this->assertEquals($group->id, $groupevent->groupid);
        $this->assertEquals('attendance', $groupevent->modulename);
        $this->assertEquals($attendance->id, $groupevent->instance);
    }

    /**
     * A teacher who can access all groups but is not a group member sees group session events.
     *
     * @covers ::attendance_create_calendar_event
     */
    public function test_group_session_event_visible_in_calendar(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablecalendar', 1, 'attendance');

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['groupmode' => SEPARATEGROUPS, 'groupmodeforce' => 1]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student1 = $generator->create_and_enrol($course, 'student');
        $student2 = $generator->create_and_enrol($course, 'student');
        $group1 = $generator->create_group(['courseid' => $course->id]);
        $group2 = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group1->id, 'userid' => $student1->id]);
        $generator->create_group_member(['groupid' => $group2->id, 'userid' => $student2->id]);

        // Only group sessions: a course-wide session of the same activity would also make the
        // calendar return the group events, because the events are matched by module, instance and type.
        $attendance = $this->create_attendance($course);
        $group1session = $this->add_session($attendance, $group1->id, time() + 2 * DAYSECS);
        $group2session = $this->add_session($attendance, $group2->id, time() + 3 * DAYSECS);

        // The teacher is not a member of any group.
        $this->assertEqualsCanonicalizing(
            [$group1session->caleventid, $group2session->caleventid],
            $this->get_calendar_event_ids($teacher, $course)
        );

        // Students only see the events of their own group.
        $this->assertEqualsCanonicalizing(
            [$group1session->caleventid],
            $this->get_calendar_event_ids($student1, $course)
        );
        $this->assertEqualsCanonicalizing(
            [$group2session->caleventid],
            $this->get_calendar_event_ids($student2, $course)
        );
    }

    /**
     * Restoring a course links each group session to one event of the restored activity.
     *
     * @covers \restore_attendance_activity_structure_step::process_attendance_session
     * @covers \restore_attendance_activity_task::after_restore
     */
    public function test_restore_group_session_event(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enablecalendar', 1, 'attendance');

        $course = $this->getDataGenerator()->create_course();
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $attendance = $this->create_attendance($course);
        $this->add_session($attendance, 0, time() + DAYSECS);
        $this->add_session($attendance, $group->id, time() + 2 * DAYSECS);

        $newcourseid = $this->backup_and_restore($course);

        $newattendance = $DB->get_record('attendance', ['course' => $newcourseid], '*', MUST_EXIST);
        $newgroup = $DB->get_record('groups', ['courseid' => $newcourseid], '*', MUST_EXIST);
        $sessions = $DB->get_records('attendance_sessions', ['attendanceid' => $newattendance->id]);
        $this->assertCount(2, $sessions);

        // Exactly one event per session, all linked to the restored activity.
        $events = $DB->get_records('event', ['modulename' => 'attendance', 'courseid' => $newcourseid]);
        $this->assertCount(2, $events);
        foreach ($sessions as $session) {
            $this->assertArrayHasKey($session->caleventid, $events);
            $event = $events[$session->caleventid];
            $this->assertEquals($newattendance->id, $event->instance);
            if (empty($session->groupid)) {
                $this->assertEquals('attendance', $event->eventtype);
                $this->assertEquals(0, $event->groupid);
            } else {
                $this->assertEquals('group', $event->eventtype);
                $this->assertEquals($newgroup->id, $session->groupid);
                $this->assertEquals($newgroup->id, $event->groupid);
            }
        }
    }

    /**
     * Create an attendance activity.
     *
     * @param stdClass $course
     * @return \mod_attendance_structure
     */
    private function create_attendance(stdClass $course): \mod_attendance_structure {
        global $DB;

        $module = $this->getDataGenerator()->create_module('attendance', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('attendance', $module->cmid, 0, false, MUST_EXIST);
        $record = $DB->get_record('attendance', ['id' => $module->id], '*', MUST_EXIST);

        return new \mod_attendance_structure($record, $cm, get_course($course->id));
    }

    /**
     * Add a session with a calendar event.
     *
     * @param \mod_attendance_structure $attendance
     * @param int $groupid
     * @param int $sessdate
     * @return stdClass the session record
     */
    private function add_session(\mod_attendance_structure $attendance, int $groupid, int $sessdate): stdClass {
        global $DB;

        $session = new stdClass();
        $session->sessdate = $sessdate;
        $session->duration = HOURSECS;
        $session->description = '';
        $session->descriptionformat = FORMAT_HTML;
        $session->descriptionitemid = 0;
        $session->timemodified = time();
        $session->statusset = 0;
        $session->groupid = $groupid;
        $session->absenteereport = 1;
        $session->calendarevent = 1;
        $sessionid = $attendance->add_session($session);

        $record = $DB->get_record('attendance_sessions', ['id' => $sessionid], '*', MUST_EXIST);
        $this->assertNotEmpty($record->caleventid);

        return $record;
    }

    /**
     * Get the ids of the attendance events that the calendar shows to a user in the next week.
     *
     * @param stdClass $user
     * @param stdClass $course
     * @return array
     */
    private function get_calendar_event_ids(stdClass $user, stdClass $course): array {
        $this->setUser($user);

        $courses = [$course->id => get_course($course->id)];
        [$courseids, $groupids, $userid] = calendar_set_filters($courses);
        $events = calendar_get_legacy_events(time(), time() + WEEKSECS, $userid, $groupids, $courseids);

        $ids = [];
        foreach ($events as $event) {
            if ($event->modulename === 'attendance') {
                $ids[] = $event->id;
            }
        }

        return $ids;
    }

    /**
     * Back up and restore the course into a new course, including calendar events and groups.
     *
     * @param stdClass $course
     * @return int the id of the new course
     */
    private function backup_and_restore(stdClass $course): int {
        global $CFG, $USER;

        $CFG->backup_file_logger_level = backup::LOG_NONE;
        // Keep the backup directory so that the restore can read it.
        $CFG->keeptempdirectoriesonbackup = true;

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = restore_dbops::create_new_course(
            $course->fullname,
            $course->shortname . '_restored',
            $course->category
        );

        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
