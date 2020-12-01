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
 * Manage presence sessions
 *
 * @package    mod_presence
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$capabilities = array(
    'mod/presence:managepresences',
    'mod/presence:takepresences',
    'mod/presence:changepresences'
);

$pageparams = new mod_presence_manage_page_params();
$id                         = required_param('id', PARAM_INT);
$from                       = optional_param('from', null, PARAM_ALPHANUMEXT);
$pageparams->view           = optional_param('view', null, PARAM_INT);
$pageparams->curdate        = optional_param('curdate', null, PARAM_INT);
$pageparams->perpage        = get_config('presence', 'resultsperpage');

//$cm             = get_coursemodule_from_id('presence', $id, 0, false, MUST_EXIST);
//$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

presence_init_page([
    'url' => new moodle_url('/mod/presence/manage.php'),
    'tab' => presence_tabs::TAB_SESSIONS,
]);
$presence = $DB->get_record('presence', array('id' => $cm->instance), '*', MUST_EXIST);
$presence = new mod_presence_structure($presence, $cm, $course, $context, $pageparams);
$filtercontrols = new presence_filter_controls($presence);
$sessiondata = new presence_sessions_data($presence);


$templatecontext = (object)[
    'sessgroupselector' => $output->render_sess_group_selector($filtercontrols),
    'curdatecontrols' => $output->render_curdate_controls($filtercontrols),
    'pagingcontrols' => $output->render_paging_controls($filtercontrols),
    'addsessionurl' => $presence->url_sessions()->out(true, ['action' => mod_presence_sessions_page_params::ACTION_ADD]),
    'sessions' => $sessiondata->sessions,
    'sessionsbydate' => $sessiondata->sessionsbydate,
];
echo $OUTPUT->render_from_template('mod_presence/manage', $templatecontext);

echo $output->footer();

