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

defined('MOODLE_INTERNAL') || die();
require_once(dirname(__FILE__) . '/upgradelib.php');

/**
 * upgrade processes for this module.
 *
 * @package   mod_attendance
 * @copyright 2011 Artem Andreev <andreev.artem@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * upgrade this attendance instance - this function could be skipped but it will be needed later
 * @param int $oldversion The old version of the attendance module
 * @return bool
 */
function xmldb_attendance_upgrade($oldversion=0) {

    global $DB;
    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    $result = true;

    if ($oldversion < 2014112000) {
        $table = new xmldb_table('attendance_sessions');

        $field = new xmldb_field('studentscanmark');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2014112000, 'attendance');
    }

    if ($oldversion < 2014112001) {
        // Replace values that reference old module "attforblock" to "attendance".
        $sql = "UPDATE {grade_items}
                   SET itemmodule = 'attendance'
                 WHERE itemmodule = 'attforblock'";

        $DB->execute($sql);

        $sql = "UPDATE {grade_items_history}
                   SET itemmodule = 'attendance'
                 WHERE itemmodule = 'attforblock'";

        $DB->execute($sql);

        /*
         * The user's custom capabilities need to be preserved due to the module renaming.
         * Capabilities with a modifierid = 0 value are installed by default.
         * Only update the user's custom capabilities where modifierid is not zero.
         */
        $sql = $DB->sql_like('capability', '?').' AND modifierid <> 0';
        $rs = $DB->get_recordset_select('role_capabilities', $sql, array('%mod/attforblock%'));
        foreach ($rs as $cap) {
            $renamedcapability = str_replace('mod/attforblock', 'mod/attendance', $cap->capability);
            $exists = $DB->record_exists('role_capabilities', array('roleid' => $cap->roleid, 'capability' => $renamedcapability));
            if (!$exists) {
                $DB->update_record('role_capabilities', array('id' => $cap->id, 'capability' => $renamedcapability));
            }
        }

        // Delete old role capabilities.
        $sql = $DB->sql_like('capability', '?');
        $DB->delete_records_select('role_capabilities', $sql, array('%mod/attforblock%'));

        // Delete old capabilities.
        $DB->delete_records_select('capabilities', 'component = ?', array('mod_attforblock'));

        upgrade_mod_savepoint(true, 2014112001, 'attendance');
    }

    if ($oldversion < 2015040501) {
        // Define table attendance_tempusers to be created.
        $table = new xmldb_table('attendance_tempusers');

        // Adding fields to table attendance_tempusers.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('fullname', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('email', XMLDB_TYPE_CHAR, '100', null, null, null, null);
        $table->add_field('created', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys to table attendance_tempusers.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for attendance_tempusers.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Conditionally launch add index courseid.
        $index = new xmldb_index('courseid', XMLDB_INDEX_NOTUNIQUE, array('courseid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Conditionally launch add index studentid.
        $index = new xmldb_index('studentid', XMLDB_INDEX_UNIQUE, array('studentid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Attendance savepoint reached.
        upgrade_mod_savepoint(true, 2015040501, 'attendance');
    }

    if ($oldversion < 2015040502) {

        // Define field setnumber to be added to attendance_statuses.
        $table = new xmldb_table('attendance_statuses');
        $field = new xmldb_field('setnumber', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0', 'deleted');

        // Conditionally launch add field setnumber.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field statusset to be added to attendance_sessions.
        $table = new xmldb_table('attendance_sessions');
        $field = new xmldb_field('statusset', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0', 'descriptionformat');

        // Conditionally launch add field statusset.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Attendance savepoint reached.
        upgrade_mod_savepoint(true, 2015040502, 'attendance');
    }

    if ($oldversion < 2015040503) {

        // Changing type of field grade on table attendance_statuses to number.
        $table = new xmldb_table('attendance_statuses');
        $field = new xmldb_field('grade', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, '0', 'description');

        // Launch change of type for field grade.
        $dbman->change_field_type($table, $field);

        // Attendance savepoint reached.
        upgrade_mod_savepoint(true, 2015040503, 'attendance');
    }

    if ($oldversion < 2016052202) {
        // Adding field to store calendar event ids.
        $table = new xmldb_table('attendance_sessions');
        $field = new xmldb_field('caleventid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', null);

        // Conditionally launch add field statusset.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Creating events for all existing sessions.
        attendance_upgrade_create_calendar_events();

        // Attendance savepoint reached.
        upgrade_mod_savepoint(true, 2016052202, 'attendance');
    }

    if ($oldversion < 2016082900) {

        // Define field timemodified to be added to attendance.
        $table = new xmldb_table('attendance');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'grade');

        // Conditionally launch add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Attendance savepoint reached.
        upgrade_mod_savepoint(true, 2016082900, 'attendance');
    }
    if ($oldversion < 2016112100) {
        $table = new xmldb_table('attendance');
        $newfield = $table->add_field('subnet', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'timemodified');
        if (!$dbman->field_exists($table, $newfield)) {
            $dbman->add_field($table, $newfield);
        }
        upgrade_mod_savepoint(true, 2016112100, 'attendance');
    }

    if ($oldversion < 2016121300) {
        $table = new xmldb_table('attendance');
        $field = new xmldb_field('sessiondetailspos', XMLDB_TYPE_CHAR, '5', null, null, null, 'left', 'subnet');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('showsessiondetails', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'subnet');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2016121300, 'attendance');
    }

    if ($oldversion < 2016121305) {
        // Define field timemodified to be added to attendance.
        $table = new xmldb_table('attendance');

        $fields = [];
        $fields[] = new xmldb_field('intro', XMLDB_TYPE_TEXT, null, null, null, null, null, 'timemodified');
        $fields[] = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, 0, 'intro');

        // Conditionally launch add field.
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Attendance savepoint reached.
        upgrade_mod_savepoint(true, 2016121305, 'attendance');
    }

    return $result;
}
