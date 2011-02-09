<?php
global $CFG;
require_once($CFG->libdir.'/gradelib.php');

define('ONE_DAY', 86400);   // Seconds in one day
define('ONE_WEEK', 604800);   // Seconds in one week

define('COMMONSESSION', 0);
define('GROUPSESSION', 1);

define('WITHOUT_SELECTOR', 0);
define('GROUP_SELECTOR', 1);
define('SESSION_TYPE_SELECTOR', 2);

define('SORTEDLISTVIEW', 0);
define('SORTEDGRIDVIEW', 1);

function show_tabs($cm, $context, $currenttab='sessions')
{
	$toprow = array();
    if (has_capability('mod/attforblock:manageattendances', $context) or
            has_capability('mod/attforblock:takeattendances', $context) or
            has_capability('mod/attforblock:changeattendances', $context)) {
        $toprow[] = new tabobject('sessions', 'manage.php?id='.$cm->id,
                    get_string('sessions','attforblock'));
    }

    if (has_capability('mod/attforblock:manageattendances', $context)) {
        $toprow[] = new tabobject('add', "sessions.php?id=$cm->id&amp;action=add",
                    get_string('add','attforblock'));
    }
    if (has_capability('mod/attforblock:viewreports', $context)) {
	    $toprow[] = new tabobject('report', 'report.php?id='.$cm->id,
	                get_string('report','attforblock'));
    }
    if (has_capability('mod/attforblock:export', $context)) {
	    $toprow[] = new tabobject('export', 'export.php?id='.$cm->id,
	                get_string('export','quiz'));
    }
    if (has_capability('mod/attforblock:changepreferences', $context)) {
	    $toprow[] = new tabobject('settings', 'attsettings.php?id='.$cm->id,
                    get_string('settings','attforblock'));
    }

    $tabs = array($toprow);
    print_tabs($tabs, $currenttab);
}


//getting settings for course

function get_statuses($attendanceid, $onlyvisible = true)
{
    global $DB;

  	if ($onlyvisible) {
  		$result = get_records_select('attendance_statuses', "attendanceid = $attendanceid AND visible = 1 AND deleted = 0", 'grade DESC');
  	} else {
  		$result = get_records_select('attendance_statuses', "attendanceid = $attendanceid AND deleted = 0", 'grade DESC');
//  		$result = get_records('attendance_statuses', 'courseid', $courseid, 'grade DESC');
  	}
    return $result;
}	

//gets attendance status for a student, returns count

function get_attendance($userid, $course, $attendance, $statusid=0)
{
	global $CFG, $DB;
	$qry = "SELECT count(*) as cnt 
		  	  FROM {attendance_log} al
			  JOIN {attendance_sessions} ats
			    ON al.sessionid = ats.id
			 WHERE ats.attendanceid = :aid
			  	AND ats.sessdate >= :cstartdate
	         	AND al.studentid = :uid";
	if ($statusid) {
		$qry .= " AND al.statusid = :sid";
	}
	
	return $DB->count_records_sql($qry, array('aid' => $attendance->id, 'cstartdate' => $course->startdate, 'uid'=>$userid, 'sid'=>$statusid ));
}

function get_grade($userid, $course, $attendance)
{
	global $CFG, $DB;
	$logs = $DB->get_records_sql("SELECT l.id, l.statusid, l.statusset
							FROM {attendance_log} l
							JOIN {attendance_sessions} s
							  ON l.sessionid = s.id
						   WHERE l.studentid = :usid
                                                     AND s.attendanceid = :aid
						     AND s.courseid  = :cid
						     AND s.sessdate >= :cstartdate", array('usid' => $userid, 'aid' => $attendance->id, 'cid' => $course->id, 'cstartdate' => $course->startdate ));
	$result = 0;
	if ($logs) {
		$stat_grades = $DB->records_to_menu($DB->get_records('attendance_statuses', array('attendanceid'=> $attendance->id)), 'id', 'grade');
		foreach ($logs as $log) {
			$result += $stat_grades[$log->statusid];
		}
	}
	
	return $result;
}

//temporary solution, for support PHP 4.3.0 which minimal requirement for Moodle 1.9.x
function local_array_intersect_key($array1, $array2) {
    $result = array();
    foreach ($array1 as $key => $value) {
        if (isset($array2[$key])) {
            $result[$key] = $value;
        }
    }
    return $result;
}

function get_maxgrade($userid, $course, $attendance)
{
	global $CFG, $DB;
	$logs = $DB->get_records_sql("SELECT l.id, l.statusid, l.statusset
							FROM {attendance_log} l
							JOIN {attendance_sessions} s
							  ON l.sessionid = s.id
						   WHERE l.studentid = :usid
                                                     AND s.attendanceid = :aid
						     AND s.courseid  = :cid
						     AND s.sessdate >= :cstartdate", array('usid' => $userid, 'aid' => $attendance->id, 'cid' => $course->id, 'cstartdate' => $course->startdate ));

	$maxgrade = 0;
	if ($logs) {
		$stat_grades = records_to_menu(get_records('attendance_statuses', array('attendanceid'=> $attendance->id)), 'id', 'grade');
		foreach ($logs as $log) {
			$ids = array_flip(explode(',', $log->statusset));
//			$grades = array_intersect_key($stat_grades, $ids); // require PHP 5.1.0 and higher
			$grades = local_array_intersect_key($stat_grades, $ids); //temporary solution, for support PHP 4.3.0 which minimal requirement for Moodle 1.9.x
			$maxgrade += max($grades);
		}
	}
	
	return $maxgrade;
}

function get_percent_adaptive($userid, $course) // NOT USED
{
	global $CFG, $DB;
	$logs = $DB->get_records_sql("SELECT l.id, l.statusid, l.statusset
							FROM {attendance_log} l
							JOIN {attendance_sessions} s
							  ON l.sessionid = s.id
						   WHERE l.studentid = :usid
                                                     AND s.attendanceid = :aid
						     AND s.courseid  = :cid
						     AND s.sessdate >= :cstartdate", array('usid' => $userid, 'aid' => $attendance->id, 'cid' => $course->id, 'cstartdate' => $course->startdate ));
	$result = 0;
	if ($logs) {
		$stat_grades = $DB->records_to_menu($DB->get_records('attendance_statuses', array('attendanceid'=> $attendance->id)), 'id', 'grade');
		
		$percent = 0;
		foreach ($logs as $log) {
			$ids = array_flip(explode(',', $log->statusset));
			$grades = array_intersect_key($stat_grades, $ids);
			$delta = max($grades) - min($grades);
			$percent += $stat_grades[$log->statusid] / $delta;
		}
		$result = $percent / count($logs) * 100;
	}
	if (!$dp = grade_get_setting($course->id, 'decimalpoints')) {
		$dp = $CFG->grade_decimalpoints;
	}
	
	return sprintf("%0.{$dp}f", $result);
}

function get_percent($userid, $course, $attforblock)
{
    global $CFG;
    
    $maxgrd = get_maxgrade($userid, $course, $attforblock);
    if ($maxgrd == 0) {
    	$result = 0;
    } else {
    	$result = get_grade($userid, $course, $attforblock) / $maxgrd * 100;
    }
    if ($result < 0) {
        $result = 0;
    }
	if (!$dp = grade_get_setting($course->id, 'decimalpoints')) {
		$dp = $CFG->grade_decimalpoints;
	}

	return sprintf("%0.{$dp}f", $result);
}

function set_current_view($courseid, $view) {
    global $SESSION;

    return $SESSION->currentattview[$courseid] = $view;
}

function get_current_view($courseid, $defaultview='weeks') {
    global $SESSION;

    if (isset($SESSION->currentattview[$courseid]))
        return $SESSION->currentattview[$courseid];
    else
        return $defaultview;
}

function set_current_date($courseid, $date) {
    global $SESSION;

    return $SESSION->currentattdate[$courseid] = $date;
}

function get_current_date($courseid) {
    global $SESSION;

    if (isset($SESSION->currentattdate[$courseid]))
        return $SESSION->currentattdate[$courseid];
    else
        return time();
}

function print_row($left, $right) {
    echo "\n<tr><td nowrap=\"nowrap\" align=\"right\" valign=\"top\" class=\"cell c0\">$left</td><td align=\"left\" valign=\"top\" class=\"info c1\">$right</td></tr>\n";
}

function print_attendance_table($user,  $course, $attforblock) {

	$complete = get_attendance($user->id, $course, $attforblock);
	
    echo '<table border="0" cellpadding="0" cellspacing="0" class="list">';
    print_row(get_string('sessionscompleted','attforblock').':', "<strong>$complete</strong>");
    $statuses = get_statuses($attforblock->id);
	foreach($statuses as $st) {
		print_row($st->description.': ', '<strong>'.get_attendance($user->id, $course,  $attforblock, $st->id).'</strong>');
	}

    if ($attforblock->grade) {
        $percent = get_percent($user->id, $course, $attforblock).'&nbsp;%';
        $grade = get_grade($user->id, $course, $attforblock);
        print_row(get_string('attendancepercent','attforblock').':', "<strong>$percent</strong>");
        print_row(get_string('attendancegrade','attforblock').':', "<strong>$grade</strong> / ".get_maxgrade($user->id, $course, $attforblock));
    }
    print_row('&nbsp;', '&nbsp;');
  	echo '</table>';
	
}

function print_user_attendaces($user, $cm, $attforblock,  $course = 0, $printing = null) {
	global $CFG, $COURSE, $mode, $current, $view, $id, $studentid, $DB;
		
	echo '<table class="userinfobox">';
    if (!$printing) {
		echo '<tr>';
	    echo '<td colspan="2" class="generalboxcontent"><div align="right">'.
	    		helpbutton('studentview', get_string('attendancereport','attforblock'), 'attforblock', true, false, '', true).
	    		"<a href=\"view.php?id={$cm->id}&amp;student={$user->id}&amp;mode=$mode&amp;printing=yes\" target=\"_blank\">[".get_string('versionforprinting','attforblock').']</a></div></td>';
	    echo '</tr>';
    }
//    echo '<tr>';
//    echo '<th colspan="2"><h2 class="main help"><center>'.get_string('attendancereport','attforblock').helpbutton('studentview', get_string('attendancereport','attforblock'), 'attforblock', true, false, '', true).'</center></h1></th>';
//    echo '</tr>';
    echo '<tr>';
    echo '<td class="left side">';
    print_user_picture($user->id, $COURSE->id, $user->picture, true);
    echo '</td>';
    echo '<td class="generalboxcontent">';
    echo '<font size="+1"><b>'.fullname($user).'</b></font>';
	if ($course) {
		echo '<hr />';
		$complete = get_attendance($user->id, $course, $attforblock);
		if($complete) {
			print_attendance_table($user,  $course, $attforblock);
		} else {
			echo get_string('attendancenotstarted','attforblock');
		}
	} else {
        $stqry = "SELECT ats.id,ats.courseid AS 'cid',ats.attendanceid AS 'aid'
					FROM {attendance_log} al
					JOIN {attendance_sessions} ats
					  ON al.sessionid = ats.id
				   WHERE al.studentid = ?
				GROUP BY cid
				ORDER BY cid,aid asc";
		$recs = get_records_sql($stqry, array($user->id));
		foreach ($recs as $rec) {
			echo '<hr />';
			echo '<table border="0" cellpadding="0" cellspacing="0" width="100%" class="list1">';
			$nextcourse = $DB->get_record('course', array('id'=> $rec['cid']));
                        $nextattendance = $DB->get_record('attforblock', array('id'=> $rec['aid']));
			echo '<tr><td valign="top"><strong>'.$nextcourse->fullname.' - '.$nextattendance->name . '</strong></td>';
			echo '<td align="right">';
			$complete = get_attendance($user->id, $nextcourse, $nextattendance);
			if($complete) {
				print_attendance_table($user,  $nextcourse, $nextattendance);
			} else {
				echo get_string('attendancenotstarted','attforblock');
			}
			echo '</td></tr>';
			echo '</table>';
		}
	}

	
	if ($course) {
        if ($current == 0)
            $current = get_current_date($course->id);
        else
            set_current_date ($course->id, $current);

        $ret = print_filter_controls("view.php", $id, $studentid);
        $startdate = $ret['startdate'];
        $enddate = $ret['enddate'];

        if ($startdate && $enddate) {
            $where = "ats.courseid=:cid AND al.studentid = :uid AND ats.sessdate >= :sdate AND ats.sessdate < :edate";
        } else {
            $where = "ats.courseid=:cid AND al.studentid = :uid";
        }

		$stqry = "SELECT ats.id,ats.sessdate,ats.description,al.statusid,al.remarks
					FROM {attendance_log} al
					JOIN {attendance_sessions} ats
					  ON al.sessionid = ats.id";
        $stqry .= " WHERE " . $where;
        $stqry .= " ORDER BY ats.sessdate asc";
        
		if ($sessions = $DB->get_records_sql($stqry, array('cid' => $course->id, 'uid'=> $user->id, 'sdate'=> $startdate, 'edate'=> $enddate))) {
	     	$statuses = get_statuses($course->id);

            $i = 0;
			$table->head = array('#', get_string('date'), get_string('time'), get_string('description','attforblock'), get_string('status','attforblock'), get_string('remarks','attforblock'));
			$table->align = array('', '', 'left', 'left', 'center', 'left');
			$table->size = array('1px', '1px', '1px', '*', '1px', '1px');
            $table->class = 'generaltable attwidth';
			foreach($sessions as $key=>$sessdata)
			{
                $i++;
                $table->data[$sessdata->id][] = $i;
                $table->data[$sessdata->id][] = userdate($sessdata->sessdate, get_string('strftimedmyw', 'attforblock'));
				$table->data[$sessdata->id][] = userdate($sessdata->sessdate, get_string('strftimehm', 'attforblock'));
                $table->data[$sessdata->id][] = empty($sessdata->description) ? get_string('nodescription', 'attforblock') : $sessdata->description;
                $table->data[$sessdata->id][] = $statuses[$sessdata->statusid]->description;
                $table->data[$sessdata->id][] = $sessdata->remarks;
            }
            print_table($table);
		}
	}
	echo '</td></tr><tr><td>&nbsp;</td></tr></table></div>';
}

function print_filter_controls($url, $id, $studentid=0, $sort=NULL, $printselector=WITHOUT_SELECTOR) {

    global $CFG, $SESSION, $current, $view, $cm;

    $date = usergetdate($current);
    $mday = $date['mday'];
    $wday = $date['wday'];
    $mon = $date['mon'];
    $year = $date['year'];

    $curdatecontrols = '';
    $curdatetxt = '';
    switch ($view) {
        case 'days':
            $format = get_string('strftimedm', 'attforblock');
            $startdate = make_timestamp($year, $mon, $mday);
            $enddate = make_timestamp($year, $mon, $mday + 1);
            $prevcur = make_timestamp($year, $mon, $mday - 1);
            $nextcur = make_timestamp($year, $mon, $mday + 1);
            $curdatetxt =  userdate($startdate, $format);
            break;
        case 'weeks':
            $format = get_string('strftimedm', 'attforblock');
            $startdate = make_timestamp($year, $mon, $mday - $wday + 1);
            $enddate = make_timestamp($year, $mon, $mday + 7 - $wday + 1) - 1;
            $prevcur = $startdate - WEEKSECS;
            $nextcur = $startdate + WEEKSECS;
            $curdatetxt = userdate($startdate, $format)." - ".userdate($enddate, $format);
            break;
        case 'months':
            $format = '%B';
            $startdate = make_timestamp($year, $mon);
            $enddate = make_timestamp($year, $mon + 1);
            $prevcur = make_timestamp($year, $mon - 1);
            $nextcur = make_timestamp($year, $mon + 1);
            $curdatetxt = userdate($startdate, $format);
            break;
        case 'alltaken':
            $startdate = 1;
            $enddate = time();
            break;
        case 'all':
            $startdate = 0;
            $enddate = 0;
            break;
    }

    $link = $url . "?id=$id" . ($sort ? "&amp;sort=$sort" : "") . ($studentid ? "&amp;student=$studentid" : "");

    $currentgroup = -1;
    $sessiontypeselector = '';
    if ($printselector === GROUP_SELECTOR) {
        $groupmode = groups_get_activity_groupmode($cm);
        $currentgroup = groups_get_activity_group($cm, true);
        $groupselector = '';
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        if ($groupmode == VISIBLEGROUPS ||
                ($groupmode && has_capability('moodle/site:accessallgroups', $context))) {
            $groupselector = groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/attforblock/' . $link, true);
        }
    } elseif ($printselector === SESSION_TYPE_SELECTOR and $groupmode = groups_get_activity_groupmode($cm)) {
        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $context)) {
            $allowedgroups = groups_get_all_groups($cm->course, 0, $cm->groupingid); // any group in grouping (all if groupings not used)
            // detect changes related to groups and fix active group
            if (!empty($SESSION->activegroup[$cm->course][VISIBLEGROUPS][$cm->groupingid])) {
                if (!array_key_exists($SESSION->activegroup[$cm->course][VISIBLEGROUPS][$cm->groupingid], $allowedgroups)) {
                    // active group does not exist anymore
                    unset($SESSION->activegroup[$cm->course][VISIBLEGROUPS][$cm->groupingid]);
                }
            }
            if (!empty($SESSION->activegroup[$cm->course]['aag'][$cm->groupingid])) {
                if (!array_key_exists($SESSION->activegroup[$cm->course]['aag'][$cm->groupingid], $allowedgroups)) {
                    // active group does not exist anymore
                    unset($SESSION->activegroup[$cm->course]['aag'][$cm->groupingid]);
                }
            }

        } else {
            $allowedgroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid); // only assigned groups
            // detect changes related to groups and fix active group
            if (isset($SESSION->activegroup[$cm->course][SEPARATEGROUPS][$cm->groupingid])) {
                if ($SESSION->activegroup[$cm->course][SEPARATEGROUPS][$cm->groupingid] == 0) {
                    if ($allowedgroups) {
                        // somebody must have assigned at least one group, we can select it now - yay!
                        unset($SESSION->activegroup[$cm->course][SEPARATEGROUPS][$cm->groupingid]);
                    }
                } else {
                    if (!array_key_exists($SESSION->activegroup[$cm->course][SEPARATEGROUPS][$cm->groupingid], $allowedgroups)) {
                        // active group not allowed or does not exist anymore
                        unset($SESSION->activegroup[$cm->course][SEPARATEGROUPS][$cm->groupingid]);
                    }
                }
            }
        }

        $group = optional_param('group', -2, PARAM_INT);
        if (!array_key_exists('attsessiontype', $SESSION)) {
            $SESSION->attsessiontype = array();
        }
        if ($group > -2) {
            $SESSION->attsessiontype[$cm->course] = $group;
        } elseif (!array_key_exists($cm->course, $SESSION->attsessiontype)) {
            $SESSION->attsessiontype[$cm->course] = -1;
        }
        
        if ($group == -1) {
            $currentgroup = $group;
            unset($SESSION->activegroup[$cm->course][VISIBLEGROUPS][$cm->groupingid]);
            unset($SESSION->activegroup[$cm->course]['aag'][$cm->groupingid]);
            unset($SESSION->activegroup[$cm->course][SEPARATEGROUPS][$cm->groupingid]);
        } else {
            $currentgroup = groups_get_activity_group($cm, true);
            if ($currentgroup == 0 and $SESSION->attsessiontype[$cm->course] == -1) {
                $currentgroup = -1;
            }
        }

        $selector = array();
        if ($allowedgroups or $groupmode == VISIBLEGROUPS or
                has_capability('moodle/site:accessallgroups', $context)) {
            $selector[-1] = get_string('all', 'attforblock');
        }
        if ($groupmode == VISIBLEGROUPS) {
            $selector[0] = get_string('commonsessions', 'attforblock');
        }

        if ($allowedgroups) {
            foreach ($allowedgroups as $group) {
                $selector[$group->id] = format_string($group->name);
            }
        }

        if (count($selector) > 1) {
            $sessiontypeselector = popup_form($url.'?id='.$cm->id.'&amp;group=', $selector, 'selectgroup', $currentgroup, '', '', '', true, 'self', get_string('sessions', 'attforblock'));
        }

        $sessiontypeselector = '<div class="groupselector">'.$sessiontypeselector.'</div>';
    }


    $views['all'] = get_string('all','attforblock');
    $views['alltaken'] = get_string('alltaken','attforblock');
    $views['months'] = get_string('months','attforblock');
    $views['weeks'] = get_string('weeks','attforblock');
    $views['days'] = get_string('days','attforblock');
    $viewcontrols = '<nobr>';
    foreach ($views as $key => $sview) {
        if ($key != $view)
            $viewcontrols .= "<span class=\"attbtn\"><a href=\"{$link}&amp;view={$key}\">$sview</a></span>";
        else
            $viewcontrols .= "<span class=\"attcurbtn\">$sview</span>";
    }
    $viewcontrols .= '</nobr>';

    echo "<div class=\"attfiltercontrols attwidth\">";
    echo "<table width=\"100%\"><tr>";
    if ($printselector === GROUP_SELECTOR) {
        echo "<td width=\"45%\">$groupselector</td>";
    } elseif ($printselector === SESSION_TYPE_SELECTOR) {
        echo "<td width=\"45%\">$sessiontypeselector</td>";
    }

    if ($curdatetxt) {
        $curdatecontrols = "<a href=\"{$link}&amp;current=$prevcur\"><span class=\"arrow \">◄</span></a>";
        $curdatecontrols .= "<form id =\"currentdate\" action=\"$url\" method=\"get\" style=\"display:inline;\">";
        $curdatecontrols .= " <button title=\"" . get_string('calshow','attforblock') . "\" id=\"show\" type=\"button\">$curdatetxt</button> ";
        $curdatecontrols .= "<input type=\"hidden\" name=\"id\" value=\"$id\" />";
        if ($sort)
            $curdatecontrols .= "<input type=\"hidden\" name=\"sort\" value=\"$sort\" />";
        if ($studentid)
            $curdatecontrols .= "<input type=\"hidden\" name=\"student\" value=\"$studentid\" />";
        $curdatecontrols .= "<input type=\"hidden\" id=\"current\" name=\"current\" value=\"\" />";
        $curdatecontrols .= "</form>";
        $curdatecontrols .= "<a href=\"{$link}&amp;current=$nextcur\"><span class=\"arrow \">►</span></a>";
        plug_yui_calendar($current);
    }
    echo "<td width=\"20%\" align=\"center\">$curdatecontrols</td>";
    echo "<td width=\"35%\" align=\"right\">$viewcontrols</td></tr></table>";

    echo "</div>";

    return array('startdate' => $startdate, 'enddate' => $enddate, 'currentgroup' => $currentgroup);
}

function plug_yui_calendar($current) {
    global $CFG;
    
    require_js(array('yui_dom-event', 'yui_dragdrop', 'yui_element', 'yui_button', 'yui_container', 'yui_calendar'));

    echo "<script type=\"text/javascript\">\n";
    echo "var cal_close = \"" . get_string('calclose','attforblock') . "\";";
    echo "var cal_today = \"" . get_string('caltoday','attforblock') . "\";";
    echo "var cal_months = [" . get_string('calmonths','attforblock') . "];";
    echo "var cal_week_days = [" . get_string('calweekdays','attforblock') . "];";
    echo "var cal_start_weekday = " . $CFG->calendar_startwday . ";";
    echo "var cal_cur_date = " . $current . ";";
    echo "</script>\n";

    require_js($CFG->wwwroot . '/mod/attforblock/calendar.js');
}
	
?>
