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

namespace mod_attendance\output;

/**
 * class Template method for generating user's session's cells
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package    mod_attendance
 */
class user_sessions_cells {
    /** @var array $cells - list of table cells. */
    protected $cells = [];

    /** @var stdClass $reportdata - data for report. */
    protected $reportdata;

    /** @var stdClass $user - user record. */
    protected $user;

    /**
     * Set up params.
     * @param mod_attendance\output\report_data $reportdata - reportdata.
     * @param stdClass $user - user record.
     */
    public function __construct(report_data $reportdata, $user) {
        $this->reportdata = $reportdata;
        $this->user = $user;
    }

    /**
     * Get cells for the table.
     *
     * @param boolean $remarks - include remarks cell.
     */
    public function get_cells($remarks = false) {
        foreach ($this->reportdata->sessions as $sess) {
            if (
                array_key_exists($sess->id, $this->reportdata->sessionslog[$this->user->id]) &&
                !empty($this->reportdata->sessionslog[$this->user->id][$sess->id]->statusid)
            ) {
                $statusid = $this->reportdata->sessionslog[$this->user->id][$sess->id]->statusid;
                if (array_key_exists($statusid, $this->reportdata->statuses)) {
                    $points = format_float($this->reportdata->statuses[$statusid]->grade, 1, true, true);
                    $maxpoints = format_float($sess->maxpoints, 1, true, true);
                    $this->construct_existing_status_cell($this->reportdata->statuses[$statusid]->acronym .
                                " ({$points}/{$maxpoints})");
                } else {
                    if (
                        !empty($this->reportdata->allstatuses[$statusid] &&
                        isset($this->reportdata->allstatuses[$statusid]->acronym))
                    ) {
                        $statusac = $this->reportdata->allstatuses[$statusid]->acronym;
                    } else {
                        $statusac = get_string('unknownstatus', 'mod_attendance', $statusid);
                    }
                    $this->construct_hidden_status_cell($statusac);
                }
                if ($remarks) {
                    $this->construct_remarks_cell($this->reportdata->sessionslog[$this->user->id][$sess->id]->remarks);
                }
            } else {
                if ($this->user->enrolmentstart > ($sess->sessdate + $sess->duration)) {
                    $starttext = get_string('enrolmentstart', 'attendance', userdate($this->user->enrolmentstart, '%d.%m.%Y'));
                    $this->construct_enrolments_info_cell($starttext);
                } else if ($this->user->enrolmentend && $this->user->enrolmentend < $sess->sessdate) {
                    $endtext = get_string('enrolmentend', 'attendance', userdate($this->user->enrolmentend, '%d.%m.%Y'));
                    $this->construct_enrolments_info_cell($endtext);
                } else if (!$this->user->enrolmentend && $this->user->enrolmentstatus == ENROL_USER_SUSPENDED) {
                    // No enrolmentend and ENROL_USER_SUSPENDED.
                    $suspendext = get_string('enrolmentsuspended', 'attendance', userdate($this->user->enrolmentend, '%d.%m.%Y'));
                    $this->construct_enrolments_info_cell($suspendext);
                } else {
                    if ($sess->groupid == 0 || array_key_exists($sess->groupid, $this->reportdata->usersgroups[$this->user->id])) {
                        $this->construct_not_taken_cell('?');
                    } else {
                        $this->construct_not_existing_for_user_session_cell('');
                    }
                }
                if ($remarks) {
                    $this->construct_remarks_cell('');
                }
            }
        }
        $this->finalize_cells();

        return $this->cells;
    }

    /**
     * Construct status cell.
     *
     * @param string $text - text for the cell.
     */
    protected function construct_existing_status_cell($text) {
        $this->cells[] = $text;
    }

    /**
     * Construct hidden status cell.
     *
     * @param string $text - text for the cell.
     */
    protected function construct_hidden_status_cell($text) {
        $this->cells[] = $text;
    }

    /**
     * Construct enrolments info cell.
     *
     * @param string $text - text for the cell.
     */
    protected function construct_enrolments_info_cell($text) {
        $this->cells[] = $text;
    }

    /**
     * Construct not taken cell.
     *
     * @param string $text - text for the cell.
     */
    protected function construct_not_taken_cell($text) {
        $this->cells[] = $text;
    }

    /**
     * Construct remarks cell.
     *
     * @param string $text - text for the cell.
     */
    protected function construct_remarks_cell($text) {
        $this->cells[] = $text;
    }

    /**
     * Construct not existing user session cell.
     *
     * @param string $text - text for the cell.
     */
    protected function construct_not_existing_for_user_session_cell($text) {
        $this->cells[] = $text;
    }

    /**
     * Dummy stub method, called at the end. - override if you need/
     */
    protected function finalize_cells() {
    }
}
