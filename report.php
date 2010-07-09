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
    $view         		= optional_param('view', 'weeks', PARAM_ALPHA);        // which page to show
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
	} else {	// display attendance report
        /// find out current groups mode
        $groupmode = groups_get_activity_groupmode($cm);
        $currentgroup = groups_get_activity_group($cm, true);
        if ($groupmode == VISIBLEGROUPS ||
                ($groupmode && has_capability('moodle/site:accessallgroups', $context))) {
            groups_print_activity_menu($cm, "report.php?id=$id&amp;sort=$sort");
        }

		echo '<div align="right">'.helpbutton ('report', get_string('help'), 'attforblock', true, true, '', true).'</div>';

        if ($currentgroup) {
        	$students = get_users_by_capability($context, 'moodle/legacy:student', '', "u.$sort ASC", '', '', $currentgroup, '', false);
        } else {
        	$students = get_users_by_capability($context, 'moodle/legacy:student', '', "u.$sort ASC", '', '', '', '', false);
        }
    
        // display date interval selector
        $rec = get_record_sql("SELECT MIN(sessdate) AS min, MAX(sessdate) AS max 
        						 FROM {$CFG->prefix}attendance_sessions 
        						WHERE courseid=$course->id AND sessdate >= $course->startdate");
        $firstdate = $rec->min;
        $lastdate = $rec->max;
        $now = time();
        $current = $current == 0 ? $now : $current;
        list(,,,$wday, $syear, $smonth, $sday) = array_values(usergetdate($firstdate));
		$wday = $wday == 0 ? 7 : $wday; //////////////////////////////////////////////////// Нужна проверка настройки календаря
		$startdate = make_timestamp($syear, $smonth, $sday-$wday+1); //GMT timestamp but for local midnight of monday
        
		$options['all'] = get_string('alltaken','attforblock');
		$options['weeks'] = get_string('weeks','attforblock');
		$options['months'] = get_string('months','attforblock');
		echo '<center>'.helpbutton ('display', get_string('display','attforblock'), 'attforblock', true, false, '', true).get_string('display','attforblock').': ';
		if (isset($_GET['view'])) //{
	        set_current_view($course->id, $_GET['view']);
	    $view = get_current_view($course->id);
		popup_form("report.php?id=$id&amp;sort=$sort&amp;view=", $options, 'viewmenu', $view, '');
		
		$out = '';
		$list = array();
		if ($view === 'weeks') {
			$format = get_string('strftimedm', 'attforblock');
			for ($i = 0, $monday = $startdate; $monday <= $lastdate; $i++, $monday += ONE_WEEK) {
				if (count_records_select('attendance_sessions', "courseid={$course->id} AND sessdate >= ".$monday." AND sessdate < ".($monday + ONE_WEEK))) {
					$list[] = $monday;
				}
			}
		} elseif ($view === 'months') {
			$startdate = make_timestamp($syear, $smonth, 1);
			$format = '%B';
			for ($i = 0, $month = $startdate; $month <= $lastdate; $i++, $month = make_timestamp($syear, $smonth+$i, 1)) {
				if (count_records_select('attendance_sessions', "courseid={$course->id} AND sessdate >= ".$month." AND sessdate < ".make_timestamp($syear, $smonth+$i+1, 1))) {
					$list[] = $month;
				}
			}
		}
		$found = false;
		for ($i = count($list)-1; $i >= 0; $i--) {
			if ($list[$i] <= $current && !$found) {
				$found = true;
				$current = $list[$i];
				$out = '<b>'.userdate($list[$i], $format).'</b> / '.$out;
			} else {
				$out = "\n<a href=\"report.php?id=$id&amp;current={$list[$i]}&amp;sort=$sort\">".userdate($list[$i], $format)."</a> / ".$out;
			}
		}
		echo substr($out, 0, -2)."</center>\n";

        $statuses = get_statuses($course->id);
        $allstatuses = get_statuses($course->id, false);
		
		if ($view === 'weeks') {
			$where = "courseid={$course->id} AND sessdate >= $course->startdate AND sessdate >= $current AND sessdate < ".($current + ONE_WEEK);
		} elseif ($view === 'months') {
			list(,,,, $syear, $smonth, $sday) = array_values(usergetdate($current));
			$where = "courseid={$course->id} AND sessdate >= $course->startdate AND sessdate >= $current AND sessdate < ".make_timestamp($syear, $smonth+1, 1);
		} else {
			$where = "courseid={$course->id} AND sessdate >= $course->startdate AND sessdate <= ".time();
		}
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
            $allowtake = has_capability('mod/attforblock:takeattendances', $context);
            $allowchange = has_capability('mod/attforblock:changeattendances', $context);
			foreach($course_sess as $sessdata) {
                if (count_records('attendance_log', 'sessionid', $sessdata->id)) {
                    if ($allowchange) {
                        $table->head[] = "<a href=\"attendances.php?id=$id&amp;sessionid={$sessdata->id}\">".
                                            userdate($sessdata->sessdate, get_string('strftimedm', 'attforblock').'<br />('.get_string('strftimehm', 'attforblock').')').
                                         '</a>';
                    } else {
                        $table->head[] = userdate($sessdata->sessdate, get_string('strftimedm', 'attforblock').'<br />('.get_string('strftimehm', 'attforblock').')');
                    }

                } else {
                    if ($allowtake) {
                        $table->head[] = "<a href=\"attendances.php?id=$id&amp;sessionid={$sessdata->id}\">".
                                            userdate($sessdata->sessdate, get_string('strftimedm', 'attforblock').'<br />('.get_string('strftimehm', 'attforblock').')').
                                         '</a>';
                    } else {
                        $table->head[] = userdate($sessdata->sessdate, get_string('strftimedm', 'attforblock').'<br />('.get_string('strftimehm', 'attforblock').')');
                    }
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
			$table->head[] = get_string('grade');//.'&nbsp;/&nbsp;'.$maxgrade;

			$table->align[] = 'right';
			$table->size[] = '1px';
			$table->head[] = '%';
			
			foreach($students as $student) {
				$table->data[$student->id][] = print_user_picture($student->id, $course->id, $student->picture, 20, true, true);
				$table->data[$student->id][] = "<a href=\"view.php?id=$id&amp;student={$student->id}\">".fullname($student).'</a>';
				foreach($course_sess as $sessdata) {
					if ($att = get_record('attendance_log', 'sessionid', $sessdata->id, 'studentid', $student->id)) {
						if (isset($statuses[$att->statusid])) {
							$table->data[$student->id][] = $statuses[$att->statusid]->acronym;
						} else {
							$table->data[$student->id][] = '<font color="red"><b>'.$allstatuses[$att->statusid]->acronym.'</b></font>';
						}
					} else {
						$table->data[$student->id][] = '-';
					}
				}
				foreach($statuses as $st) {
					$table->data[$student->id][] = get_attendance($student->id, $course, $st->id);
				}
				$table->data[$student->id][] = get_grade($student->id, $course).'&nbsp;/&nbsp;'.get_maxgrade($student->id, $course);
				$table->data[$student->id][] = get_percent($student->id, $course).'%';
			}
			echo '<br />';
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