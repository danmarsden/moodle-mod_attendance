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
 * External attendance API
 *
 * @package    mod_attendance
 * @copyright  2015 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

/**
 * Attendance functions
 * @copyright 2015 Daniel Neis Araujo <danielneis@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_attendance_external extends external_api {

    /**
     * Describes the parameters for take_attendance
     *
     */
    public static function take_attendance_parameters() {
        return new external_function_parameters(
            array(
                'users' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'userid'           => new external_value(PARAM_INT, 'student id'),
                            'statusid'           => new external_value(PARAM_INT, 'status id'),
                            'remarks'           => new external_value(PARAM_TEXT, 'remarks'),
                        )
                    )
                ),
                'sessionid' => new external_value(PARAM_INT, 'the session id'),
                'takenby'   => new external_value(PARAM_INT, 'taken by user id'),
                'grouptype' => new external_value(PARAM_INT, 'grouptype', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * This function allows to register (add/update) attendance logs for a given sessionid.
     *
     */
    public static function take_attendance($users, $sessionid, $takenby, $grouptype = 0) {
        global $DB, $CFG;

        $params = self::validate_parameters(self::take_attendance_parameters(),
                                            array('sessionid' => $sessionid,
                                                  'takenby' => $takenby,
                                                  'users' => $users,
                                                  'grouptype' => $grouptype));

        $id = $DB->get_field('attendance_sessions', 'attendanceid', array('id' => $sessionid), MUST_EXIST);
        $cm = get_coursemodule_from_instance('attendance', $id, 0, false, MUST_EXIST);

        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $att    = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);

        require_once($CFG->dirroot.'/mod/attendance/locallib.php');
        $pageparams = new att_take_page_params();

        $pageparams->id = $id;
        $pageparams->sessionid = $sessionid;
        $pageparams->grouptype = $grouptype;
        $pageparams->group = groups_get_activity_group($cm, true);

        $pageparams->init($course->id);
        $att = new attendance($att, $cm, $course, null, $pageparams);

        if (!$att->perm->can_take_session($pageparams->grouptype, $takenby)) {
            $group = groups_get_group($pageparams->grouptype);
            throw new moodle_exception('cannottakeforgroup', 'attendance', '', $pageparams->grouptype);
        }
        $formdata = new stdclass();
        $formdata->users = $users;
        $formdata->sessionid = $sessionid;
        $att->take_attendance($formdata, $takenby);

        return $sessionid;
    }

    public static function take_attendance_returns() {
        return new external_value(PARAM_INT, 'session id');
    }

    /**
     * Describes the parameters for add_sessions
     *
     */
    public static function add_sessions_parameters() {
        return new external_function_parameters(
            array(
                'sessions' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'sessdate'           => new external_value(PARAM_INT, 'session date'),
                            'duration'           => new external_value(PARAM_INT, 'duration'),
                            'descriptionitemid'  => new external_value(PARAM_INT, 'description item id'),
                            'description'        => new external_value(PARAM_TEXT, 'description'),
                            'descriptionformat'  => new external_value(PARAM_TEXT, 'description format'),
                            'timemodified'       => new external_value(PARAM_INT, 'time modified'),
                            'groupid'            => new external_value(PARAM_INT, 'group id'),
                        )
                    )
                ),
                'coursemoduleid' => new external_value(PARAM_INT, 'course module id'),
            )
        );
    }

    public static function add_sessions($sessions, $coursemoduleid) {

        $params = self::validate_parameters(self::add_sessions_parameters(),
                                            array('sessions' => $sessions,
                                                  'coursemoduleid' => $coursemoduleid,
                                                  ));

        $cm     = get_coursemodule_from_id('attendance', $coursemoduleid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $att    = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);

        $pageparams = new att_take_page_params();
        $pageparams->id = $id;
        $pageparams->init($course->id);

        $att = new attendance($att, $cm, $course, null, $pageparams);

        $att->add_sessions($params->sessions);
    }

    public static function add_sessions_returns() {
        return new external_value(PARAM_INT, 0);
    }
}
