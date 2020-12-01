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
require_once(dirname(__FILE__) . '/classes/calendar.php');

$capabilities = array(
    'mod/presence:view',
);

$pageparams = new mod_presence_manage_page_params();
$pageparams->curdate        = optional_param('curdate', null, PARAM_INT);

presence_init_page([
    'url' => new moodle_url('/mod/presence/roomplanner.php'),
    'tab' => presence_tabs::TAB_ROOMPLANNER,
]);

$cal = new mod_presence\calendar($presence);
$roomplan = $cal->get_room_planner_schedule();

$params = [
    'hasrooms' => boolval(count($cal->get_rooms())),
    'roomslist' => array_values($cal->get_rooms()),
    'roomplan' => array_values($roomplan),
];
echo $OUTPUT->render_from_template('mod_presence/roomplanner', $params);
echo $output->footer();

