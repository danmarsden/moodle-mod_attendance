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
 * Prints presence info for particular user
 *
 * @package    mod_presence
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
$PAGE->requires->js('/mod/presence/js/rooms.js');


$capabilities = array(
    'mod/presence:view',
);

$pageparams = new mod_presence_view_page_params();

$id                     = required_param('id', PARAM_INT);
$pageparams->studentid  = optional_param('studentid', null, PARAM_INT);
$pageparams->mode       = optional_param('mode', mod_presence_view_page_params::MODE_THIS_BOOKING,PARAM_INT);
$pageparams->view       = optional_param('view', null, PARAM_INT);
$pageparams->curdate    = optional_param('curdate', null, PARAM_INT);


presence_init_page([
    'url' => new moodle_url('/mod/presence/evaluation.php'),
    'tab' => presence_tabs::TAB_BOOKING,
]);

$pageparams->startdate = time();
$pageparams->enddate = PHP_INT_MAX;
$userdata = new presence_user_data($presence, $USER->id);
$templatecontext = (object)[
    'sessions' => $userdata->sessionslog,
    'sessionsbydate' => $userdata->sessionsbydate,
//    'urlfinishall' => $presence->url_evaluation(['action' => mod_presence_sessions_page_params::ACTION_EVALUATE_FINISH_ALL]),
//    'buttoncaption' => $pageparams->showfinished ?
//        get_string('showevaluations_open', 'presence') : get_string('showevaluations_all', 'presence'),
//    'buttonurl' => $presence->url_evaluation(['showfinished' => intval($pageparams->showfinished) ^ 1]),
];

// TODO: updated layout - almost ready
echo $OUTPUT->render_from_template('mod_presence/booking', $templatecontext);

echo $output->render($userdata);

echo $output->footer();
