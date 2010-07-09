<?php  //$Id: upgrade.php,v 1.1.2.2 2009/02/23 19:22:42 dlnsk Exp $

// This file keeps track of upgrades to 
// the forum module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php

function xmldb_attforblock_upgrade($oldversion=0) {

    global $CFG, $THEME, $db;

    $result = true;

/// And upgrade begins here. For each one, you'll need one 
/// block of code similar to the next one. Please, delete 
/// this comment lines once this file start handling proper
/// upgrade code.

	if ($result && $oldversion < 2008021904) { //New version in version.php
		global $USER;
		if ($sessions = get_records('attendance_sessions', 'takenby', 0)) {
			foreach ($sessions as $sess) {
				if (count_records('attendance_log', 'attsid', $sess->id) > 0) {
					$sess->takenby = $USER->id;
					$sess->timetaken = $sess->timemodified ? $sess->timemodified : time();
					$sess->description = addslashes($sess->description);
					$result = update_record('attendance_sessions', $sess) and $result;
				}
			}
		}
	}

    if ($oldversion < 2008102401 and $result) {
    	
        $table = new XMLDBTable('attforblock');
        
        $field = new XMLDBField('grade');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, null, '100', 'name');
        $result = $result && add_field($table, $field);
    	
        
        $table = new XMLDBTable('attendance_sessions');
        
        $field = new XMLDBField('courseid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'id');
        $result = $result && change_field_unsigned($table, $field);
    	
//        $field = new XMLDBField('creator');
//        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'courseid');
//        $result = $result && change_field_unsigned($table, $field);
    	
        $field = new XMLDBField('sessdate');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'creator');
        $result = $result && change_field_unsigned($table, $field);
    	
        $field = new XMLDBField('duration');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'sessdate');
        $result = $result && add_field($table, $field);
        
        $field = new XMLDBField('timetaken');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'takenby');
        $result = $result && change_field_unsigned($table, $field);
    	$result = $result && rename_field($table, $field, 'lasttaken');

        $field = new XMLDBField('takenby');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'lasttaken');
        $result = $result && change_field_unsigned($table, $field);
        $result = $result && rename_field($table, $field, 'lasttakenby');
    	
        $field = new XMLDBField('timemodified');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null, 'lasttaken');
        $result = $result && change_field_unsigned($table, $field);
    	
        
    	$table = new XMLDBTable('attendance_log');
        
        $field = new XMLDBField('attsid');
		$field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'id');
    	$result = $result && change_field_unsigned($table, $field);
    	
        $field = new XMLDBField('studentid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'attsid');
    	$result = $result && change_field_unsigned($table, $field);
    	
    	$field = new XMLDBField('statusid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'status');
    	$result = $result && add_field($table, $field);
    	
        $field = new XMLDBField('statusset');
        $field->setAttributes(XMLDB_TYPE_CHAR, '100', null, null, null, null, null, null, 'statusid');
        $result = $result && add_field($table, $field);
    	
        $field = new XMLDBField('timetaken');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'statusid');
    	$result = $result && add_field($table, $field);
    	
        $field = new XMLDBField('takenby');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'timetaken');
    	$result = $result && add_field($table, $field);
    	
        //Indexes
        $index = new XMLDBIndex('statusid');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('statusid'));
    	$result = $result && add_index($table, $index);
    	
        $index = new XMLDBIndex('attsid');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('attsid'));
        $result = $result && drop_index($table, $index);
    	
        $field = new XMLDBField('attsid'); //Rename field
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'id');
        $result = $result && rename_field($table, $field, 'sessionid');
        
        $index = new XMLDBIndex('sessionid');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('sessionid'));
        $result = $result && add_index($table, $index);
        
    	
    	$table = new XMLDBTable('attendance_settings');
        
        $field = new XMLDBField('courseid');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'id');
        $result = $result && change_field_unsigned($table, $field);
    	
        $field = new XMLDBField('visible');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '1', 'grade');
        $result = $result && add_field($table, $field);
        
        $field = new XMLDBField('deleted');
        $field->setAttributes(XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'visible');
        $result = $result && add_field($table, $field);
        
        //Indexes
        $index = new XMLDBIndex('visible');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('visible'));
        $result = $result && add_index($table, $index);
        
        $index = new XMLDBIndex('deleted');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('deleted'));
        $result = $result && add_index($table, $index);
        
    	$result = $result && rename_table($table, 'attendance_statuses');
    }
    
    if ($oldversion < 2008102406 and $result) {
    	
	    if ($courses = get_records_sql("SELECT courseid FROM {$CFG->prefix}attendance_sessions GROUP BY courseid")) {
	    		foreach ($courses as $c) {
	    			//Adding own status for course (now it must have own)
	    			if (!count_records('attendance_statuses', 'courseid', $c->courseid)) {
	    				$statuses = get_records('attendance_statuses', 'courseid', 0);
						foreach($statuses as $stat) {
							$rec = $stat;
							$rec->courseid = $c->courseid;
							insert_record('attendance_statuses', $rec);
						}
	    			}
	    			$statuses = get_records('attendance_statuses', 'courseid', $c->courseid);
	    			$statlist = implode(',', array_keys($statuses));
	    			$sess = get_records_select_menu('attendance_sessions', "courseid = $c->courseid AND lasttakenby > 0");
	    			$sesslist = implode(',', array_keys($sess));
					foreach($statuses as $stat) {
						execute_sql("UPDATE {$CFG->prefix}attendance_log 
										SET statusid = {$stat->id}, statusset = '$statlist'
									  WHERE sessionid IN ($sesslist) AND status = '$stat->status'");
					}
	    			$sessions = get_records_list('attendance_sessions', 'id', $sesslist);
					foreach($sessions as $sess) {
						execute_sql("UPDATE {$CFG->prefix}attendance_log 
										SET timetaken = {$sess->lasttaken}, 
											takenby = {$sess->lasttakenby}
									  WHERE sessionid = {$sess->id}");
					}
	    			
	    		}
	    	}
    	    	
     }
     
    if ($oldversion < 2008102409 and $result) {
        $table = new XMLDBTable('attendance_statuses');
        
        $field = new XMLDBField('status');
        $result = $result && drop_field($table, $field);
        
        $index = new XMLDBIndex('status');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('status'));
        $result = $result && drop_index($table, $index);

        
        $table = new XMLDBTable('attendance_log');
        
        $field = new XMLDBField('status');
        $result = $result && drop_field($table, $field);
        
        $index = new XMLDBIndex('status');
        $index->setAttributes(XMLDB_INDEX_NOTUNIQUE, array('status'));
        $result = $result && drop_index($table, $index);
        
        $table = new XMLDBTable('attendance_sessions');

        $field = new XMLDBField('creator');
        $result = $result && drop_field($table, $field);
        
    } 
    return $result;
}

?>
