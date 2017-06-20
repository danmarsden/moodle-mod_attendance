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
        $orderby = 'ORDER BY cm.id, atl.studentid, n.warningpercent ASC';
        $records = attendance_get_users_to_notify(array(), $orderby, $lastrun, true);
        $sentnotifications = array();
        foreach($records as $record) {
            // Only send one notification to this user from each attendance in this run. - flag any higher percent notifications as sent.
            if (empty($sentnotifications[$record->userid]) || !in_array($record->aid, $sentnotifications[$record->userid])) {
                // Convert variables in emailcontent.
                $record = attendance_template_variables($record);
                $user = $DB->get_record('user', array('id' => $record->userid));
                $from = \core_user::get_noreply_user();

                $emailcontent = format_text($record->emailcontent, $record->emailcontentformat);

                email_to_user($user, $from, $record->emailsubject, $emailcontent, $emailcontent);

                if (empty($sentnotifications[$record->userid])) {
                    $sentnotifications[$record->userid] = array();
                }

                $sentnotifications[$record->userid][] = $record->aid;
            }

            $notify = new \stdClass();
            $notify->userid = $record->userid;
            $notify->notifyid = $record->notifyid;
            $notify->timesent = $now;
            $DB->insert_record('attendance_notification_sent', $notify);
        }

        set_config('notifylastrun', $now, 'mod_attendance');
    }
}