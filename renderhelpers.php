<?php
/**
 * Attendance module renderering helpers
 *
 * @package    mod
 * @subpackage attforblock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/renderables.php');

/**
 * Template method for generating user's session's cells
 */
class user_sessions_cells_generator {
    protected $cells = array();

    protected $reportdata;
    protected $user;

    public function  __construct(attforblock_report_data $reportdata, $user) {
        $this->reportdata = $reportdata;
        $this->user = $user;
    }

    public function get_cells() {
        $this->init_cells();
        foreach ($this->reportdata->sessions as $sess) {
            if (array_key_exists($sess->id, $this->reportdata->sessionslog[$this->user->id])) {
                $statusid = $this->reportdata->sessionslog[$this->user->id][$sess->id]->statusid;
                if (array_key_exists($statusid, $this->reportdata->statuses)) {
                    $this->construct_existing_status_cell($this->reportdata->statuses[$statusid]->acronym);
                } else {
                    $this->construct_hidden_status_cell($this->reportdata->allstatuses[$statusid]->acronym);
                }
            } else {
                if ($this->user->enrolmentstart > $sess->sessdate) {
                    $starttext = get_string('enrolmentstart', 'attforblock', userdate($this->user->enrolmentstart, '%d.%m.%Y'));
                    $this->construct_enrolments_info_cell($starttext);
                }
                elseif ($this->user->enrolmentend and $this->user->enrolmentend < $sess->sessdate) {
                    $endtext = get_string('enrolmentend', 'attforblock', userdate($this->user->enrolmentend, '%d.%m.%Y'));
                    $this->construct_enrolments_info_cell($endtext);
                }
                // no enrolmentend and ENROL_USER_SUSPENDED
                elseif (!$this->user->enrolmentend and $this->user->enrolmentstatus == ENROL_USER_SUSPENDED) {
                    $suspendext = get_string('enrolmentsuspended', 'attforblock', userdate($this->user->enrolmentend, '%d.%m.%Y'));
                    $this->construct_enrolments_info_cell($suspendext);
                }
                else {
                    if ($sess->groupid == 0 or array_key_exists($sess->groupid, $this->reportdata->usersgroups[$this->user->id]))
                        $this->construct_not_taken_cell('?');
                    else
                        $this->construct_not_existing_for_user_session_cell('');
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

    protected function construct_not_existing_for_user_session_cell($text) {
        $this->cells[] = $text;
    }

    protected function finalize_cells() {
        
    }
}

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
        }
        else {
            if ($this->cell->text != $text) {
                $this->cells[] = $this->cell;
                $this->cell = new html_table_cell($text);
                $this->cell->colspan = 1;
            }
            else
                $this->cell->colspan++;
        }
    }

    private function close_open_cell_if_needed(){
        if ($this->cell) {
            $this->cells[] = $this->cell;
            $this->cell = null;
        }
    }
    
    protected function construct_not_taken_cell($text) {
        $this->close_open_cell_if_needed();
        $this->cells[] = $text;
    }

    protected function construct_not_existing_for_user_session_cell($text) {
        $this->close_open_cell_if_needed();
        $this->cells[] = $text;
    }

    protected function finalize_cells() {
        if ($this->cell)
            $this->cells[] = $this->cell;
    }
}

class user_sessions_cells_text_generator extends user_sessions_cells_generator {
    private $enrolments_info_cell_text;

    protected function construct_hidden_status_cell($text) {
        $this->cells[] = '-'.$text;
    }

    protected function construct_enrolments_info_cell($text) {
        if ($this->enrolments_info_cell_text != $text) {
            $this->enrolments_info_cell_text = $text;
            $this->cells[] = $text;
        }
        else
            $this->cells[] = '←';
    }
}

function construct_session_time($datetime, $duration) {
    $starttime = userdate($datetime, get_string('strftimehm', 'attforblock'));
    $endtime = userdate($datetime + $duration, get_string('strftimehm', 'attforblock'));

    return $starttime . ($duration > 0 ? ' - ' . $endtime : '');
}

function construct_session_full_date_time($datetime, $duration) {
    $sessinfo = userdate($datetime, get_string('strftimedmyw', 'attforblock'));
    $sessinfo .= ' '.construct_session_time($datetime, $duration);

    return $sessinfo;
}

function construct_user_data_stat($stat, $statuses, $gradable, $grade, $maxgrade, $decimalpoints) {
    global $OUTPUT;
    
    $stattable = new html_table();
    $stattable->attributes['class'] = 'attlist';
    $row = new html_table_row();
    $row->cells[] = get_string('sessionscompleted','attforblock').':';
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
        $row->cells[] = get_string('attendancegrade','attforblock') . $OUTPUT->help_icon('gradebookexplanation', 'attforblock') . ':';
        $row->cells[] = $grade . ' / ' . $maxgrade;
        $stattable->data[] = $row;

        $row = new html_table_row();
        $row->cells[] = get_string('attendancepercent','attforblock') . ':';
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

function construct_full_user_stat_html_table($attforblock, $course, $user) {
        global $CFG;

        $gradeable = $attforblock->grade > 0;
        $statuses = att_get_statuses($attforblock->id);
        $userstatusesstat = att_get_user_statuses_stat($attforblock->id, $course->startdate, $user->id);
        $stat['completed'] = att_get_user_taken_sessions_count($attforblock->id, $course->startdate, $user->id);
        $stat['statuses'] = $userstatusesstat;
        if ($gradeable) {
            $grade = att_get_user_grade($userstatusesstat, $statuses);
            $maxgrade = att_get_user_max_grade(att_get_user_taken_sessions_count($attforblock->id, $course->startdate, $user->id), $statuses);
            if (!$decimalpoints = grade_get_setting($course->id, 'decimalpoints')) {
                $decimalpoints = $CFG->grade_decimalpoints;
            }
        }
        else {
            $grade = 0;
            $maxgrade = 0;
            $decimalpoints = 0;
        }

		return construct_user_data_stat($stat, $statuses,
                    $gradeable, $grade, $maxgrade, $decimalpoints);
}


?>
