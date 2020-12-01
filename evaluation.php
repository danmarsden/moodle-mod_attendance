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
 * @copyright  2020 Florian Metzger-Noel (github.com/flocko-motion)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');

$PAGE->requires->js('/mod/presence/js/presence.js');
$PAGE->requires->js('/mod/presence/js/evaluation.js');

$capabilities = array(
    'mod/presence:managepresences',
    'mod/presence:takepresences',
    'mod/presence:changepresences'
);

$pageparams = new mod_presence_manage_page_params();
$from                       = optional_param('from', null, PARAM_ALPHANUMEXT);
$pageparams->view           = optional_param('view', null, PARAM_INT);
$pageparams->curdate        = optional_param('curdate', null, PARAM_INT);
$pageparams->action         = optional_param('action',  null, PARAM_INT);
$pageparams->sessionid      = optional_param('sessionid',  null, PARAM_INT);
$pageparams->showfinished   = optional_param('showfinished',  null, PARAM_INT);
$pageparams->perpage        = get_config('presence', 'resultsperpage');

if (!$pageparams->sessionid && $pageparams->action != mod_presence_sessions_page_params::ACTION_EVALUATE_FINISH_ALL) {
    $pageparams->action = null;
}

presence_init_page([
    'url' => new moodle_url('/mod/presence/evaluation.php'),
    'tab' => presence_tabs::TAB_EVALUATION,
]);

switch ($presence->pageparams->action) {
    case mod_presence_sessions_page_params::ACTION_EVALUATE:
        $evaluationdata = new presence_evaluation_data($presence);
        $usersvalues = array_values($evaluationdata->users);
        $templatecontext = (object)[
            'session' => $evaluationdata->session,
            'durationoptions' => $evaluationdata->session->durationoptions,
            'urlfinish' => $evaluationdata->urlfinish,
            'users' => $usersvalues,
            'users?' => count($usersvalues) > 0,
        ];
        echo '<input type="hidden" data-module="mod_presence" data-sessionid value="'.$evaluationdata->session->id.'" />';
        echo $OUTPUT->render_from_template('mod_presence/evaluate_session', $templatecontext);
        break;
    case mod_presence_sessions_page_params::ACTION_EVALUATE_FINISH:
        presence_finish_evaluation($presence, $pageparams->sessionid);
        redirect($presence->url_evaluation(), get_string('evaluationfinished', 'presence'), \core\output\notification::NOTIFY_SUCCESS);
        break;
    case mod_presence_sessions_page_params::ACTION_EVALUATE_FINISH_ALL:
        presence_finish_all_evaluations($presence);
        redirect($presence->url_evaluation(), get_string('evaluationsfinished', 'presence'), \core\output\notification::NOTIFY_SUCCESS);
        break;
    default:
        $pageparams->startdate = 0;
        $pageparams->enddate = time();
        $sessiondata = new presence_sessions_data($presence);
        $templatecontext = (object)[
            'sessions' => $sessiondata->sessions,
            'sessionsbydate' => $sessiondata->sessionsbydate,
            'urlfinishall' => $presence->url_evaluation(['action' => mod_presence_sessions_page_params::ACTION_EVALUATE_FINISH_ALL]),
            'buttoncaption' => $pageparams->showfinished ?
                get_string('showevaluations_open', 'presence') : get_string('showevaluations_all', 'presence'),
            'buttonurl' => $presence->url_evaluation(['showfinished' => intval($pageparams->showfinished) ^ 1]),
        ];
        echo $OUTPUT->render_from_template('mod_presence/evaluation', $templatecontext);
       break;
}

echo $output->footer();

