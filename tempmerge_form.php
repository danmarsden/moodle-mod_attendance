<?php

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/formslib.php');

class tempmerge_form extends moodleform {

    function definition() {
        global $COURSE;

        $context = context_course::instance($COURSE->id);
        $namefields = get_all_user_name_fields(true, 'u');
        $students = get_enrolled_users($context, 'mod/attendance:canbelisted', 0, 'u.id,'.$namefields.',u.email',
                                       'u.lastname, u.firstname', 0, 0, true);
        $partarray = array();
        foreach ($students as $student) {
            $partarray[$student->id] = fullname($student).' ('.$student->email.')';
        }

        $mform = $this->_form;
        $description = $this->_customdata['description'];

        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'userid', 0);
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('header', 'attheader', get_string('tempusermerge', 'attendance'));
        $mform->addElement('static', 'description', get_string('tempuser', 'attendance'), $description);

        $mform->addElement('select', 'participant', get_string('participant', 'attendance'), $partarray);

        $mform->addElement('static', 'requiredentries', '', get_string('requiredentries', 'attendance'));
        $mform->addHelpButton('requiredentries', 'requiredentry', 'attendance');

        $this->add_action_buttons(true, get_string('mergeuser', 'attendance'));
    }
}