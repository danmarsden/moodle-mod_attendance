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
require_once(dirname(__FILE__).'/classes/tools.php');
$PAGE->requires->js('/mod/presence/js/presence.js');
$PAGE->requires->js('/mod/presence/js/userprofile.js');

$capabilities = array(
    'mod/presence:managepresences',
    'mod/presence:takepresences',
    'mod/presence:changepresences'
);

$pageparams = new mod_presence_manage_page_params();
$id                         = required_param('id', PARAM_INT);
$userid                     = required_param('userid', PARAM_INT);

presence_init_page([
    'url' => new moodle_url('/mod/presence/evaluation.php'),
    'tab' => presence_tabs::TAB_EVALUATION,
]);

$pageparams->init($cm);
$presence = new mod_presence_structure(
    $DB->get_record('presence', array('id' => $cm->instance), '*', MUST_EXIST),
    $cm, $course, $context, $pageparams);

$user = $presence->get_user($userid);

$days = (time() - $user->timecreated) / (3600 * 24);
if ($days >= 365) {
    $user->signedupsince = get_string('signedupfor_years', 'presence', floor($days / 365));
} else if ($days >= 31) {
    $user->signedupsince = get_string('signedupfor_months', 'presence', floor($days / 30.42));
} else if ($days >= 14) {
    $user->signedupsince = get_string('signedupfor_weeks', 'presence', floor($days / 7));
} else {
    $user->signedupsince = get_string('signedupfor_days', 'presence', floor($days));
}
$presences = $presence->get_attendet_sessions($userid);



\mod_presence\tools::lang_to_html("error");
\mod_presence\tools::lang_to_html("success");
\mod_presence\tools::lang_to_html("messagesent", "presence");
\mod_presence\tools::lang_to_html("messageempty", "presence");
\mod_presence\tools::lang_to_html("errorsavingdata", "presence");
\mod_presence\tools::var_val_to_html("courseid", $course->id);


$templatecontext = [
    'user' => $user,
    'courseremarks' => $presence->get_course_remarks($userid),
    'sessions' => $presences['sessions'],
    'sessions?' => count($presences['sessions']),
    'totalhours' => $presences['totalhours'],
];
echo $OUTPUT->render_from_template('mod_presence/userprofile', $templatecontext);

echo $output->footer();

