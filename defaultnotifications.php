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
 * Allows default notifications to be modified.
 *
 * @package   mod_attendance
 * @copyright 2017 Dan Marsden http://danmarsden.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/mod/attendance/lib.php');
require_once($CFG->dirroot.'/mod/attendance/locallib.php');

$action = optional_param('action', '', PARAM_ALPHA);
$notid = optional_param('notid', 0, PARAM_INT);

admin_externalpage_setup('managemodules');
$url = new moodle_url('/mod/attendance/defaultnotifications.php');

$output = $PAGE->get_renderer('mod_attendance');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('defaultnotifications', 'mod_attendance'));
$tabmenu = attendance_print_settings_tabs('defaultnotifications');
echo $tabmenu;

$mform = new mod_attendance_add_notification_form($url, array('notid' => $notid));

if ($data = $mform->get_data()) {
    if (empty($data->notid)) {
        // Insert new record.
        $notify = new stdClass();
        $notify->notifylevel = 0;
        $notify->idnumber = 0;
        $notify->warningpercent = $data->warningpercent;
        $notify->warnafter = $data->warnafter;
        $notify->emailuser = $data->emailuser;
        $notify->emailsubject = $data->emailsubject;
        $notify->emailcontent = $data->emailcontent['text'];
        $notify->emailcontentformat = $data->emailcontent['format'];
        $DB->insert_record('attendance_notification', $notify);
        echo $OUTPUT->notification(get_string('notificationupdated', 'mod_attendance'), 'success');

    } else {
        $notify = new stdClass();
        $notify->id = $data->notid;
        $notify->notifylevel = $data->notifylevel;
        $notify->idnumber = $data->idnumber;
        $notify->warningpercent = $data->warningpercent;
        $notify->warnafter = $data->warnafter;
        $notify->emailuser = $data->emailuser;
        $notify->emailsubject = $data->emailsubject;
        $notify->emailcontentformat = $data->emailcontent['format'];
        $notify->emailcontent = $data->emailcontent['text'];
        $DB->update_record('attendance_notification', $notify);
        echo $OUTPUT->notification(get_string('notificationupdated', 'mod_attendance'), 'success');
    }
}
if ($action == 'delete' && !empty($notid)) {
    if (!optional_param('confirm', false, PARAM_BOOL)) {
        $cancelurl = $url;
        $url->params(array('action' => 'delete', 'notid' => $notid, 'sesskey' => sesskey(), 'confirm' => true));
        echo $OUTPUT->confirm(get_string('deletenotificationconfirm', 'mod_attendance'), $url, $cancelurl);
        echo $OUTPUT->footer();
        exit;
    } else {
        require_sesskey();
        $DB->delete_records('attendance_notification', array('id' => $notid));
        echo $OUTPUT->notification(get_string('notificationdeleted', 'mod_attendance'), 'success');
    }
}
if ($action == 'update' && !empty($notid)) {
    $existing = $DB->get_record('attendance_notification', array('id' => $notid));
    $content = $existing->emailcontent;
    $existing->emailcontent = array();
    $existing->emailcontent['text'] = $content;
    $existing->emailcontent['format'] = $existing->emailcontentformat;
    $existing->notid = $existing->id;
    $mform->set_data($existing);
    $mform->display();
} else if ($action == 'add' && confirm_sesskey()) {
    $mform->display();
} else {
    echo $OUTPUT->box(get_string('notificationdesc', 'mod_attendance'), 'generalbox', 'notice');
    $existingnotifications = $DB->get_records('attendance_notification',
        array('notifylevel' => ATTENDANCE_NOTIFYLEVEL_ATTENDANCE, 'idnumber' => 0),
        'warningpercent');
    if (!empty($existingnotifications)) {
        $table = new html_table();
        $table->head = array(get_string('percentage', 'mod_attendance'),
            get_string('numsessions', 'mod_attendance'),
            get_string('emailsubject', 'mod_attendance'),
            '');
        foreach ($existingnotifications as $notification) {
            $url->params(array('action' => 'delete', 'notid' => $notification->id));
            $actionbuttons = $OUTPUT->action_icon($url, new pix_icon('t/delete',
                get_string('delete', 'attendance')), null, null);
            $url->params(array('action' => 'update', 'notid' => $notification->id));
            $actionbuttons .= $OUTPUT->action_icon($url, new pix_icon('t/edit',
                get_string('update', 'attendance')), null, null);
            $table->data[] = array($notification->warningpercent, $notification->warnafter, $notification->emailsubject, $actionbuttons);
        }
        echo html_writer::table($table);
    }
    $addurl = new moodle_url('/mod/attendance/defaultnotifications.php', array('action' => 'add'));
    echo $OUTPUT->single_button($addurl, get_string('addnotification', 'mod_attendance'));

}

echo $OUTPUT->footer();