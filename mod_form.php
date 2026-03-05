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

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/moodleform_mod.php');

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
        $attendanceconfig = get_config('attendance');
        if (!isset($attendanceconfig->subnet)) {
            $attendanceconfig->subnet = '';
        }
        $mform    =& $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->setDefault('name', get_string('modulename', 'attendance'));

        $this->standard_intro_elements();

        // Grade settings.
        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements(true);

        // IP address.
        if (get_config('attendance', 'subnetactivitylevel')) {
            $mform->addElement('header', 'security', get_string('extrarestrictions', 'attendance'));
            $mform->addElement('text', 'subnet', get_string('defaultsubnet', 'attendance'), ['size' => '164']);
            $mform->setType('subnet', PARAM_TEXT);
            $mform->addHelpButton('subnet', 'defaultsubnet', 'attendance');
            $mform->setDefault('subnet', $attendanceconfig->subnet);
        } else {
            $mform->addElement('hidden', 'subnet', '');
            $mform->setType('subnet', PARAM_TEXT);
        }

        // Planned course size for warning basis.
        $mform->addElement('header', 'warningbasis', get_string('warningbasis', 'mod_attendance'));
        $mform->addElement('text', 'plannedtotalsessions', get_string('plannedtotalsessions', 'mod_attendance'), ['size' => '6']);
        $mform->setType('plannedtotalsessions', PARAM_INT);
        $mform->addHelpButton('plannedtotalsessions', 'plannedtotalsessions', 'mod_attendance');
        $mform->setDefault('plannedtotalsessions', '');

        $mform->addElement('text', 'plannedtotalhours', get_string('plannedtotalhours', 'mod_attendance'), ['size' => '6']);
        $mform->setType('plannedtotalhours', PARAM_FLOAT);
        $mform->addHelpButton('plannedtotalhours', 'plannedtotalhours', 'mod_attendance');
        $mform->setDefault('plannedtotalhours', '');

        $basismodeoptions = [
            'current_sessions' => get_string('warningbasismode_current', 'mod_attendance'),
            'planned_sessions' => get_string('warningbasismode_planned_sessions', 'mod_attendance'),
            'planned_hours' => get_string('warningbasismode_planned_hours', 'mod_attendance'),
        ];
        $mform->addElement('select', 'warningbasismode', get_string('warningbasismode', 'mod_attendance'), $basismodeoptions);
        $mform->addHelpButton('warningbasismode', 'warningbasismode', 'mod_attendance');
        $mform->setDefault('warningbasismode', 'current_sessions');

        $this->add_action_buttons();
    }

    /**
     * Form validation.
     *
     * @param array $data form data
     * @param array $files uploaded files
     * @return array errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['warningbasismode'])) {
            if ($data['warningbasismode'] === 'planned_sessions') {
                if (empty($data['plannedtotalsessions']) || (int)$data['plannedtotalsessions'] < 1) {
                    $errors['plannedtotalsessions'] = get_string('plannedtotalsessions_required', 'mod_attendance');
                }
            }
            if ($data['warningbasismode'] === 'planned_hours') {
                if (!isset($data['plannedtotalhours']) || $data['plannedtotalhours'] === '' ||
                        (float)$data['plannedtotalhours'] <= 0) {
                    $errors['plannedtotalhours'] = get_string('plannedtotalhours_required', 'mod_attendance');
                }
            }
        }
        return $errors;
    }
}
