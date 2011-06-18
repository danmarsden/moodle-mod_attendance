<?php

/**
 * @package    mod
 * @subpackage attforblock
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/attforblock/backup/moodle2/restore_attforblock_stepslib.php');

/**
 * attforblock restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_attforblock_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Choice only has one structure step
        $this->add_step(new restore_attforblock_activity_structure_step('attforblock_structure', 'attforblock.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('attendance_sessions',
                          array('description'), 'attforblock_session');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('ATTFORBLOCKVIEWBYID',
                    '/mod/attforblock/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('ATTFORBLOCKVIEWBYIDSTUD',
                    '/mod/attforblock/view.php?id=$1&studentid=$2', array('course_module', 'user'));

        return $rules;

    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * attforblock logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        // TODO: log restore
        /*
        $rules[] = new restore_log_rule('attforblock', 'add', 'view.php?id={course_module}', '{attforblock}');
        $rules[] = new restore_log_rule('attforblock', 'update', 'view.php?id={course_module}', '{attforblock}');
        $rules[] = new restore_log_rule('attforblock', 'view', 'view.php?id={course_module}', '{attforblock}');

        $rules[] = new restore_log_rule('attforblock', 'add assessment',
                       'assessment.php?asid={attforblock_assessment}', '{attforblock_submission}');
        $rules[] = new restore_log_rule('attforblock', 'update assessment',
                       'assessment.php?asid={attforblock_assessment}', '{attforblock_submission}');

        $rules[] = new restore_log_rule('attforblock', 'add reference assessment',
                       'exassessment.php?asid={attforblock_referenceassessment}', '{attforblock_examplesubmission}');
        $rules[] = new restore_log_rule('attforblock', 'update reference assessment',
                       'exassessment.php?asid={attforblock_referenceassessment}', '{attforblock_examplesubmission}');

        $rules[] = new restore_log_rule('attforblock', 'add example assessment',
                       'exassessment.php?asid={attforblock_exampleassessment}', '{attforblock_examplesubmission}');
        $rules[] = new restore_log_rule('attforblock', 'update example assessment',
                       'exassessment.php?asid={attforblock_exampleassessment}', '{attforblock_examplesubmission}');

        $rules[] = new restore_log_rule('attforblock', 'view submission',
                       'submission.php?cmid={course_module}&id={attforblock_submission}', '{attforblock_submission}');
        $rules[] = new restore_log_rule('attforblock', 'add submission',
                       'submission.php?cmid={course_module}&id={attforblock_submission}', '{attforblock_submission}');
        $rules[] = new restore_log_rule('attforblock', 'update submission',
                       'submission.php?cmid={course_module}&id={attforblock_submission}', '{attforblock_submission}');

        $rules[] = new restore_log_rule('attforblock', 'view example',
                       'exsubmission.php?cmid={course_module}&id={attforblock_examplesubmission}', '{attforblock_examplesubmission}');
        $rules[] = new restore_log_rule('attforblock', 'add example',
                       'exsubmission.php?cmid={course_module}&id={attforblock_examplesubmission}', '{attforblock_examplesubmission}');
        $rules[] = new restore_log_rule('attforblock', 'update example',
                       'exsubmission.php?cmid={course_module}&id={attforblock_examplesubmission}', '{attforblock_examplesubmission}');

        $rules[] = new restore_log_rule('attforblock', 'update aggregate grades', 'view.php?id={course_module}', '{attforblock}');
        $rules[] = new restore_log_rule('attforblock', 'update switch phase', 'view.php?id={course_module}', '[phase]');
        $rules[] = new restore_log_rule('attforblock', 'update clear aggregated grades', 'view.php?id={course_module}', '{attforblock}');
        $rules[] = new restore_log_rule('attforblock', 'update clear assessments', 'view.php?id={course_module}', '{attforblock}');
         *
         */

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        return $rules;
    }
}
