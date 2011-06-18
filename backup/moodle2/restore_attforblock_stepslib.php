<?php

/**
 * @package    mod
 * @subpackage attforblock
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define all the restore steps that will be used by the restore_attforblock_activity_task
 */

/**
 * Structure step to restore one attforblock activity
 */
class restore_attforblock_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();

        $userinfo = $this->get_setting_value('userinfo'); // are we including userinfo?

        ////////////////////////////////////////////////////////////////////////
        // XML interesting paths - non-user data
        ////////////////////////////////////////////////////////////////////////

        $paths[] = new restore_path_element('attforblock', '/activity/attforblock');

        $paths[] = new restore_path_element('attforblock_status',
                       '/activity/attforblock/statuses/status');

        $paths[] = new restore_path_element('attforblock_session',
                       '/activity/attforblock/sessions/session');

        // End here if no-user data has been selected
        if (!$userinfo) {
            return $this->prepare_activity_structure($paths);
        }

        ////////////////////////////////////////////////////////////////////////
        // XML interesting paths - user data
        ////////////////////////////////////////////////////////////////////////

        $paths[] = new restore_path_element('attforblock_log',
                       '/activity/attforblock/sessions/session/logs/log');

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_attforblock($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // insert the attforblock record
        $newitemid = $DB->insert_record('attforblock', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
    }

    protected function process_attforblock_status($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->attendanceid = $this->get_new_parentid('attforblock');

        $newitemid = $DB->insert_record('attendance_statuses', $data);
        $this->set_mapping('attforblock_status', $oldid, $newitemid);
    }

    protected function process_attforblock_session($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->attendanceid = $this->get_new_parentid('attforblock');
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->sessdate = $this->apply_date_offset($data->sessdate);
        $data->lasttaken = $this->apply_date_offset($data->lasttaken);
        $data->lasttakenby = $this->get_mappingid('user', $data->lasttakenby);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('attendance_sessions', $data);
        $this->set_mapping('attforblock_session', $oldid, $newitemid, true);
    }

    protected function process_attforblock_log($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->sessionid = $this->get_mappingid('attforblock_session', $data->sessionid);
        $data->studentid = $this->get_mappingid('user', $data->studentid);
        $data->statusid = $this->get_mappingid('attforblock_status', $data->statusid);
        $statusset = explode(',', $data->statusset);
        foreach ($statusset as $st) $st = $this->get_mappingid('attforblock_status', $st);
        $data->statusset = implode(',', $statusset);
        $data->timetaken = $this->apply_date_offset($data->timetaken);
        $data->takenby = $this->get_mappingid('user', $data->takenby);

        $newitemid = $DB->insert_record('attendance_log', $data);
    }

    protected function after_execute() {
        $this->add_related_files('mod_attforblock', 'session', 'attforblock_session');
    }
}
