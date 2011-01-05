<?php
require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_attforblock_mod_form extends moodleform_mod {

    function definition() {

        global $CFG;
        $mform    =& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setDefault('name', get_string('modulename', 'attforblock'));
        
        $mform->addElement('static', 'attdescription', '', get_string('moduledescription', 'attforblock'));

        $mform->addElement('modgrade', 'grade', get_string('grade'));
        $mform->setDefault('grade', 100);
        
        $this->standard_coursemodule_elements(true);

//-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }

}
?>