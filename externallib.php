<?php
require_once($CFG->libdir . '/externallib.php');
/**
 * 
 * Attendance Module Web Services
 * @author Michael Hughes, University of Strathclyde
 * @package    mod
 * @subpackage attforblock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attforblock_external extends external_api {
	/**
	 * 
	 * Defines the Input Parameters for the get_attendance_data web service function.
	 */
	public static function get_attendance_data_parameters() {
		return new external_function_parameters(
			array(
				'attendanceid' => new external_value(PARAM_INT,'Attendance Activity ID')
			)
		);
	}
	/**
	 * 
	 * Defines the output structure for the get_attendance_data web service function
	 */
	public static function get_attendance_data_returns() {
		return new external_multiple_structure(
			new external_single_structure(array(
					'id'		=>	new external_value(PARAM_INT,'attendance instance ID'),	//scalar ID of the session
					'sessiondate'=>new external_value(PARAM_INT,'Session\'s date'),
					'users'		=> 	new external_multiple_structure(	//array of arrays
							new external_single_structure(
							array(
								'id'=>new external_value(PARAM_INT,'UserID'),
								'status'	=>	new external_value(PARAM_ALPHANUMEXT,'Attendance status'),
								'description'	=>	new external_value(PARAM_ALPHANUMEXT,'Attendance status'),
							))
					)
					
			))
		)
		;
	}
	/**
	 * 
	 * Returns attendance data for a specific attendance instance.
	 * 
	 * @param int $attendanceid
	 * @return array Array of Sessions in the attendance instance and attendance of each user in session. 
	 */
	public static function get_attendance_data($attendanceid = 0) {
		global $CFG,$DB;
		
		require_once("{$CFG->dirroot}/mod/attforblock/locallib.php");
		$params = self::validate_parameters(self::get_attendance_data_parameters(),array('attendanceid'=>$attendanceid));
		//print_object($params);
		$cm             = get_coursemodule_from_instance('attforblock', $params['attendanceid'], 0, false, MUST_EXIST);
		
		$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
		
		$attendancedata = array();
		$att = $DB->get_record('attforblock', array('id' => $params['attendanceid']), '*', MUST_EXIST);
		$att = new attforblock($att, $cm, $course, $PAGE->context, $pageparams);
		$statuses = $att->get_statuses();
		//print_object($statuses);
		//$sessions = $att->get_filtered_sessions();
		//print_object($sessions);
		$users = $att->get_users();
		//print_object($users);
		foreach($users as $user) {
			$stat = $att->get_user_filtered_sessions_log_extended($user->id);
			//print_object($stat);
			foreach($stat as $sess) {
				
				if (!array_key_exists($sess->id)) {
					$attendancedata[$sess->id] = array();
					$attendancedata[$sess->id]['id'] = $sess->id;
					$attendancedata[$sess->id]['sessiondate'] = $sess->sessdate;
					$attendancedata[$sess->id]['users'] = array();
				}
				$data = array();	//this is the user data for a particular session
				$data['id'] = $user->id;
				$user_status = $statuses[$sess->statusid];
				$data['status'] = $user_status>acronym;
				$data['description'] = $user_status->description;
				//print_object($data);
				//$data['studenregno']	='a';
				$attendancedata[$sess->id]['users'][] =$data;
			}
		}
		//print_object($attendancedata);
		//die('halted');
		return $attendancedata;
	}
	

	
}
