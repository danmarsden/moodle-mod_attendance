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
 * External function tests for add_session.
 *
 * @package    mod_attendance
 * @category   test
 * @copyright  2026 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once(__DIR__ . '/base_external_testcase.php');
require_once($CFG->dirroot . '/mod/attendance/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

use core_external\external_api;

/**
 * External function tests for add_session.
 *
 * @group mod_attendance
 */
final class add_session_test extends base_external_testcase {
    /**
     * Tests adding a session as a teacher.
     *
     * @covers \mod_attendance\external\add_session::execute
     */
    public function test_execute(): void {
        global $DB;

        $this->resetAfterTest(true);
        $context = $this->create_attendance_context();
        $this->setUser($context['teacher']);

        $time = time();
        $result = add_session::execute($context['attendance']->id, 'Session A', $time, 1800, 0, true);
        $result = external_api::clean_returnvalue(add_session::execute_returns(), $result);

        $session = $DB->get_record('attendance_sessions', ['id' => $result['sessionid']], '*', MUST_EXIST);
        $this->assertSame('Session A', $session->description);
        $this->assertSame($time, (int)$session->sessdate);
    }

    /**
     * Tests that separate groups mode requires a non-zero group id.
     *
     * @covers \mod_attendance\external\add_session::execute
     */
    public function test_execute_requires_group_for_separate_groups(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $attendance = add_attendance::execute($course->id, 'Attendance A', 'Intro', SEPARATEGROUPS);
        $attendance = external_api::clean_returnvalue(add_attendance::execute_returns(), $attendance);

        $this->expectException(\invalid_parameter_exception::class);
        add_session::execute($attendance['attendanceid'], 'Session A', time(), 1800, 0, false);
    }

    /**
     * Tests that students cannot add sessions.
     *
     * @covers \mod_attendance\external\add_session::execute
     */
    public function test_execute_denies_student(): void {
        $this->resetAfterTest(true);
        $context = $this->create_attendance_context();
        $this->setUser($context['students'][0]);

        $this->expectException(\required_capability_exception::class);
        add_session::execute($context['attendance']->id, 'Session A', time(), 1800, 0, false);
    }

    /**
     * Tests that sessions keep the previous option values when no options are passed.
     *
     * @covers \mod_attendance\external\add_session::execute
     */
    public function test_execute_option_defaults(): void {
        $this->resetAfterTest(true);
        set_config('absenteereport_default', 0, 'attendance');
        set_config('studentsearlyopentime', 300, 'attendance');
        $context = $this->create_attendance_context();
        $this->setUser($context['teacher']);

        $session = $this->add_session_with_options($context['attendance']->id, []);

        $this->assertEquals(0, $session->studentscanmark);
        $this->assertEquals(0, $session->allowupdatestatus);
        $this->assertEmpty($session->studentpassword);
        $this->assertEquals(0, $session->includeqrcode);
        $this->assertEquals(0, $session->rotateqrcode);
        $this->assertEquals(0, $session->autoassignstatus);
        $this->assertEquals(ATTENDANCE_AUTOMARK_DISABLED, $session->automark);
        $this->assertEquals(0, $session->automarkcmid);
        $this->assertEquals(ATTENDANCE_SHAREDIP_DISABLED, $session->preventsharedip);
        $this->assertEquals(0, $session->absenteereport);
        $this->assertEquals(300, $session->studentsearlyopentime);
    }

    /**
     * Tests adding a self-marking session with options.
     *
     * @covers \mod_attendance\external\add_session::execute
     */
    public function test_execute_with_self_marking_options(): void {
        global $DB;

        $this->resetAfterTest(true);
        set_config('allowupdatestatus', 1, 'attendance');
        set_config('enablewarnings', 1, 'attendance');
        $context = $this->create_attendance_context();
        $attendanceid = $context['attendance']->id;
        // Automatic marking needs a status to set for unmarked students.
        $DB->set_field('attendance_statuses', 'setunmarked', 1, ['attendanceid' => $attendanceid, 'acronym' => 'A']);
        $this->setUser($context['teacher']);

        $session = $this->add_session_with_options($attendanceid, [
            'studentscanmark' => true,
            'allowupdatestatus' => true,
            'studentpassword' => 'secret',
            'includeqrcode' => true,
            'autoassignstatus' => true,
            'automark' => ATTENDANCE_AUTOMARK_CLOSE,
            'preventsharedip' => ATTENDANCE_SHAREDIP_MINUTES,
            'preventsharediptime' => 10,
            'absenteereport' => false,
            'studentsearlyopentime' => 600,
            'subnet' => '10.0.0.0/8',
        ]);

        $this->assertEquals(1, $session->studentscanmark);
        $this->assertEquals(1, $session->allowupdatestatus);
        $this->assertSame('secret', $session->studentpassword);
        $this->assertEquals(1, $session->includeqrcode);
        $this->assertEquals(0, $session->rotateqrcode);
        $this->assertEquals(1, $session->autoassignstatus);
        $this->assertEquals(ATTENDANCE_AUTOMARK_CLOSE, $session->automark);
        $this->assertEquals(ATTENDANCE_SHAREDIP_MINUTES, $session->preventsharedip);
        $this->assertEquals(10, $session->preventsharediptime);
        $this->assertEquals(0, $session->absenteereport);
        $this->assertEquals(600, $session->studentsearlyopentime);
        $this->assertSame('10.0.0.0/8', $session->subnet);
    }

    /**
     * Tests that a random password replaces the given password.
     *
     * @covers \mod_attendance\external\add_session::execute
     */
    public function test_execute_random_password(): void {
        $this->resetAfterTest(true);
        $context = $this->create_attendance_context();
        $this->setUser($context['teacher']);

        $session = $this->add_session_with_options($context['attendance']->id, [
            'studentscanmark' => true,
            'studentpassword' => 'secret',
            'randompassword' => true,
        ]);

        $this->assertEquals(6, \core_text::strlen($session->studentpassword));
        $this->assertNotSame('secret', $session->studentpassword);
    }

    /**
     * Tests that a rotating QR code gets a generated password and secret and replaces the static QR code.
     *
     * @covers \mod_attendance\external\add_session::execute
     */
    public function test_execute_rotating_qrcode(): void {
        $this->resetAfterTest(true);
        $context = $this->create_attendance_context();
        $this->setUser($context['teacher']);

        $session = $this->add_session_with_options($context['attendance']->id, [
            'studentscanmark' => true,
            'studentpassword' => 'secret',
            'includeqrcode' => true,
            'rotateqrcode' => true,
        ]);

        $this->assertEquals(1, $session->rotateqrcode);
        $this->assertEquals(0, $session->includeqrcode);
        $this->assertNotEmpty($session->rotateqrcodesecret);
        $this->assertEquals(6, \core_text::strlen($session->studentpassword));
        $this->assertNotSame('secret', $session->studentpassword);
    }

    /**
     * Tests that self-marking options are not used when self-marking is off for the session or the site.
     *
     * @covers \mod_attendance\external\add_session::execute
     */
    public function test_execute_self_marking_options_need_studentscanmark(): void {
        $this->resetAfterTest(true);
        $context = $this->create_attendance_context();
        $attendanceid = $context['attendance']->id;
        $this->setUser($context['teacher']);

        // Self-marking is off for the session.
        $session = $this->add_session_with_options($attendanceid, [
            'randompassword' => true,
            'autoassignstatus' => true,
            'preventsharedip' => ATTENDANCE_SHAREDIP_FORCE,
        ]);
        $this->assertEquals(0, $session->studentscanmark);
        $this->assertEmpty($session->studentpassword);
        $this->assertEquals(0, $session->autoassignstatus);
        $this->assertEquals(ATTENDANCE_SHAREDIP_DISABLED, $session->preventsharedip);

        // Students changing their own status is off for the site by default.
        $session = $this->add_session_with_options($attendanceid, [
            'studentscanmark' => true,
            'allowupdatestatus' => true,
        ]);
        $this->assertEquals(1, $session->studentscanmark);
        $this->assertEquals(0, $session->allowupdatestatus);

        // Self-marking is off for the site, so the self-marking options are neither used nor checked against each other.
        set_config('studentscanmark', 0, 'attendance');
        $session = $this->add_session_with_options($attendanceid, [
            'studentscanmark' => true,
            'randompassword' => true,
            'includeqrcode' => true,
            'preventsharedip' => ATTENDANCE_SHAREDIP_MINUTES,
        ]);
        $this->assertEquals(0, $session->studentscanmark);
        $this->assertEmpty($session->studentpassword);
        $this->assertEquals(0, $session->includeqrcode);
        $this->assertEquals(ATTENDANCE_SHAREDIP_DISABLED, $session->preventsharedip);
        $this->assertEmpty($session->preventsharediptime);
    }

    /**
     * Tests that the absentee report option is only used when warnings are enabled.
     *
     * @covers \mod_attendance\external\add_session::execute
     */
    public function test_execute_absenteereport_needs_warnings(): void {
        $this->resetAfterTest(true);
        set_config('absenteereport_default', 1, 'attendance');
        set_config('enablewarnings', 0, 'attendance');
        $context = $this->create_attendance_context();
        $this->setUser($context['teacher']);

        $session = $this->add_session_with_options($context['attendance']->id, ['absenteereport' => false]);

        $this->assertEquals(1, $session->absenteereport);
    }

    /**
     * Tests automatic marking on activity completion.
     *
     * @covers \mod_attendance\external\add_session::execute
     */
    public function test_execute_automark_activity_completion(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $attendance = $this->getDataGenerator()->create_module('attendance', ['course' => $course->id]);
        $page = $this->getDataGenerator()->create_module(
            'page',
            ['course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL]
        );
        // Automatic marking needs a status to set for unmarked students.
        $DB->set_field('attendance_statuses', 'setunmarked', 1, ['attendanceid' => $attendance->id, 'acronym' => 'A']);
        $this->setUser($teacher);

        $session = $this->add_session_with_options($attendance->id, [
            'automark' => ATTENDANCE_AUTOMARK_ACTIVITYCOMPLETION,
            'automarkcmid' => $page->cmid,
        ]);
        $this->assertEquals(ATTENDANCE_AUTOMARK_ACTIVITYCOMPLETION, $session->automark);
        $this->assertEquals($page->cmid, $session->automarkcmid);

        // The activity must be one with completion tracking in this course.
        $this->expectException(\invalid_parameter_exception::class);
        $this->add_session_with_options($attendance->id, [
            'automark' => ATTENDANCE_AUTOMARK_ACTIVITYCOMPLETION,
            'automarkcmid' => 0,
        ]);
    }

    /**
     * Tests that automatic marking on activity completion needs completion tracking in the course of the attendance.
     *
     * @covers \mod_attendance\external\add_session::execute
     */
    public function test_execute_automark_activity_completion_needs_course_completion(): void {
        global $DB;

        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_DISABLED]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $attendance = $this->getDataGenerator()->create_module('attendance', ['course' => $course->id]);
        // Automatic marking needs a status to set for unmarked students.
        $DB->set_field('attendance_statuses', 'setunmarked', 1, ['attendanceid' => $attendance->id, 'acronym' => 'A']);
        $this->setUser($teacher);

        // On activity completion is not offered, because the course of the attendance has no completion tracking.
        try {
            $this->add_session_with_options($attendance->id, ['automark' => ATTENDANCE_AUTOMARK_ACTIVITYCOMPLETION]);
            $this->fail('No exception for automatic marking on activity completion without completion tracking');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('Invalid automark value', $e->debuginfo);
        }
    }

    /**
     * Tests that the automark options are those of the given course instead of the current course.
     *
     * @covers ::attendance_get_automarkoptions
     */
    public function test_get_automarkoptions_for_course(): void {
        global $COURSE;

        $this->resetAfterTest(true);
        $nocompletion = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_DISABLED]);
        $completion = $this->getDataGenerator()->create_course(['enablecompletion' => COMPLETION_ENABLED]);

        // The current course is the site, which offers all options (for the admin settings).
        $this->assertEquals(SITEID, $COURSE->id);
        $this->assertArrayHasKey(ATTENDANCE_AUTOMARK_ACTIVITYCOMPLETION, attendance_get_automarkoptions());

        $this->assertArrayNotHasKey(ATTENDANCE_AUTOMARK_ACTIVITYCOMPLETION, attendance_get_automarkoptions($nocompletion));
        $this->assertArrayHasKey(ATTENDANCE_AUTOMARK_ACTIVITYCOMPLETION, attendance_get_automarkoptions($completion));
    }

    /**
     * Tests that invalid options are rejected.
     *
     * @covers \mod_attendance\external\add_session::execute
     */
    public function test_execute_invalid_options(): void {
        $this->resetAfterTest(true);
        set_config('studentscanmark', 1, 'attendance');
        $context = $this->create_attendance_context();
        $this->setUser($context['teacher']);

        // Each case has the options and a part of the expected error.
        $invalidoptions = [
            'unknown automark value' => [['automark' => 99], 'Invalid automark value'],
            'automark without a status for unmarked students' => [
                ['automark' => ATTENDANCE_AUTOMARK_CLOSE],
                'automark needs a status set',
            ],
            'unknown preventsharedip value' => [['preventsharedip' => 5], 'Invalid preventsharedip value'],
            'preventsharedip for some minutes without minutes' => [
                ['studentscanmark' => true, 'preventsharedip' => ATTENDANCE_SHAREDIP_MINUTES],
                'preventsharediptime is required',
            ],
            'negative minutes' => [['preventsharediptime' => -1], 'preventsharediptime must not be negative'],
            'QR code without self-marking' => [['includeqrcode' => true], 'includeqrcode needs studentscanmark'],
            'negative early open time' => [['studentsearlyopentime' => -1], 'studentsearlyopentime must not be negative'],
            'password longer than 50 characters' => [
                ['studentscanmark' => true, 'studentpassword' => str_repeat('x', 51)],
                'studentpassword must not be longer than 50 characters',
            ],
        ];
        foreach ($invalidoptions as $case => [$options, $message]) {
            try {
                $this->add_session_with_options($context['attendance']->id, $options);
                $this->fail('No exception for ' . $case);
            } catch (\invalid_parameter_exception $e) {
                $this->assertStringContainsString($message, $e->debuginfo, $case);
            }
        }
    }

    /**
     * Adds a session with the given options and returns the stored record.
     *
     * @param int $attendanceid
     * @param array $options parameters of add_session::execute(), by name
     * @return \stdClass
     */
    private function add_session_with_options(int $attendanceid, array $options): \stdClass {
        global $DB;

        $params = array_merge(['attendanceid' => $attendanceid, 'sessiontime' => time(), 'duration' => 3600], $options);
        $result = add_session::execute(...$params);
        $result = external_api::clean_returnvalue(add_session::execute_returns(), $result);

        return $DB->get_record('attendance_sessions', ['id' => $result['sessionid']], '*', MUST_EXIST);
    }
}
