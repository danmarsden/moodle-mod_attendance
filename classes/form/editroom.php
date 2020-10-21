<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Form for editing a room
 *
 * @package     mod_attendance
 * @copyright   Florian Metzger-Noel (github.com/flocko-motion)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/formslib.php");

/**
 * class for displaying edit room form.
 *
 * @copyright  Florian Metzger-Noel (github.com/flocko-motion)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editroom extends moodleform {

    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {

        $mform = $this->_form; // Don't forget the underscore!

        $mform->addElement('hidden', 'id', $this->_customdata->id);

        $mform->addElement('text', 'name', get_string('roomname', 'mod_attendance'));
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

}