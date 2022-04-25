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
 * Attendance module renderable component.
 *
 * @package    mod_attendance
 * @copyright  2022 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\output;

use renderable;
use mod_attendance_structure;
use mod_attendance_sessions_page_params;
use tabobject;

/**
 * Represents info about attendance tabs.
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */
class tabs implements renderable {
    /** Sessions tab */
    const TAB_SESSIONS      = 1;
    /** Add tab */
    const TAB_ADD           = 2;
    /** Rerort tab */
    const TAB_REPORT        = 3;
    /** Export tab */
    const TAB_EXPORT        = 4;
    /** Preferences tab */
    const TAB_PREFERENCES   = 5;
    /** Temp users tab */
    const TAB_TEMPORARYUSERS = 6; // Tab for managing temporary users.
    /** Update tab */
    const TAB_UPDATE        = 7;
    /** Warnings tab */
    const TAB_WARNINGS = 8;
    /** Absentee tab */
    const TAB_ABSENTEE      = 9;
    /** Import tab */
    const TAB_IMPORT        = 10;
    /** @var int current tab */
    public $currenttab;

    /** @var stdClass attendance */
    private $att;

    /**
     * Prepare info about sessions for attendance taking into account view parameters.
     *
     * @param mod_attendance_structure $att
     * @param int $currenttab - one of mod_attendance\output\tabs constants
     */
    public function  __construct(mod_attendance_structure $att, $currenttab=null) {
        $this->att = $att;
        $this->currenttab = $currenttab;
    }

    /**
     * Return array of rows where each row is an array of tab objects
     * taking into account permissions of current user
     */
    public function get_tabs() {
        $toprow = array();
        $context = $this->att->context;
        $capabilities = array(
            'mod/attendance:manageattendances',
            'mod/attendance:takeattendances',
            'mod/attendance:changeattendances'
        );
        if (has_any_capability($capabilities, $context)) {
            $toprow[] = new tabobject(self::TAB_SESSIONS, $this->att->url_manage()->out(),
                            get_string('sessions', 'attendance'));
        }

        if (has_capability('mod/attendance:manageattendances', $context)) {
            $toprow[] = new tabobject(self::TAB_ADD,
                            $this->att->url_sessions()->out(true,
                                array('action' => mod_attendance_sessions_page_params::ACTION_ADD)),
                                get_string('addsession', 'attendance'));
        }
        if (has_capability('mod/attendance:viewreports', $context)) {
            $toprow[] = new tabobject(self::TAB_REPORT, $this->att->url_report()->out(),
                            get_string('report', 'attendance'));
        }

        if (has_capability('mod/attendance:viewreports', $context) &&
            get_config('attendance', 'enablewarnings')) {
            $toprow[] = new tabobject(self::TAB_ABSENTEE, $this->att->url_absentee()->out(),
                get_string('absenteereport', 'attendance'));
        }

        if (has_capability('mod/attendance:import', $context)) {
            $toprow[] = new tabobject(self::TAB_IMPORT, $this->att->url_import()->out(),
                get_string('import', 'attendance'));
        }

        if (has_capability('mod/attendance:export', $context)) {
            $toprow[] = new tabobject(self::TAB_EXPORT, $this->att->url_export()->out(),
                            get_string('export', 'attendance'));
        }

        if (has_capability('mod/attendance:changepreferences', $context)) {
            $toprow[] = new tabobject(self::TAB_PREFERENCES, $this->att->url_preferences()->out(),
                            get_string('statussetsettings', 'attendance'));

            if (get_config('attendance', 'enablewarnings')) {
                $toprow[] = new tabobject(self::TAB_WARNINGS, $this->att->url_warnings()->out(),
                    get_string('warnings', 'attendance'));
            }
        }
        if (has_capability('mod/attendance:managetemporaryusers', $context)) {
            $toprow[] = new tabobject(self::TAB_TEMPORARYUSERS, $this->att->url_managetemp()->out(),
                            get_string('tempusers', 'attendance'));
        }
        if ($this->currenttab == self::TAB_UPDATE && has_capability('mod/attendance:manageattendances', $context)) {
            $toprow[] = new tabobject(self::TAB_UPDATE,
                            $this->att->url_sessions()->out(true,
                                array('action' => mod_attendance_sessions_page_params::ACTION_UPDATE)),
                                get_string('changesession', 'attendance'));
        }

        return array($toprow);
    }
}
