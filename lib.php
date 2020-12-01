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
 * Library of functions and constants for module presence
 *
 * @package   mod_presence
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(dirname(__FILE__) . '/classes/calendar_helpers.php');

/**
 * Returns the information if the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function presence_supports($feature) {
    switch($feature) {
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        // Artem Andreev: AFAIK it's not tested.
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        default:
            return null;
    }
}


/**
 * Add new presence instance.
 *
 * @param stdClass $presence
 * @return bool|int
 */
function presence_add_instance($presence) {
    global $DB;

    $presence->timemodified = time();

    // Default grade (similar to what db fields defaults if no grade attribute is passed),
    // but we need it in object for grading update.
    if (!isset($presence->grade)) {
        $presence->grade = 100;
    }
    $presence->name = get_string('modulename', 'presence');
    $presence->id = $DB->insert_record('presence', $presence);

    return $presence->id;
}

/**
 * Update existing presence instance.
 *
 * @param stdClass $presence
 * @return bool
 */
function presence_update_instance($presence) {
    global $DB;

    $presence->timemodified = time();
    $presence->id = $presence->instance;

    if (! $DB->update_record('presence', $presence)) {
        return false;
    }

    presence_grade_item_update($presence);

    return true;
}

/**
 * Delete existing presence
 *
 * @param int $id
 * @return bool
 */
function presence_delete_instance($id) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/mod/presence/locallib.php');

    if (! $presence = $DB->get_record('presence', array('id' => $id))) {
        return false;
    }

    if ($sessids = array_keys($DB->get_records('presence_sessions', array('presenceid' => $id), '', 'id'))) {
        if (presence_existing_calendar_events_ids($sessids)) {
            presence_delete_calendar_events($sessids);
        }
        $DB->delete_records_list('presence_evaluations', 'sessionid', $sessids);
        $DB->delete_records('presence_sessions', array('presenceid' => $id));
    }
    $DB->delete_records('presence_statuses', array('presenceid' => $id));

    $DB->delete_records('presence_warning', array('idnumber' => $id));

    $DB->delete_records('presence', array('id' => $id));

    presence_grade_item_delete($presence);

    return true;
}

/**
 * Called by course/reset.php
 * @param moodleform $mform form passed by reference
 */
function presence_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'presenceheader', get_string('modulename', 'presence'));

    $mform->addElement('static', 'description', get_string('description', 'presence'),
                                get_string('resetdescription', 'presence'));
    $mform->addElement('checkbox', 'reset_presence_log', get_string('deletelogs', 'presence'));

    $mform->addElement('checkbox', 'reset_presence_sessions', get_string('deletesessions', 'presence'));
    $mform->disabledIf('reset_presence_sessions', 'reset_presence_log', 'notchecked');

}

/**
 * Course reset form defaults.
 *
 * @param stdClass $course
 * @return array
 */
function presence_reset_course_form_defaults($course) {
    return array('reset_presence_log' => 0, 'reset_presence_statuses' => 0, 'reset_presence_sessions' => 0);
}

/**
 * Reset user data within presence.
 *
 * @param stdClass $data
 * @return array
 */
function presence_reset_userdata($data) {
    global $DB;

    $status = array();

    $presenceids = array_keys($DB->get_records('presence', array('course' => $data->courseid), '', 'id'));

    if (!empty($data->reset_presence_log)) {
        $sess = $DB->get_records_list('presence_sessions', 'presenceid', $presenceids, '', 'id');
        if (!empty($sess)) {
            list($sql, $params) = $DB->get_in_or_equal(array_keys($sess));
            $DB->delete_records_select('presence_evaluations', "sessionid $sql", $params);
            list($sql, $params) = $DB->get_in_or_equal($presenceids);
            $DB->set_field_select('presence_sessions', 'lasttaken', 0, "presenceid $sql", $params);
            if (empty($data->reset_presence_sessions)) {
                // If sessions are being retained, clear automarkcompleted value.
                $DB->set_field_select('presence_sessions', 'automarkcompleted', 0, "presenceid $sql", $params);
            }

            $status[] = array(
                'component' => get_string('modulenameplural', 'presence'),
                'item' => get_string('presencedata', 'presence'),
                'error' => false
            );
        }
    }


    if (!empty($data->reset_presence_sessions)) {
        $sessionsids = array_keys($DB->get_records_list('presence_sessions', 'presenceid', $presenceids, '', 'id'));
        if (presence_existing_calendar_events_ids($sessionsids)) {
            presence_delete_calendar_events($sessionsids);
        }
        $DB->delete_records_list('presence_sessions', 'presenceid', $presenceids);

        $status[] = array(
            'component' => get_string('modulenameplural', 'presence'),
            'item' => get_string('statuses', 'presence'),
            'error' => false
        );
    }

    return $status;
}
/**
 * Return a small object with summary information about what a
 *  user has done with a given particular instance of this module
 *  Used for user activity reports.
 *  $return->time = the time they did it
 *  $return->info = a short text description
 *
 * @param stdClass $course - full course record.
 * @param stdClass $user - full user record
 * @param stdClass $mod
 * @param stdClass $presence
 * @return stdClass.
 */
function presence_user_outline($course, $user, $mod, $presence) {
    global $CFG;
    require_once(dirname(__FILE__).'/locallib.php');
    require_once($CFG->libdir.'/gradelib.php');

    $grades = grade_get_grades($course->id, 'mod', 'presence', $presence->id, $user->id);

    $result = new stdClass();
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        $result->time = $grade->dategraded;
    } else {
        $result->time = 0;
    }
    if (has_capability('mod/presence:canbelisted', $mod->context, $user->id)) {
        $summary = new mod_presence_summary($presence->id, $user->id);
        $usersummary = $summary->get_all_sessions_summary_for($user->id);

        $result->info = $usersummary->pointsallsessions;
    }

    return $result;
}
/**
 * Print a detailed representation of what a  user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $presence
 */
function presence_user_complete($course, $user, $mod, $presence) {
    global $CFG;

    require_once(dirname(__FILE__).'/renderhelpers.php');
    require_once($CFG->libdir.'/gradelib.php');

    if (has_capability('mod/presence:canbelisted', $mod->context, $user->id)) {
        echo construct_full_user_stat_html_table($presence, $user);
    }
}

/**
 * Dummy function - must exist to allow quick editing of module name.
 *
 * @param stdClass $presence
 * @param int $userid
 * @param bool $nullifnone
 */
function presence_update_grades($presence, $userid=0, $nullifnone=true) {
    // We need this function to exist so that quick editing of module name is passed to gradebook.
}
/**
 * Create grade item for given presence
 *
 * @param stdClass $presence object with extra cmidnumber
 * @param mixed $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function presence_grade_item_update($presence, $grades=null) {
    global $CFG, $DB;

    require_once('locallib.php');

    if (!function_exists('grade_update')) { // Workaround for buggy PHP versions.
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (!isset($presence->courseid)) {
        $presence->courseid = $presence->course;
    }
    if (!$DB->get_record('course', array('id' => $presence->course))) {
        error("Course is misconfigured");
    }

    if (!empty($presence->cmidnumber)) {
        $params = array('itemname' => $presence->name, 'idnumber' => $presence->cmidnumber);
    } else {
        // MDL-14303.
        $params = array('itemname' => $presence->name);
    }

    if ($presence->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $presence->grade;
        $params['grademin']  = 0;
    } else if ($presence->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$presence->grade;

    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/presence', $presence->courseid, 'mod', 'presence', $presence->id, 0, $grades, $params);
}

/**
 * Delete grade item for given presence
 *
 * @param object $presence object
 * @return object presence
 */
function presence_grade_item_delete($presence) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if (!isset($presence->courseid)) {
        $presence->courseid = $presence->course;
    }

    return grade_update('mod/presence', $presence->courseid, 'mod', 'presence',
                        $presence->id, 0, null, array('deleted' => 1));
}

/**
 * This function returns if a scale is being used by one presence
 * it it has support for grading and scales. Commented code should be
 * modified if necessary. See book, glossary or journal modules
 * as reference.
 *
 * @param int $presenceid
 * @param int $scaleid
 * @return boolean True if the scale is used by any presence
 */
function presence_scale_used ($presenceid, $scaleid) {
    return false;
}

/**
 * Checks if scale is being used by any instance of presence
 *
 * This is used to find out if scale used anywhere
 *
 * @param int $scaleid
 * @return bool true if the scale is used by any book
 */
function presence_scale_used_anywhere($scaleid) {
    return false;
}

/**
 * Serves the presence sessions descriptions files.
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 */
function presence_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);

    if (!$DB->record_exists('presence', array('id' => $cm->instance))) {
        return false;
    }

    // Session area is served by pluginfile.php.
    $fileareas = array('session');
    if (!in_array($filearea, $fileareas)) {
        return false;
    }

    $sessid = (int)array_shift($args);
    if (!$DB->record_exists('presence_sessions', array('id' => $sessid))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_presence/$filearea/$sessid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true);
}

/**
 * Print tabs on presence settings page.
 *
 * @param string $selected - current selected tab.
 */
function presence_print_settings_tabs($selected = 'settings') {
    global $CFG;
    // Print tabs for different settings pages.
    $tabs = array();
    $tabs[] = new tabobject('settings', "{$CFG->wwwroot}/{$CFG->admin}/settings.php?section=modsettingpresence",
        get_string('settings', 'presence'), get_string('settings'), false);

    $tabs[] = new tabobject('rooms', $CFG->wwwroot.'/mod/presence/rooms.php',
        get_string('rooms', 'presence'), get_string('rooms', 'presence'), false);

    ob_start();
    print_tabs(array($tabs), $selected);
    $tabmenu = ob_get_contents();
    ob_end_clean();

    return $tabmenu;
}

/**
 * Helper function to remove a user from the thirdpartyemails record of the presence_warning table.
 *
 * @param array $warnings - list of warnings to parse.
 * @param int $userid - User id of user to remove.
 */
function presence_remove_user_from_thirdpartyemails($warnings, $userid) {
    global $DB;

    // Update the third party emails list for all the relevant warnings.
    $updatedwarnings = array_map(
        function(stdClass $warning) use ($userid) : stdClass {
            $warning->thirdpartyemails = implode(',', array_diff(explode(',', $warning->thirdpartyemails), [$userid]));
            return $warning;
        },
        array_filter(
            $warnings,
            function (stdClass $warning) use ($userid) : bool {
                return in_array($userid, explode(',', $warning->thirdpartyemails));
            }
        )
    );

    // Sadly need to update each individually, no way to bulk update as all the thirdpartyemails field can be different.
    foreach ($updatedwarnings as $updatedwarning) {
        $DB->update_record('presence_warning', $updatedwarning);
    }
}