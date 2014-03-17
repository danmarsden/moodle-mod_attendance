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
 * Manage attendance settings
 *
 * @package    mod_attendance
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$pageparams = new att_preferences_page_params();

$id                         = required_param('id', PARAM_INT);
$pageparams->action         = optional_param('action', null, PARAM_INT);
$pageparams->statusid       = optional_param('statusid', null, PARAM_INT);

$cm             = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att            = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$att = new attendance($att, $cm, $course, $PAGE->context, $pageparams);

$att->perm->require_change_preferences_capability();

$PAGE->set_url($att->url_preferences());
$PAGE->set_title($course->shortname. ": ".$att->name.' - '.get_string('settings', 'attendance'));
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->set_button($OUTPUT->update_module_button($cm->id, 'attendance'));
$PAGE->navbar->add(get_string('settings', 'attendance'));

switch ($att->pageparams->action) {
    case att_preferences_page_params::ACTION_ADD:
        $newacronym         = optional_param('newacronym', null, PARAM_TEXT);
        $newdescription     = optional_param('newdescription', null, PARAM_TEXT);
        $newgrade           = optional_param('newgrade', 0, PARAM_INT);

        $att->add_status($newacronym, $newdescription, $newgrade);
        break;
    case att_preferences_page_params::ACTION_DELETE:
        if (att_has_logs_for_status($att->pageparams->statusid)) {
            print_error('cantdeletestatus', 'attendance', "attsettings.php?id=$id");
        }

        $confirm    = optional_param('confirm', null, PARAM_INT);
        if (isset($confirm)) {
            $att->remove_status($att->pageparams->statusid);
            redirect($att->url_preferences(), get_string('statusdeleted', 'attendance'));
        }

        $statuses = $att->get_statuses();
        $status = $statuses[$att->pageparams->statusid];
        $message = get_string('deletecheckfull', '', get_string('variable', 'attendance'));
        $message .= str_repeat(html_writer::empty_tag('br'), 2);
        $message .= $status->acronym.': '.
                    ($status->description ? $status->description : get_string('nodescription', 'attendance'));
        $params = array_merge($att->pageparams->get_significant_params(), array('confirm' => 1));
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('attendanceforthecourse', 'attendance').' :: ' .$course->fullname);
        echo $OUTPUT->confirm($message, $att->url_preferences($params), $att->url_preferences());
        echo $OUTPUT->footer();
        exit;
    case att_preferences_page_params::ACTION_HIDE:
        $att->update_status($att->pageparams->statusid, null, null, null, 0);
        break;
    case att_preferences_page_params::ACTION_SHOW:
        $att->update_status($att->pageparams->statusid, null, null, null, 1);
        break;
    case att_preferences_page_params::ACTION_SAVE:
        $acronym        = required_param_array('acronym', PARAM_MULTILANG);
        $description    = required_param_array('description', PARAM_MULTILANG);
        $grade          = required_param_array('grade', PARAM_INT);

        foreach ($acronym as $id => $v) {
            $att->update_status($id, $acronym[$id], $description[$id], $grade[$id], null);
        }
        att_update_all_users_grades($att->id, $att->course, $att->context, $cm);
        break;
}

$output = $PAGE->get_renderer('mod_attendance');
$tabs = new attendance_tabs($att, attendance_tabs::TAB_PREFERENCES);
$prefdata = new attendance_preferences_data($att);

// Output starts here.

echo $output->header();
echo $output->heading(get_string('attendanceforthecourse', 'attendance').' :: ' .$course->fullname);
echo $output->render($tabs);
echo $output->render($prefdata);

echo $output->footer();
