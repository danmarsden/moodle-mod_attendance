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
 * Version information
 *
 * @package    mod_attendance
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$module->version  = 2014022800;
$module->requires = 2013040500;
$module->release = '2.6.0';
$module->maturity  = MATURITY_STABLE;
$module->cron     = 0;
$module->component = 'mod_attendance';

// Nasty upgrade code to check if need to upgrade from attforblock.
// TODO: remove this asap.
if (defined('MOODLE_INTERNAL')) { // Only run if config.php has already been included.
    global $DB;
    $moduleexists = false;

    try {
        $moduleexists = $DB->record_exists('modules', array('name' =>'attforblock'));
    } catch (Exception $e) {
        // Probably a fresh install - modules table doesn't exist
    }
    if ($moduleexists) {
        require_once('locallib.php');
        attforblock_upgrade();
    }

}
