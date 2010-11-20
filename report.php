<?PHP // $Id: report.php,v 1.1.2.4 2009/02/28 16:49:17 dlnsk Exp $

//  generates sessions

    require_once('../../config.php');    
	require_once($CFG->libdir.'/blocklib.php');
	require_once('locallib.php');	
	
    define('USER_SMALL_CLASS', 20);   // Below this is considered small
    define('USER_LARGE_CLASS', 200);  // Above this is considered large
    define('DEFAULT_PAGE_SIZE', 20);

    $id           		= required_param('id', PARAM_INT);
    $group        		= optional_param('group', -1, PARAM_INT);              // Group to show
    $view         		= optional_param('view', NULL, PARAM_ALPHA);        // which page to show
	$current			= optional_param('current', 0, PARAM_INT);
    $sort         		= optional_param('sort', 'lastname', PARAM_ALPHA);
	
    if ($id) {
        if (! $cm = get_record('course_modules', 'id', $id)) {
            error('Course Module ID was incorrect');
        }
        if (! $course = get_record('course', 'id', $cm->course)) {
            error('Course is misconfigured');
        }
	    if (! $attforblock = get_record('attforblock', 'id', $cm->instance)) {
	        error("Course module is incorrect");
	    }
    }

    require_login($course->id);

    if (! $user = get_record('user', 'id', $USER->id) ) {
        error("No such user in this course");
    }
    
    if (!$context = get_context_instance(CONTEXT_MODULE, $cm->id)) {
        print_error('badcontext');
    }

    if ($view)
        set_current_view($course->id, $_GET['view']);
    else
	    $view = get_current_view($course->id);

    
    require_capability('mod/attforblock:viewreports', $context);

	//add info to log
	add_to_log($course->id, 'attendance', 'report displayed', 'mod/attforblock/report.php?id='.$id, $user->lastname.' '.$user->firstname);

	/// Print headers
    $navlinks[] = array('name' => $attforblock->name, 'link' => "view.php?id=$id", 'type' => 'activity');
    $navlinks[] = array('name' => get_string('report', 'attforblock'), 'link' => null, 'type' => 'activityinstance');
    $navigation = build_navigation($navlinks);
    print_header("$course->shortname: ".$attforblock->name.' - ' .get_string('report','attforblock'), $course->fullname,
                 $navigation, "", "", true, "&nbsp;", navmenu($course));
    
    show_tabs($cm, $context, 'report');
    
	$sort = $sort == 'firstname' ? 'firstname' : 'lastname';
	
	if(!count_records('attendance_sessions', 'courseid', $course->id)) {	// no session exists for this course
		redirect("sessions.php?id=$cm->id&amp;action=add");			
	} else {
        if ($current == 0)
            $current = get_current_date($course->id);
        else
            set_current_date ($course->id, $current);

        list($startdate, $enddate, $currentgroup) =
                print_filter_controls("report.php", $id, 0, $sort, GROUP_SELECTOR);

		if ($startdate && $enddate) {
			$where = "courseid={$course->id} AND sessdate >= $course->startdate AND sessdate >= $startdate AND sessdate < $enddate";
		} else {
			$where = "courseid={$course->id} AND sessdate >= $course->startdate";
		}

        if ($currentgroup) {
            $where .= " AND (groupid=0 OR groupid=".$currentgroup.")";
        	$students = get_users_by_capability($context, 'moodle/legacy:student', '', "u.$sort ASC", '', '', $currentgroup, '', false);
        } else {
        	$students = get_users_by_capability($context, 'moodle/legacy:student', '', "u.$sort ASC", '', '', '', '', false);
        }

        $statuses = get_statuses($course->id);
        $allstatuses = get_statuses($course->id, false);


		if ($students and
		       ($course_sess = get_records_select('attendance_sessions', $where, 'sessdate ASC'))) {
			
		    $firstname = "<a href=\"report.php?id=$id&amp;sort=firstname\">".get_string('firstname').'</a>';
			$lastname  = "<a href=\"report.php?id=$id&amp;sort=lastname\">".get_string('lastname').'</a>';
		    if ($CFG->fullnamedisplay == 'lastname firstname') { // for better view (dlnsk)
		        $fullnamehead = "$lastname / $firstname";
		    } else {
		        $fullnamehead = "$firstname / $lastname";
		    }
		    
		    $table->head[] = '';
			$table->align[] = '';
			$table->size[] = '1px';
		    $table->head[] = $fullnamehead;
			$table->align[] = 'left';
			$table->size[] = '';
            $table->class = 'generaltable attwidth';
            $allowtake = has_capability('mod/attforblock:takeattendances', $context);
            $allowchange = has_capability('mod/attforblock:changeattendances', $context);
            $groups = groups_get_all_groups($course->id);
			foreach($course_sess as $sessdata) {
                if (count_records('attendance_log', 'sessionid', $sessdata->id)) {
                    if ($allowchange) {
                        $sessdate = "<a href=\"attendances.php?id=$id&amp;sessionid={$sessdata->id}&amp;grouptype={$sessdata->groupid}\">".
                                            userdate($sessdata->sessdate, get_string('strftimedm', 'attforblock').'<br />('.get_string('strftimehm', 'attforblock').')').
                                         '</a>';
                    } else {
                        $sessdate = userdate($sessdata->sessdate, get_string('strftimedm', 'attforblock').'<br />('.get_string('strftimehm', 'attforblock').')');
                    }
                    $sesstype = $sessdata->groupid ? $groups[$sessdata->groupid]->name : get_string('commonsession', 'attforblock');
                    $table->head[] = $sessdate.'<br />'.$sesstype;
                } else {
                    if ($allowtake) {
                        $sessdate = "<a href=\"attendances.php?id=$id&amp;sessionid={$sessdata->id}&amp;grouptype={$sessdata->groupid}\">".
                                            userdate($sessdata->sessdate, get_string('strftimedm', 'attforblock').'<br />('.get_string('strftimehm', 'attforblock').')').
                                         '</a>';
                    } else {
                        $sessdate = userdate($sessdata->sessdate, get_string('strftimedm', 'attforblock').'<br />('.get_string('strftimehm', 'attforblock').')');
                    }
                    $sesstype = $sessdata->groupid ? $groups[$sessdata->groupid]->name : get_string('commonsession', 'attforblock');
                    $table->head[] = $sessdate.'<br />'.$sesstype;
                }
				$table->align[] = 'center';
				$table->size[] = '1px';
			}
			for ($i=0; $i<5; $i++) {
				$table->align[] = 'center';
				$table->size[] = '1px';
			}

			foreach($statuses as $st) {
				$table->head[] = $st->acronym;
			}

            if ($attforblock->grade) {
                $table->head[] = get_string('grade');//.'&nbsp;/&nbsp;'.$maxgrade;

                $table->align[] = 'right';
                $table->size[] = '1px';
                $table->head[] = '%';
            }
			
			foreach($students as $student) {
				$table->data[$student->id][] = print_user_picture($student->id, $course->id, $student->picture, 20, true, true);
				$table->data[$student->id][] = "<a href=\"view.php?id=$id&amp;student={$student->id}\">".fullname($student).'</a>';
                $studgroups = groups_get_all_groups($COURSE->id, $student->id);
				foreach($course_sess as $sessdata) {
					if ($att = get_record('attendance_log', 'sessionid', $sessdata->id, 'studentid', $student->id)) {
						if (isset($statuses[$att->statusid])) {
							$table->data[$student->id][] = $statuses[$att->statusid]->acronym;
						} else {
							$table->data[$student->id][] = '<font color="red"><b>'.$allstatuses[$att->statusid]->acronym.'</b></font>';
						}
					} else {
                        if ($sessdata->groupid && !$studgroups[$sessdata->groupid])
                            $table->data[$student->id][] = '';
                        else
                            $table->data[$student->id][] = '?';
					}
				}
				foreach($statuses as $st) {
					$table->data[$student->id][] = get_attendance($student->id, $course, $st->id);
				}
                if ($attforblock->grade) {
                    $table->data[$student->id][] = get_grade($student->id, $course).'&nbsp;/&nbsp;'.get_maxgrade($student->id, $course);
                    $table->data[$student->id][] = get_percent($student->id, $course).'%';
                }
			}
    		print_table($table);
		} else {
			print_heading(get_string('nothingtodisplay'), 'center');
		}

		echo get_string('status','attforblock').':<br />'; 
		foreach($statuses as $st) {
			echo $st->acronym.' - '.$st->description.'<br />';
		}
	}
	print_footer($course);
	exit;
?>