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

?>
