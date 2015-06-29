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

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * External mod attendance functions unit tests
 *
 * @package mod_attendance
 * @category external
 * @copyright 2012 Paul Charsley (mod assign)
 * @copyright 2015 Daniel Neis
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_attendance_external_testcase extends externallib_advanced_testcase {

    /**
     * Tests set up
     */
    protected function setUp() {
        global $CFG;
        require_once($CFG->dirroot . '/mod/attendance/externallib.php');
    }

    /**
     * Test take_attendance .
     */
    public function test_take_attendance() {
        $this->resetAfterTest(true);

        // Create a course and assignment.
        $coursedata['idnumber'] = 'idnumbercourse';
        $coursedata['fullname'] = 'Lightwork Course';
        $coursedata['summary'] = 'Lightwork Course description';
        $coursedata['summaryformat'] = FORMAT_MOODLE;
        $course = self::getDataGenerator()->create_course($coursedata);

        $attendancedata['course'] = $course->id;
        $attendancedata['name'] = 'lightwork attendance';

        $attendance = self::getDataGenerator()->create_module('attendance', $attendancedata);

        // Create a manual enrolment record.
        $manualenroldata['enrol'] = 'manual';
        $manualenroldata['status'] = 0;
        $manualenroldata['courseid'] = $course->id;
        $enrolid = $DB->insert_record('enrol', $manualenroldata);

        // Create a teacher and give them capabilities.
        $context = context_course::instance($course->id);
        $roleid = $this->assignUserCapability('moodle/course:viewparticipants', $context->id, 3);
        $context = context_module::instance($attendance->cmid);
        $this->assignUserCapability('mod/attendance:takeattendances', $context->id, $roleid);

        // Create the teacher's enrolment record.
        $userenrolmentdata['status'] = 0;
        $userenrolmentdata['enrolid'] = $enrolid;
        $userenrolmentdata['userid'] = $USER->id;
        $DB->insert_record('user_enrolments', $userenrolmentdata);

        // TODO: add sessions
        $users = array(array('userid' => 2, 'statusid' => 1,
                             'remarks' => 'consideracoes'),
                       array('userid' => 1, 'statusid' => 2,
                             'remarks' => 'outras consideracoes'),
                      );
        $sessionid = 1;
        $takenby = $USER->id; // Created by first call of assignUserCapability() as teacher.

        $result = mod_attendance_external::take_attendance($users, $sessionid, $takenby);

        // We need to execute the return values cleaning process to simulate the web service server.
        $result = external_api::clean_returnvalue(mod_attendance_external::take_attendance_returns(), $result);

        // Check that returned value is equal to sessionid .
        $this->assertEquals($sessionid, $result);
    }
}
