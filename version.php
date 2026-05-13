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
defined('MOODLE_INTERNAL') || die();

$plugin->version  = 2026030501;
$plugin->release = 2026030501;
// Minimum Moodle core (YYYYMMDD). Use a 4.5-era floor so Moodle Workplace 4.5 builds
// that report a slightly lower core version than standard 4.5.0 still pass the check.
$plugin->requires = 2024092700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->cron     = 0;
$plugin->component = 'mod_attendance';
// Omit $plugin->supported: a tight [405, 405] range often blocks install on Moodle Workplace
// and other distributions even when the core branch is 4.5-compatible.
