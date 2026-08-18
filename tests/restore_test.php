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
 * Restore tests for attendance.
 *
 * @package    mod_attendance
 * @category   test
 * @copyright  2026 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance;

use advanced_testcase;
use backup;
use backup_controller;
use backup_setting;
use restore_controller;
use restore_dbops;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/attendance/classes/structure.php');

/**
 * Restore tests for attendance.
 *
 * @package    mod_attendance
 * @category   test
 * @covers     \restore_attendance_activity_structure_step::process_attendance_log
 * @group      mod_attendance
 */
final class restore_test extends advanced_testcase {
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
     * Ensure attendance_log.statusset IDs are remapped when restoring user data.
     */
    public function test_restore_remaps_log_statusset_ids(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');

        $attendance = $generator->create_module('attendance', ['course' => $course->id]);
        $cm = get_coursemodule_from_id('attendance', $attendance->cmid, 0, false, MUST_EXIST);
        $coursesql = $DB->get_record('course', ['id' => $course->id], '*', MUST_EXIST);
        $attendanceinstance = new \mod_attendance_structure($attendance, $cm, $coursesql);

        $session = new stdClass();
        $session->sessdate = time();
        $session->duration = 3600;
        $session->description = '';
        $session->descriptionformat = FORMAT_HTML;
        $session->descriptionitemid = 0;
        $session->timemodified = time();
        $session->statusset = 0;
        $session->groupid = 0;
        $session->absenteereport = 1;
        $session->calendarevent = 0;
        $attendanceinstance->add_sessions([$session]);

        $sessionrecord = $DB->get_record('attendance_sessions', ['attendanceid' => $attendance->id], '*', MUST_EXIST);
        $statuses = $DB->get_records('attendance_statuses', ['attendanceid' => $attendance->id, 'deleted' => 0], 'id ASC', 'id');
        $this->assertGreaterThanOrEqual(2, count($statuses));
        $statusids = array_keys($statuses);

        $originallog = (object) [
            'sessionid' => $sessionrecord->id,
            'studentid' => $student->id,
            'statusid' => (int) $statusids[0],
            'statusset' => implode(',', [(int) $statusids[0], (int) $statusids[1]]),
            'timetaken' => time(),
            'takenby' => $teacher->id,
            'remarks' => 'Restore mapping test',
            'ipaddress' => '',
        ];
        $DB->insert_record('attendance_log', $originallog);

        $newcourseid = $this->backup_and_restore($course, true);
        $newattendance = $DB->get_record('attendance', ['course' => $newcourseid], '*', MUST_EXIST);
        $newsession = $DB->get_record('attendance_sessions', ['attendanceid' => $newattendance->id], '*', MUST_EXIST);

        $restoredlog = $DB->get_record('attendance_log', [
            'sessionid' => $newsession->id,
            'studentid' => $student->id,
        ], '*', MUST_EXIST);

        $this->assertNotEquals($originallog->statusset, $restoredlog->statusset);

        $restoredstatussetids = array_filter(array_map('intval', explode(',', (string) $restoredlog->statusset)));
        $this->assertNotEmpty($restoredstatussetids);

        foreach ($restoredstatussetids as $restoredstatusid) {
            $statusrecord = $DB->get_record('attendance_statuses', ['id' => $restoredstatusid], 'id, attendanceid', MUST_EXIST);
            $this->assertEquals($newattendance->id, $statusrecord->attendanceid);
        }

        $restoredstatusrecord = $DB->get_record('attendance_statuses', ['id' => $restoredlog->statusid], 'id, attendanceid', MUST_EXIST);
        $this->assertEquals($newattendance->id, $restoredstatusrecord->attendanceid);
    }

    /**
     * Back up and restore the course.
     *
     * @param stdClass $course
     * @param bool $userdata
     * @return int
     */
    private function backup_and_restore(stdClass $course, bool $userdata): int {
        global $CFG, $USER;

        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );

        $bc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value($userdata);

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

        $rc->get_plan()->get_setting('users')->set_status(backup_setting::NOT_LOCKED);
        $rc->get_plan()->get_setting('users')->set_value($userdata);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
