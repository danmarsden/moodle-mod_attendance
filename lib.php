<?PHP

/// Library of functions and constants for module attforblock

/**
 * Returns the information if the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function attforblock_supports($feature) {
    switch($feature) {
        case FEATURE_GRADE_HAS_GRADE:   return true;
        case FEATURE_GROUPS:            return true;
        // Artem Andreev: AFAIK it's not tested
        // we need implement filtration of groups list by grouping
        case FEATURE_GROUPINGS:         return false;
        // Artem Andreev: AFAIK it's not tested
        // harder "All courses" report
        case FEATURE_GROUPMEMBERSONLY:  return false;
        case FEATURE_MOD_INTRO:         return false;
        case FEATURE_BACKUP_MOODLE2:    return true;
        // Artem Andreev: AFAIK it's not tested
        case FEATURE_COMPLETION_TRACKS_VIEWS:
                                        return false;
        default:                        return null;
    }
}

function att_add_default_statuses($attid) {
    global $DB;

    $statuses = $DB->get_records('attendance_statuses', array('attendanceid'=> 0), 'id');
    foreach($statuses as $st) {
        $rec = $st;
        $rec->attendanceid = $attid;
        $DB->insert_record('attendance_statuses', $rec);
    }
}

function attforblock_add_instance($attforblock) {
/// Given an object containing all the necessary data, 
/// (defined by the form in mod.html) this function 
/// will create a new instance and return the id number 
/// of the new instance.

    global $DB;
    
    $attforblock->timemodified = time();

    $attforblock->id = $DB->insert_record('attforblock', $attforblock);

    att_add_default_statuses($attforblock->id);

    attforblock_grade_item_update($attforblock);
//	attforblock_update_grades($attforblock);
    return $attforblock->id;
}


function attforblock_update_instance($attforblock) {
/// Given an object containing all the necessary data,
/// (defined by the form in mod.html) this function
/// will update an existing instance with new data.

    global $DB;

    $attforblock->timemodified = time();
    $attforblock->id = $attforblock->instance;

    if (! $DB->update_record('attforblock', $attforblock)) {
        return false;
    }

    attforblock_grade_item_update($attforblock);

    return true;
}


function attforblock_delete_instance($id) {
    global $DB;

    if (! $attforblock = $DB->get_record('attforblock', array('id'=> $id))) {
        return false;
    }
    
	if ($sessids = array_keys($DB->get_records('attendance_sessions', array('attendanceid'=> $id), '', 'id'))) {
        $DB->delete_records_list('attendance_log', 'sessionid', $sessids);
        $DB->delete_records('attendance_sessions', array('attendanceid'=> $id));
    }
	$DB->delete_records('attendance_statuses', array('attendanceid'=> $id));

    $DB->delete_records('attforblock', array('id'=> $id));

    attforblock_grade_item_delete($attforblock);

    return true;
}

function attforblock_delete_course($course, $feedback=true){
    global $DB;

    $attids = array_keys($DB->get_records('attforblock', array('course'=> $course->id), '', 'id'));
    $sessids = array_keys($DB->get_records_list('attendance_sessions', 'attendanceid', $attids, '', 'id'));
    if ($sessids)
        $DB->delete_records_list('attendance_log', 'sessionid', $sessids);
    if ($attids) {
        $DB->delete_records_list('attendance_statuses', 'attendanceid', $attids);
        $DB->delete_records_list('attendance_sessions', 'attendanceid', $attids);
    }
	$DB->delete_records('attforblock', array('course'=> $course->id));
	
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
    global $DB;

    $status = array();

    $attids = array_keys($DB->get_records('attforblock', array('course'=> $data->courseid), '', 'id'));

    if (!empty($data->reset_attendance_log)) {
		$sess = $DB->get_records_list('attendance_sessions', 'attendanceid', $attids, '', 'id');
        if (!empty($sess)) {
            list($sql, $params) = $DB->get_in_or_equal(array_keys($sess));
            $DB->delete_records_select('attendance_log', "sessionid $sql", $params);
            list($sql, $params) = $DB->get_in_or_equal($attids);
            $DB->set_field_select('attendance_sessions', 'lasttaken', 0, "attendanceid $sql", $params);

            $status[] = array(
                'component' => get_string('modulenameplural', 'attforblock'),
                'item' => get_string('attendancedata', 'attforblock'),
                'error' => false
            );
        }
    }

    if (!empty($data->reset_attendance_statuses)) {
    	$DB->delete_records_list('attendance_statuses', 'attendanceid', $attids);
        foreach($attids as $attid) {
            att_add_default_statuses($attid);
        }

        $status[] = array(
            'component' => get_string('modulenameplural', 'attforblock'),
            'item' => get_string('sessions', 'attforblock'),
            'error' => false
        );
    }

    if (!empty($data->reset_attendance_sessions)) {
    	$DB->delete_records_list('attendance_sessions', 'attendanceid', $attids);

        $status[] = array(
            'component' => get_string('modulenameplural', 'attforblock'),
            'item' => get_string('statuses', 'attforblock'),
            'error' => false
        );
    }

    return $status;
}

function attforblock_user_outline($course, $user, $mod, $attforblock) {
/// Return a small object with summary information about what a 
/// user has done with a given particular instance of this module
/// Used for user activity reports.
/// $return->time = the time they did it
/// $return->info = a short text description
    global $CFG;
	
    require_once(dirname(__FILE__).'/locallib.php');
    require_once($CFG->libdir.'/gradelib.php');

    $grades = grade_get_grades($course->id, 'mod', 'attforblock', $attforblock->id, $user->id);

    $result = new stdClass();
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        $result->time = $grade->dategraded;
    }
    else
        $result->time = 0;
  	if (has_capability('mod/attforblock:canbelisted', $mod->context, $user->id)) {
        $statuses = att_get_statuses($attforblock->id);
        $grade = att_get_user_grade(get_user_statuses_stat($attforblock->id, $course->startdate, $user->id), $statuses);
        $maxgrade = att_get_user_max_grade(get_user_taken_sessions_count($attforblock->id, $course->startdate, $user->id), $statuses);

        $result->info = $grade.' / '.$maxgrade;
  	}
  	
	return $result;
}

function attforblock_user_complete($course, $user, $mod, $attforblock) {
/// Print a detailed representation of what a  user has done with 
/// a given particular instance of this module, for user activity reports.
    global $CFG;

    require_once(dirname(__FILE__).'/renderhelpers.php');
    require_once($CFG->libdir.'/gradelib.php');
	
  	if (has_capability('mod/attforblock:canbelisted', $mod->context, $user->id)) {
        echo construct_full_user_stat_html_table($attforblock, $course, $user);
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
/*function attforblock_get_user_grades($attforblock, $userid=0) {
    global $CFG, $DB;
    
	require_once('_locallib.php');
	
    if (! $course = $DB->get_record('course', array('id'=> $attforblock->course))) {
        error("Course is misconfigured");
    }

    $result = false;
    if ($userid) {
    	$result = array();
    	$result[$userid]->userid = $userid;
    	$result[$userid]->rawgrade = $attforblock->grade * get_percent($userid, $course, $attforblock) / 100;
    } else {
    	if ($students = get_course_students($course->id)) {
    		$result = array();
    		foreach ($students as $student) {
		    	$result[$student->id]->userid = $student->id;
		    	$result[$student->id]->rawgrade = $attforblock->grade * get_percent($student->id, $course, $attforblock) / 100;
    		}
    	}
    }

    return $result;
}*/

/**
 * Update grades by firing grade_updated event
 *
 * @param object $attforblock null means all attforblocks
 * @param int $userid specific user only, 0 mean all
 */
/*function attforblock_update_grades($attforblock=null, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
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
                  FROM {attforblock} a, {course_modules} cm, {modules} m
                 WHERE m.name='attforblock' AND m.id=cm.module AND cm.instance=a.id";
        if ($rs = $DB->get_records_sql($sql)) {
            foreach ($rs as $attforblock) {
//                if ($attforblock->grade != 0) {
                    attforblock_update_grades($attforblock);
//                } else {
//                    attforblock_grade_item_update($attforblock);
//                }
            }
            $rs->close($rs);
        }
    }
}*/

/**
 * Create grade item for given attforblock
 *
 * @param object $attforblock object with extra cmidnumber
 * @param mixed optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function attforblock_grade_item_update($attforblock, $grades=NULL) {
    global $CFG, $DB;
    
	require_once('locallib.php');
	
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    if (!isset($attforblock->courseid)) {
        $attforblock->courseid = $attforblock->course;
    }
    if (! $course = $DB->get_record('course', array('id'=> $attforblock->course))) {
        error("Course is misconfigured");
    }
    //$attforblock->grade = get_maxgrade($course);

    if(!empty($attforblock->cmidnumber)){
        $params = array('itemname'=>$attforblock->name, 'idnumber'=>$attforblock->cmidnumber);
    }else{
        // MDL-14303
        $cm = get_coursemodule_from_instance('attforblock', $attforblock->id);
        $params = array('itemname'=>$attforblock->name/*, 'idnumber'=>$attforblock->id*/);
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
}

/**
 * Serves the attendance sessions descriptions files.
 *
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - justsend the file
 */
function attforblock_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);

    if (!$att = $DB->get_record('attforblock', array('id' => $cm->instance))) {
        return false;
    }

    // 'session' area is served by pluginfile.php
    $fileareas = array('session');
    if (!in_array($filearea, $fileareas)) {
        return false;
    }

    $sessid = (int)array_shift($args);
    if (!$sess = $DB->get_record('attendance_sessions', array('id' => $sessid))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_attforblock/$filearea/$sessid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, true);
}

//////////////////////////////////////////////////////////////////////////////////////
/// Any other attforblock functions go here.  Each of them must have a name that 
/// starts with attforblock_


?>
