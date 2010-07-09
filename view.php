<?PHP  // $Id: view.php,v 1.3.2.2 2009/02/23 19:22:41 dlnsk Exp $

/// This page prints a particular instance of attforblock
/// (Replace attforblock with the name of your module)

    require_once("../../config.php");
    require_once('locallib.php');

    $id   = optional_param('id', -1, PARAM_INT);   // Course Module ID, or
//    $a    = optional_param('a', -1, PARAM_INT);    // attforblock ID
	$studentid			= optional_param('student', 0, PARAM_INT);
	$printing			= optional_param('printing');
    $mode 				= optional_param('mode', 'thiscourse');
	
    if ($id) {
        if (! $cm = get_record("course_modules", "id", $id)) {
            error("Course Module ID was incorrect");
        }
    
        if (! $course = get_record("course", "id", $cm->course)) {
            error("Course is misconfigured");
        }
    
        if (! $attforblock = get_record("attforblock", "id", $cm->instance)) {
            error("Course module is incorrect");
        }

    } else {
    	error("Module id is incorrect.");
//        if (! $attforblock = get_record("attforblock", "id", $a)) {
//            error("Course module is incorrect");
//        }
//        if (! $course = get_record("course", "id", $attforblock->course)) {
//            error("Course is misconfigured");
//        }
//        if (! $cm = get_coursemodule_from_instance("attforblock", $attforblock->id, $course->id)) {
//            error("Course Module ID was incorrect");
//        }
    }

    require_login($course->id);

    if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        print_error('badcontext');
    }
    
    if (!$studentid && (has_capability('mod/attforblock:manageattendances', $context) ||
                        has_capability('mod/attforblock:takeattendances', $context) ||
                        has_capability('mod/attforblock:changeattendances', $context))) {
        redirect("manage.php?id=$cm->id");
    }
    if (!$studentid && has_capability('mod/attforblock:viewreports', $context)) {
        redirect("report.php?id=$cm->id");
    }
    
    if (! $user = get_record('user', 'id', $USER->id) ) {
        error("No such user in this course");
    }
	
    require_capability('mod/attforblock:view', $context);
    
	$student = false;
    if ($studentid) {
    	if ($studentid == $USER->id or has_capability('mod/attforblock:viewreports', $context)) {
		    if (!$student = get_record('user', 'id', $studentid) ) {
		        error("No such user in this course");
		    }
    	}
	}
	
//    if (empty($student) && has_capability('mod/attforblock:manageattendances', $context)) {
//        redirect("manage.php?id=$cm->id");
//    }
    
	if ($student) {
		$user = $student;
	}
	if ($printing) {
		if ($mode === 'thiscourse') {
    		print_header('', $course->fullname.' - '.get_string('attendancereport','attforblock'));
			print_user_attendaces($user, $cm, $course, 'printing');
		} else {
    		print_header('', get_string('attendancereport','attforblock'));
			print_user_attendaces($user, $cm, 0, 'printing');
		}
    	exit();
    }

/// Print headers
	    $navlinks[] = array('name' => $attforblock->name, 'link' => "view.php?id=$id", 'type' => 'activityinstance');
	    $navlinks[] = array('name' => get_string('attendancereport', 'attforblock'), 'link' => null, 'type' => 'title');
	    $navigation = build_navigation($navlinks);
	    print_header("$course->shortname: ".$attforblock->name.' - ' .get_string('export', 'quiz'), $course->fullname,
	                 $navigation, "", "", true, "&nbsp;", navmenu($course));

	//add info to log
	add_to_log($course->id, 'attendance', 'student view', "mod/attforblock/view.php?course=$course->id&amp;student=$USER->id", $USER->lastname.' '.$USER->firstname);
//	print_heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);

	/// Prints out tabs
    $currenttab = $mode;
    $studstr = $student ? '&amp;student='.$student->id : '';
    $toprow = array();
    $toprow[] = new tabobject('thiscourse', "view.php?id=$id&amp;mode=thiscourse{$studstr}",
                get_string('thiscourse','attforblock'));
    $toprow[] = new tabobject('allcourses', "view.php?id=$id&amp;mode=allcourses{$studstr}",
                   get_string('allcourses','attforblock'));
    print_tabs(array($toprow), $currenttab);
    
	if ($mode === 'thiscourse') {
		print_user_attendaces($user, $cm, $course);
	} else {
    	print_user_attendaces($user, $cm);
	}

	print_footer($course);

?>