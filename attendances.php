<?PHP // $Id: attendances.php,v 1.2.2.5 2009/02/23 19:22:40 dlnsk Exp $

//  Lists all the sessions for a course

    require_once('../../config.php');    
	require_once($CFG->libdir.'/blocklib.php');
	require_once('locallib.php');
	require_once('lib.php');	
	
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    $id 		= required_param('id', PARAM_INT);
	$sessionid	= required_param('sessionid', PARAM_INT);
    $group    	= optional_param('group', -1, PARAM_INT);              // Group to show
	$sort 		= optional_param('sort','lastname', PARAM_ALPHA);

    if (! $cm = get_record('course_modules', 'id', $id)) {
        error('Course Module ID was incorrect');
    }
    
    if (! $course = get_record('course', 'id', $cm->course)) {
        error('Course is misconfigured');
    }
    
    require_login($course->id);

    if (! $attforblock = get_record('attforblock', 'id', $cm->instance)) {
        error("Course module is incorrect");
    }
    if (! $user = get_record('user', 'id', $USER->id) ) {
        error("No such user in this course");
    }
    
    if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        print_error('badcontext');
    }
    
    $statlist = implode(',', array_keys( (array)get_statuses($course->id) ));
    if ($form = data_submitted()) {
    	$students = array();			// stores students ids
		$formarr = (array)$form;
		$i = 0;
		$now = time();
		foreach($formarr as $key => $value) {
			if(substr($key,0,7) == 'student' && $value !== '') {
				$students[$i] = new Object();
				$sid = substr($key,7);		// gets studeent id from radiobutton name
				$students[$i]->studentid = $sid;
				$students[$i]->statusid = $value;
				$students[$i]->statusset = $statlist;
				$students[$i]->remarks = array_key_exists('remarks'.$sid, $formarr) ? $formarr['remarks'.$sid] : '';
				$students[$i]->sessionid = $sessionid;
				$students[$i]->timetaken = $now;
				$students[$i]->takenby = $USER->id;
				$i++;
			}
		}
		$attforblockrecord = get_record('attforblock', 'course', $course->id);

		foreach($students as $student) {
			if ($log = get_record('attendance_log', 'sessionid', $sessionid, 'studentid', $student->studentid)) {
				$student->id = $log->id; // this is id of log
				update_record('attendance_log', $student);
			} else {
				insert_record('attendance_log', $student);
			}
		}
		set_field('attendance_sessions', 'lasttaken', $now, 'id', $sessionid);
		set_field('attendance_sessions', 'lasttakenby', $USER->id, 'id', $sessionid);
		
		attforblock_update_grades($attforblockrecord);
		add_to_log($course->id, 'attendance', 'updated', 'mod/attforblock/report.php?id='.$id, $user->lastname.' '.$user->firstname);
		redirect('manage.php?id='.$id, get_string('attendancesuccess','attforblock'), 3);
    	exit();
    }
    
/// Print headers
    $navlinks[] = array('name' => $attforblock->name, 'link' => "view.php?id=$id", 'type' => 'activity');
    $navlinks[] = array('name' => get_string('update', 'attforblock'), 'link' => null, 'type' => 'activityinstance');
    $navigation = build_navigation($navlinks);
    print_header("$course->shortname: ".$attforblock->name.' - ' .get_string('update','attforblock'), $course->fullname,
                 $navigation, "", "", true, "&nbsp;", navmenu($course));

//check for hack
    if (!$sessdata = get_record('attendance_sessions', 'id', $sessionid)) {
		error("Required Information is missing", "manage.php?id=".$id);
    }
	$help = helpbutton ('updateattendance', get_string('help'), 'attforblock', true, false, '', true);
	$update = count_records('attendance_log', 'sessionid', $sessionid);
	
	if ($update) {
        require_capability('mod/attforblock:changeattendances', $context);
		print_heading(get_string('update','attforblock').' ' .get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname.$help);
	} else {
        require_capability('mod/attforblock:takeattendances', $context);
		print_heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname.$help);
	}

    /// find out current groups mode
    $groupmode = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm, true);

    if ($currentgroup) {
        $students = get_users_by_capability($context, 'moodle/legacy:student', '', "u.$sort ASC", '', '', $currentgroup, '', false);
    } else {
        $students = get_users_by_capability($context, 'moodle/legacy:student', '', "u.$sort ASC", '', '', '', '', false);
    }

	$sort = $sort == 'firstname' ? 'firstname' : 'lastname';
    /// Now we need a menu for separategroups as well!
    if ($groupmode == VISIBLEGROUPS || 
            ($groupmode && has_capability('moodle/site:accessallgroups', $context))) {
        groups_print_activity_menu($cm, "attendances.php?id=$id&amp;sessionid=$sessionid&amp;sort=$sort");
    }
	
	$table->data[][] = '<b>'.get_string('sessiondate','attforblock').': '.userdate($sessdata->sessdate, get_string('strftimedate').', '.get_string('strftimehm', 'attforblock')).
							', "'.($sessdata->description ? $sessdata->description : get_string('nodescription', 'attforblock')).'"</b>';
	print_table($table);
	
    $statuses = get_statuses($course->id);
	$i = 3;
  	foreach($statuses as $st) {
		$tabhead[] = "<a href=\"javascript:select_all_in('TD', 'cell c{$i}', null);\"><u>$st->acronym</u></a>";
		$i++;
	}
	$tabhead[] = get_string('remarks','attforblock');
	
	$firstname = "<a href=\"attendances.php?id=$id&amp;sessionid=$sessionid&amp;sort=firstname\">".get_string('firstname').'</a>';
	$lastname  = "<a href=\"attendances.php?id=$id&amp;sessionid=$sessionid&amp;sort=lastname\">".get_string('lastname').'</a>';
    if ($CFG->fullnamedisplay == 'lastname firstname') { // for better view (dlnsk)
        $fullnamehead = "$lastname / $firstname";
    } else {
        $fullnamehead = "$firstname / $lastname";
    }
	
	if ($students) {
        unset($table);
        $table->width = '0%';
        $table->head[] = '#';
        $table->align[] = 'center';
        $table->size[] = '20px';
        
        $table->head[] = '';
        $table->align[] = '';
        $table->size[] = '1px';
        
        $table->head[] = $fullnamehead;
        $table->align[] = 'left';
        $table->size[] = '';
        $table->wrap[2] = 'nowrap';
        foreach ($tabhead as $hd) {
            $table->head[] = $hd;
            $table->align[] = 'center';
            $table->size[] = '20px';
        }
        $i = 0;
        foreach($students as $student) {
            $i++;
            $att = get_record('attendance_log', 'sessionid', $sessionid, 'studentid', $student->id);
            $table->data[$student->id][] = (!$att && $update) ? "<font color=\"red\"><b>$i</b></font>" : $i; 
            $table->data[$student->id][] = print_user_picture($student->id, $course->id, $student->picture, 20, true, true);//, $returnstring=false, $link=true, $target=''); 
			$table->data[$student->id][] = "<a href=\"view.php?id=$id&amp;student={$student->id}\">".((!$att && $update) ? '<font color="red"><b>' : '').fullname($student).((!$att && $update) ? '</b></font>' : '').'</a>';

            foreach($statuses as $st) {
                 @$table->data[$student->id][] = '<input name="student'.$student->id.'" type="radio" value="'.$st->id.'" '.($st->id == $att->statusid ? 'checked' : '').'>';
            }
            $table->data[$student->id][] = '<input type="text" name="remarks'.$student->id.'" size="" value="'.($att ? $att->remarks : '').'">';
        }

        echo '<form name="takeattendance" method="post" action="attendances.php">';
        print_table($table);
        echo '<input type="hidden" name="id" value="'.$id.'">';
        echo '<input type="hidden" name="sessionid" value="'.$sessionid.'">';
        echo '<input type="hidden" name="formfrom" value="editsessvals">';
        echo '<center><input type="submit" name="esv" value="'.get_string('ok').'"></center>';
        echo '</form>';
    } else {
		print_heading(get_string('nothingtodisplay'), 'center');
	}
	 
	echo get_string('status','attforblock').':<br />'; 
	foreach($statuses as $st) {
		echo $st->acronym.' - '.$st->description.'<br />';
	}

    print_footer($course);
    
?>
