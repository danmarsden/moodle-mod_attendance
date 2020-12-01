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
 * This file contains an event for when an presence is taken.
 *
 * @package    mod_presence
 * @copyright  2014 onwards Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_presence\event;
defined('MOODLE_INTERNAL') || die();

/**
 * Event for when an presence is taken.
 *
 * @property-read array $other {
 *      Extra information about event properties.
 *
 *    string mode Mode of the report viewed.
 * }
 * @package    mod_presence
 * @since      Moodle 2.7
 * @copyright  2013 onwards Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presence_taken extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'presence_evaluations';
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return 'User with id ' . $this->userid . ' took presence with instanceid ' .
            $this->objectid;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventtaken', 'mod_presence');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/presence/take.php', array('id' => $this->contextinstanceid,
                                                                 'sessionid' => $this->other['sessionid'],
                                                                 'grouptype' => $this->other['grouptype']));
    }

    /**
     * Replace add_to_log() statement.
     *
     * @return array of parameters to be passed to legacy add_to_log() function.
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, 'presence', 'taken', $this->get_url(),
            '', $this->contextinstanceid);
    }

    /**
     * Get objectid mapping
     *
     * @return array of parameters for object mapping.
     */
    public static function get_objectid_mapping() {
        return array('db' => 'presence', 'restore' => 'presence');
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        if (empty($this->other['sessionid'])) {
            throw new \coding_exception('The event mod_presence\\event\\presence_taken must specify sessionid.');
        }
        parent::validate_data();
    }
}
