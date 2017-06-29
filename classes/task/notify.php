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
 * Attendance task - Send warnings.
 *
 * @package    mod_attendance
 * @copyright  2017 onwards Dan Marsden http://danmarsden.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\task;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/attendance/locallib.php');
/**
 * Task class
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
        if (empty(get_config('attendance', 'enablewarnings'))) {
            return; // Warnings not enabled.
        }
        $now = time(); // Store current time to use in queries so they all match nicely.
        $lastrun = get_config('mod_attendance', 'notifylastrun');
        if (empty($lastrun)) {
            $lastrun = 0;
        }
        if (!empty($lastrun)) {
            mtrace("Get warnings to send for sessions that have ended since: ".userdate($lastrun));
        }

        $orderby = 'ORDER BY cm.id, atl.studentid, n.warningpercent ASC';
        $records = attendance_get_users_to_notify(array(), $orderby, $lastrun, true);
        $sentnotifications = array();
        $thirdpartynotifications = array();
        $numsentusers = 0;
        $numsentthird = 0;
        foreach ($records as $record) {
            if (empty($sentnotifications[$record->userid])) {
                $sentnotifications[$record->userid] = array();
            }

            if (!empty($record->emailuser)) {
                // Only send one warning to this user from each attendance in this run. - flag any higher percent notifications as sent.
                if (empty($sentnotifications[$record->userid]) || !in_array($record->aid, $sentnotifications[$record->userid])) {
                    // Convert variables in emailcontent.
                    $record = attendance_template_variables($record);
                    $user = $DB->get_record('user', array('id' => $record->userid));
                    $from = \core_user::get_noreply_user();

                    $emailcontent = format_text($record->emailcontent, $record->emailcontentformat);

                    email_to_user($user, $from, $record->emailsubject, $emailcontent, $emailcontent);

                    $sentnotifications[$record->userid][] = $record->aid;
                    $numsentusers++;
                }
            }
            // Only send one warning to this user from each attendance in this run. - flag any higher percent notifications as sent.
            if (!empty($record->thirdpartyemails)) {
                $sendto = explode(',', $record->thirdpartyemails);
                $record->percent = round($record->percent * 100)."%";
                $context = \context_module::instance($record->cmid);
                foreach ($sendto as $senduser) {
                    // Check user is allowed to receive warningemails.
                    if (has_capability('mod/attendance:warningemails', $context, $senduser)) {
                        if (empty($thirdpartynotifications[$senduser])) {
                            $thirdpartynotifications[$senduser] = array();
                        }
                        if (!isset($thirdpartynotifications[$senduser][$record->aid . '_' . $record->userid])) {
                            $thirdpartynotifications[$senduser][$record->aid . '_' . $record->userid] = get_string('thirdpartyemailtext', 'attendance', $record);
                        }
                    } else {
                        mtrace("user".$senduser. "does not have capablity in cm".$record->cmid);
                    }
                }
            }

            $notify = new \stdClass();
            $notify->userid = $record->userid;
            $notify->notifyid = $record->notifyid;
            $notify->timesent = $now;
            $DB->insert_record('attendance_warning_done', $notify);
        }
        if (!empty($numsentusers)) {
            mtrace($numsentusers ." user emails sent");
        }
        if (!empty($thirdpartynotifications)) {
            foreach ($thirdpartynotifications as $sendid => $notifications) {
                $user = $DB->get_record('user', array('id' => $sendid));
                $from = \core_user::get_noreply_user();

                $emailcontent = implode("\n", $notifications);
                $emailcontent .= "\n\n".get_string('thirdpartyemailtextfooter', 'attendance');
                $emailcontent = format_text($emailcontent);
                $emailsubject = get_string('thirdpartyemailsubject', 'attendance');

                email_to_user($user, $from, $emailsubject, $emailcontent, $emailcontent);
                $numsentthird++;
            }
            if (!empty($numsentthird)) {
                mtrace($numsentthird ." thirdparty emails sent");
            }
        }

        set_config('notifylastrun', $now, 'mod_attendance');
    }
}