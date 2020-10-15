<?php
/**
 * @package     mod_attendance
 * @author      Florian Metzger-Noel (github.com/flocko-motion)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

//moodleform is defined in formslib.php
global $CFG, $DB, $OUTPUT, $PAGE, $SESSION, $USER;
require_once("$CFG->libdir/formslib.php");


class editroom extends moodleform {
    //Add elements to form
    public function definition() {

        $mform = $this->_form; // Don't forget the underscore!

        $mform->addElement('hidden', 'id', $this->_customdata->id);

        $mform->addElement('text', 'name', get_string('roomname','mod_attendance'));
        $mform->setType('name', PARAM_NOTAGS);
        $mform->setDefault('name', trim($this->_customdata->name));
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 64), 'maxlength', 64, 'client');


        $mform->addElement('text', 'description', get_string('description'));
        $mform->setType('description', PARAM_NOTAGS);
        $mform->setDefault('description', $this->_customdata->description);

        $mform->addElement('text', 'capacity', get_string('roomcapacity', 'mod_attendance'));
        $mform->setType('capacity', PARAM_INT);
        $mform->setDefault('capacity', $this->_customdata->capacity);
        $mform->addRule('capacity', get_string('mustbenumericorempty', 'mod_attendance'), 'numeric', null, 'client');

        $options = [
            0 => get_string('no'),
            1 => get_string('yes'),
        ];
        $mform->addElement('select', 'bookable', get_string('roombookable', 'mod_attendance'), $options);
        $mform->setDefault('bookable', $this->_customdata->bookable);

        $this->add_action_buttons();

    }
    //Custom validation should be added here
    function validation($data, $files) {
        return array();
    }
}