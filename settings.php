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
 * presence plugin settings
 *
 * @package    mod_presence
 * @copyright  2013 Netspot, Tim Lock.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once(dirname(__FILE__).'/lib.php');
    require_once(dirname(__FILE__).'/locallib.php');
    require_once($CFG->dirroot . '/user/profile/lib.php');

    $tabmenu = presence_print_settings_tabs();

    $settings->add(new admin_setting_heading('presence_header', '', $tabmenu));

    $plugininfos = core_plugin_manager::instance()->get_plugins_of_type('local');

    // Paging options.
    $options = array(
          0 => get_string('donotusepaging', 'presence'),
         25 => 25,
         50 => 50,
         75 => 75,
         100 => 100,
         250 => 250,
         500 => 500,
         1000 => 1000,
    );

    $settings->add(new admin_setting_configselect('presence/resultsperpage',
        get_string('resultsperpage', 'presence'), get_string('resultsperpage_desc', 'presence'), 25, $options));

    $options = array(
        PRESENCE_VIEW_ALL => get_string('all', 'presence'),
        PRESENCE_VIEW_ALLPAST => get_string('allpast', 'presence'),
        PRESENCE_VIEW_ALLFUTURE => get_string('allfuture', 'presence'),
        PRESENCE_VIEW_MONTHS => get_string('months', 'presence'),
        PRESENCE_VIEW_WEEKS => get_string('weeks', 'presence'),
        PRESENCE_VIEW_DAYS => get_string('days', 'presence')
    );

    $settings->add(new admin_setting_configselect('presence/defaultview',
        get_string('defaultview', 'presence'),
            get_string('defaultview_desc', 'presence'), PRESENCE_VIEW_WEEKS, $options));

    $fields = array('id' => get_string('studentid', 'presence'));
    $customfields = profile_get_custom_fields();
    foreach ($customfields as $field) {
        $fields[$field->shortname] = format_string($field->name);
    }

}
