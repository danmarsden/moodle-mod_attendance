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

require_once($CFG->dirroot . '/mod/attendance/lib.php');
require_once($CFG->dirroot . '/mod/attendance/locallib.php');
/**
 * Task class
 *
 * @package    mod_attendance
 * @copyright  2017 onwards Dan Marsden http://danmarsden.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notify extends \core\task\scheduled_task {
    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public function get_name() {
        // Shown in admin screens.
        return get_string('notifytask', 'mod_attendance');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;
        if (empty(get_config('attendance', 'enablewarnings'))) {
            return; // Warnings not enabled.
        }
        $now = time(); // Store current time to use in queries so they all match nicely.

        $orderby = 'ORDER BY cm.id, atl.studentid, n.warningpercent ASC';

        // Get records for attendance sessions that have been updated since last time this task ran.
        // Note: this returns all users for these sessions - even if the users attendance wasn't changed
        // since last time we ran, before sending a notification we check to see if the users have
        // updated attendance logs since last time they were notified.
        $records = attendance_get_users_to_notify([], $orderby, true);
        $sentnotifications = [];
        $thirdpartynotifications = [];
        $numsentusers = 0;
        $numsentthird = 0;
        $skippedbasismode = 0;
        $skippedemailuseroff = 0;
        $skippedtimesent = 0;
        $skippedplannedpercent = 0;
        if (empty($records)) {
            mtrace('Attendance notify: no users below warning threshold in attendances with at least one session in the last 7 days (and Include in absentee report).');
        }
        foreach ($records as $record) {
            // Build attendance structure and warning context for basismode gating.
            $attrecord = $DB->get_record('attendance', ['id' => $record->aid]);
            if (!$attrecord) {
                continue;
            }
            $cm = get_coursemodule_from_id('attendance', $record->cmid, $record->courseid, false, MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $record->courseid], '*', MUST_EXIST);
            $att = new \mod_attendance_structure($attrecord, $cm, $course, \context_module::instance($record->cmid));
            $warning = $DB->get_record('attendance_warning', ['id' => $record->notifyid]);
            if (!$warning) {
                continue;
            }
            $ctx = attendance_warning_context_from_notify_aggregate($record);
            if (!attendance_warning_basismode_allows_trigger($att, $ctx, $warning)) {
                $skippedbasismode++;
                continue;
            }

            // In planned_sessions/planned_hours mode, use percent vs planned total (e.g. 100h - 20h absent = 80%).
            // Only send when that effective percent is below the threshold; avoid false positives from "taken sessions only" percent.
            $effectivepercent = attendance_warning_effective_percent($ctx);
            if ($ctx->basismode === 'planned_hours') {
                mtrace('Attendance notify debug: aid=' . $record->aid .
                    ', uid=' . $record->userid .
                    ', plannedhours=' . format_float((float)($ctx->plannedtotalhours ?? 0), 1) .
                    ', points=' . format_float((float)$ctx->points, 1) .
                    ', maxpoints=' . format_float((float)$ctx->maxpoints, 1) .
                    ', missinghours=' . format_float((float)$ctx->absent_hours, 1) .
                    ', takensessions=' . (int)$ctx->numtakensessions .
                    ', effectivepercent=' . format_float((float)$effectivepercent, 1));
            }
            if ($effectivepercent >= (float)$warning->warningpercent) {
                $skippedplannedpercent++;
                continue;
            }

            // Use planned-based percent for email and display so the student sees the correct figure (e.g. 80% not 50%).
            $record->effectivepercent = $effectivepercent;
            $record->percent = $effectivepercent / 100;
            $record->attendancepercent = format_float((float)$effectivepercent, 2);
            $record->absencepercent = format_float(attendance_warning_absence_percent_for_display($record), 2);

            if (empty($sentnotifications[$record->userid])) {
                $sentnotifications[$record->userid] = [];
            }
            $didsendrecord = false;

            if (!empty($record->emailuser)) {
                // Only send one warning to this user from each attendance in this run.
                // Flag any higher percent notifications as sent.
                if (empty($sentnotifications[$record->userid]) || !in_array($record->aid, $sentnotifications[$record->userid])) {
                    // If has previously been sent a warning, check to see if this user has
                    // attendance updated since the last time the notification was sent.
                    if (!empty($record->timesent)) {
                        $sql = "SELECT *
                              FROM {attendance_log} l
                              JOIN {attendance_sessions} s ON s.id = l.sessionid
                             WHERE s.attendanceid = ? AND studentid = ? AND timetaken > ?";
                        if (!$DB->record_exists_sql($sql, [$record->aid, $record->userid, $record->timesent])) {
                            $skippedtimesent++;
                            continue; // Skip this record and move to the next user.
                        }
                    }

                    // Convert variables in emailcontent.
                    $record = attendance_template_variables($record);
                    $user = $DB->get_record('user', ['id' => $record->userid]);
                    $from = \core_user::get_noreply_user();
                    $oldforcelang = force_current_language($user->lang);

                    $emailcontent = format_text($record->emailcontent, $record->emailcontentformat);
                    $emailsubject = format_text($record->emailsubject, FORMAT_HTML);
                    email_to_user($user, $from, $emailsubject, $emailcontent, $emailcontent);

                    force_current_language($oldforcelang);
                    $sentnotifications[$record->userid][] = $record->aid;
                    $numsentusers++;
                    $didsendrecord = true;
                }
            } else {
                $skippedemailuseroff++;
            }
            // Only send one warning to this user from each attendance in this run. - flag any higher percent notifications as sent.
            $thirdpartyusers = [];
            if (!empty($record->thirdpartyemails)) {
                $sendto = explode(',', $record->thirdpartyemails);
                // Legacy {$a->percent}: attendance (same basis as warnings). Prefer attendancepercent / absencepercent in lang.
                $record->percent = $record->attendancepercent . '%';
                $context = \context_module::instance($record->cmid);
                foreach ($sendto as $senduser) {
                    if (empty($senduser)) {
                        // Probably an extra comma in the thirdpartyusers field.
                        continue;
                    }
                    // Create array of the warnings this user will recieve in case we need to clean up.
                    $thirdpartyusers[$senduser][] = $record->notifyid;

                    // Check user is allowed to receive warningemails.
                    if (has_capability('mod/attendance:warningemails', $context, $senduser)) {
                        if (empty($thirdpartynotifications[$senduser])) {
                            $thirdpartynotifications[$senduser] = [];
                        }
                        if (!isset($thirdpartynotifications[$senduser][$record->aid . '_' . $record->userid])) {
                            $thirdpartynotifications[$senduser][$record->aid . '_' . $record->userid]
                                = get_string('thirdpartyemailtext', 'attendance', $record);
                            $didsendrecord = true;
                        }
                    } else {
                        mtrace("user" . $senduser . "does not have capablity in cm" . $record->cmid);
                    }
                }
            }
            if ($didsendrecord) {
                $notify = new \stdClass();
                $notify->userid = $record->userid;
                $notify->notifyid = $record->notifyid;
                $notify->timesent = $now;
                $DB->insert_record('attendance_warning_done', $notify);
            }
        }
        if (!empty($numsentusers)) {
            mtrace($numsentusers . " user emails sent");
        }
        if (!empty($thirdpartynotifications)) {
            foreach ($thirdpartynotifications as $sendid => $notifications) {
                $user = $DB->get_record('user', ['id' => $sendid]);
                if (empty($user) || !empty($user->deleted)) {
                    // Clean this user up and remove from the notification list.
                    $warnings = $DB->get_records_list('attendance_warning', 'id', $thirdpartyusers[$sendid]);
                    if (!empty($warnings)) {
                        attendance_remove_user_from_thirdpartyemails($warnings, $sendid);
                    }
                    // Don't send and skip to next notification.
                    continue;
                }

                $from = \core_user::get_noreply_user();
                $oldforcelang = force_current_language($user->lang);

                $emailcontent = implode("\n", $notifications);
                $emailcontent .= "\n\n" . get_string('thirdpartyemailtextfooter', 'attendance');
                $emailcontent = format_text($emailcontent);
                $emailsubject = get_string('thirdpartyemailsubject', 'attendance');

                email_to_user($user, $from, $emailsubject, $emailcontent, $emailcontent);
                force_current_language($oldforcelang);
                $numsentthird++;
            }
            if (!empty($numsentthird)) {
                mtrace($numsentthird . " thirdparty emails sent");
            }
        }
        if (!empty($records) && $numsentusers === 0 && $numsentthird === 0) {
            $reasons = [];
            if ($skippedbasismode > 0) {
                $reasons[] = $skippedbasismode . ' skipped by warning basis (need more sessions/hours taken for planned basis)';
            }
            if ($skippedplannedpercent > 0) {
                $reasons[] = $skippedplannedpercent . ' skipped (planned basis: percent not below threshold)';
            }
            if ($skippedemailuseroff > 0) {
                $reasons[] = $skippedemailuseroff . ' skipped (Email user not enabled for this warning rule)';
            }
            if ($skippedtimesent > 0) {
                $reasons[] = $skippedtimesent . ' skipped (already notified, no new attendance since)';
            }
            mtrace('Attendance notify: had ' . count($records) . ' record(s) but no emails sent. ' .
                (empty($reasons) ? 'Check thirdparty settings.' : implode('; ', $reasons)));
        }
    }
}
