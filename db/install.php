<?php

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
		unset($rec);
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
