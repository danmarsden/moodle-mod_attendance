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
 * This file replaces the legacy STATEMENTS section in db/install.xml,
 * lib.php/modulename_install() post installation hook and partially defaults.php
 *
 * @package    mod
 * @subpackage attforblock
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Post installation procedure
 */
function xmldb_attforblock_install() {
    global $DB;

	$result = true;
	$arr = array('P' => 2, 'A' => 0, 'L' => 1, 'E' => 1);
	foreach ($arr as $k => $v) {
		$rec = new stdClass;
		$rec->attendanceid = 0;
		$rec->acronym = get_string($k.'acronym', 'attforblock');
		$rec->description = get_string($k.'full', 'attforblock');
		$rec->grade = $v;
		$rec->visible = 1;
		$rec->deleted = 0;
		$result = $result && $DB->insert_record('attendance_statuses', $rec);
	}

	return $result;
}
