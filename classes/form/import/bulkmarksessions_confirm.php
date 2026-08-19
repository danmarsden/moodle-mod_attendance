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
 * Class definition for mod_attendance_manage_page_params
 *
 * @package   mod_attendance
 * @copyright  2016 Dan Marsden http://danmarsden.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * stores constants/data passed depending on view.
 *
 * @copyright  2016 Dan Marsden http://danmarsden.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\form\import;

use moodleform;

require_once($CFG->libdir . '/formslib.php');

/**
 * Class for confirming bulk attendance CSV mappings.
 *
 * @package   mod_attendance
 * @copyright 2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulkmarksessions_confirm extends moodleform {

    /**
     * Called to define this moodle form.
     *
     * @return void
     */
    public function definition() {
        $params = $this->_customdata;
        $importer = $this->_customdata['importer'];

        $mform = $this->_form;
        $mform->addElement('hidden', 'confirm', 1);
        $mform->setType('confirm', PARAM_BOOL);

        $foundheaders = $importer->list_found_headers();

        $mform->addElement('header', 'mappingheader', get_string('confirmcolumnmappings', 'attendance'));

        // Session mapping.
        $mform->addElement('select', 'sessionfrom', get_string('sessionimportfield', 'attendance'), $foundheaders);
        $mform->addHelpButton('sessionfrom', 'sessionimportfield', 'attendance');

        $sessionoptions = [
            'sessiondate' => get_string('sessiondate', 'attendance'),
            'sessionid'   => get_string('sessionid', 'attendance'),
        ];
        $mform->addElement('select', 'sessionto', get_string('sessionimportto', 'attendance'), $sessionoptions);
        $mform->addHelpButton('sessionto', 'sessionimportto', 'attendance');

        // Auto-detect default session mapping.
        foreach (['sessiondate', 'date', 'session_date'] as $h) {
            $key = array_search($h, $foundheaders);
            if ($key !== false) {
                $mform->setDefault('sessionfrom', $key);
                $mform->setDefault('sessionto', 'sessiondate');
                break;
            }
        }
        if ($mform->getElement('sessionfrom')->getValue() === null) {
            foreach (['sessionid', 'session_id', 'session'] as $h) {
                $key = array_search($h, $foundheaders);
                if ($key !== false) {
                    $mform->setDefault('sessionfrom', $key);
                    $mform->setDefault('sessionto', 'sessionid');
                    break;
                }
            }
        }

        // User mapping.
        $mform->addElement('select', 'userfrom', get_string('userimportfield', 'attendance'), $foundheaders);
        $mform->addHelpButton('userfrom', 'userimportfield', 'attendance');

        $useroptions = [
            'email'    => get_string('email'),
            'username' => get_string('username'),
            'idnumber' => get_string('idnumber'),
            'id'       => get_string('userid', 'attendance'),
        ];
        $mform->addElement('select', 'userto', get_string('userimportto', 'attendance'), $useroptions);
        $mform->addHelpButton('userto', 'userimportto', 'attendance');

        // Auto-detect default user mapping.
        foreach (array_keys($useroptions) as $o) {
            $key = array_search($o, $foundheaders);
            if ($key !== false) {
                $mform->setDefault('userto', $o);
                $mform->setDefault('userfrom', $key);
                break;
            }
        }

        // Optional columns header with "not set" option.
        $foundheaderswithnone = $foundheaders;
        $foundheaderswithnone[-1] = get_string('notset', 'mod_attendance');
        ksort($foundheaderswithnone);

        // Status mapping.
        $mform->addElement('select', 'status', get_string('importstatus', 'attendance'), $foundheaderswithnone);
        $mform->addHelpButton('status', 'importstatus', 'attendance');

        // Scantime mapping.
        $mform->addElement('select', 'scantime', get_string('scantime', 'attendance'), $foundheaderswithnone);
        $mform->addHelpButton('scantime', 'scantime', 'attendance');

        $mform->disabledif('status', 'scantime', 'noteq', -1);
        $mform->disabledif('scantime', 'status', 'noteq', -1);

        // Auto-detect status vs scantime.
        $keystatus = array_search('status', $foundheaders);
        if ($keystatus !== false) {
            $mform->setDefault('status', $keystatus);
            $mform->setDefault('scantime', -1);
        } else {
            $keyscan = array_search('scantime', $foundheaders);
            if ($keyscan !== false) {
                $mform->setDefault('status', -1);
                $mform->setDefault('scantime', $keyscan);
            } else {
                $mform->setDefault('status', -1);
                $mform->setDefault('scantime', -1);
            }
        }

        // Remarks mapping.
        $mform->addElement('select', 'remarks', get_string('remarks', 'attendance'), $foundheaderswithnone);
        $keyremarks = array_search('remarks', $foundheaders);
        if ($keyremarks !== false) {
            $mform->setDefault('remarks', $keyremarks);
        } else {
            $mform->setDefault('remarks', -1);
        }

        $mform->addElement('hidden', 'id', $params['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'importid', $importer->get_importid());
        $mform->setType('importid', PARAM_INT);
        $mform->setConstant('importid', $importer->get_importid());

        $this->add_action_buttons(true, get_string('bulkuploadattendance', 'attendance'));
    }
}
