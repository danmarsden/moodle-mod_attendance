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
 * Export presence sessions
 *
 * @package   mod_presence
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/renderables.php');
require_once(dirname(__FILE__).'/renderhelpers.php');
require_once($CFG->libdir.'/formslib.php');

$id             = required_param('id', PARAM_INT);

$cm             = get_coursemodule_from_id('presence', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$presence            = $DB->get_record('presence', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/presence:export', $context);

$presence = new mod_presence_structure($presence, $cm, $course, $context);

$PAGE->set_url($presence->url_export());
$PAGE->set_title($course->shortname. ": ".$presence->name);
$PAGE->set_heading($course->fullname);
$PAGE->force_settings_menu(true);
$PAGE->set_cacheable(true);
$PAGE->navbar->add(get_string('export', 'presence'));

$formparams = array('course' => $course, 'cm' => $cm, 'modcontext' => $context);
$mform = new mod_presence\form\export($presence->url_export(), $formparams);

if ($formdata = $mform->get_data()) {

    $pageparams = new mod_presence_page_with_filter_controls();
    $pageparams->init($cm);
    $pageparams->page = 0;
    $pageparams->group = $formdata->group;
    $pageparams->set_current_sesstype($formdata->group ? $formdata->group : mod_presence_page_with_filter_controls::SESSTYPE_ALL);
    if (isset($formdata->includeallsessions)) {
        if (isset($formdata->includenottaken)) {
            $pageparams->view = PRESENCE_VIEW_ALL;
        } else {
            $pageparams->view = PRESENCE_VIEW_ALLPAST;
            $pageparams->curdate = time();
        }
        $pageparams->init_start_end_date();
    } else {
        $pageparams->startdate = $formdata->sessionstartdate;
        $pageparams->enddate = $formdata->sessionenddate;
    }
    if ($formdata->selectedusers) {
        $pageparams->userids = $formdata->users;
    }
    $presence->pageparams = $pageparams;

    $reportdata = new presence_report_data($presence);
    if ($reportdata->users) {
        $filename = clean_filename($course->shortname.'_'.
            get_string('modulenameplural', 'presence').
            '_'.userdate(time(), '%Y%m%d-%H%M'));

        $group = $formdata->group ? $reportdata->groups[$formdata->group] : 0;
        $data = new stdClass;
        $data->tabhead = array();
        $data->course = $presence->course->fullname;
        $data->group = $group ? $group->name : get_string('allparticipants');

        $data->tabhead[] = get_string('lastname');
        $data->tabhead[] = get_string('firstname');
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if (!empty($groupmode)) {
            $data->tabhead[] = get_string('groups');
        }
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $customfields = profile_get_custom_fields(false);

        if (isset($formdata->ident)) {
            foreach (array_keys($formdata->ident) as $opt) {
                if ($opt == 'id') {
                    $data->tabhead[] = get_string('studentid', 'presence');
                } else if (in_array($opt, array_column($customfields, 'shortname'))) {
                    foreach ($customfields as $customfield) {
                        if ($opt == $customfield->shortname) {
                            $data->tabhead[] = format_string($customfield->name, true, array('context' => $context));
                        }
                    }
                } else {
                    $data->tabhead[] = get_string($opt);
                }
            }
        }

        if (count($reportdata->sessions) > 0) {
            foreach ($reportdata->sessions as $sess) {
                $text = userdate($sess->sessdate, get_string('strftimedmyhm', 'presence'));
                $text .= ' ';
                if (!empty($sess->groupid) && empty($reportdata->groups[$sess->groupid])) {
                    $text .= get_string('deletedgroup', 'presence');
                } else {
                    $text .= $sess->groupid ? $reportdata->groups[$sess->groupid]->name : get_string('commonsession', 'presence');
                }
                if (isset($formdata->includedescription) && !empty($sess->description)) {
                    $text .= " ". strip_tags($sess->description);
                }
                $data->tabhead[] = $text;
                if (isset($formdata->includeremarks)) {
                    $data->tabhead[] = ''; // Space for the remarks.
                }
            }
        } else {
            print_error('sessionsnotfound', 'presence', $presence->url_manage());
        }

        $setnumber = -1;
        foreach ($reportdata->statuses as $sts) {
            if ($sts->setnumber != $setnumber) {
                $setnumber = $sts->setnumber;
            }

            $data->tabhead[] = $sts->acronym;
        }

        $data->tabhead[] = get_string('takensessions', 'presence');
        $data->tabhead[] = get_string('points', 'presence');
        $data->tabhead[] = get_string('percentage', 'presence');

        $i = 0;
        $data->table = array();
        foreach ($reportdata->users as $user) {
            profile_load_custom_fields($user);

            $data->table[$i][] = $user->lastname;
            $data->table[$i][] = $user->firstname;
            if (!empty($groupmode)) {
                $grouptext = '';
                $groupsraw = groups_get_all_groups($course->id, $user->id, 0, 'g.name');
                $groups = array();
                foreach ($groupsraw as $group) {
                    $groups[] = $group->name;;
                }
                $data->table[$i][] = implode(', ', $groups);
            }

            if (isset($formdata->ident)) {
                foreach (array_keys($formdata->ident) as $opt) {
                    if (in_array($opt, array_column($customfields, 'shortname'))) {
                        if (isset($user->profile[$opt])) {
                            $data->table[$i][] = format_string($user->profile[$opt], true, array('context' => $context));
                        } else {
                            $data->table[$i][] = '';
                        }
                        continue;
                    }

                    $data->table[$i][] = $user->$opt;
                }
            }

            $cellsgenerator = new user_sessions_cells_text_generator($reportdata, $user);
            $data->table[$i] = array_merge($data->table[$i], $cellsgenerator->get_cells(isset($formdata->includeremarks)));

            $usersummary = $reportdata->summary->get_taken_sessions_summary_for($user->id);

            foreach ($reportdata->statuses as $sts) {
                if (isset($usersummary->userstakensessionsbyacronym[$sts->setnumber][$sts->acronym])) {
                    $data->table[$i][] = $usersummary->userstakensessionsbyacronym[$sts->setnumber][$sts->acronym];
                } else {
                    $data->table[$i][] = 0;
                }
            }

            $data->table[$i][] = $usersummary->numtakensessions;
            $data->table[$i][] = $usersummary->pointssessionscompleted;
            $data->table[$i][] = format_float($usersummary->takensessionspercentage * 100);

            $i++;
        }

        if ($formdata->format === 'text') {
            presence_exporttocsv($data, $filename);
        } else {
            presence_exporttotableed($data, $filename, $formdata->format);
        }
        exit;
    } else {
        print_error('studentsnotfound', 'presence', $presence->url_manage());
    }
}

$output = $PAGE->get_renderer('mod_presence');
$tabs = new presence_tabs($presence, presence_tabs::TAB_EXPORT);
echo $output->header();
echo $output->heading(get_string('presenceforthecourse', 'presence').' :: ' .format_string($course->fullname));
echo $output->render($tabs);

$mform->display();

echo $OUTPUT->footer();



