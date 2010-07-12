<?PHP  // $Id: lib.php,v 1.4.2.5 2009/03/11 18:21:08 dlnsk Exp $

/// Library of functions and constants for module attforblock

$attforblock_CONSTANT = 7;     /// for example

function attforblock_install() {
	
	$result = true;
	$arr = array('P' => 2, 'A' => 0, 'L' => 1, 'E' => 1);
	foreach ($arr as $k => $v) {
		unset($rec);
		$rec->courseid = 0;
		$rec->acronym = get_string($k.'acronym', 'attforblock');
		$rec->description = get_string($k.'full', 'attforblock');
		$rec->grade = $v;
		$rec->visible = 1;
		$rec->deleted = 0;
		$result = $result && insert_record('attendance_statuses', $rec);
	}
	return $result;
}

function attforblock_add_instance($attforblock) {
/// Given an object containing all the necessary data, 
/// (defined by the form in mod.html) this function 
/// will create a new instance and return the id number 
/// of the new instance.

    $attforblock->timemodified = time();

    if ($att = get_record('attforblock', 'course', $attforblock->course)) {
    	$modnum = get_field('modules', 'id', 'name', 'attforblock');
    	if (!get_record('course_modules', 'course', $attforblock->course, 'module', $modnum)) {
    		delete_records('attforblock', 'course', $attforblock->course);
    		$attforblock->id = insert_record('attforblock', $attforblock);
    	} else {
    		return false;
    	}
    } else {
    	$attforblock->id = insert_record('attforblock', $attforblock);
    }

    //Copy statuses for new instance from defaults
    if (!get_records('attendance_statuses', 'courseid', $attforblock->course)) {
	    $statuses = get_records('attendance_statuses', 'courseid', 0, 'id');
		foreach($statuses as $stat) {
			$rec = $stat;
			$rec->courseid = $attforblock->course;
			insert_record('attendance_statuses', $rec);
		}
    }
						
//    attforblock_grade_item_update($attforblock);
//	attforblock_update_grades($attforblock);
    return $attforblock->id;
}


function attforblock_update_instance($attforblock) {
/// Given an object containing all the necessary data, 
/// (defined by the form in mod.html) this function 
/// will update an existing instance with new data.

    $attforblock->timemodified = time();
    $attforblock->id = $attforblock->instance;

    if (! update_record('attforblock', $attforblock)) {
        return false;
    }

    attforblock_grade_item_update($attforblock);

    return true;
}


function attforblock_delete_instance($id) {
/// Given an ID of an instance of this module, 
/// this function will permanently delete the instance 
/// and any data that depends on it.  

    if (! $attforblock = get_record('attforblock', 'id', $id)) {
        return false;
    }
    
    $result = delete_records('attforblock', 'id', $id);

    attforblock_grade_item_delete($attforblock);

    return $result;
}

function attforblock_delete_course($course, $feedback=true){
	
	if ($sess = get_records('attendance_sessions', 'courseid', $course->id, '', 'id')) {
        $slist = implode(',', array_keys($sess));
        delete_records_select('attendance_log', "sessionid IN ($slist)");
        delete_records('attendance_sessions', 'courseid', $course->id);
    }
	delete_records('attendance_statuses', 'courseid', $course->id);
	
    //Inform about changes performed if feedback is enabled
//    if ($feedback) {
//        notify(get_string('deletedefaults', 'lesson', $count));
//    }

    return true;
}

/**
 * Called by course/reset.php
 * @param $mform form passed by reference
 */
function attforblock_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'attendanceheader', get_string('modulename', 'attforblock'));

	$mform->addElement('static', 'description', get_string('description', 'attforblock'),
								get_string('resetdescription', 'attforblock'));    
    $mform->addElement('checkbox', 'reset_attendance_log', get_string('deletelogs','attforblock'));

    $mform->addElement('checkbox', 'reset_attendance_sessions', get_string('deletesessions','attforblock'));
    $mform->disabledIf('reset_attendance_sessions', 'reset_attendance_log', 'notchecked');

    $mform->addElement('checkbox', 'reset_attendance_statuses', get_string('resetstatuses','attforblock'));
    $mform->setAdvanced('reset_attendance_statuses');
    $mform->disabledIf('reset_attendance_statuses', 'reset_attendance_log', 'notchecked');
}

/**
 * Course reset form defaults.
 */
function attforblock_reset_course_form_defaults($course) {
    return array('reset_attendance_log'=>0, 'reset_attendance_statuses'=>0, 'reset_attendance_sessions'=>0);
}

function attforblock_reset_userdata($data) {
    if (!empty($data->reset_attendance_log)) {
		$sess = get_records('attendance_sessions', 'courseid', $data->courseid, '', 'id');
		$slist = implode(',', array_keys($sess));
    	delete_records_select('attendance_log', "sessionid IN ($slist)");
        set_field('attendance_sessions', 'lasttaken', 0, 'courseid', $data->courseid);
    }
    if (!empty($data->reset_attendance_statuses)) {
    	delete_records('attendance_statuses', 'courseid', $data->courseid);
	    $statuses = get_records('attendance_statuses', 'courseid', 0, 'id');
		foreach($statuses as $stat) {
			$rec = $stat;
			$rec->courseid = $data->courseid;
			insert_record('attendance_statuses', $rec);
		}
    }
    if (!empty($data->reset_attendance_sessions)) {
    	delete_records('attendance_sessions', 'courseid', $data->courseid);
    }
}

function attforblock_user_outline($course, $user, $mod, $attforblock) {
/// Return a small object with summary information about what a 
/// user has done with a given particular instance of this module
/// Used for user activity reports.
/// $return->time = the time they did it
/// $return->info = a short text description
	
	require_once('locallib.php');
	
  	if (isstudent($course->id, $user->id)) {
	  	if ($sescount = get_attendance($user->id,$course)) {
	  		$strgrade = get_string('grade');
	  		$maxgrade = get_maxgrade($user->id, $course);
	  		$usergrade = get_grade($user->id, $course);
	  		$percent = get_percent($user->id,$course);
	  		$result->info = "$strgrade: $usergrade / $maxgrade ($percent%)";
	  	}
  	}
  	
	return $result;
}

function attforblock_user_complete($course, $user, $mod, $attforblock) {
/// Print a detailed representation of what a  user has done with 
/// a given particular instance of this module, for user activity reports.

	require_once('locallib.php');
	
	if (isstudent($course->id, $user->id)) {
//        if (! $cm = get_coursemodule_from_instance("attforblock", $attforblock->id, $course->id)) {
//            error("Course Module ID was incorrect");
//        }
		print_user_attendaces($user, $mod, $course);
	}

    //return true;
}

function attforblock_print_recent_activity($course, $isteacher, $timestart) {
/// Given a course and a time, this module should find recent activity 
/// that has occurred in attforblock activities and print it out. 
/// Return true if there was output, or false is there was none.

    return false;  //  True if anything was printed, otherwise false 
}

function attforblock_cron () {
/// Function to be run periodically according to the moodle cron
/// This function searches for things that need to be done, such 
/// as sending out mail, toggling flags etc ... 

    return true;
}

/**
 * Return grade for given user or all users.
 *
 * @param int $attforblockid id of attforblock
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function attforblock_get_user_grades($attforblock, $userid=0) {
    global $CFG;
    
	require_once('locallib.php');
	
    if (! $course = get_record('course', 'id', $attforblock->course)) {
        error("Course is misconfigured");
    }

    $result = false;
    if ($userid) {
    	$result = array();
    	$result[$userid]->userid = $userid;
    	$result[$userid]->rawgrade = $attforblock->grade * get_percent($userid, $course) / 100;
    } else {
    	if ($students = get_course_students($course->id)) {
    		$result = array();
    		foreach ($students as $student) {
		    	$result[$student->id]->userid = $student->id;
		    	$result[$student->id]->rawgrade = $attforblock->grade * get_percent($student->id, $course) / 100;
    		}
    	}
    }

    return $result;
}

/**
 * Update grades by firing grade_updated event
 *
 * @param object $attforblock null means all attforblocks
 * @param int $userid specific user only, 0 mean all
 */
function attforblock_update_grades($attforblock=null, $userid=0, $nullifnone=true) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if ($attforblock != null) {
        if ($grades = attforblock_get_user_grades($attforblock, $userid)) {
            foreach($grades as $k=>$v) {
                if ($v->rawgrade == -1) {
                    $grades[$k]->rawgrade = null;
                }
            }
            attforblock_grade_item_update($attforblock, $grades);
        } else {
            attforblock_grade_item_update($attforblock);
        }

    } else {
        $sql = "SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid
                  FROM {$CFG->prefix}attforblock a, {$CFG->prefix}course_modules cm, {$CFG->prefix}modules m
                 WHERE m.name='attforblock' AND m.id=cm.module AND cm.instance=a.id";
        if ($rs = get_recordset_sql($sql)) {
            while ($attforblock = rs_fetch_next_record($rs)) {
//                if ($attforblock->grade != 0) {
                    attforblock_update_grades($attforblock);
//                } else {
//                    attforblock_grade_item_update($attforblock);
//                }
            }
            rs_close($rs);
        }
    }
}

/**
 * Create grade item for given attforblock
 *
 * @param object $attforblock object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function attforblock_grade_item_update($attforblock, $grades=NULL) {
    global $CFG;
    
	require_once('locallib.php');
	
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (!isset($attforblock->courseid)) {
        $attforblock->courseid = $attforblock->course;
    }
    if (! $course = get_record('course', 'id', $attforblock->course)) {
        error("Course is misconfigured");
    }
    //$attforblock->grade = get_maxgrade($course);

    if(!empty($attforblock->cmidnumber)){
        $params = array('itemname'=>$attforblock->name, 'idnumber'=>$attforblock->cmidnumber);
    }else{
        // MDL-14303
        $cm = get_coursemodule_from_instance('attforblock', $attforblock->id);
        $params = array('itemname'=>$attforblock->name, 'idnumber'=>$cm->id);
    }
    
    if ($attforblock->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $attforblock->grade;
        $params['grademin']  = 0;

    } 
    else if ($attforblock->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$attforblock->grade;

    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/attforblock', $attforblock->courseid, 'mod', 'attforblock', $attforblock->id, 0, $grades, $params);
}

/**
 * Delete grade item for given attforblock
 *
 * @param object $attforblock object
 * @return object attforblock
 */
function attforblock_grade_item_delete($attforblock) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if (!isset($attforblock->courseid)) {
        $attforblock->courseid = $attforblock->course;
    }

    return grade_update('mod/attforblock', $attforblock->courseid, 'mod', 'attforblock', $attforblock->id, 0, NULL, array('deleted'=>1));
}

function attforblock_get_participants($attforblockid) {
//Must return an array of user records (all data) who are participants
//for a given instance of attforblock. Must include every user involved
//in the instance, independient of his role (student, teacher, admin...)
//See other modules as example.

    return false;
}

function attforblock_scale_used ($attforblockid, $scaleid) {
//This function returns if a scale is being used by one attforblock
//it it has support for grading and scales. Commented code should be
//modified if necessary. See forum, glossary or journal modules
//as reference.
   
    $return = false;

    //$rec = get_record("attforblock","id","$attforblockid","scale","-$scaleid");
    //
    //if (!empty($rec)  && !empty($scaleid)) {
    //    $return = true;
    //}
   
    return $return;
}

//////////////////////////////////////////////////////////////////////////////////////
/// Any other attforblock functions go here.  Each of them must have a name that 
/// starts with attforblock_


?>
