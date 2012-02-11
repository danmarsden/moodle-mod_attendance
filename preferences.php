<?php

/**
 * Manage attendance settings
 *
 * @package    mod
 * @subpackage attforblock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$pageparams = new att_preferences_page_params();

$id                         = required_param('id', PARAM_INT);
$pageparams->action         = optional_param('action', NULL, PARAM_INT);
$pageparams->statusid       = optional_param('statusid', NULL, PARAM_INT);

$cm             = get_coursemodule_from_id('attforblock', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att            = $DB->get_record('attforblock', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$att = new attforblock($att, $cm, $course, $PAGE->context, $pageparams);

$att->perm->require_change_preferences_capability();

$PAGE->set_url($att->url_preferences());
$PAGE->set_title($course->shortname. ": ".$att->name.' - '.get_string('settings', 'attforblock'));
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->set_button($OUTPUT->update_module_button($cm->id, 'attforblock'));
$PAGE->navbar->add(get_string('settings', 'attforblock'));

switch ($att->pageparams->action) {
    case att_preferences_page_params::ACTION_ADD:
        $newacronym			= optional_param('newacronym', null, PARAM_MULTILANG);
        $newdescription		= optional_param('newdescription', null, PARAM_MULTILANG);
        $newgrade			= optional_param('newgrade', 0, PARAM_INT);

        $att->add_status($newacronym, $newdescription, $newgrade);
        break;
    case att_preferences_page_params::ACTION_DELETE:
        if (att_has_logs_for_status($att->pageparams->statusid))
            print_error('cantdeletestatus', 'attforblock', "attsettings.php?id=$id");

        $confirm    = optional_param('confirm', NULL, PARAM_INT);
        if (isset($confirm)) {
            $att->remove_status($att->pageparams->statusid);
            redirect($att->url_preferences(), get_string('statusdeleted','attforblock'));
        }

        $statuses = $att->get_statuses();
        $status = $statuses[$att->pageparams->statusid];
        $message = get_string('deletecheckfull', '', get_string('variable', 'attforblock'));
        $message .= str_repeat(html_writer::empty_tag('br'), 2);
        $message .= $status->acronym.': '.($status->description ? $status->description : get_string('nodescription', 'attforblock'));
        $params = array_merge($att->pageparams->get_significant_params(), array('confirm' => 1));
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);
        echo $OUTPUT->confirm($message, $att->url_preferences($params), $att->url_preferences());
        echo $OUTPUT->footer();
        exit;
    case att_preferences_page_params::ACTION_HIDE:
        $att->update_status($att->pageparams->statusid, null, null, null, 0);
        break;
    case att_preferences_page_params::ACTION_SHOW:
        $att->update_status($att->pageparams->statusid, null, null, null, 1);
        break;
    case att_preferences_page_params::ACTION_SAVE:
        $acronym 		= required_param('acronym', PARAM_MULTILANG);
        $description	= required_param('description', PARAM_MULTILANG);
        $grade			= required_param('grade', PARAM_INT);

        foreach ($acronym as $id => $v) {
            $att->update_status($id, $acronym[$id], $description[$id], $grade[$id], null);
        }
        att_update_all_users_grades($att->id, $att->course, $att->context);
        break;
}

$output = $PAGE->get_renderer('mod_attforblock');
$tabs = new attforblock_tabs($att, attforblock_tabs::TAB_PREFERENCES);
$prefdata = new attforblock_preferences_data($att);

/// Output starts here

echo $output->header();
echo $output->heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);
echo $output->render($tabs);
echo $output->render($prefdata);

echo $output->footer();

?>
