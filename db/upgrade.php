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

// This file keeps track of upgrades to
// the attendance module.

function xmldb_attendance_upgrade($oldversion=0) {

    global $CFG, $THEME, $DB;
    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    $result = true;

    // UPGRADES from attforblock are only supported for sites that are running attforblock version 2012120700
    return $result;
}
