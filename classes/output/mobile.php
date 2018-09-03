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
 * Contains the mobile output class for the attendance
 *
 * @package   mod_attendance
 * @copyright 2018 Dan Marsden
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\output;

defined('MOODLE_INTERNAL') || die();
/**
 * Mobile output class for the attendance.
 *
 * @copyright 2018 Dan Marsden
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * Returns the initial page when viewing the activity for the mobile app.
     *
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and other data
     */
    public static function mobile_view_activity($args) {
        global $OUTPUT, $DB, $USER, $PAGE, $CFG;

        require_once($CFG->dirroot.'/mod/attendance/locallib.php');

        $args = (object) $args;
        $cmid = $args->cmid;
        $courseid = $args->courseid;
        $groupid = empty($args->group) ? 0 : $args->group; // By default, group 0.

        // Capabilities check.
        $cm = get_coursemodule_from_id('attendance', $cmid);

        require_login($courseid, false , $cm, true, true);

        $context = \context_module::instance($cm->id);
        require_capability('mod/attendance:view', $context);

        $attendance    = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);
        $course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

        $pageparams = new \mod_attendance_view_page_params();

        $pageparams->studentid = $USER->id;
        $pageparams->mode = \mod_attendance_view_page_params::MODE_THIS_COURSE;
        $pageparams->view = 5; // Show all sessions for this course?

        $att = new \mod_attendance_structure($attendance, $cm, $course, $context, $pageparams);

        $summary = new \mod_attendance_summary($att->id, array($USER->id), $att->pageparams->startdate,
            $att->pageparams->enddate);
        $data = array('attendance' => $attendance,
                      'summary' => $summary->get_all_sessions_summary_for($USER->id));

        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_attendance/mobile_view_page', $data),
                ],
            ],
            'javascript' => '',
            'otherdata' => ''
        ];
    }
}