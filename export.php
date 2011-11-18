<?php

/**
 * Export attendance sessions
 *
 * @package    mod
 * @subpackage attforblock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/export_form.php');
require_once(dirname(__FILE__).'/renderables.php');
require_once(dirname(__FILE__).'/renderhelpers.php');

$id             = required_param('id', PARAM_INT);

$cm             = get_coursemodule_from_id('attforblock', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att            = $DB->get_record('attforblock', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);

$att = new attforblock($att, $cm, $course, $PAGE->context);

$att->perm->require_export_capability();

$PAGE->set_url($att->url_export());
$PAGE->set_title($course->shortname. ": ".$att->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->set_button($OUTPUT->update_module_button($cm->id, 'attforblock'));
$PAGE->navbar->add(get_string('export', 'quiz'));

$formparams = array('course' => $course, 'cm' => $cm, 'modcontext' => $PAGE->context);
$mform = new mod_attforblock_export_form($att->url_export(), $formparams);

if ($mform->is_submitted()) {
    $formdata = $mform->get_data();

    $pageparams = new att_page_with_filter_controls();
    $pageparams->init($cm);
    $pageparams->group = $formdata->group;
    $pageparams->set_current_sesstype($formdata->group ? $formdata->group : att_page_with_filter_controls::SESSTYPE_ALL);
    if (isset($formdata->includeallsessions)) {
        if (isset($formdata->includenottaken)) {
            $pageparams->view = ATT_VIEW_ALL;
        } else {
            $pageparams->view = ATT_VIEW_ALLPAST;
            $pageparams->curdate = time();
        }
        $pageparams->init_start_end_date();
    } else {
        $pageparams->startdate = $formdata->sessionstartdate;
        $pageparams->enddate = $formdata->sessionenddate;
    }
    $att->pageparams = $pageparams;

    $reportdata = new attforblock_report_data($att);
    if ($reportdata->users) {
        $filename = clean_filename($course->shortname.'_Attendances_'.userdate(time(), '%Y%m%d-%H%M'));

		$group = $formdata->group ? $reportdata->groups[$formdata->group] : 0;
        $data->tabhead = array();
        $data->course = $att->course->fullname;
        $data->group = $group ? $group->name : get_string('allparticipants');

        if (isset($formdata->ident['id'])) {
            $data->tabhead[] = get_string('studentid', 'attforblock');
        }
        if (isset($formdata->ident['uname'])) {
            $data->tabhead[] = get_string('username');
        }
        $data->tabhead[] = get_string('lastname');
        $data->tabhead[] = get_string('firstname');


        if (count($reportdata->sessions) > 0) {
            foreach($reportdata->sessions as $sess) {
                $text = userdate($sess->sessdate, get_string('strftimedmyhm', 'attforblock'));
                $text .= ' ';
                $text .= $sess->groupid ? $reportdata->groups[$sess->groupid]->name : get_string('commonsession', 'attforblock');
                $data->tabhead[] = $text;
            }
        } else {
            print_error('sessionsnotfound', 'attforblock', $att->url_manage());
        }
        if ($reportdata->gradable)
            $data->tabhead[] = get_string('grade');

        $i = 0;
        $data->table = array();
        foreach($reportdata->users as $user) {
            if (isset($formdata->ident['id'])) {
                $data->table[$i][] = $user->id;
            }
            if (isset($formdata->ident['uname'])) {
                $data->table[$i][] = $user->username;
            }
            $data->table[$i][] = $user->lastname;
            $data->table[$i][] = $user->firstname;
            $cellsgenerator = new user_sessions_cells_text_generator($reportdata, $user);
            $data->table[$i] = array_merge($data->table[$i], $cellsgenerator->get_cells());
            if ($reportdata->gradable)
                $data->table[$i][] = $reportdata->grades[$user->id].' / '.$reportdata->maxgrades[$user->id];
            $i++;
        }

        if ($formdata->format === 'text') {
            ExportToCSV($data, $filename);
        } else {
            ExportToTableEd($data, $filename, $formdata->format);
        }
        exit;
    } else {
        print_error('studentsnotfound', 'attendance', $att->url_manage());
    }
}

$output = $PAGE->get_renderer('mod_attforblock');
$tabs = new attforblock_tabs($att, attforblock_tabs::TAB_EXPORT);
echo $output->header();
echo $output->heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);
echo $output->render($tabs);

$mform->display();

echo $OUTPUT->footer();


function ExportToTableEd($data, $filename, $format) {
	global $CFG;

    if ($format === 'excel') {
	    require_once("$CFG->libdir/excellib.class.php");
	    $filename .= ".xls";
	    $workbook = new MoodleExcelWorkbook("-");
    } else {
	    require_once("$CFG->libdir/odslib.class.php");
	    $filename .= ".ods";
	    $workbook = new MoodleODSWorkbook("-");
    }
/// Sending HTTP headers
    $workbook->send($filename);
/// Creating the first worksheet
    $myxls =& $workbook->add_worksheet('Attendances');
/// format types
    $formatbc =& $workbook->add_format();
    $formatbc->set_bold(1);

    $myxls->write(0, 0, get_string('course'), $formatbc);
    $myxls->write(0, 1, $data->course);
    $myxls->write(1, 0, get_string('group'), $formatbc);
    $myxls->write(1, 1, $data->group);

    $i = 3;
    $j = 0;
    foreach ($data->tabhead as $cell) {
    	$myxls->write($i, $j++, $cell, $formatbc);
    }
    $i++;
    $j = 0;
    foreach ($data->table as $row) {
    	foreach ($row as $cell) {
    		$myxls->write($i, $j++, $cell);
    	}
		$i++;
		$j = 0;
    }
	$workbook->close();
}

function ExportToCSV($data, $filename) {
    $filename .= ".txt";

    header("Content-Type: application/download\n");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Expires: 0");
    header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
    header("Pragma: public");

    echo get_string('course')."\t".$data->course."\n";
    echo get_string('group')."\t".$data->group."\n\n";

    echo implode("\t", $data->tabhead)."\n";
    foreach ($data->table as $row) {
    	echo implode("\t", $row)."\n";
    }
}

?>
