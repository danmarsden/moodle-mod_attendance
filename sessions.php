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
 * Adding presence sessions
 *
 * @package    mod_presence
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once($CFG->dirroot.'/lib/formslib.php');

$capabilities = array(
    'mod/presence:managepresences',
);

$pageparams = new mod_presence_sessions_page_params();
$id                     = required_param('id', PARAM_INT);
$pageparams->action     = required_param('action', PARAM_INT);
$pageparams->maxattendants = optional_param('maxattendants', 0, PARAM_INT);

presence_init_page([
    'url' => new moodle_url('/mod/presence/manage.php'),
    'tab' => $pageparams->action == mod_presence_sessions_page_params::ACTION_ADD ?
        presence_tabs::TAB_ADD : presence_tabs::TAB_UPDATE,
]);

$formparams = array('course' => $course, 'cm' => $cm, 'modcontext' => $context, 'att' => $presence);
switch ($presence->pageparams->action) {
    case mod_presence_sessions_page_params::ACTION_ADD:
        $PAGE->requires->js('/mod/presence/js/rooms.js');
        $url = $presence->url_sessions(array('action' => mod_presence_sessions_page_params::ACTION_ADD));
        $mform = new \mod_presence\form\addsession($url, $formparams);

        if ($mform->is_cancelled()) {
            redirect($presence->url_manage());
        }

        if ($formdata = $mform->get_data()) {
            $formdata->maxattendants = $pageparams->maxattendants;
            $sessions = presence_construct_sessions_data_for_add($formdata, $presence);
            $presence->add_sessions($sessions);
            if (count($sessions) == 1) {
                $message = get_string('sessiongenerated', 'presence');
            } else {
                $message = get_string('sessionsgenerated', 'presence', count($sessions));
            }

            mod_presence_notifyqueue::notify_success($message);
            // Redirect to the sessions tab always showing all sessions.
            $SESSION->presencecurrentpresenceview[$cm->course] = PRESENCE_VIEW_ALL;
            redirect($presence->url_manage());
        }
        break;
    case mod_presence_sessions_page_params::ACTION_UPDATE:
        $PAGE->requires->js('/mod/presence/js/rooms.js');
        $sessionid = required_param('sessionid', PARAM_INT);
        $url = $presence->url_sessions(array('action' => mod_presence_sessions_page_params::ACTION_UPDATE, 'sessionid' => $sessionid));
        $formparams['sessionid'] = $sessionid;
        $mform = new \mod_presence\form\updatesession($url, $formparams);

        if ($mform->is_cancelled()) {
            redirect($presence->url_manage());
        }

        if ($formdata = $mform->get_data()) {
            $formdata->maxattendants = $pageparams->maxattendants;
            $presence->update_session_from_form_data($formdata, $sessionid);

            mod_presence_notifyqueue::notify_success(get_string('sessionupdated', 'presence'));
            redirect($presence->url_manage());
        }
        $currenttab = presence_tabs::TAB_UPDATE;
        break;
    case mod_presence_sessions_page_params::ACTION_DELETE:
        $sessionid = required_param('sessionid', PARAM_INT);
        $confirm   = optional_param('confirm', null, PARAM_INT);

        if (isset($confirm) && confirm_sesskey()) {
            $presence->delete_sessions(array($sessionid));
            redirect($presence->url_manage(), get_string('sessiondeleted', 'presence'));
        }

        $sessinfo = $presence->get_session_info($sessionid);

        $message = get_string('deletecheckfull', 'presence', get_string('session', 'presence'));
        $message .= str_repeat(html_writer::empty_tag('br'), 2);
        $message .= userdate($sessinfo->sessdate, get_string('strftimedatetime', 'langconfig'));
        $message .= html_writer::empty_tag('br');
        $message .= $sessinfo->description;

        $params = array('action' => $presence->pageparams->action, 'sessionid' => $sessionid, 'confirm' => 1, 'sesskey' => sesskey());

        echo $OUTPUT->confirm($message, $presence->url_sessions($params), $presence->url_manage());
        echo $OUTPUT->footer();
        exit;
    case mod_presence_sessions_page_params::ACTION_DELETE_SELECTED:
        $confirm    = optional_param('confirm', null, PARAM_INT);
        $message = get_string('deletecheckfull', 'presence', get_string('sessions', 'presence'));

        if (isset($confirm) && confirm_sesskey()) {
            $sessionsids = required_param('sessionsids', PARAM_ALPHANUMEXT);
            $sessionsids = explode('_', $sessionsids);
            if ($presence->pageparams->action == mod_presence_sessions_page_params::ACTION_DELETE_SELECTED) {
                $presence->delete_sessions($sessionsids);
                presence_update_users_grade($presence);
                redirect($presence->url_manage(), get_string('sessiondeleted', 'presence'));
            }
        }
        $sessid = optional_param_array('sessid', '', PARAM_SEQUENCE);
        if (empty($sessid)) {
            print_error('nosessionsselected', 'presence', $presence->url_manage());
        }
        $sessionsinfo = $presence->get_sessions_info($sessid);

        $message .= html_writer::empty_tag('br');
        foreach ($sessionsinfo as $sessinfo) {
            $message .= html_writer::empty_tag('br');
            $message .= userdate($sessinfo->sessdate, get_string('strftimedmyhm', 'presence'));
            $message .= html_writer::empty_tag('br');
            $message .= $sessinfo->description;
        }

        $sessionsids = implode('_', $sessid);
        $params = array('action' => $presence->pageparams->action, 'sessionsids' => $sessionsids,
                        'confirm' => 1, 'sesskey' => sesskey());

        echo $OUTPUT->confirm($message, $presence->url_sessions($params), $presence->url_manage());
        echo $OUTPUT->footer();
        exit;
//    case mod_presence_sessions_page_params::ACTION_CHANGE_DURATION:
//        $sessid = optional_param_array('sessid', '', PARAM_SEQUENCE);
//        $ids = optional_param('ids', '', PARAM_ALPHANUMEXT);
//
//        $slist = !empty($sessid) ? implode('_', $sessid) : '';
//
//        $url = $presence->url_sessions(array('action' => mod_presence_sessions_page_params::ACTION_CHANGE_DURATION));
//        $formparams['ids'] = $slist;
//        $mform = new mod_presence\form\duration($url, $formparams);
//
//        if ($mform->is_cancelled()) {
//            redirect($presence->url_manage());
//        }
//
//        if ($formdata = $mform->get_data()) {
//            $sessionsids = explode('_', $ids);
//            $duration = $formdata->durtime['hours'] * HOURSECS + $formdata->durtime['minutes'] * MINSECS;
//            $presence->update_sessions_duration($sessionsids, $duration);
//            redirect($presence->url_manage(), get_string('sessionupdated', 'presence'));
//        }
//
//        if ($slist === '') {
//            print_error('nosessionsselected', 'presence', $presence->url_manage());
//        }
//
//        break;
//    case mod_presence_sessions_page_params::ACTION_DELETE_HIDDEN:
//        $confirm  = optional_param('confirm', null, PARAM_INT);
//        if ($confirm && confirm_sesskey()) {
//            $sessions = $presence->get_hidden_sessions();
//            $presence->delete_sessions(array_keys($sessions));
//            redirect($presence->url_manage(), get_string('hiddensessionsdeleted', 'presence'));
//        }
//
//        $a = new stdClass();
//        $a->count = $presence->get_hidden_sessions_count();
//        $a->date = userdate($course->startdate);
//        $message = get_string('confirmdeletehiddensessions', 'presence', $a);
//
//        $params = array('action' => $presence->pageparams->action, 'confirm' => 1, 'sesskey' => sesskey());
//        echo $OUTPUT->confirm($message, $presence->url_sessions($params), $presence->url_manage());
//        echo $OUTPUT->footer();
//        exit;
}

//$output = $PAGE->get_renderer('mod_presence');
//$tabs = new presence_tabs($presence, $currenttab);
//echo $output->header();
//echo $output->heading(get_string('presenceforthecourse', 'presence').' :: ' .format_string($course->fullname));
//echo $output->render($tabs);

$mform->display();

echo $OUTPUT->footer();