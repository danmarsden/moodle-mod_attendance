<?php
global $CFG;
require_once($CFG->libdir.'/gradelib.php');

define('ONE_DAY', 86400);   // Seconds in one day
define('ONE_WEEK', 604800);   // Seconds in one week

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

function get_statuses($courseid, $onlyvisible = true)
{
  	if ($onlyvisible) {
  		$result = get_records_select('attendance_statuses', "courseid = $courseid AND visible = 1 AND deleted = 0", 'grade DESC');
  	} else {
  		$result = get_records_select('attendance_statuses', "courseid = $courseid AND deleted = 0", 'grade DESC');  		
//  		$result = get_records('attendance_statuses', 'courseid', $courseid, 'grade DESC');
  	}
    return $result;
}	

//gets attendance status for a student, returns count

function get_attendance($userid, $course, $statusid=0)
{
	global $CFG;
	$qry = "SELECT count(*) as cnt 
		  	  FROM {$CFG->prefix}attendance_log al 
			  JOIN {$CFG->prefix}attendance_sessions ats 
			    ON al.sessionid = ats.id
			 WHERE ats.courseid = $course->id 
			  	AND ats.sessdate >= $course->startdate
	         	AND al.studentid = $userid";
	if ($statusid) {
		$qry .= " AND al.statusid = $statusid";
	}
	
	return count_records_sql($qry);
}

function get_grade($userid, $course)
{
	global $CFG;
	$logs = get_records_sql("SELECT l.id, l.statusid, l.statusset
							FROM {$CFG->prefix}attendance_log l
							JOIN {$CFG->prefix}attendance_sessions s
							  ON l.sessionid = s.id
						   WHERE l.studentid = $userid
						     AND s.courseid  = $course->id
						     AND s.sessdate >= $course->startdate");
	$result = 0;
	if ($logs) {
		$stat_grades = records_to_menu(get_records('attendance_statuses', 'courseid', $course->id), 'id', 'grade');
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

function get_maxgrade($userid, $course)
{
	global $CFG;
	$logs = get_records_sql("SELECT l.id, l.statusid, l.statusset
							FROM {$CFG->prefix}attendance_log l
							JOIN {$CFG->prefix}attendance_sessions s
							  ON l.sessionid = s.id
						   WHERE l.studentid = $userid
						     AND s.courseid  = $course->id
						     AND s.sessdate >= $course->startdate");
	$maxgrade = 0;
	if ($logs) {
		$stat_grades = records_to_menu(get_records('attendance_statuses', 'courseid', $course->id), 'id', 'grade');
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
	global $CFG;
	$logs = get_records_sql("SELECT l.id, l.statusid, l.statusset
							FROM {$CFG->prefix}attendance_log l
							JOIN {$CFG->prefix}attendance_sessions s
							  ON l.sessionid = s.id
						   WHERE l.studentid = $userid
						     AND s.courseid  = $course->id
						     AND s.sessdate >= $course->startdate");
	$result = 0;
	if ($logs) {
		$stat_grades = records_to_menu(get_records('attendance_statuses', 'courseid', $course->id), 'id', 'grade');
		
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

function get_percent($userid, $course)
{
    global $CFG;
    
    $maxgrd = get_maxgrade($userid, $course);
    if ($maxgrd == 0) {
    	$result = 0;
    } else {
    	$result = get_grade($userid, $course) / $maxgrd * 100;
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

function get_current_view($courseid) {
    global $SESSION;

    if (isset($SESSION->currentattview[$courseid]))
        return $SESSION->currentattview[$courseid];
    else
        return 'all';
}

function print_row($left, $right) {
    echo "\n<tr><td nowrap=\"nowrap\" align=\"right\" valign=\"top\" class=\"cell c0\">$left</td><td align=\"left\" valign=\"top\" class=\"info c1\">$right</td></tr>\n";
}

function print_attendance_table($user,  $course) {

	$complete = get_attendance($user->id, $course);
	$percent = get_percent($user->id, $course).'&nbsp;%';
	$grade = get_grade($user->id, $course);
	
    echo '<table border="0" cellpadding="0" cellspacing="0" class="list">';
    print_row(get_string('sessionscompleted','attforblock').':', "<strong>$complete</strong>");
    $statuses = get_statuses($course->id);
	foreach($statuses as $st) {
		print_row($st->description.': ', '<strong>'.get_attendance($user->id, $course, $st->id).'</strong>');
	}
    print_row(get_string('attendancepercent','attforblock').':', "<strong>$percent</strong>");
    print_row(get_string('attendancegrade','attforblock').':', "<strong>$grade</strong> / ".get_maxgrade($user->id, $course));
    print_row('&nbsp;', '&nbsp;');
  	echo '</table>';
	
}

function print_user_attendaces($user, $cm,  $course = 0, $printing = null) {
	global $CFG, $COURSE, $mode;
		
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
		$complete = get_attendance($user->id, $course);
		if($complete) {
			print_attendance_table($user,  $course);
		} else {
			echo get_string('attendancenotstarted','attforblock');
		}
	} else {
		$stqry = "SELECT ats.id,ats.courseid 
					FROM {$CFG->prefix}attendance_log al 
					JOIN {$CFG->prefix}attendance_sessions ats 
					  ON al.sessionid = ats.id
				   WHERE al.studentid = {$user->id}
				GROUP BY ats.courseid
				ORDER BY ats.courseid asc";
		$recs = get_records_sql_menu($stqry);
		foreach ($recs as $id => $courseid) {
			echo '<hr />';
			echo '<table border="0" cellpadding="0" cellspacing="0" width="100%" class="list1">';
			$nextcourse = get_record('course', 'id', $courseid);
			echo '<tr><td valign="top"><strong>'.$nextcourse->fullname.'</strong></td>';
			echo '<td align="right">';
			$complete = get_attendance($user->id, $nextcourse);
			if($complete) {
				print_attendance_table($user,  $nextcourse);
			} else {
				echo get_string('attendancenotstarted','attforblock');
			}
			echo '</td></tr>';
			echo '</table>';
		}
	}

	
	if ($course) {
		$stqry = "SELECT ats.sessdate,ats.description,al.statusid,al.remarks 
					FROM {$CFG->prefix}attendance_log al 
					JOIN {$CFG->prefix}attendance_sessions ats 
					  ON al.sessionid = ats.id
				   WHERE ats.courseid = {$course->id} AND al.studentid = {$user->id} 
				ORDER BY ats.sessdate asc";
		if ($sessions = get_records_sql($stqry)) {
	     	$statuses = get_statuses($course->id);
	     	?>
			<div id="mod-assignment-submissions">
			<table align="left" cellpadding="3" cellspacing="0" class="submissions">
			  <tr>
				<th>#</th>
				<th align="center"><?php print_string('date')?></th>
				<th align="center"><?php print_string('time')?></th>
				<th align="center"><?php print_string('description','attforblock')?></th>
				<th align="center"><?php print_string('status','attforblock')?></th>
				<th align="center"><?php print_string('remarks','attforblock')?></th>
			  </tr>
			  <?php 
		  	$i = 1;
			foreach($sessions as $key=>$session)
			{
			  ?>
			  <tr>
				<td align="center"><?php echo $i++;?></td>
				<td><?php echo userdate($session->sessdate, get_string('strftimedmyw', 'attforblock')); //userdate($students->sessdate,'%d.%m.%y&nbsp;(%a)', 99, false);?></td>
				<td><?php echo userdate($session->sessdate, get_string('strftimehm', 'attforblock')); ?></td>
				<td><?php echo empty($session->description) ? get_string('nodescription', 'attforblock') : $session->description;  ?></td>
				<td><?php echo $statuses[$session->statusid]->description ?></td>
				<td><?php echo $session->remarks;?></td>
			  </tr>
			  <?php
	  		}
	  		echo '</table>';
		} else {
			print_heading(get_string('noattforuser','attforblock'));
		}
	}
	echo '</td></tr><tr><td>&nbsp;</td></tr></table></div>';
}
	
?>
