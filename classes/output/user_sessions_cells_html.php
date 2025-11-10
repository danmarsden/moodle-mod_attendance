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

use core\output\html_writer;
use core_table\output\html_table_cell;
/**
 * class Template method for generating user's session's cells in html
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package    mod_attendance
 */
class user_sessions_cells_html extends user_sessions_cells {
    /** @var html_table_cell $cell */
    private $cell;

    /**
     * Construct status cell.
     *
     * @param string $text - text for the cell.
     */
    protected function construct_existing_status_cell($text) {
        $this->close_open_cell_if_needed();
        $this->cells[] = html_writer::span($text, 'attendancestatus-' . $text);
    }

    /**
     * Construct hidden status cell.
     *
     * @param string $text - text for the cell.
     */
    protected function construct_hidden_status_cell($text) {
        $this->cells[] = html_writer::tag('s', $text);
    }

    /**
     * Construct enrolments info cell.
     *
     * @param string $text - text for the cell.
     */
    protected function construct_enrolments_info_cell($text) {
        if (is_null($this->cell)) {
            $this->cell = new html_table_cell($text);
            $this->cell->colspan = 1;
        } else {
            if ($this->cell->text != $text) {
                $this->cells[] = $this->cell;
                $this->cell = new html_table_cell($text);
                $this->cell->colspan = 1;
            } else {
                $this->cell->colspan++;
            }
        }
    }

    /**
     * Close cell if needed.
     */
    private function close_open_cell_if_needed() {
        if ($this->cell) {
            $this->cells[] = $this->cell;
            $this->cell = null;
        }
    }

    /**
     * Construct not taken cell.
     *
     * @param string $text - text for the cell.
     */
    protected function construct_not_taken_cell($text) {
        $this->close_open_cell_if_needed();
        $this->cells[] = $text;
    }

    /**
     * Construct remarks cell.
     *
     * @param string $text - text for the cell.
     */
    protected function construct_remarks_cell($text) {
        global $OUTPUT;

        if (!trim($text)) {
            return;
        }

        // Format the remark.
        $icon = $OUTPUT->pix_icon('i/info', '');
        $remark = html_writer::span(s($text), 'remarkcontent');
        $remark = html_writer::span($icon . $remark, 'remarkholder');

        // Add it into the previous cell.
        $markcell = array_pop($this->cells);
        $markcell .= ' ' . $remark;
        $this->cells[] = $markcell;
    }

    /**
     * Construct not existing for user session cell.
     *
     * @param string $text - text for the cell.
     */
    protected function construct_not_existing_for_user_session_cell($text) {
        $this->close_open_cell_if_needed();
        $this->cells[] = $text;
    }

    /**
     * Finalize cells.
     *
     */
    protected function finalize_cells() {
        if ($this->cell) {
            $this->cells[] = $this->cell;
        }
    }
}
