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
    $grouptype  = required_param('grouptype', PARAM_INT);
    $group    	= optional_param('group', -1, PARAM_INT);              // Group to show
	$sort 		= optional_param('sort','lastname', PARAM_ALPHA);
    $copyfrom  	= optional_param('copyfrom', -1, PARAM_INT);

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
    
    $statlist = implode(',', array_keys( (array)get_statuses($attforblock->id) ));
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
		$attforblockrecord = get_record('attforblock', 'id', $cm->instance);//'course', $course->id);

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

    // get the viewmode & grid columns
    $attforblockrecord = get_record('attforblock', 'id', $cm->instance);//'course', $course->id);'course', $course->id);
    $view       = optional_param('view', -1, PARAM_INT);
    if ($view != -1) {
        set_user_preference("attforblock_viewmode", $view);
    }
    else {
        $view = get_user_preferences("attforblock_viewmode", SORTEDLISTVIEW);
    }
    $gridcols   = optional_param('gridcols', -1, PARAM_INT);
    if ($gridcols != -1) {
        set_user_preference("attforblock_gridcolumns", $gridcols);
    }
    else {
        $gridcols = get_user_preferences("attforblock_gridcolumns",5);
    }

    echo '<table class="controls" cellspacing="0"><tr>'; //echo '<center>';
    $options = array (SORTEDLISTVIEW => get_string('sortedlist','attforblock'), SORTEDGRIDVIEW => get_string('sortedgrid','attforblock'));
    $dataurl = "attendances.php?id=$id&grouptype=$grouptype&gridcols=$gridcols";
    if ($group!=-1) {
        $dataurl = $dataurl . "&group=$group";
    }
    $today = usergetmidnight($sessdata->sessdate);
    $select = "sessdate>={$today} AND sessdate<{$today}+86400 AND attendanceid={$cm->instance}";
    $todaysessions = get_records_select('attendance_sessions', $select, 'sessdate ASC');
    $optionssesions = array();
    if (count($todaysessions)>1) {
        echo '<td class="right"><label for="fastsessionmenu_jump">'. get_string('jumpto','attforblock') . "&nbsp;</label>";
        foreach($todaysessions as $sessdatarow) {
            $descr = userdate($sessdatarow->sessdate, get_string('strftimehm', 'attforblock')) . "-" . userdate($sessdatarow->sessdate+$sessdatarow->duration, get_string('strftimehm', 'attforblock'));
            if ($sessdatarow->description) {
                $descr = $sessdatarow->description . ' ('.$descr.')';
            }
            $optionssessions[$sessdatarow->id] = $descr;
        }
        popup_form("$dataurl&sessionid=", $optionssessions, 'fastsessionmenu', $sessionid, '');
        echo "<td/><tr/><tr>";
    }
    $dataurl .= "&sessionid=$sessionid";
    echo '<td class="right"><label for="viewmenu_jump">'. get_string('viewmode','attforblock') . "&nbsp;</label>";
    popup_form("$dataurl&view=", $options, 'viewmenu', $view, '');
    if ($view == SORTEDGRIDVIEW) {
        $options = array (1 => '1 '.get_string('column','attforblock'),'2 '.get_string('columns','attforblock'),'3 '.get_string('columns','attforblock'),
                               '4 '.get_string('columns','attforblock'),'5 '.get_string('columns','attforblock'),'6 '.get_string('columns','attforblock'),
                               '7 '.get_string('columns','attforblock'),'8 '.get_string('columns','attforblock'),'9 '.get_string('columns','attforblock'),
                               '10 '.get_string('columns','attforblock'));
        $dataurl .= "&view=$view";
        popup_form("$dataurl&gridcols=", $options, 'colsmenu', $gridcols, '');
    }
    echo '</td></tr></table>';//</center>';
    if ($grouptype === 0) {
        if ($currentgroup) {
            $students = get_users_by_capability($context, 'moodle/legacy:student', '', "u.$sort ASC", '', '', $currentgroup, '', false);
        } else {
            $students = get_users_by_capability($context, 'moodle/legacy:student', '', "u.$sort ASC", '', '', '', '', false);
        }
    } else {
        $students = get_users_by_capability($context, 'moodle/legacy:student', '', "u.$sort ASC", '', '', $grouptype, '', false);
    }

	$sort = $sort == 'firstname' ? 'firstname' : 'lastname';
    /// Now we need a menu for separategroups as well!
    if ($grouptype === 0 &&
            ($groupmode == VISIBLEGROUPS ||
            ($groupmode && has_capability('moodle/site:accessallgroups', $context)))) {
        groups_print_activity_menu($cm, "attendances.php?id=$id&amp;sessionid=$sessionid&amp;grouptype=$grouptype&amp;sort=$sort");
    }
	
	$table->data[][] = '<b>'.get_string('sessiondate','attforblock').': '.userdate($sessdata->sessdate, get_string('strftimedate').', '.get_string('strftimehm', 'attforblock')).
							', "'.($sessdata->description ? $sessdata->description : get_string('nodescription', 'attforblock')).'"</b>';
	print_table($table);

    $statuses = get_statuses($attforblock->id);
	$i = 3;
  	foreach($statuses as $st) {
                switch($view) {
                    case SORTEDLISTVIEW:
		$tabhead[] = "<a href=\"javascript:select_all_in('TD', 'cell c{$i}', null);\"><u>$st->acronym</u></a>";
                        break;
                    case SORTEDGRIDVIEW:
                $tabhead[] = "<a href=\"javascript:select_all_in('INPUT', '". $st->acronym . "', null);\"><u>$st->acronym</u></a>";
                        break;
                }
		$i++;
	}
    if ($view == SORTEDLISTVIEW) {
	$tabhead[] = get_string('remarks','attforblock');
        }
	
	$firstname = "<a href=\"attendances.php?id=$id&amp;sessionid=$sessionid&amp;sort=firstname\">".get_string('firstname').'</a>';
	$lastname  = "<a href=\"attendances.php?id=$id&amp;sessionid=$sessionid&amp;sort=lastname\">".get_string('lastname').'</a>';
    if ($CFG->fullnamedisplay == 'lastname firstname') { // for better view (dlnsk)
        $fullnamehead = "$lastname / $firstname";
    } else {
        $fullnamehead = "$firstname / $lastname";
    }
	
	if ($students) {
        unset($table);

        switch($view) {
        case SORTEDLISTVIEW:     // sorted list
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
                $copyid = ($copyfrom == "-1") ? $sessionid : $copyfrom;
                $att = get_record('attendance_log', 'sessionid', $copyid, 'studentid', $student->id);
                $currentstatusid = $att===false ? -1 : $att->statusid;
                 @$table->data[$student->id][] = '<input name="student'.$student->id.'" type="radio" value="'.$st->id.'" '.($st->id == $currentstatusid ? 'checked' : '').'>';
            }
            $table->data[$student->id][] = '<input type="text" name="remarks'.$student->id.'" size="" value="'.($att ? $att->remarks : '').'">';
        }
            break;
        case SORTEDGRIDVIEW:     // sorted grid
            $table->width = '0%';

            $data = '';
            foreach ($tabhead as $hd) {
                $data = $data . $hd . '&nbsp';
            }
            print_heading($data,'center');
            
            $i = 0;
            // sanity check
            $gridcols = $gridcols < 1 ? 1 : $gridcols;
            for ($i=0; $i<$gridcols; $i++) {
                $table->head[] = '&nbsp;';
                $table->align[] = 'center';
                $table->size[] = '110px';
            }

            $i = 0;
            foreach($students as $student) {
                $i++;
                $copyid = ($copyfrom == "-1") ? $sessionid : $copyfrom;
                $att = get_record('attendance_log', 'sessionid', $copyid, 'studentid', $student->id);
                $currentstatusid = $att===false ? -1 : $att->statusid;
                $data = "<span class='userinfobox' style='font-size:80%;border:none'>" . print_user_picture($student, $course->id, $student->picture, true, true, '', fullname($student)) . "<br/>" . fullname($student) . "<br/></span>";//, $returnstring=false, $link=true, $target='');
                foreach($statuses as $st) {
                     $data = $data . '<nobr><input name="student'.$student->id.'" type="radio" class="' . $st->acronym . '" value="'.$st->id.'" '.($st->id == $currentstatusid ? 'checked' : '').'>' . $st->acronym . "</nobr> ";
                }
                $table->data[($i-1) / ($gridcols)][] = $data;
            }
            break;
        }

        echo '<form name="takeattendance" method="post" action="attendances.php">';
        print_table($table);
        echo '<input type="hidden" name="id" value="'.$id.'">';
        echo '<input type="hidden" name="sessionid" value="'.$sessionid.'">';
        echo '<input type="hidden" name="grouptype" value="'.$grouptype.'">';
        echo '<input type="hidden" name="formfrom" value="editsessvals">';
        echo '<center><input type="submit" name="esv" value="'.get_string('save','attforblock').'"></center>';
        echo '</form>';

        if (count($todaysessions)>1) {
            echo '<br/><table class="controls" cellspacing="0"><tr><td class="center">';
            echo '<label for="copysessionmenu_jump">'. get_string('copyfrom','attforblock') . "&nbsp;</label>";
            popup_form("$dataurl&copyfrom=", $optionssessions, 'copysessionmenu', $sessionid, '');
            echo '</td></tr></table>';
        }

        } else {
		print_heading(get_string('nothingtodisplay'), 'center');
	}
	 
	echo get_string('status','attforblock').':<br />'; 
	foreach($statuses as $st) {
		echo $st->acronym.' - '.$st->description.'<br />';
	}

    print_footer($course);
    
?>
