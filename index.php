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

/// This page lists all the instances of attforblock in a particular course
/// Replace attforblock with the name of your module

    require_once('../../config.php');

    $id = required_param('id', PARAM_INT);                 // Course id

    if (! $course = $DB->get_record('course', array('id'=> $id))) {
        error('Course ID is incorrect');
    }

	if ($att = array_pop(get_all_instances_in_course('attforblock', $course, NULL, true))) {
    	redirect("view.php?id=$att->coursemodule");
	} else {
		print_error('notfound', 'attforblock');
	}
