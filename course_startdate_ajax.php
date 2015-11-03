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
 * Return the course start date
 *
 * @package    mod_attendance
 * @copyright  2015 <antonio.c.mariani@ufsc.br>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(dirname(dirname(__DIR__)) . '/config.php');

$id        = required_param('courseid', PARAM_INT);
$course    = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_login($course);

$cstartdate = userdate($course->startdate, get_string('strftimedmy', 'attendance'));
$result = array('courseid' => $course->id,
                'coursestartdate' => $course->startdate,
                'confirmmessage' => get_string('confirmaddmsg', 'attendance', $cstartdate));
echo json_encode($result);
die;
