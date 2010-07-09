<?php 

    function attforblock_restore_mods($mod,$restore) {

        global $CFG, $oldidarray;

        $status = true;

        //Get record from backup_ids
        $data = backup_getid($restore->backup_unique_code, $mod->modtype, $mod->id);

        if ($data) {
            //Now get completed xmlized object   
            $info = $data->info;

            if (count_records('attforblock', 'course', $restore->course_id)) {
                return false;
            }

            //Now, build the attforblock record structure
            $attforblock->course = $restore->course_id;
//            $attforblock->teacher = backup_todb($info['MOD']['#']['TEACHER']['0']['#']);
            $attforblock->name = backup_todb($info['MOD']['#']['NAME']['0']['#']);
            if (isset($info['MOD']['#']['GRADE'])) {
                $attforblock->grade = backup_todb($info['MOD']['#']['GRADE']['0']['#']);
            } else {
                $attforblock->grade = 100;
            }

            //The structure is equal to the db, so insert the attforblock
            $newid = insert_record ('attforblock', $attforblock);
            if ($newid) {
                //We have the newid, update backup_ids
                backup_putid($restore->backup_unique_code, $mod->modtype, $mod->id, $newid);
                
                attforblock_restore_attendance_statuses ($mod->id, $newid, $info, $restore);
                attforblock_restore_attendance_sessions ($mod->id, $newid, $info, $restore);
                //Now check if want to restore user data and do it.
                if (restore_userdata_selected($restore, 'attforblock', $mod->id)) {
                    attforblock_restore_attendance_log ($mod->id, $newid, $info, $restore);
                }
            } else {
                $status = false;
            }
        } else {
            $status = false;
        }

        return $status;
    }


    
    function attforblock_restore_attendance_sessions ($old_attforblock_id, $new_attforblock_id, $info, $restore) {

        global $CFG, $oldidarray;

        $status = true;

        if (isset($info['MOD']['#']['SESSIONS'])) {
            @$stats = $info['MOD']['#']['SESSIONS']['0']['#']['SESSION'];
        }else {
            @$stats = $info['MOD']['#']['ATTFORBLOCK_SESSIONS']['0']['#']['ROWS'];
        }
        for($i = 0; $i < sizeof($stats); $i++) {
            $stat_info = $stats[$i];
            //Now, build the attforblock_SESSIONS record structure

            $stat->courseid = $restore->course_id;
//            $stat->creator = backup_todb($stat_info['#']['CREATOR']['0']['#']);
            $stat->sessdate = backup_todb($stat_info['#']['SESSDATE']['0']['#']);
            $stat->timemodified = backup_todb($stat_info['#']['TIMEMODIFIED']['0']['#']);
            $stat->description = backup_todb($stat_info['#']['DESCRIPTION']['0']['#']);
            if (isset($info['MOD']['#']['SESSIONS'])) {
                $stat->duration = backup_todb($stat_info['#']['DURATION']['0']['#']);;
                $stat->lasttaken = backup_todb($stat_info['#']['LASTTAKEN']['0']['#']);
                $stat->lasttakenby = backup_todb($stat_info['#']['LASTTAKENBY']['0']['#']);
            } else { //Old backup
                $stat->duration = 0;
                $stat->lasttaken = backup_todb($stat_info['#']['TIMETAKEN']['0']['#']);
                $stat->lasttakenby = backup_todb($stat_info['#']['TAKENBY']['0']['#']);
            }
            if (restore_userdata_selected($restore, 'attforblock', $old_attforblock_id)) {
                if ($user = backup_getid($restore->backup_unique_code, 'user', $stat->lasttakenby)) {
                    $stat->lasttakenby = $user->new_id;
                }
            } else {
                $stat->lasttaken = 0;
                $stat->lasttakenby = 0;
            }

            $newid = insert_record ('attendance_sessions', $stat);
            $oldidarray[$old_attforblock_id]['attendance_sessions'][backup_todb($stat_info['#']['ID']['0']['#'])] = $newid;
        }
        
        return $status;
    }
    

    
    function attforblock_restore_attendance_statuses ($old_attforblock_id, $new_attforblock_id,$info,$restore) {

        global $CFG, $oldidarray;

        $status = true;

        //Get the statuses array
        if (isset($info['MOD']['#']['STATUSES'])) {
            $stats = $info['MOD']['#']['STATUSES']['0']['#']['STATUS'];
            for($i = 0; $i < sizeof($stats); $i++) {
                $stat_info = $stats[$i];
                //Now, build the attforblock_STATUS record structure

                $stat->courseid  = $restore->course_id;
                $stat->acronym = backup_todb($stat_info['#']['ACRONYM']['0']['#']);
                $stat->description = backup_todb($stat_info['#']['DESCRIPTION']['0']['#']);
                $stat->grade = backup_todb($stat_info['#']['GRADE']['0']['#']);
                $stat->visible = backup_todb($stat_info['#']['VISIBLE']['0']['#']);
                $stat->deleted = backup_todb($stat_info['#']['DELETED']['0']['#']);
                //if user's data not required, we don't restore invisible and deleted statuses
                if (!restore_userdata_selected($restore, 'attforblock', $old_attforblock_id)
                        and (!$stat->visible or $stat->deleted)) {
                    continue;
                }

                $newid = insert_record ('attendance_statuses', $stat);
                $oldidarray[$old_attforblock_id]['attendance_statuses'][backup_todb($stat_info['#']['ID']['0']['#'])] = $newid;
            }

        } elseif (isset($info['MOD']['#']['ATTFORBLOCK_SETTINGS'])) {
            $stats = $info['MOD']['#']['ATTFORBLOCK_SETTINGS']['0']['#']['ROWS'];
            for($i = 0; $i < sizeof($stats); $i++) {
                $stat_info = $stats[$i];
                //Now, build the attforblock_STATUS record structure

                $stat->courseid  = $restore->course_id;
                $stat->acronym = backup_todb($stat_info['#']['ACRONYM']['0']['#']);
                $stat->description = backup_todb($stat_info['#']['DESCRIPTION']['0']['#']);
                $stat->grade = backup_todb($stat_info['#']['GRADE']['0']['#']);
                $stat->visible = 1;
                $stat->deleted = 0;

                $newid = insert_record ('attendance_statuses', $stat);
                $oldidarray[$old_attforblock_id]['attendance_statuses'][backup_todb($stat_info['#']['STATUS']['0']['#'])] = $newid;

            }

        } else {
            // ATTFORBLOCK_SETTINGS tag don't exists
            // so course used default statuses (can be only in old version)
            $stats = get_records('attendance_statuses', 'courseid', 0, 'id ASC');
            $oldstats = array('P', 'A', 'L', 'E');
            $i = 0;
            foreach($stats as $stat) {
//                $stat = $stats[$i];
                $stat->courseid = $restore->course_id;
                $newid = insert_record('attendance_statuses', $stat);
                $oldidarray[$old_attforblock_id]['attendance_statuses'][$oldstats[$i++]] = $newid;
//                $i++;
            }
        }
        
        return $status;
    }
    
    
    function attforblock_restore_attendance_log ($old_attforblock_id, $new_attforblock_id,$info,$restore) {

        global $CFG, $oldidarray;

        $status = true;

        //Get the logs array
        if (isset($info['MOD']['#']['LOGS'])) {
            @$logs = $info['MOD']['#']['LOGS']['0']['#']['LOG'];
        } else {
            @$logs = $info['MOD']['#']['ATTFORBLOCK_LOG']['0']['#']['ROWS'];
        }

        $stats = get_records_menu('attendance_statuses', 'courseid', $restore->course_id);
        $statslist = implode(',', array_keys($stats));
        $sessions = get_records('attendance_sessions', 'courseid', $restore->course_id);

        //Iterate over logs
        for($i = 0; $i < sizeof($logs); $i++) {
            $log_info = $logs[$i];
            //Now, build the attforblock_LOG record structure
            
            $log->studentid = backup_todb($log_info['#']['STUDENTID']['0']['#']);
            $log->remarks = backup_todb($log_info['#']['REMARKS']['0']['#']);
            $user = backup_getid($restore->backup_unique_code, 'user', $log->studentid);
            if ($user) {
                $log->studentid = $user->new_id;
            }
            if (isset($info['MOD']['#']['LOGS'])) {
                $log->sessionid    = $oldidarray[$old_attforblock_id]['attendance_sessions'][backup_todb($log_info['#']['SESSIONID']['0']['#'])];
                $log->statusid     = $oldidarray[$old_attforblock_id]['attendance_statuses'][backup_todb($log_info['#']['STATUSID']['0']['#'])];
                $log->timetaken    = backup_todb($log_info['#']['TIMETAKEN']['0']['#']);

                $log->statusset    = backup_todb($log_info['#']['STATUSSET']['0']['#']);
                $ids = explode(',', $log->statusset);
                foreach ($ids as $id) {
                    $new_ids[] = $oldidarray[$old_attforblock_id]['attendance_statuses'][$id];
                }
                $log->statusset    = implode(',', $new_ids);

                $log->takenby      = backup_todb($log_info['#']['TAKENBY']['0']['#']);
                $user = backup_getid($restore->backup_unique_code, 'user', $log->takenby);
                if ($user) {
                    $log->takenby = $user->new_id;
                }

            } else { //Old version
                // Catching bug of first version of backup
                if (isset($oldidarray[$old_attforblock_id]['attendance_sessions'][backup_todb($log_info['#']['ATTSID']['0']['#'])])) {
                    $log->sessionid = $oldidarray[$old_attforblock_id]['attendance_sessions'][backup_todb($log_info['#']['ATTSID']['0']['#'])];
                } else {
                    continue;
                }
                $log->statusid     = $oldidarray[$old_attforblock_id]['attendance_statuses'][backup_todb($log_info['#']['STATUS']['0']['#'])];
                $log->statusset    = $statslist;
//                $log->timetaken    = get_field('attendance_sessions', 'lasttaken', 'id', $log->sessionid);
                $log->timetaken    = $sessions[$log->sessionid]->lasttaken;
                $log->takenby      = $sessions[$log->sessionid]->lasttakenby;
//                $log->takenby      = backup_todb($log_info['#']['TAKENBY']['0']['#']);
            }

            $newid = insert_record ('attendance_log', $log);
            $oldidarray[$old_attforblock_id]['attendance_log'][backup_todb($log_info['#']['ID']['0']['#'])] = $newid;


            //Do some output
            if (($i+1) % 50 == 0) {
                if (!defined('RESTORE_SILENTLY')) {
                    echo '.';
                    if (($i+1) % 1000 == 0) {
                        echo '<br />';
                    }
                }
                backup_flush(300);
            }
        }
        
        return $status;
    }
    
    
//    function attforblock_restore_logs($restore,$log) {
//
//        $status = true;
//
//        return $status;
//    }
    
?>