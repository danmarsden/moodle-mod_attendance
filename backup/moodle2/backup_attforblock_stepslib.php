<?php

/**
 * Defines all the backup steps that will be used by {@link backup_attforblock_activity_task}
 *
 * @package    mod
 * @subpackage attforblock
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the complete attforblock structure for backup, with file and id annotations
 */
class backup_attforblock_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // are we including userinfo?
        $userinfo = $this->get_setting_value('userinfo');

        ////////////////////////////////////////////////////////////////////////
        // XML nodes declaration - non-user data
        ////////////////////////////////////////////////////////////////////////

        $attforblock = new backup_nested_element('attforblock', array('id'), array(
            'name', 'grade'));

        $statuses = new backup_nested_element('statuses');
        $status  = new backup_nested_element('status', array('id'), array(
            'acronym', 'description', 'grade', 'visible', 'deleted'));

        $sessions = new backup_nested_element('sessions');
        $session  = new backup_nested_element('session', array('id'), array(
            'groupid', 'sessdate', 'duration', 'lasttaken', 'lasttakenby',
            'timemodified', 'description', 'descriptionformat'));

        ////////////////////////////////////////////////////////////////////////
        // XML nodes declaration - user data
        ////////////////////////////////////////////////////////////////////////

        $logs = new backup_nested_element('logs');
        $log  = new backup_nested_element('log', array('id'), array(
            'sessionid', 'studentid', 'statusid', 'lasttaken', 'statusset',
            'timetaken', 'takenby', 'remarks'));

        ////////////////////////////////////////////////////////////////////////
        // build the tree in the order needed for restore
        ////////////////////////////////////////////////////////////////////////
        $attforblock->add_child($statuses);
        $statuses->add_child($status);

        $attforblock->add_child($sessions);
        $sessions->add_child($session);

        $session->add_child($logs);
        $logs->add_child($log);

        ////////////////////////////////////////////////////////////////////////
        // data sources - non-user data
        ////////////////////////////////////////////////////////////////////////

        $attforblock->set_source_table('attforblock', array('id' => backup::VAR_ACTIVITYID));

        $status->set_source_table('attendance_statuses', array('attendanceid' => backup::VAR_PARENTID));

        $session->set_source_table('attendance_sessions', array('attendanceid' => backup::VAR_PARENTID));

        ////////////////////////////////////////////////////////////////////////
        // data sources - user related data
        ////////////////////////////////////////////////////////////////////////

        if ($userinfo) {
            $log->set_source_table('attendance_log', array('sessionid' => backup::VAR_PARENTID));
        }

        ////////////////////////////////////////////////////////////////////////
        // id annotations
        ////////////////////////////////////////////////////////////////////////

        $session->annotate_ids('user', 'lasttakenby');
        $session->annotate_ids('group', 'groupid');
        $log->annotate_ids('user', 'studentid');
        $log->annotate_ids('user', 'takenby');

        ////////////////////////////////////////////////////////////////////////
        // file annotations
        ////////////////////////////////////////////////////////////////////////

        $session->annotate_files('mod_attforblock', 'session', 'id');

        // return the root element (workshop), wrapped into standard activity structure
        return $this->prepare_activity_structure($attforblock);
    }
}
