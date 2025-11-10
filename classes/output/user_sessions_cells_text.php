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
 * class Template method for generating user's session's cells in text
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package    mod_attendance
 */
class user_sessions_cells_text extends user_sessions_cells {
    /** @var string $enrolmentsinfocelltext. */
    private $enrolmentsinfocelltext;

    /**
     * Construct hidden status cell.
     *
     * @param string $text - text for the cell.
     */
    protected function construct_hidden_status_cell($text) {
        $this->cells[] = '-' . $text;
    }

    /**
     * Construct enrolments info cell.
     *
     * @param string $text - text for the cell.
     */
    protected function construct_enrolments_info_cell($text) {
        if ($this->enrolmentsinfocelltext != $text) {
            $this->enrolmentsinfocelltext = $text;
            $this->cells[] = $text;
        } else {
            $this->cells[] = '←';
        }
    }
}
