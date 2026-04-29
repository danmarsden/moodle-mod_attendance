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
 * CLI script to create a test course with an attendance activity for mod_attendance development.
 *
 * Usage:
 *   php create_test_course.php [--students=N] [--help]
 *   php create_test_course.php --cleanup
 *
 * @package   mod_attendance
 * @copyright 2024 mod_attendance contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/mod/attendance/lib.php');

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
const COURSE_IDNUMBER_PREFIX = 'att_test_';
const INSTRUCTOR_USERNAME    = 'instructor';
const INSTRUCTOR_PASSWORD    = '1nstructor_pa55word';
const STUDENT_PASSWORD       = '57uden7_pa55word';
const SESSION_PASSWORD       = 'attend123';

// ---------------------------------------------------------------------------
// Parse CLI options
// ---------------------------------------------------------------------------
[$options, $unrecognized] = cli_get_params(
    ['cleanup' => false, 'students' => 3, 'help' => false],
    ['h' => 'help']
);

if (!empty($unrecognized)) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error("Unrecognized options:\n  {$unrecognized}\nUse --help for help.");
}

if ($options['help']) {
    cli_writeln(<<<EOT
Create a test course with mod_attendance activity for development and testing.

Each invocation creates a new, uniquely-named course so the script can be run
repeatedly without conflict.  Users (instructor and students) are reused if
they already exist.

Usage:
  php create_test_course.php [options]

Options:
  --cleanup         Remove every test course previously created by this script.
                    Users are left intact.
  --students=N      Number of student accounts to create / enrol (default: 3).
  -h, --help        Show this help message.

EOT
    );
    exit(0);
}

// ---------------------------------------------------------------------------
// Dispatch
// ---------------------------------------------------------------------------
if ($options['cleanup']) {
    cleanup_test_courses();
} else {
    create_test_course((int)$options['students']);
}

// ---------------------------------------------------------------------------
// Functions
// ---------------------------------------------------------------------------

/**
 * Remove all courses whose idnumber starts with the script's prefix.
 */
function cleanup_test_courses(): void {
    global $DB;

    $sql  = 'SELECT * FROM {course} WHERE ' . $DB->sql_like('idnumber', ':prefix', false);
    $rows = $DB->get_records_sql($sql, ['prefix' => COURSE_IDNUMBER_PREFIX . '%']);

    if (empty($rows)) {
        cli_writeln('No test courses found — nothing to clean up.');
        return;
    }

    foreach ($rows as $course) {
        delete_course($course->id, false);
        cli_writeln("Deleted course: {$course->fullname} (id={$course->id}, idnumber={$course->idnumber})");
    }

    fix_course_sortorder();
    cli_writeln('Cleanup complete.');
}

/**
 * Create a fresh test course with attendance, instructor, and students.
 *
 * @param int $numstudents Number of students to create and enrol.
 */
function create_test_course(int $numstudents): void {
    global $CFG;

    // --- Users ---------------------------------------------------------------
    $instructor = get_or_create_user(
        INSTRUCTOR_USERNAME,
        INSTRUCTOR_PASSWORD,
        'Test',
        'Instructor',
        'instructor@example.com'
    );
    cli_writeln("Instructor: {$instructor->username} (id={$instructor->id})");

    $students = [];
    for ($i = 1; $i <= $numstudents; $i++) {
        $student    = get_or_create_user(
            "student{$i}",
            STUDENT_PASSWORD,
            'Student',
            "User{$i}",
            "student{$i}@example.com"
        );
        $students[] = $student;
        cli_writeln("Student:    {$student->username} (id={$student->id})");
    }

    // --- Course --------------------------------------------------------------
    $timestamp = time();
    $coursedata                = new stdClass();
    $coursedata->fullname      = 'Attendance Test Course ' . date('Y-m-d H:i:s', $timestamp);
    $coursedata->shortname     = 'att_test_' . $timestamp;
    $coursedata->idnumber      = COURSE_IDNUMBER_PREFIX . $timestamp;
    $coursedata->category      = 1;
    $coursedata->visible       = 1;
    $coursedata->startdate     = $timestamp;

    $course = create_course($coursedata);
    cli_writeln("Course:     \"{$course->fullname}\" (id={$course->id})");

    // --- Enrolments ----------------------------------------------------------
    enrol_user_in_course($instructor->id, $course->id, 'editingteacher');
    cli_writeln("Enrolled instructor as editingteacher.");

    foreach ($students as $student) {
        enrol_user_in_course($student->id, $course->id, 'student');
    }
    cli_writeln("Enrolled {$numstudents} student(s).");

    // --- Attendance module ---------------------------------------------------
    $attendanceid = create_attendance_module($course);
    cli_writeln("Attendance: module created (attendance.id={$attendanceid})");

    // --- Sessions ------------------------------------------------------------
    $now = time();
    for ($i = 0; $i < 3; $i++) {
        $sessdate = $now + ($i * 3600);
        $sessid   = create_attendance_session($attendanceid, $sessdate);
        cli_writeln(sprintf(
            "Session %d: id=%d, starts=%s, ends=%s",
            $i + 1,
            $sessid,
            date('Y-m-d H:i', $sessdate),
            date('Y-m-d H:i', $sessdate + 3600)
        ));
    }

    // --- Summary -------------------------------------------------------------
    $wwwroot = rtrim($CFG->wwwroot, '/');
    cli_writeln('');
    cli_writeln('========================================================');
    cli_writeln('  Test course created successfully!');
    cli_writeln("  URL      : {$wwwroot}/course/view.php?id={$course->id}");
    cli_writeln('  Instructor login');
    cli_writeln('    username : ' . INSTRUCTOR_USERNAME);
    cli_writeln('    password : ' . INSTRUCTOR_PASSWORD);
    cli_writeln("  Student logins (student1 .. student{$numstudents})");
    cli_writeln('    password : ' . STUDENT_PASSWORD);
    cli_writeln('  Session password : ' . SESSION_PASSWORD);
    cli_writeln('========================================================');
}

/**
 * Return an existing Moodle user with the given username, or create one.
 *
 * @param string $username
 * @param string $password  Plain-text password (used only when creating).
 * @param string $firstname
 * @param string $lastname
 * @param string $email
 * @return stdClass  The user record (with at least id and username).
 */
function get_or_create_user(
    string $username,
    string $password,
    string $firstname,
    string $lastname,
    string $email
): stdClass {
    global $DB, $CFG;

    $existing = $DB->get_record('user', ['username' => $username, 'deleted' => 0, 'mnethostid' => $CFG->mnet_localhost_id]);
    if ($existing) {
        return $existing;
    }

    $user               = new stdClass();
    $user->username     = $username;
    $user->password     = hash_internal_user_password($password);
    $user->firstname    = $firstname;
    $user->lastname     = $lastname;
    $user->email        = $email;
    $user->confirmed    = 1;
    $user->auth         = 'manual';
    $user->mnethostid   = $CFG->mnet_localhost_id;
    $user->lang         = $CFG->lang ?? 'en';
    $user->calendartype = $CFG->calendartype ?? 'gregorian';
    $user->timecreated  = time();
    $user->timemodified = time();

    $user->id = $DB->insert_record('user', $user);
    return $user;
}

/**
 * Enrol a user in a course using the manual enrolment method.
 *
 * @param int    $userid
 * @param int    $courseid
 * @param string $roleshortname  e.g. 'editingteacher' or 'student'
 */
function enrol_user_in_course(int $userid, int $courseid, string $roleshortname): void {
    global $DB;

    $role   = $DB->get_record('role', ['shortname' => $roleshortname], '*', MUST_EXIST);
    $enrol  = enrol_get_plugin('manual');
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

    $instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'manual']);
    if (!$instance) {
        $instanceid = $enrol->add_instance($course);
        $instance   = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
    }

    $enrol->enrol_user($instance, $userid, $role->id);
}

/**
 * Add an Attendance activity to section 0 of the given course.
 *
 * @param stdClass $course  Course record.
 * @return int  The id of the new attendance record.
 */
function create_attendance_module(stdClass $course): int {
    global $DB;

    // Find the module type record.
    $module = $DB->get_record('modules', ['name' => 'attendance'], '*', MUST_EXIST);

    // Insert the attendance instance record.
    $attendance               = new stdClass();
    $attendance->course       = $course->id;
    $attendance->name         = 'Attendance';
    $attendance->intro        = '';
    $attendance->introformat  = FORMAT_HTML;
    $attendance->grade        = 100;
    $attendance->timemodified = time();

    $attendance->id = $DB->insert_record('attendance', $attendance);

    // Set up default statuses (Present, Absent, etc.).
    att_add_default_statuses($attendance->id);

    // Ensure section 0 exists.
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
    if (!$section) {
        $section            = new stdClass();
        $section->course    = $course->id;
        $section->section   = 0;
        $section->summary   = '';
        $section->summaryformat = FORMAT_HTML;
        $section->sequence  = '';
        $section->visible   = 1;
        $section->id        = $DB->insert_record('course_sections', $section);
    }

    // Insert the course_modules record.
    $cm                         = new stdClass();
    $cm->course                 = $course->id;
    $cm->module                 = $module->id;
    $cm->instance               = $attendance->id;
    $cm->section                = $section->id;
    $cm->visible                = 1;
    $cm->visibleoncoursepage    = 1;
    $cm->added                  = time();
    $cm->id                     = $DB->insert_record('course_modules', $cm);

    // Append the new cm to the section sequence.
    $sequence          = $section->sequence ? $section->sequence . ',' . $cm->id : (string)$cm->id;
    $DB->set_field('course_sections', 'sequence', $sequence, ['id' => $section->id]);

    // Rebuild course cache so the activity shows up immediately.
    rebuild_course_cache($course->id, true);

    return $attendance->id;
}

/**
 * Insert a single attendance session record.
 *
 * @param int $attendanceid  The attendance instance id.
 * @param int $sessdate      Unix timestamp for the session start.
 * @return int  The new session id.
 */
function create_attendance_session(int $attendanceid, int $sessdate): int {
    global $DB;

    $sess                      = new stdClass();
    $sess->attendanceid        = $attendanceid;
    $sess->groupid             = 0;
    $sess->sessdate            = $sessdate;
    $sess->duration            = 3600;          // 1 hour in seconds.
    $sess->description         = '';
    $sess->descriptionformat   = FORMAT_HTML;
    $sess->studentscanmark     = 1;             // Allow students to self-mark.
    $sess->allowupdatestatus   = 0;
    $sess->studentsearlyopentime = 0;
    $sess->autoassignstatus    = 0;
    $sess->studentpassword     = SESSION_PASSWORD;
    $sess->includeqrcode       = 1;             // Show QR code alongside password.
    $sess->rotateqrcode        = 0;
    $sess->rotateqrcodesecret  = '';
    $sess->subnet              = '';
    $sess->automark            = 0;
    $sess->automarkcompleted   = 0;
    $sess->statusset           = 0;
    $sess->absenteereport      = 1;
    $sess->preventsharedip     = 0;
    $sess->preventsharediptime = null;
    $sess->calendarevent       = 1;
    $sess->caleventid          = 0;
    $sess->timemodified        = time();
    $sess->lasttaken           = null;
    $sess->lasttakenby         = 0;
    $sess->automarkcmid        = 0;

    return $DB->insert_record('attendance_sessions', $sess);
}
