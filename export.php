<?PHP // $Id: export.php,v 1.1.2.2 2009/02/23 19:22:40 dlnsk Exp $

//  Lists all the sessions for a course

    require_once('../../config.php');    
	require_once('locallib.php');
//	require_once('grouplib.php');
	require_once('export_form.php');
	

    $id 	= required_param('id', PARAM_INT);
//	$format	= optional_param('format', '', PARAM_ACTION);
	
    if (! $cm = get_record("course_modules", "id", $id)) {
        error("Course Module ID was incorrect");
    }

    if (! $course = get_record("course", "id", $cm->course)) {
        error("Course is misconfigured");
    }
    
    if (! $attforblock = get_record('attforblock', 'id', $cm->instance)) {
        error("Course module is incorrect");
    }
    
    require_login($course->id);
	
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    require_capability('mod/attforblock:export', $context);
    
	$mform_export = new mod_attforblock_export_form('export.php', array('course'=>$course, 'cm'=>$cm, 'modcontext'=>$context));
    
	if ($fromform = $mform_export->get_data()) {
		$group = groups_get_group($fromform->group);
	    if ($group) {
	    	$students = get_users_by_capability($context, 'moodle/legacy:student', '', 'u.lastname ASC', '', '', $group->id, '', false);
	    } else {
	    	$students = get_users_by_capability($context, 'moodle/legacy:student', '', 'u.lastname ASC', '', '', '', '', false);
	    }
	    
		if ($students) {
		    $filename = clean_filename($course->shortname.'_Attendances_'.userdate(time(), '%Y%m%d-%H%M'));
		    
		    $data->tabhead = array();
//			$data->sheettitle = $course->fullname.' - ';
//			$data->sheettitle .= $group ? $group->name : get_string('allparticipants');
			$data->course = $course->fullname;
			$data->group = $group ? $group->name : get_string('allparticipants');

			if (isset($fromform->ident['id'])) {
				$data->tabhead[] = get_string('studentid','attforblock');
			}
			if (isset($fromform->ident['uname'])) {
				$data->tabhead[] = get_string('username');
			}
			$data->tabhead[] = get_string('lastname');
			$data->tabhead[] = get_string('firstname');
			
			$select = "courseid = {$course->id} AND sessdate >= {$course->startdate}";
			if (isset($fromform->includenottaken)) {
				$select .= " AND sessdate <= {$fromform->sessionenddate}";
			} else {
				$select .= " AND lasttaken != 0";
			}
	
			if ($sessions = get_records_select('attendance_sessions', $select, 'sessdate ASC')) {
				foreach($sessions as $sess) {
					$data->tabhead[] = userdate($sess->sessdate, get_string('strftimedmyhm', 'attforblock'));
				}
			} else {
				error('Sessions not found!', 'report.php?id='.$id);
			}
			$data->tabhead[] = '%';
			
			$i = 0;
		    $data->table = array();
			$statuses = get_statuses($course->id);
			foreach($students as $student) {
				if (isset($fromform->ident['id'])) {
					$data->table[$i][] = $student->id;
				}
				if (isset($fromform->ident['uname'])) {
					$data->table[$i][] = $student->username;
				}
				$data->table[$i][] = $student->lastname;
				$data->table[$i][] = $student->firstname;
				foreach ($sessions as $sess) {
					if ($rec = get_record('attendance_log', 'sessionid', $sess->id, 'studentid', $student->id)) {
						$data->table[$i][] = $statuses[$rec->statusid]->acronym;
					} else {
						$data->table[$i][] = '-';
					}
				}
				$data->table[$i][] = get_percent($student->id, $course).'%';
				$i++;
			}
			
			if ($fromform->format === 'text') {
				ExportToCSV($data, $filename);
			} else {
				ExportToTableEd($data, $filename, $fromform->format);
			}
			exit;
		} else {
			error('Students not found!', 'report.php?id='.$id);
		}
    } else {
		/// Print headers
	    $navlinks[] = array('name' => $attforblock->name, 'link' => "view.php?id=$id", 'type' => 'activity');
	    $navlinks[] = array('name' => get_string('export', 'quiz'), 'link' => null, 'type' => 'activityinstance');
	    $navigation = build_navigation($navlinks);
	    print_header("$course->shortname: ".$attforblock->name.' - ' .get_string('export', 'quiz'), $course->fullname,
	                 $navigation, "", "", true, "&nbsp;", navmenu($course));
	    
	    show_tabs($cm, $context, 'export');
    	$mform_export->display();
    }
	print_footer($course);
    
/////////////////////////////////////////////////////////////////////////////////

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
//    		if (is_numeric($cell)) {
//    			$myxls->write_number($i, $j++, $cell);
//    		} else {
//    			$myxls->write_string($i, $j++, $cell);
//    		}
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
