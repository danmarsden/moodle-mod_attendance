<?php

/**
 * Adding attendance sessions
 *
 * @package    mod
 * @subpackage attforblock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/add_form.php');
require_once(dirname(__FILE__).'/update_form.php');
require_once(dirname(__FILE__).'/duration_form.php');

$pageparams = new att_sessions_page_params();

$id                     = required_param('id', PARAM_INT);
$pageparams->action     = required_param('action', PARAM_INT);

$cm             = get_coursemodule_from_id('attforblock', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att            = $DB->get_record('attforblock', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$att = new attforblock($att, $cm, $course, $PAGE->context, $pageparams);

$att->perm->require_manage_capability();

$PAGE->set_url($att->url_sessions());
$PAGE->set_title($course->shortname. ": ".$att->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->set_button($OUTPUT->update_module_button($cm->id, 'attforblock'));
$PAGE->navbar->add($att->name);

$formparams = array('course' => $course, 'cm' => $cm, 'modcontext' => $PAGE->context);
switch ($att->pageparams->action) {
    case att_sessions_page_params::ACTION_ADD:
        $url = $att->url_sessions(array('action' => att_sessions_page_params::ACTION_ADD));
		$mform = new mod_attforblock_add_form($url, $formparams);
        
        if ($formdata = $mform->get_data()) {
            $sessions = construct_sessions_data_for_add($formdata);
            $att->add_sessions($sessions);
            redirect($url, get_string('sessionsgenerated','attforblock'));
        }
        break;
    case att_sessions_page_params::ACTION_UPDATE:
		$sessionid	= required_param('sessionid', PARAM_INT);

        $url = $att->url_sessions(array('action' => att_sessions_page_params::ACTION_UPDATE, 'sessionid' => $sessionid));
        $formparams['sessionid'] = $sessionid;
		$mform = new mod_attforblock_update_form($url, $formparams);
        
	    if ($mform->is_cancelled()) {
	    	redirect($att->url_manage());
	    }

        if ($formdata = $mform->get_data()) {
            $att->update_session_from_form_data($formdata, $sessionid);

            redirect($att->url_manage(), get_string('sessionupdated','attforblock'));
        }
        break;
    case att_sessions_page_params::ACTION_DELETE:
		$sessionid	= required_param('sessionid', PARAM_INT);
		$confirm    = optional_param('confirm', NULL, PARAM_INT);

        if (isset($confirm)) {
            $att->delete_sessions(array($sessionid));
            att_update_all_users_grades($att->id, $att->course, $att->context);
            redirect($att->url_manage(), get_string('sessiondeleted','attforblock'));
        }

        $sessinfo = $att->get_session_info($sessionid);

        $message = get_string('deletecheckfull', '', get_string('session', 'attforblock'));
        $message .= str_repeat(html_writer::empty_tag('br'), 2);
        $message .= userdate($sessinfo->sessdate, get_string('strftimedmyhm', 'attforblock'));
        $message .= html_writer::empty_tag('br');
        $message .= $sessinfo->description;

        $params = array('action' => $att->pageparams->action, 'sessionid' => $sessionid, 'confirm' => 1);

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);
        echo $OUTPUT->confirm($message, $att->url_sessions($params), $att->url_manage());
        echo $OUTPUT->footer();
        exit;
    case att_sessions_page_params::ACTION_DELETE_SELECTED:
		$confirm    = optional_param('confirm', NULL, PARAM_INT);

        if (isset($confirm)) {
    		$sessionsids = required_param('sessionsids', PARAM_ALPHANUMEXT);
            $sessionsids = explode('_', $sessionsids);

            $att->delete_sessions($sessionsids);
            att_update_all_users_grades($att->id, $att->course, $att->context);
            redirect($att->url_manage(), get_string('sessiondeleted','attforblock'));
        }

		$fromform = data_submitted();
        // nothing selected
        if (!isset($fromform->sessid))
            print_error ('nosessionsselected', 'attforblock', $att->url_manage());

        $sessionsinfo = $att->get_sessions_info($fromform->sessid);

        $message = get_string('deletecheckfull', '', get_string('session', 'attforblock'));
        $message .= html_writer::empty_tag('br');
        foreach ($sessionsinfo as $sessinfo) {
            $message .= html_writer::empty_tag('br');
            $message .= userdate($sessinfo->sessdate, get_string('strftimedmyhm', 'attforblock'));
            $message .= html_writer::empty_tag('br');
            $message .= $sessinfo->description;
        }

        $sessionsids = implode('_', $fromform->sessid);
        $params = array('action' => $att->pageparams->action, 'sessionsids' => $sessionsids, 'confirm' => 1);

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);
        echo $OUTPUT->confirm($message, $att->url_sessions($params), $att->url_manage());
        echo $OUTPUT->footer();
        exit;
    case att_sessions_page_params::ACTION_CHANGE_DURATION:
		$fromform = data_submitted();
        $slist = isset($fromform->sessid) ? implode('_', $fromform->sessid) : '';

        $url = $att->url_sessions(array('action' => att_sessions_page_params::ACTION_CHANGE_DURATION));
        $formparams['ids'] = $slist;
		$mform = new mod_attforblock_duration_form($url, $formparams);

	    if ($mform->is_cancelled()) {
	    	redirect($att->url_manage());
	    }

        if ($formdata = $mform->get_data()) {
            $sessionsids = explode('_', $fromform->ids);
            $duration = $formdata->durtime['hours']*HOURSECS + $formdata->durtime['minutes']*MINSECS;
            $att->update_sessions_duration($sessionsids, $duration);
            redirect($att->url_manage(), get_string('sessionupdated','attforblock'));
        }
        
        if ($slist === '')
            print_error ('nosessionsselected','attforblock', $att->url_manage());

        break;
}

$output = $PAGE->get_renderer('mod_attforblock');
$tabs = new attforblock_tabs($att, attforblock_tabs::TAB_ADD);
echo $output->header();
echo $output->heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);
echo $output->render($tabs);

$mform->display();

echo $OUTPUT->footer();

function construct_sessions_data_for_add($formdata) {
    global $CFG;

    $duration = $formdata->durtime['hours']*HOURSECS + $formdata->durtime['minutes']*MINSECS;
    $now = time();

    $sessions = array();
    if (isset($formdata->addmultiply)) {
        $startdate = $formdata->sessiondate;
        $starttime = $startdate - usergetmidnight($startdate);
        $enddate = $formdata->sessionenddate + DAYSECS; // because enddate in 0:0am

        if ($enddate < $startdate) return NULL;

        $days = (int)ceil(($enddate - $startdate) / DAYSECS);

        // Getting first day of week
        $sdate = $startdate;
        $dinfo = usergetdate($sdate);
        if ($CFG->calendar_startwday === '0') { //week start from sunday
            $startweek = $startdate - $dinfo['wday'] * DAYSECS; //call new variable
        } else {
            $wday = $dinfo['wday'] === 0 ? 7 : $dinfo['wday'];
            $startweek = $startdate - ($wday-1) * DAYSECS;
        }

        $wdaydesc = array(0=>'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');

        while ($sdate < $enddate) {
            if($sdate < $startweek + WEEKSECS) {
                $dinfo = usergetdate($sdate);
                if(key_exists($wdaydesc[$dinfo['wday']], $formdata->sdays)) {
                    $sess->sessdate =  usergetmidnight($sdate) + $starttime;
                    $sess->duration = $duration;
                    $sess->descriptionitemid = $formdata->sdescription['itemid'];
                    $sess->description = $formdata->sdescription['text'];
                    $sess->descriptionformat = $formdata->sdescription['format'];
                    $sess->timemodified = $now;

                    fill_groupid($formdata, $sessions, $sess);
                }
                $sdate += DAYSECS;
            } else {
                $startweek += WEEKSECS * $formdata->period;
                $sdate = $startweek;
            }
        }
    } else {
        $sess->sessdate = $formdata->sessiondate;
        $sess->duration = $duration;
        $sess->descriptionitemid = $formdata->sdescription['itemid'];
        $sess->description = $formdata->sdescription['text'];
        $sess->descriptionformat = $formdata->sdescription['format'];
        $sess->timemodified = $now;

        fill_groupid($formdata, $sessions, $sess);
    }

    return $sessions;
}

function fill_groupid($formdata, &$sessions, $sess) {
    if ($formdata->sessiontype == attforblock::SESSION_COMMON) {
        $sess = clone $sess;
        $sess->groupid = 0;
        $sessions[] = $sess;
    }
    else {
        foreach ($formdata->groups as $groupid) {
            $sess = clone $sess;
            $sess->groupid = $groupid;
            $sessions[] = $sess;
        }
    }
}

?>
