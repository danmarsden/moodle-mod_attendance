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
defined('MOODLE_INTERNAL') || die();

/**
 * Class definition for mod_attendance_view_page_params
 *
 * @package   mod_attendance
 * @copyright  2016 Dan Marsden http://danmarsden.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * contains functions/constants used by attendance view page.
 *
 * @copyright  2016 Dan Marsden http://danmarsden.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_attendance_view_page_params extends mod_attendance_page_with_filter_controls {
    const MODE_THIS_COURSE  = 0;
    const MODE_ALL_COURSES  = 1;

    public $studentid;

    public $mode;

    public function  __construct() {
        $this->defaultview = ATT_VIEW_MONTHS;
    }

    public function get_significant_params() {
        $params = array();

        if (isset($this->studentid)) {
            $params['studentid'] = $this->studentid;
        }
        if ($this->mode != self::MODE_THIS_COURSE) {
            $params['mode'] = $this->mode;
        }

        return $params;
    }
}