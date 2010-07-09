<?php


    function attforblock_check_backup_mods($course, $user_data=false, $backup_unique_code=null, $instances=null) {

        if (!empty($instances) && is_array($instances) && count($instances)) {
            $info = array();
            foreach ($instances as $id => $instance) {
                $info += attforblock_check_backup_mods_instances($course, $instance, $backup_unique_code);
            }
            return $info;
        }
        return $info;
    }

    

    function attforblock_check_backup_mods_instances($course, $instance, $backup_unique_code) {
        //First the course data
        $info[$instance->id.'0'][0] = '<b>'.$instance->name.'</b>';
        $info[$instance->id.'0'][1] = '';

        $sessions = get_records_menu('attendance_sessions', 'courseid', $course);
        $info[$instance->id.'1'][0] = get_string('sessions', 'attforblock');
        $info[$instance->id.'1'][1] = count($sessions);

        //Now, if requested, the user_data
        if (!empty($instance->userdata)) {
            $info[$instance->id.'2'][0] = get_string('attrecords', 'attforblock');
            $sesslist = implode(',', array_keys($sessions));
            if ($datas = get_records_list('attendance_log', 'sessionid', $sesslist)) {
                $info[$instance->id.'2'][1] = count($datas);
            } else {
                $info[$instance->id.'2'][1] = 0;
            }
        }
        return $info;
    }


    function attforblock_backup_mods($bf, $preferences) {

        global $CFG;

        $status = true;

        //Iterate over attforblock table
        $attforblocks = get_records ('attforblock', 'course', $preferences->backup_course, 'id');
        if ($attforblocks) {
            foreach ($attforblocks as $attforblock) {
                if (backup_mod_selected($preferences, 'attforblock', $attforblock->id)) {
                    $status = attforblock_backup_one_mod($bf, $preferences, $attforblock);
                }
            }
        }
 
        return $status;  
    }

    

    function attforblock_backup_one_mod($bf, $preferences, $attforblock) {

        global $CFG;
    
        if (is_numeric($attforblock)) {
            $attforblock = get_record('attforblock', 'id', $attforblock);
        }
    
        $status = true;

        //Start mod
        fwrite ($bf,start_tag('MOD',3,true));
        //Print attforblock data
        fwrite ($bf,full_tag('ID',4,false,$attforblock->id));
        fwrite ($bf,full_tag('MODTYPE',4,false,'attforblock'));
        fwrite ($bf,full_tag('COURSE',4,false,$attforblock->course));
        fwrite ($bf,full_tag('NAME',4,false,$attforblock->name));
        fwrite ($bf,full_tag('GRADE',4,false,$attforblock->grade));

        attforblock_backup_attendance_statuses ($bf,$preferences,$attforblock);
        attforblock_backup_attendance_sessions ($bf,$preferences,$attforblock);
        if (backup_userdata_selected($preferences, 'attforblock', $attforblock->id)) {
            attforblock_backup_attendance_log ($bf,$preferences,$attforblock);
        }

        //End mod
        $status =fwrite ($bf,end_tag('MOD',3,true));

        return $status;
    }
    
    
    function attforblock_backup_attendance_sessions ($bf,$preferences,$attforblock) {

        global $CFG;

        $status = true;

        $datas = get_records('attendance_sessions', 'courseid', $attforblock->course);
        if ($datas) {
            //Write start tag
            $status =fwrite ($bf,start_tag('SESSIONS',4,true));
            //Iterate over each session
            foreach ($datas as $item) {
                //Start session
                $status =fwrite ($bf,start_tag('SESSION',5,true));
                //Print contents
                fwrite ($bf,full_tag('ID',6,false,$item->id));
                fwrite ($bf,full_tag('COURSEID',6,false,$item->courseid));
                fwrite ($bf,full_tag('SESSDATE',6,false,$item->sessdate));
                fwrite ($bf,full_tag('DURATION',6,false,$item->duration));
                fwrite ($bf,full_tag('TIMEMODIFIED',6,false,$item->timemodified));
                fwrite ($bf,full_tag('DESCRIPTION',6,false,$item->description));
                if (backup_userdata_selected($preferences, 'attforblock', $attforblock->id)) {
                    fwrite ($bf,full_tag('LASTTAKEN',6,false,$item->lasttaken));
                    fwrite ($bf,full_tag('LASTTAKENBY',6,false,$item->lasttakenby));
                } else {
                    fwrite ($bf,full_tag('LASTTAKEN',6,false,0));
                    fwrite ($bf,full_tag('LASTTAKENBY',6,false,0));
                }
                //End submission
                $status =fwrite ($bf,end_tag('SESSION',5,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag('SESSIONS',4,true));
        }
        return $status;
    }
    
    
    function attforblock_backup_attendance_statuses ($bf,$preferences,$attforblock) {

        global $CFG;

        $status = true;

        $datas = get_records('attendance_statuses', 'courseid', $attforblock->course);
        //If there is levels
        if ($datas) {
            //Write start tag
            $status =fwrite ($bf,start_tag('STATUSES',4,true));
            //Iterate over each status
            foreach ($datas as $item) {
                //Start status
                $status =fwrite ($bf,start_tag('STATUS',5,true));
                //Print status contents
                fwrite ($bf,full_tag('ID',6,false,$item->id));
                fwrite ($bf,full_tag('COURSEID',6,false,$item->courseid));
                fwrite ($bf,full_tag('ACRONYM',6,false,$item->acronym));
                fwrite ($bf,full_tag('DESCRIPTION',6,false,$item->description));
                fwrite ($bf,full_tag('GRADE',6,false,$item->grade));
                fwrite ($bf,full_tag('VISIBLE',6,false,$item->visible));
                fwrite ($bf,full_tag('DELETED',6,false,$item->deleted));
                //End submission
                $status =fwrite ($bf,end_tag('STATUS',5,true));
            }
            //Write end tag
            $status =fwrite ($bf,end_tag('STATUSES',4,true));
        }
        return $status;
    }

    
    
    function attforblock_backup_attendance_log ($bf,$preferences,$attforblock) {

        global $CFG;

        $status = true;

        $sessions = get_records_menu('attendance_sessions', 'courseid', $attforblock->course);
        $sesslist = implode(',', array_keys($sessions));
        $datas = get_records_list('attendance_log', 'sessionid', $sesslist);
        //If there is levels
        if ($datas) {
            //Write start tag
            $status = fwrite ($bf,start_tag('LOGS',4,true));
            //Iterate over each log
            foreach ($datas as $item) {
                //Start log
                $status = fwrite ($bf,start_tag('LOG',5,true));
                //Print log contents
                fwrite ($bf,full_tag('ID',6,false,$item->id));
                fwrite ($bf,full_tag('SESSIONID',6,false,$item->sessionid));
                fwrite ($bf,full_tag('STUDENTID',6,false,$item->studentid));
                fwrite ($bf,full_tag('STATUSID',6,false,$item->statusid));
                fwrite ($bf,full_tag('TIMETAKEN',6,false,$item->timetaken));
                fwrite ($bf,full_tag('TAKENBY',6,false,$item->takenby));
                fwrite ($bf,full_tag('STATUSSET',6,false,$item->statusset));
                fwrite ($bf,full_tag('REMARKS',6,false,$item->remarks));
                //End submission
                $status = fwrite ($bf,end_tag('LOG',5,true));
            }
            //Write end tag
            $status = fwrite ($bf,end_tag('LOGS',4,true));
        }
        return $status;
    }


?>