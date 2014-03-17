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
 * Attendance module renderering helpers
 *
 * @package    mod_attendance
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/renderables.php');

/**
 * class Template method for generating user's session's cells
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_sessions_cells_generator {
    protected $cells = array();

    protected $reportdata;
    protected $user;

    public function  __construct(attendance_report_data $reportdata, $user) {
        $this->reportdata = $reportdata;
        $this->user = $user;
    }

    public function get_cells($remarks = false) {
        $this->init_cells();
        foreach ($this->reportdata->sessions as $sess) {
            if (array_key_exists($sess->id, $this->reportdata->sessionslog[$this->user->id])) {
                $statusid = $this->reportdata->sessionslog[$this->user->id][$sess->id]->statusid;
                if (array_key_exists($statusid, $this->reportdata->statuses)) {
                    $this->construct_existing_status_cell($this->reportdata->statuses[$statusid]->acronym);
                } else {
                    $this->construct_hidden_status_cell($this->reportdata->allstatuses[$statusid]->acronym);
                }
                if ($remarks) {
                    $this->construct_remarks_cell($this->reportdata->sessionslog[$this->user->id][$sess->id]->remarks);
                }
            } else {
                if ($this->user->enrolmentstart > $sess->sessdate) {
                    $starttext = get_string('enrolmentstart', 'attendance', userdate($this->user->enrolmentstart, '%d.%m.%Y'));
                    $this->construct_enrolments_info_cell($starttext);
                } else if ($this->user->enrolmentend and $this->user->enrolmentend < $sess->sessdate) {
                    $endtext = get_string('enrolmentend', 'attendance', userdate($this->user->enrolmentend, '%d.%m.%Y'));
                    $this->construct_enrolments_info_cell($endtext);
                } else if (!$this->user->enrolmentend and $this->user->enrolmentstatus == ENROL_USER_SUSPENDED) {
                    // No enrolmentend and ENROL_USER_SUSPENDED.
                    $suspendext = get_string('enrolmentsuspended', 'attendance', userdate($this->user->enrolmentend, '%d.%m.%Y'));
                    $this->construct_enrolments_info_cell($suspendext);
                } else {
                    if ($sess->groupid == 0 or array_key_exists($sess->groupid, $this->reportdata->usersgroups[$this->user->id])) {
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

    protected function init_cells() {

    }

    protected function construct_existing_status_cell($text) {
        $this->cells[] = $text;
    }

    protected function construct_hidden_status_cell($text) {
        $this->cells[] = $text;
    }

    protected function construct_enrolments_info_cell($text) {
        $this->cells[] = $text;
    }

    protected function construct_not_taken_cell($text) {
        $this->cells[] = $text;
    }
    
    protected function construct_remarks_cell($text) {
        $this->cells[] = $text;
    }

    protected function construct_not_existing_for_user_session_cell($text) {
        $this->cells[] = $text;
    }

    protected function finalize_cells() {
    }
}

/**
 * class Template method for generating user's session's cells in html
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_sessions_cells_html_generator extends user_sessions_cells_generator {
    private $cell;

    protected function construct_existing_status_cell($text) {
        $this->close_open_cell_if_needed();
        $this->cells[] = $text;
    }

    protected function construct_hidden_status_cell($text) {
        $this->cells[] = html_writer::tag('s', $text);
    }

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

    private function close_open_cell_if_needed() {
        if ($this->cell) {
            $this->cells[] = $this->cell;
            $this->cell = null;
        }
    }

    protected function construct_not_taken_cell($text) {
        $this->close_open_cell_if_needed();
        $this->cells[] = $text;
    }
    
    protected function construct_remarks_cell($text) {
        $this->close_open_cell_if_needed();
        $this->cells[] = $text;
    }

    protected function construct_not_existing_for_user_session_cell($text) {
        $this->close_open_cell_if_needed();
        $this->cells[] = $text;
    }

    protected function finalize_cells() {
        if ($this->cell) {
            $this->cells[] = $this->cell;
        }
    }
}

/**
 * class Template method for generating user's session's cells in text
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_sessions_cells_text_generator extends user_sessions_cells_generator {
    private $enrolments_info_cell_text;

    protected function construct_hidden_status_cell($text) {
        $this->cells[] = '-'.$text;
    }

    protected function construct_enrolments_info_cell($text) {
        if ($this->enrolments_info_cell_text != $text) {
            $this->enrolments_info_cell_text = $text;
            $this->cells[] = $text;
        } else {
            $this->cells[] = '←';
        }
    }
}

function construct_session_time($datetime, $duration) {
    $starttime = userdate($datetime, get_string('strftimehm', 'attendance'));
    $endtime = userdate($datetime + $duration, get_string('strftimehm', 'attendance'));

    return $starttime . ($duration > 0 ? ' - ' . $endtime : '');
}

function construct_session_full_date_time($datetime, $duration) {
    $sessinfo = userdate($datetime, get_string('strftimedmyw', 'attendance'));
    $sessinfo .= ' '.construct_session_time($datetime, $duration);

    return $sessinfo;
}

function construct_user_data_stat($stat, $statuses, $gradable, $grade, $maxgrade, $decimalpoints) {
    global $OUTPUT;

    $stattable = new html_table();
    $stattable->attributes['class'] = 'attlist';
    $row = new html_table_row();
    $row->cells[] = get_string('sessionscompleted', 'attendance').':';
    $row->cells[] = $stat['completed'];
    $stattable->data[] = $row;

    foreach ($statuses as $st) {
        $row = new html_table_row();
        $row->cells[] = $st->description . ':';
        $row->cells[] = array_key_exists($st->id, $stat['statuses']) ? $stat['statuses'][$st->id]->stcnt : 0;

        $stattable->data[] = $row;
    }

    if ($gradable) {
        $row = new html_table_row();
        $row->cells[] = get_string('attendancegrade', 'attendance') .
                        $OUTPUT->help_icon('gradebookexplanation', 'attendance') . ':';
        $row->cells[] = $grade . ' / ' . $maxgrade;
        $stattable->data[] = $row;

        $row = new html_table_row();
        $row->cells[] = get_string('attendancepercent', 'attendance') . ':';
        if ($maxgrade == 0) {
            $percent = 0;
        } else {
            $percent = $grade / $maxgrade * 100;
        }
        $row->cells[] = sprintf("%0.{$decimalpoints}f", $percent);
        $stattable->data[] = $row;
    }

    return html_writer::table($stattable);
}

function construct_full_user_stat_html_table($attendance, $course, $user, $coursemodule) {
    global $CFG;
    $gradeable = $attendance->grade > 0;
    $statuses = att_get_statuses($attendance->id);
    $userstatusesstat = att_get_user_statuses_stat($attendance->id, $course->startdate, $user->id, $coursemodule);
    $stat['completed'] = att_get_user_taken_sessions_count($attendance->id, $course->startdate, $user->id, $coursemodule);
    $stat['statuses'] = $userstatusesstat;
    if ($gradeable) {
        $grade = att_get_user_grade($userstatusesstat, $statuses);
        $maxgrade = att_get_user_max_grade(att_get_user_taken_sessions_count($attendance->id, $course->startdate,
                                                                             $user->id, $coursemodule), $statuses);
        if (!$decimalpoints = grade_get_setting($course->id, 'decimalpoints')) {
            $decimalpoints = $CFG->grade_decimalpoints;
        }
    } else {
        $grade = 0;
        $maxgrade = 0;
        $decimalpoints = 0;
    }

    return construct_user_data_stat($stat, $statuses,
                $gradeable, $grade, $maxgrade, $decimalpoints);
}
