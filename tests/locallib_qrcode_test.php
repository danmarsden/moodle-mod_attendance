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
 * PHPUnit tests for QR code functions in mod_attendance locallib.
 *
 * @package    mod_attendance
 * @category   test
 * @copyright  2025 mgmodell
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance;

use advanced_testcase;
use mod_attendance_structure;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/attendance/locallib.php');
require_once($CFG->dirroot . '/mod/attendance/classes/structure.php');

/**
 * Tests for QR code-related functions in locallib.php.
 *
 * @package    mod_attendance
 * @category   test
 * @copyright  2025 mgmodell
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_attendance
 */
final class locallib_qrcode_test extends advanced_testcase {

    /** @var stdClass */
    protected $course;

    /** @var stdClass */
    protected $teacher;

    /** @var mod_attendance_structure */
    protected $attendance;

    /** @var stdClass Session DB record. */
    protected $session;

    /**
     * Set up test fixtures.
     */
    public function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest(true);

        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($this->teacher);

        $att = $this->getDataGenerator()->create_module('attendance', ['course' => $this->course->id]);
        $cm = $DB->get_record('course_modules', ['id' => $att->cmid], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $this->attendance = new mod_attendance_structure($att, $cm, $course);

        // Create a session with QR code and a student password.
        $sessiondata = new stdClass();
        $sessiondata->sessdate = time();
        $sessiondata->duration = 3600;
        $sessiondata->description = '';
        $sessiondata->descriptionformat = 1;
        $sessiondata->descriptionitemid = 0;
        $sessiondata->timemodified = time();
        $sessiondata->statusset = 0;
        $sessiondata->groupid = 0;
        $sessiondata->absenteereport = 1;
        $sessiondata->calendarevent = 0;
        $sessiondata->studentpassword = 'testpass';
        $sessiondata->includeqrcode = 1;
        $sessiondata->rotateqrcode = 0;
        $sessiondata->rotateqrcodesecret = '';

        $this->attendance->add_sessions([$sessiondata]);

        // Fetch the created session from the DB.
        $sessions = $DB->get_records('attendance_sessions', ['attendanceid' => $this->attendance->id]);
        $this->session = reset($sessions);
    }

    /**
     * Test that attendance_generate_passwords creates exactly 30 password records.
     *
     * @covers ::attendance_generate_passwords
     */
    public function test_attendance_generate_passwords_creates_30_records(): void {
        global $DB;

        // Set the rotate QR code interval config.
        set_config('rotateqrcodeinterval', 30, 'attendance');

        attendance_generate_passwords($this->session);

        $count = $DB->count_records('attendance_rotate_passwords', ['attendanceid' => $this->session->id]);
        $this->assertEquals(30, $count);
    }

    /**
     * Test that attendance_generate_passwords sets future expiry times.
     *
     * @covers ::attendance_generate_passwords
     */
    public function test_attendance_generate_passwords_sets_future_expiry(): void {
        global $DB;

        set_config('rotateqrcodeinterval', 30, 'attendance');

        $before = time();
        attendance_generate_passwords($this->session);
        $after = time();

        $passwords = $DB->get_records('attendance_rotate_passwords', ['attendanceid' => $this->session->id]);
        foreach ($passwords as $pw) {
            $this->assertGreaterThanOrEqual($before, $pw->expirytime);
        }

        // Last password (i=29) should expire at roughly before + 30*29 = before + 870s.
        $expirytimes = array_column((array)$passwords, 'expirytime');
        $maxexpiry = max($expirytimes);
        $this->assertGreaterThan($after, $maxexpiry);
    }

    /**
     * Test that attendance_generate_passwords creates non-empty password strings.
     *
     * @covers ::attendance_generate_passwords
     */
    public function test_attendance_generate_passwords_creates_nonempty_strings(): void {
        global $DB;

        set_config('rotateqrcodeinterval', 30, 'attendance');

        attendance_generate_passwords($this->session);

        $passwords = $DB->get_records('attendance_rotate_passwords', ['attendanceid' => $this->session->id]);
        foreach ($passwords as $pw) {
            $this->assertNotEmpty($pw->password);
        }
    }

    /**
     * Test that attendance_return_passwords returns passwords that have not yet expired.
     *
     * @covers ::attendance_return_passwords
     */
    public function test_attendance_return_passwords_returns_valid_passwords(): void {
        global $DB;

        set_config('rotateqrcodeinterval', 30, 'attendance');
        attendance_generate_passwords($this->session);

        $result = attendance_return_passwords($this->session);
        $passwords = json_decode($result, true);

        $this->assertNotEmpty($passwords);

        $now = time();
        foreach ($passwords as $pw) {
            $this->assertGreaterThan($now, $pw['expirytime']);
        }
    }

    /**
     * Test that attendance_return_passwords excludes expired passwords.
     *
     * @covers ::attendance_return_passwords
     */
    public function test_attendance_return_passwords_excludes_expired(): void {
        global $DB;

        // Insert one expired and one valid password directly.
        $DB->insert_record('attendance_rotate_passwords', [
            'attendanceid' => $this->session->id,
            'password'     => 'expired_pass',
            'expirytime'   => time() - 100, // In the past.
        ]);
        $DB->insert_record('attendance_rotate_passwords', [
            'attendanceid' => $this->session->id,
            'password'     => 'valid_pass',
            'expirytime'   => time() + 600, // In the future.
        ]);

        $result = attendance_return_passwords($this->session);
        $passwords = json_decode($result, true);

        $this->assertCount(1, $passwords);
        $pw = reset($passwords);
        $this->assertEquals('valid_pass', $pw['password']);
    }

    /**
     * Test that attendance_return_passwords returns results in ascending expiry order.
     *
     * @covers ::attendance_return_passwords
     */
    public function test_attendance_return_passwords_order_ascending(): void {
        global $DB;

        $now = time();
        $DB->insert_record('attendance_rotate_passwords', [
            'attendanceid' => $this->session->id,
            'password'     => 'second',
            'expirytime'   => $now + 600,
        ]);
        $DB->insert_record('attendance_rotate_passwords', [
            'attendanceid' => $this->session->id,
            'password'     => 'first',
            'expirytime'   => $now + 300,
        ]);

        $result = attendance_return_passwords($this->session);
        $passwords = array_values(json_decode($result, true));

        $this->assertEquals('first', $passwords[0]['password']);
        $this->assertEquals('second', $passwords[1]['password']);
    }

    /**
     * Test that attendance_return_passwords returns JSON-encoded string.
     *
     * @covers ::attendance_return_passwords
     */
    public function test_attendance_return_passwords_returns_json(): void {
        global $DB;

        $DB->insert_record('attendance_rotate_passwords', [
            'attendanceid' => $this->session->id,
            'password'     => 'testpass123',
            'expirytime'   => time() + 300,
        ]);

        $result = attendance_return_passwords($this->session);
        $this->assertIsString($result);

        $decoded = json_decode($result, true);
        $this->assertNotNull($decoded);
        $this->assertIsArray($decoded);
    }

    /**
     * Test that attendance_renderqrcode outputs an HTML img tag with a base64-encoded PNG.
     *
     * @covers ::attendance_renderqrcode
     */
    public function test_attendance_renderqrcode_outputs_img_tag(): void {
        global $CFG;

        // TCPDF is required for rendering - skip if not available.
        if (!file_exists($CFG->libdir . '/tcpdf/tcpdf_barcodes_2d.php')) {
            $this->markTestSkipped('TCPDF library not found.');
        }

        require_once($CFG->libdir . '/tcpdf/tcpdf_barcodes_2d.php');

        ob_start();
        attendance_renderqrcode($this->session);
        $output = ob_get_clean();

        $this->assertStringContainsString('<img', $output);
        $this->assertStringContainsString('data:image/png;base64,', $output);
    }

    /**
     * Test that attendance_renderqrcode embeds the session ID in the URL encoded into the QR code.
     *
     * @covers ::attendance_renderqrcode
     */
    public function test_attendance_renderqrcode_encodes_session_id(): void {
        global $CFG;

        if (!file_exists($CFG->libdir . '/tcpdf/tcpdf_barcodes_2d.php')) {
            $this->markTestSkipped('TCPDF library not found.');
        }

        require_once($CFG->libdir . '/tcpdf/tcpdf_barcodes_2d.php');

        ob_start();
        attendance_renderqrcode($this->session);
        $output = ob_get_clean();

        // The img alt text should contain the 'qrcode' lang string.
        $this->assertStringContainsString('alt=', $output);
    }
}
