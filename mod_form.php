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
 * Forms for updating/adding attendance
 *
 * @package    mod_attendance
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * class for displaying add/update form.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_attendance_mod_form extends moodleform_mod {

    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {
        $mform    =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setDefault('name', get_string('modulename', 'attendance'));

        // Grade settings.
        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements(true);
        
        $mform->addElement('header', 'security', get_string('extrarestrictions', 'attendance'));
        
        $mform->addElement('selectyesno', 'requirelan', get_string('requirelan', 'attendance'));
        // IP address.
        $mform->addElement('text', 'subnet', get_string('requiresubnet', 'attendance'), array('size' => '164'));
        $mform->setType('subnet', PARAM_TEXT);
        $mform->addHelpButton('subnet', 'requiresubnet', 'attendance');
        $mform->disabledIf('subnet', 'requirelan', 'eq', 0);
        $mform->setDefault('subnet', '');  // we put here the IPS of our campus' LAN ('132.66,132.67,172.16,172.18')
        
        $this->add_action_buttons();
    }
}
