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
 * Attendance plugin settings
 *
 * @package    mod_attendance
 * @copyright  2013 Netspot, Tim Lock.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once(dirname(__FILE__).'/lib.php');
    require_once(dirname(__FILE__).'/locallib.php');
    require_once($CFG->dirroot . '/user/profile/lib.php');

    $tabmenu = attendance_print_settings_tabs();

    $settings->add(new admin_setting_heading('attendance_header', '', $tabmenu));

    $plugininfos = core_plugin_manager::instance()->get_plugins_of_type('local');

    // Paging options.
    $options = array(
          0 => get_string('donotusepaging', 'attendance'),
         25 => 25,
         50 => 50,
         75 => 75,
         100 => 100,
         250 => 250,
         500 => 500,
         1000 => 1000,
    );

    $settings->add(new admin_setting_configselect('attendance/resultsperpage',
        get_string('resultsperpage', 'attendance'), get_string('resultsperpage_desc', 'attendance'), 25, $options));

    $options = array(
        ATT_VIEW_ALL => get_string('all', 'attendance'),
        ATT_VIEW_ALLPAST => get_string('allpast', 'attendance'),
        ATT_VIEW_NOTPRESENT => get_string('below', 'attendance', 'X'),
        ATT_VIEW_MONTHS => get_string('months', 'attendance'),
        ATT_VIEW_WEEKS => get_string('weeks', 'attendance'),
        ATT_VIEW_DAYS => get_string('days', 'attendance')
    );

    $settings->add(new admin_setting_configselect('attendance/defaultview',
        get_string('defaultview', 'attendance'),
            get_string('defaultview_desc', 'attendance'), ATT_VIEW_WEEKS, $options));

    $settings->add(new admin_setting_configcheckbox('attendance/multisessionexpanded',
        get_string('multisessionexpanded', 'attendance'),
        get_string('multisessionexpanded_desc', 'attendance'), 0));

    $settings->add(new admin_setting_configcheckbox('attendance/showsessiondescriptiononreport',
        get_string('showsessiondescriptiononreport', 'attendance'),
        get_string('showsessiondescriptiononreport_desc', 'attendance'), 0));

    $settings->add(new admin_setting_configcheckbox('attendance/studentrecordingexpanded',
        get_string('studentrecordingexpanded', 'attendance'),
        get_string('studentrecordingexpanded_desc', 'attendance'), 1));

    $settings->add(new admin_setting_configcheckbox('attendance/enablecalendar',
        get_string('enablecalendar', 'attendance'),
        get_string('enablecalendar_desc', 'attendance'), 1));



    $fields = array('id' => get_string('studentid', 'attendance'));
    $customfields = profile_get_custom_fields();
    foreach ($customfields as $field) {
        $fields[$field->shortname] = format_string($field->name);
    }

    $settings->add(new admin_setting_configmultiselect('attendance/customexportfields',
            new lang_string('customexportfields', 'attendance'),
            new lang_string('customexportfields_help', 'attendance'),
            array('id'), $fields)
    );

    $name = new lang_string('mobilesettings', 'mod_attendance');
    $description = new lang_string('mobilesettings_help', 'mod_attendance');
    $settings->add(new admin_setting_heading('mobilesettings', $name, $description));

    $settings->add(new admin_setting_configduration('attendance/mobilesessionfrom',
        get_string('mobilesessionfrom', 'attendance'), get_string('mobilesessionfrom_help', 'attendance'),
         6 * HOURSECS, PARAM_RAW));

    $settings->add(new admin_setting_configduration('attendance/mobilesessionto',
        get_string('mobilesessionto', 'attendance'), get_string('mobilesessionto_help', 'attendance'),
        24 * HOURSECS, PARAM_RAW));

    $name = new lang_string('defaultsettings', 'mod_attendance');
    $description = new lang_string('defaultsettings_help', 'mod_attendance');
    $settings->add(new admin_setting_heading('defaultsettings', $name, $description));


    $name = new lang_string('defaultsessionsettings', 'mod_attendance');
    $description = new lang_string('defaultsessionsettings_help', 'mod_attendance');
    $settings->add(new admin_setting_heading('defaultsessionsettings', $name, $description));

    $settings->add(new admin_setting_configcheckbox('attendance/calendarevent_default',
        get_string('calendarevent', 'attendance'), '', 1));


}
