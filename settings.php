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

    $settings->add(new admin_setting_configcheckbox('attendance/studentscanmark',
        get_string('studentscanmark', 'attendance'), get_string('studentscanmark_desc', 'attendance'), 1));
}
