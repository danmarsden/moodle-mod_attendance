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
 * Attendance task - Send notifications.
 *
 * @package    mod_attendance
 * @copyright  2017 onwards Dan Marsden http://danmarsden.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\task;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/attendance/locallib.php');
/**
 * Send notifications class.
 *
 * @package    mod_attendance
 * @copyright  2017 onwards Dan Marsden http://danmarsden.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notify extends \core\task\scheduled_task {
    public function get_name() {
        // Shown in admin screens.
        return get_string('notifytask', 'mod_attendance');
    }
    public function execute() {
        global $DB;

        $now = time(); // Store current time to use in queries so they all match nicely.
        $lastrun = get_config('mod_attendance','notifylastrun');
        if (empty($lastrun)) {
            $lastrun = 0;
        }

        $sql = "SELECT a.id, 
                  FROM {attendance} a
                  JOIN {attendance_sessions} s ON s.attendanceid = a.id
                  JOIN {attendance_notifications} n ON n.idnumber = a.id AND n.notifylevel = ".ATTENDANCE_NOTIFYLEVEL_ATTENDANCE."
                  WHERE s.sessdate > ? AND s.sessdate < ?";
        $attendances = $DB->get_recordset_sql($sql, array($lastrun, $now));

        foreach ($attendances as $attendance) {

        }
        set_config('notifylastrun', $now, 'mod_attendance');
    }
}