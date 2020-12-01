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
 * presence report
 *
 * @package    mod_presence
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$pageparams = new mod_presence_report_page_params();

$id                     = required_param('id', PARAM_INT);
$from                   = optional_param('from', null, PARAM_ACTION);
$pageparams->view       = optional_param('view', null, PARAM_INT);
$pageparams->curdate    = optional_param('curdate', null, PARAM_INT);
$pageparams->group      = optional_param('group', null, PARAM_INT);
$pageparams->sort       = optional_param('sort', presence_SORT_DEFAULT, PARAM_INT);
$pageparams->page       = optional_param('page', 1, PARAM_INT);
$pageparams->perpage    = get_config('presence', 'resultsperpage');

$cm             = get_coursemodule_from_id('presence', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$presencerecord = $DB->get_record('presence', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/presence:viewreports', $context);

$pageparams->init($cm);
$pageparams->showextrauserdetails = optional_param('showextrauserdetails', $presencerecord->showextrauserdetails, PARAM_INT);
$pageparams->showsessiondetails = optional_param('showsessiondetails', $presencerecord->showsessiondetails, PARAM_INT);
$pageparams->sessiondetailspos = optional_param('sessiondetailspos', $presencerecord->sessiondetailspos, PARAM_TEXT);

$presence = new mod_presence_structure($presencerecord, $cm, $course, $context, $pageparams);

$PAGE->set_url($presence->url_report());
$PAGE->set_pagelayout('report');
$PAGE->set_title($course->shortname. ": ".$presence->name.' - '.get_string('report', 'presence'));
$PAGE->set_heading($course->fullname);
$PAGE->force_settings_menu(true);
$PAGE->set_cacheable(true);
$PAGE->navbar->add(get_string('report', 'presence'));

$output = $PAGE->get_renderer('mod_presence');
$tabs = new presence_tabs($presence, presence_tabs::TAB_REPORT);
$filtercontrols = new presence_filter_controls($presence, true);
$reportdata = new presence_report_data($presence);

// Trigger a report viewed event.
$event = \mod_presence\event\report_viewed::create(array(
    'objectid' => $presence->id,
    'context' => $PAGE->context,
    'other' => array()
));
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('presence', $presencerecord);
$event->trigger();

$title = get_string('presenceforthecourse', 'presence').' :: ' .format_string($course->fullname);
$header = new mod_presence_header($presence, $title);

// Output starts here.
echo $output->header();
echo $output->render($header);
echo $output->render($tabs);
echo $output->render($filtercontrols);
echo $output->render($reportdata);
echo $output->footer();

