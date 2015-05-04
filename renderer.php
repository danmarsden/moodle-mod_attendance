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
 * Attendance module renderering methods
 *
 * @package    mod_attendance
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/renderables.php');
require_once(dirname(__FILE__).'/renderhelpers.php');

/**
 * Attendance module renderer class
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_attendance_renderer extends plugin_renderer_base {
    // External API - methods to render attendance renderable components.

    /**
     * Renders tabs for attendance
     *
     * @param atttabs - tabs to display
     * @return string html code
     */
    protected function render_attendance_tabs(attendance_tabs $atttabs) {
        return print_tabs($atttabs->get_tabs(), $atttabs->currenttab, null, null, true);
    }

    /**
     * Renders filter controls for attendance
     *
     * @param fcontrols - filter controls data to display
     * @return string html code
     */
    protected function render_attendance_filter_controls(attendance_filter_controls $fcontrols) {
        $filtertable = new html_table();
        $filtertable->attributes['class'] = ' ';
        $filtertable->width = '100%';
        $filtertable->align = array('left', 'center', 'right', 'right');

        $filtertable->data[0][] = $this->render_sess_group_selector($fcontrols);

        $filtertable->data[0][] = $this->render_curdate_controls($fcontrols);

        $filtertable->data[0][] = $this->render_paging_controls($fcontrols);

        $filtertable->data[0][] = $this->render_view_controls($fcontrols);

        $o = html_writer::table($filtertable);
        $o = $this->output->container($o, 'attfiltercontrols attwidth');

        return $o;
    }

    protected function render_sess_group_selector(attendance_filter_controls $fcontrols) {
        switch ($fcontrols->pageparams->selectortype) {
            case att_page_with_filter_controls::SELECTOR_SESS_TYPE:
                $sessgroups = $fcontrols->get_sess_groups_list();
                if ($sessgroups) {
                    $select = new single_select($fcontrols->url(), 'group', $sessgroups,
                                                $fcontrols->get_current_sesstype(), null, 'selectgroup');
                    $select->label = get_string('sessions', 'attendance');
                    $output = $this->output->render($select);

                    return html_writer::tag('div', $output, array('class' => 'groupselector'));
                }
                break;
            case att_page_with_filter_controls::SELECTOR_GROUP:
                return groups_print_activity_menu($fcontrols->cm, $fcontrols->url(), true);
        }

        return '';
    }

    protected function render_paging_controls(attendance_filter_controls $fcontrols) {
        global $CFG;

        $paging_controls = '';

        $group = 0;
        if (!empty($fcontrols->pageparams->group)) {
            $group = $fcontrols->pageparams->group;
        }

        $totalusers = count_enrolled_users(context_module::instance($fcontrols->cm->id), 'mod/attendance:canbelisted', $group);
        
        if (empty($fcontrols->pageparams->page) || !$fcontrols->pageparams->page || !$totalusers || empty($fcontrols->pageparams->perpage)) {
            return $paging_controls;
        }

        $numberofpages = ceil($totalusers / $fcontrols->pageparams->perpage);

        if ($fcontrols->pageparams->page > 1) {
            $paging_controls .= html_writer::link($fcontrols->url(array('curdate' => $fcontrols->curdate, 'page' => $fcontrols->pageparams->page - 1)),
                                                                         $this->output->larrow());
        }
        $paging_controls .= html_writer::tag('span', "Page {$fcontrols->pageparams->page} of $numberofpages", array('class' => 'attbtn'));
        if ($fcontrols->pageparams->page < $numberofpages) {
            $paging_controls .= html_writer::link($fcontrols->url(array('curdate' => $fcontrols->curdate, 'page' => $fcontrols->pageparams->page + 1)),
                                                                         $this->output->rarrow());
        }

        return $paging_controls ;
    }

    protected function render_curdate_controls(attendance_filter_controls $fcontrols) {
        global $CFG;

        $curdate_controls = '';
        if ($fcontrols->curdatetxt) {
            $this->page->requires->strings_for_js(array('calclose', 'caltoday'), 'attendance');
            $jsvals = array(
                    'cal_months'    => explode(',', get_string('calmonths', 'attendance')),
                    'cal_week_days' => explode(',', get_string('calweekdays', 'attendance')),
                    'cal_start_weekday' => $CFG->calendar_startwday,
                    'cal_cur_date'  => $fcontrols->curdate);
            $curdate_controls = html_writer::script(js_writer::set_variable('M.attendance', $jsvals));

            $this->page->requires->js('/mod/attendance/calendar.js');

            $curdate_controls .= html_writer::link($fcontrols->url(array('curdate' => $fcontrols->prevcur)),
                                                                         $this->output->larrow());
            $params = array(
                    'title' => get_string('calshow', 'attendance'),
                    'id'    => 'show',
                    'type'  => 'button');
            $button_form = html_writer::tag('button', $fcontrols->curdatetxt, $params);
            foreach ($fcontrols->url_params(array('curdate' => '')) as $name => $value) {
                $params = array(
                        'type'  => 'hidden',
                        'id'    => $name,
                        'name'  => $name,
                        'value' => $value);
                $button_form .= html_writer::empty_tag('input', $params);
            }
            $params = array(
                    'id'        => 'currentdate',
                    'action'    => $fcontrols->url_path(),
                    'method'    => 'post'
            );

            $button_form = html_writer::tag('form', $button_form, $params);
            $curdate_controls .= $button_form;

            $curdate_controls .= html_writer::link($fcontrols->url(array('curdate' => $fcontrols->nextcur)),
                                                                         $this->output->rarrow());
        }

        return $curdate_controls;
    }

    protected function render_view_controls(attendance_filter_controls $fcontrols) {
        $views[ATT_VIEW_ALL] = get_string('all', 'attendance');
        $views[ATT_VIEW_ALLPAST] = get_string('allpast', 'attendance');
        if ($fcontrols->reportcontrol) {
            $views[ATT_VIEW_NOTPRESENT] = get_string('lowgrade', 'attendance');
        }
        $views[ATT_VIEW_MONTHS] = get_string('months', 'attendance');
        $views[ATT_VIEW_WEEKS] = get_string('weeks', 'attendance');
        $views[ATT_VIEW_DAYS] = get_string('days', 'attendance');
        $viewcontrols = '';
        foreach ($views as $key => $sview) {
            if ($key != $fcontrols->pageparams->view) {
                $link = html_writer::link($fcontrols->url(array('view' => $key)), $sview);
                $viewcontrols .= html_writer::tag('span', $link, array('class' => 'attbtn'));
            } else {
                $viewcontrols .= html_writer::tag('span', $sview, array('class' => 'attcurbtn'));
            }
        }

        return html_writer::tag('nobr', $viewcontrols);
    }

    /**
     * Renders attendance sessions managing table
     *
     * @param attendance_manage_data $sessdata to display
     * @return string html code
     */
    protected function render_attendance_manage_data(attendance_manage_data $sessdata) {
        $o = $this->render_sess_manage_table($sessdata) . $this->render_sess_manage_control($sessdata);
        $o = html_writer::tag('form', $o, array('method' => 'post', 'action' => $sessdata->url_sessions()->out()));
        $o = $this->output->container($o, 'generalbox attwidth');
        $o = $this->output->container($o, 'attsessions_manage_table');

        return $o;
    }

    protected function render_sess_manage_table(attendance_manage_data $sessdata) {
        $this->page->requires->js_init_call('M.mod_attendance.init_manage');

        $table = new html_table();
        $table->width = '100%';
        $table->head = array(
                '#',
                get_string('sessiontypeshort', 'attendance'),
                get_string('date'),
                get_string('time'),
                get_string('description', 'attendance'),
                get_string('actions'),
                html_writer::checkbox('cb_selector', 0, false, '', array('id' => 'cb_selector'))
            );
        $table->align = array('', '', '', '', 'center', 'center', 'center');
        $table->size = array('1px', '', '1px', '1px', '*', '1px', '1px');

        $i = 0;
        foreach ($sessdata->sessions as $key => $sess) {
            $i++;

            $dta = $this->construct_date_time_actions($sessdata, $sess);

            $table->data[$sess->id][] = $i;
            $table->data[$sess->id][] = $sess->groupid ? $sessdata->groups[$sess->groupid]->name :
                                                         get_string('commonsession', 'attendance');
            $table->data[$sess->id][] = $dta['date'];
            $table->data[$sess->id][] = $dta['time'];
            $table->data[$sess->id][] = $sess->description;
            $table->data[$sess->id][] = $dta['actions'];
            $table->data[$sess->id][] = html_writer::checkbox('sessid[]', $sess->id, false);
        }

        return html_writer::table($table);
    }

    private function construct_date_time_actions(attendance_manage_data $sessdata, $sess) {
        $actions = '';

        $date = userdate($sess->sessdate, get_string('strftimedmyw', 'attendance'));
        $time = $this->construct_time($sess->sessdate, $sess->duration);
        if ($sess->lasttaken > 0) {
            if ($sessdata->perm->can_change()) {
                $url = $sessdata->url_take($sess->id, $sess->groupid);
                $title = get_string('changeattendance', 'attendance');

                $date = html_writer::link($url, $date, array('title' => $title));
                $time = html_writer::link($url, $time, array('title' => $title));

                $actions = $this->output->action_icon($url, new pix_icon('redo', $title, 'attendance'));
            } else {
                $date = '<i>' . $date . '</i>';
                $time = '<i>' . $time . '</i>';
            }
        } else {
            if ($sessdata->perm->can_take()) {
                $url = $sessdata->url_take($sess->id, $sess->groupid);
                $title = get_string('takeattendance', 'attendance');
                $actions = $this->output->action_icon($url, new pix_icon('t/go', $title));
            }
        }
        if ($sessdata->perm->can_manage()) {
            $url = $sessdata->url_sessions($sess->id, att_sessions_page_params::ACTION_UPDATE);
            $title = get_string('editsession', 'attendance');
            $actions .= $this->output->action_icon($url, new pix_icon('t/edit', $title));

            $url = $sessdata->url_sessions($sess->id, att_sessions_page_params::ACTION_DELETE);
            $title = get_string('deletesession', 'attendance');
            $actions .= $this->output->action_icon($url, new pix_icon('t/delete', $title));
        }

        return array('date' => $date, 'time' => $time, 'actions' => $actions);
    }

    protected function render_sess_manage_control(attendance_manage_data $sessdata) {
        $table = new html_table();
        $table->attributes['class'] = ' ';
        $table->width = '100%';
        $table->align = array('left', 'right');

        $table->data[0][] = $this->output->help_icon('hiddensessions', 'attendance',
                get_string('hiddensessions', 'attendance').': '.$sessdata->hiddensessionscount);

        if ($sessdata->perm->can_manage()) {
            $options = array(
                        att_sessions_page_params::ACTION_DELETE_SELECTED => get_string('delete'),
                        att_sessions_page_params::ACTION_CHANGE_DURATION => get_string('changeduration', 'attendance'));



            $controls = html_writer::select($options, 'action');
            $attributes = array(
                    'type'  => 'submit',
                    'name'  => 'ok',
                    'value' => get_string('ok'));
            $controls .= html_writer::empty_tag('input', $attributes);
        } else {
            $controls = get_string('youcantdo', 'attendance'); // You can't do anything.
        }
        $table->data[0][] = $controls;

        return html_writer::table($table);
    }

    protected function render_attendance_take_data(attendance_take_data $takedata) {
        $controls = $this->render_attendance_take_controls($takedata);

        if ($takedata->pageparams->viewmode == att_take_page_params::SORTED_LIST) {
            $table = $this->render_attendance_take_list($takedata);
        } else {
            $table = $this->render_attendance_take_grid($takedata);
        }
        $table .= html_writer::input_hidden_params($takedata->url(array('sesskey' => sesskey(), 'page' => $takedata->pageparams->page)));
        $params = array(
                'type'  => 'submit',
                'value' => get_string('save', 'attendance'));
        $table .= html_writer::tag('center', html_writer::empty_tag('input', $params));
        $table = html_writer::tag('form', $table, array('method' => 'post', 'action' => $takedata->url_path()));

        foreach($takedata->statuses as $status) {
            $sessionstats[$status->id] = 0;
        }
        // Calculate the sum of statuses for each user
        $sessionstats[] = array();
        foreach ($takedata->sessionlog as $userlog) {
            foreach($takedata->statuses as $status) {
                if ($userlog->statusid == $status->id) $sessionstats[$status->id]++;
            }
        }

        $statsoutput = '<br/>';
        foreach($takedata->statuses as $status) {
            $statsoutput .= "$status->description = ".$sessionstats[$status->id]." <br/>";
        }

        return $controls.$table.$statsoutput;
    }

    protected function render_attendance_take_controls(attendance_take_data $takedata) {
        $table = new html_table();
        $table->attributes['class'] = ' ';

        $table->data[0][] = $this->construct_take_session_info($takedata);
        $table->data[0][] = $this->construct_take_controls($takedata);

        return $this->output->container(html_writer::table($table), 'generalbox takecontrols');
    }

    private function construct_take_session_info(attendance_take_data $takedata) {
        $sess = $takedata->sessioninfo;
        $date = userdate($sess->sessdate, get_string('strftimedate'));
        $starttime = userdate($sess->sessdate, get_string('strftimehm', 'attendance'));
        $endtime = userdate($sess->sessdate + $sess->duration, get_string('strftimehm', 'attendance'));
        $time = html_writer::tag('nobr', $starttime . ($sess->duration > 0 ? ' - ' . $endtime : ''));
        $sessinfo = $date.' '.$time;
        $sessinfo .= html_writer::empty_tag('br');
        $sessinfo .= html_writer::empty_tag('br');
        $sessinfo .= $sess->description;

        return $sessinfo;
    }

    private function construct_take_controls(attendance_take_data $takedata) {
        GLOBAL $CFG;
        
        $controls = '';

        $group = 0;
        if ($takedata->pageparams->grouptype != attendance::SESSION_COMMON) {
            $group = $takedata->pageparams->grouptype;
        } else {
            if ($takedata->pageparams->group) {
                $group = $takedata->pageparams->group;
            }
        }

        if (!empty($CFG->enablegroupmembersonly) and $takedata->cm->groupmembersonly) {
            if ($group == 0) {
                $groups = array_keys(groups_get_all_groups($takedata->cm->course, 0, $takedata->cm->groupingid, 'g.id'));
            } else {
                $groups = $group;
            }
            $users = get_users_by_capability(context_module::instance($takedata->cm->id), 'mod/attendance:canbelisted',
                            'u.id, u.firstname, u.lastname, u.email',
                            '', '', '', $groups,
                            '', false, true);
            $totalusers = count($users);
        } else {
            $totalusers = count_enrolled_users(context_module::instance($takedata->cm->id), 'mod/attendance:canbelisted', $group);
        }
        $usersperpage = $takedata->pageparams->perpage;
        if (!empty($takedata->pageparams->page) && $takedata->pageparams->page && $totalusers && $usersperpage) {
            $controls .= html_writer::empty_tag('br');
            $numberofpages = ceil($totalusers / $usersperpage);

            if ($takedata->pageparams->page > 1) {
                $controls .= html_writer::link($takedata->url(array('page' => $takedata->pageparams->page - 1)), $this->output->larrow());
            }
            $controls .= html_writer::tag('span', "Page {$takedata->pageparams->page} of $numberofpages", array('class' => 'attbtn'));
            if ($takedata->pageparams->page < $numberofpages) {
                $controls .= html_writer::link($takedata->url(array('page' => $takedata->pageparams->page + 1,
                            'perpage' => $takedata->pageparams->perpage)), $this->output->rarrow());
            }
        }

        if ($takedata->pageparams->grouptype == attendance::SESSION_COMMON and
                ($takedata->groupmode == VISIBLEGROUPS or
                ($takedata->groupmode and $takedata->perm->can_access_all_groups()))) {
            $controls .= groups_print_activity_menu($takedata->cm, $takedata->url(), true);
        }

        $controls .= html_writer::empty_tag('br');

        $options = array(
                att_take_page_params::SORTED_LIST   => get_string('sortedlist', 'attendance'),
                att_take_page_params::SORTED_GRID   => get_string('sortedgrid', 'attendance'));
        $select = new single_select($takedata->url(), 'viewmode', $options, $takedata->pageparams->viewmode, null);
        $select->set_label(get_string('viewmode', 'attendance'));
        $select->class = 'singleselect inline';
        $controls .= $this->output->render($select);

        if ($takedata->pageparams->viewmode == att_take_page_params::SORTED_LIST) {
            $options = array(
                    0 => get_string('donotusepaging', 'attendance'),
                   get_config('attendance', 'resultsperpage') => get_config('attendance', 'resultsperpage'));
            $select = new single_select($takedata->url(), 'perpage', $options, $takedata->pageparams->perpage, null);
            $select->class = 'singleselect inline';
            $controls .= $this->output->render($select);
        }

        if ($takedata->pageparams->viewmode == att_take_page_params::SORTED_GRID) {
            $options = array (1 => '1 '.get_string('column', 'attendance'), '2 '.get_string('columns', 'attendance'),
                                   '3 '.get_string('columns', 'attendance'), '4 '.get_string('columns', 'attendance'),
                                   '5 '.get_string('columns', 'attendance'), '6 '.get_string('columns', 'attendance'),
                                   '7 '.get_string('columns', 'attendance'), '8 '.get_string('columns', 'attendance'),
                                   '9 '.get_string('columns', 'attendance'), '10 '.get_string('columns', 'attendance'));
            $select = new single_select($takedata->url(), 'gridcols', $options, $takedata->pageparams->gridcols, null);
            $select->class = 'singleselect inline';
            $controls .= $this->output->render($select);
        }

        if (count($takedata->sessions4copy) > 0) {
            $controls .= html_writer::empty_tag('br');
            $controls .= html_writer::empty_tag('br');

            $options = array();
            foreach ($takedata->sessions4copy as $sess) {
                $start = userdate($sess->sessdate, get_string('strftimehm', 'attendance'));
                $end = $sess->duration ? ' - '.userdate($sess->sessdate + $sess->duration,
                                                        get_string('strftimehm', 'attendance')) : '';
                $options[$sess->id] = $start . $end;
            }
            $select = new single_select($takedata->url(array(), array('copyfrom')), 'copyfrom', $options);
            $select->set_label(get_string('copyfrom', 'attendance'));
            $select->class = 'singleselect inline';
            $controls .= $this->output->render($select);
        }

        return $controls;
    }

    protected function render_attendance_take_list(attendance_take_data $takedata) {
        $table = new html_table();
        $table->width = '0%';
        $table->head = array(
                '#',
                $this->construct_fullname_head($takedata)
            );
        $table->align = array('left', 'left');
        $table->size = array('20px', '');
        $table->wrap[1] = 'nowrap';
        foreach ($takedata->statuses as $st) {
            $table->head[] = html_writer::link("javascript:select_all_in(null, 'st" . $st->id . "', null);", $st->acronym,
                                               array('title' => get_string('setallstatusesto', 'attendance', $st->description)));
            $table->align[] = 'center';
            $table->size[] = '20px';
        }
        $table->head[] = get_string('remarks', 'attendance');
        $table->align[] = 'center';
        $table->size[] = '20px';
        $table->attributes['class'] = 'generaltable takelist';

        $i = 0;
        foreach ($takedata->users as $user) {
            $i++;
            $row = new html_table_row();
            $row->cells[] = $i;
            $fullname = html_writer::link($takedata->url_view(array('studentid' => $user->id)), fullname($user));
            $fullname = $this->output->user_picture($user).$fullname;

            $ucdata = $this->construct_take_user_controls($takedata, $user);
            if (array_key_exists('warning', $ucdata)) {
                $fullname .= html_writer::empty_tag('br');
                $fullname .= $ucdata['warning'];
            }
            $row->cells[] = $fullname;

            if (array_key_exists('colspan', $ucdata)) {
                $cell = new html_table_cell($ucdata['text']);
                $cell->colspan = $ucdata['colspan'];
                $row->cells[] = $cell;
            } else {
                $row->cells = array_merge($row->cells, $ucdata['text']);
            }

            if (array_key_exists('class', $ucdata)) {
                $row->attributes['class'] = $ucdata['class'];
            }

            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    protected function render_attendance_take_grid(attendance_take_data $takedata) {
        $table = new html_table();
        for ($i=0; $i < $takedata->pageparams->gridcols; $i++) {
            $table->align[] = 'center';
            $table->size[] = '110px';
        }
        $table->attributes['class'] = 'generaltable takegrid';
        $table->headspan = $takedata->pageparams->gridcols;
        $head = array();
        foreach ($takedata->statuses as $st) {
            $head[] = html_writer::link("javascript:select_all_in(null, 'st" . $st->id . "', null);", $st->acronym,
                                        array('title' => get_string('setallstatusesto', 'attendance', $st->description)));
        }
        $table->head[] = implode('&nbsp;&nbsp;', $head);

        $i = 0;
        $row = new html_table_row();
        foreach ($takedata->users as $user) {
            $celltext = $this->output->user_picture($user, array('size' => 100));
            $celltext .= html_writer::empty_tag('br');
            $fullname = html_writer::link($takedata->url_view(array('studentid' => $user->id)), fullname($user));
            $celltext .= html_writer::tag('span', $fullname, array('class' => 'fullname'));
            $celltext .= html_writer::empty_tag('br');
            $ucdata = $this->construct_take_user_controls($takedata, $user);
            $celltext .= is_array($ucdata['text']) ? implode('', $ucdata['text']) : $ucdata['text'];
            if (array_key_exists('warning', $ucdata)) {
                $celltext .= html_writer::empty_tag('br');
                $celltext .= $ucdata['warning'];
            }

            $cell = new html_table_cell($celltext);
            if (array_key_exists('class', $ucdata)) {
                $cell->attributes['class'] = $ucdata['class'];
            }
            $row->cells[] = $cell;

            $i++;
            if ($i % $takedata->pageparams->gridcols == 0) {
                $table->data[] = $row;
                $row = new html_table_row();
            }
        }
        if ($i % $takedata->pageparams->gridcols > 0) {
            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    private function construct_fullname_head($data) {
        global $CFG;

        if ($data->pageparams->sort == ATT_SORT_LASTNAME) {
            $firstname = html_writer::link($data->url(array('sort' => ATT_SORT_FIRSTNAME)), get_string('firstname'));
        } else {
            $firstname = get_string('firstname');
        }

        if ($data->pageparams->sort == ATT_SORT_FIRSTNAME) {
            $lastname = html_writer::link($data->url(array('sort' => ATT_SORT_LASTNAME)), get_string('lastname'));
        } else {
            $lastname = get_string('lastname');
        }

        if ($CFG->fullnamedisplay == 'lastname firstname') {
            $fullnamehead = "$lastname / $firstname";
        } else {
            $fullnamehead = "$firstname / $lastname";
        }

        return $fullnamehead;
    }

    private function construct_take_user_controls(attendance_take_data $takedata, $user) {
        $celldata = array();
        if ($user->enrolmentend and $user->enrolmentend < $takedata->sessioninfo->sessdate) {
            $celldata['text'] = get_string('enrolmentend', 'attendance', userdate($user->enrolmentend, '%d.%m.%Y'));
            $celldata['colspan'] = count($takedata->statuses) + 1;
            $celldata['class'] = 'userwithoutenrol';
        } else if (!$user->enrolmentend and $user->enrolmentstatus == ENROL_USER_SUSPENDED) {
            // No enrolmentend and ENROL_USER_SUSPENDED.
            $celldata['text'] = get_string('enrolmentsuspended', 'attendance');
            $celldata['colspan'] = count($takedata->statuses) + 1;
            $celldata['class'] = 'userwithoutenrol';
        } else {
            if ($takedata->updatemode and !array_key_exists($user->id, $takedata->sessionlog)) {
                $celldata['class'] = 'userwithoutdata';
            }

            $celldata['text'] = array();
            foreach ($takedata->statuses as $st) {
                $params = array(
                        'type'  => 'radio',
                        'name'  => 'user'.$user->id,
                        'class' => 'st'.$st->id,
                        'value' => $st->id);
                if (array_key_exists($user->id, $takedata->sessionlog) and $st->id == $takedata->sessionlog[$user->id]->statusid) {
                    $params['checked'] = '';
                }

                $input = html_writer::empty_tag('input', $params);

                if ($takedata->pageparams->viewmode == att_take_page_params::SORTED_GRID) {
                    $input = html_writer::tag('nobr', $input . $st->acronym);
                }

                $celldata['text'][] = $input;
            }
            $params = array(
                    'type'  => 'text',
                    'name'  => 'remarks'.$user->id,
                    'maxlength' => 255);
            if (array_key_exists($user->id, $takedata->sessionlog)) {
                $params['value'] = $takedata->sessionlog[$user->id]->remarks;
            }
            $celldata['text'][] = html_writer::empty_tag('input', $params);

            if ($user->enrolmentstart > $takedata->sessioninfo->sessdate + $takedata->sessioninfo->duration) {
                $celldata['warning'] = get_string('enrolmentstart', 'attendance',
                                                  userdate($user->enrolmentstart, '%H:%M %d.%m.%Y'));
                $celldata['class'] = 'userwithoutenrol';
            }
        }

        return $celldata;
    }

    protected function render_attendance_user_data(attendance_user_data $userdata) {
        $o = $this->render_user_report_tabs($userdata);

        $table = new html_table();

        $table->attributes['class'] = 'userinfobox';
        $table->colclasses = array('left side', '');
        $table->data[0][] = $this->output->user_picture($userdata->user, array('size' => 100));
        $table->data[0][] = $this->construct_user_data($userdata);

        $o .= html_writer::table($table);

        return $o;
    }

    protected function render_user_report_tabs(attendance_user_data $userdata) {
        $tabs = array();

        $tabs[] = new tabobject(att_view_page_params::MODE_THIS_COURSE,
                        $userdata->url()->out(true, array('mode' => att_view_page_params::MODE_THIS_COURSE)),
                        get_string('thiscourse', 'attendance'));

        $tabs[] = new tabobject(att_view_page_params::MODE_ALL_COURSES,
                        $userdata->url()->out(true, array('mode' => att_view_page_params::MODE_ALL_COURSES)),
                        get_string('allcourses', 'attendance'));

        return print_tabs(array($tabs), $userdata->pageparams->mode, null, null, true);
    }

    private function construct_user_data(attendance_user_data $userdata) {
        $o = html_writer::tag('h2', fullname($userdata->user));

        if ($userdata->pageparams->mode == att_view_page_params::MODE_THIS_COURSE) {
            $o .= html_writer::empty_tag('hr');

            $o .= construct_user_data_stat($userdata->stat, $userdata->statuses,
                        $userdata->gradable, $userdata->grade, $userdata->maxgrade, $userdata->decimalpoints);

            $o .= $this->render_attendance_filter_controls($userdata->filtercontrols);

            $o .= $this->construct_user_sessions_log($userdata);
        } else {
            $prevcid = 0;
            foreach ($userdata->coursesatts as $ca) {
                if ($prevcid != $ca->courseid) {
                    $o .= html_writer::empty_tag('hr');
                    $prevcid = $ca->courseid;

                    $o .= html_writer::tag('h3', $ca->coursefullname);
                }
                $o .= html_writer::tag('h4', $ca->attname);

                $o .= construct_user_data_stat($userdata->stat[$ca->attid], $userdata->statuses[$ca->attid],
                            $userdata->gradable[$ca->attid], $userdata->grade[$ca->attid],
                            $userdata->maxgrade[$ca->attid], $userdata->decimalpoints);
            }
        }

        return $o;
    }

    private function construct_user_sessions_log(attendance_user_data $userdata) {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable attwidth boxaligncenter';
        $table->head = array(
            '#',
            get_string('sessiontypeshort', 'attendance'),
            get_string('date'),
            get_string('time'),
            get_string('description', 'attendance'),
            get_string('status', 'attendance'),
            get_string('remarks', 'attendance')
        );
        $table->align = array('', '', '', 'left', 'left', 'center', 'left', 'center');
        $table->size = array('1px', '1px', '1px', '1px', '*', '1px', '1px', '*');

        $i = 0;
        foreach ($userdata->sessionslog as $sess) {
            $i++;

            $row = new html_table_row();
            $row->cells[] = $i;
            $row->cells[] = html_writer::tag('nobr', $sess->groupid ? $userdata->groups[$sess->groupid]->name :
                                                                      get_string('commonsession', 'attendance'));
            $row->cells[] = userdate($sess->sessdate, get_string('strftimedmyw', 'attendance'));
            $row->cells[] = $this->construct_time($sess->sessdate, $sess->duration);
            $row->cells[] = $sess->description;
            if (isset($sess->statusid)) {
                $row->cells[] = $userdata->statuses[$sess->statusid]->description;
                $row->cells[] = $sess->remarks;
            } else if ($sess->sessdate < $userdata->user->enrolmentstart) {
                $cell = new html_table_cell(get_string('enrolmentstart', 'attendance',
                                            userdate($userdata->user->enrolmentstart, '%d.%m.%Y')));
                $cell->colspan = 2;
                $row->cells[] = $cell;
            } else if ($userdata->user->enrolmentend and $sess->sessdate > $userdata->user->enrolmentend) {
                $cell = new html_table_cell(get_string('enrolmentend', 'attendance',
                                            userdate($userdata->user->enrolmentend, '%d.%m.%Y')));
                $cell->colspan = 2;
                $row->cells[] = $cell;
            } else {
                if (!empty($sess->studentscanmark)) { // Student can mark their own attendance.
                    // URL to the page that lets the student modify their attendance.
                    $url = new moodle_url('/mod/attendance/attendance.php',
                            array('sessid' => $sess->id, 'sesskey' => sesskey()));
                    $cell = new html_table_cell(html_writer::link($url, get_string('submitattendance', 'attendance')));
                    $cell->colspan = 2;
                    $row->cells[] = $cell;
                } else { // Student cannot mark their own attendace.
                    $row->cells[] = '?';
                    $row->cells[] = '';
                }
            }

            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    private function construct_time($datetime, $duration) {
        $time = html_writer::tag('nobr', construct_session_time($datetime, $duration));

        return $time;
    }

    protected function render_attendance_report_data(attendance_report_data $reportdata) {
        global $PAGE;

        // Initilise Javascript used to (un)check all checkboxes.
        $this->page->requires->js_init_call('M.mod_attendance.init_manage');

        // Check if the user should be able to bulk send messages to other users on the course.
        $bulkmessagecapability = has_capability('moodle/course:bulkmessaging', $PAGE->context);

        $table = new html_table();

        $table->attributes['class'] = 'generaltable attwidth';

        // User picture.
        $table->head[] = '';
        $table->align[] = 'left';
        $table->size[] = '1px';

        $table->head[] = $this->construct_fullname_head($reportdata);
        $table->align[] = 'left';
        $table->size[] = '';
        $sessionstats = array();

        foreach ($reportdata->sessions as $sess) {
            $sesstext = userdate($sess->sessdate, get_string('strftimedm', 'attendance'));
            $sesstext .= html_writer::empty_tag('br');
            $sesstext .= userdate($sess->sessdate, '('.get_string('strftimehm', 'attendance').')');
            if (is_null($sess->lasttaken) and $reportdata->perm->can_take() or $reportdata->perm->can_change()) {
                $sesstext = html_writer::link($reportdata->url_take($sess->id, $sess->groupid), $sesstext);
            }
            $sesstext .= html_writer::empty_tag('br');
            $sesstext .= $sess->groupid ? $reportdata->groups[$sess->groupid]->name : get_string('commonsession', 'attendance');

            $table->head[] = $sesstext;
            $table->align[] = 'center';
            $table->size[] = '1px';
        }

        foreach ($reportdata->statuses as $status) {
            $table->head[] = $status->acronym;
            $table->align[] = 'center';
            $table->size[] = '1px';
            $sessionstats[$status->id] = 0;
        }

        if ($reportdata->gradable) {
            $table->head[] = get_string('grade');
            $table->align[] = 'center';
            $table->size[] = '1px';
        }

        if ($reportdata->sessionslog) {
            $table->head[] = get_string('remarks', 'attendance');
            $table->align[] = 'center';
            $table->size[] = '200px';
         }

        if ($bulkmessagecapability) { // Display the table header for bulk messaging.
            // The checkbox must have an id of cb_selector so that the JavaScript will pick it up.
            $table->head[] = html_writer::checkbox('cb_selector', 0, false, '', array('id' => 'cb_selector'));
            $table->align[] = 'center';
            $table->size[] = '1px';
        }

        foreach ($reportdata->users as $user) {
            $row = new html_table_row();

            $row->cells[] = $this->output->user_picture($user);
            $row->cells[] = html_writer::link($reportdata->url_view(array('studentid' => $user->id)), fullname($user));
            $cellsgenerator = new user_sessions_cells_html_generator($reportdata, $user);
            $row->cells = array_merge($row->cells, $cellsgenerator->get_cells());

            foreach ($reportdata->statuses as $status) {
                if (array_key_exists($status->id, $reportdata->usersstats[$user->id])) {
                    $row->cells[] = $reportdata->usersstats[$user->id][$status->id]->stcnt;
                } else {
                    // No attendance data for this $status => no statistic for this status.
                    $row->cells[] = 0;
                }
            }

            if ($reportdata->gradable) {
                $row->cells[] = $reportdata->grades[$user->id].' / '.$reportdata->maxgrades[$user->id];
            }

            if ($reportdata->sessionslog) {
                if (isset($sess) && isset($reportdata->sessionslog[$user->id][$sess->id]->remarks)) {
                    $row->cells[] = $reportdata->sessionslog[$user->id][$sess->id]->remarks;
                } else {
                    $row->cells[] = '';
                }
            }

            if ($bulkmessagecapability) { // Create the checkbox for bulk messaging.
                $row->cells[] = html_writer::checkbox('user'.$user->id, 'on', false);
            }
            
            $table->data[] = $row;
        }

        // Calculate the sum of statuses for each user
        $statrow = new html_table_row();
        $statrow->cells[] = '';
        $statrow->cells[] = get_string('summary');
        foreach ($reportdata->sessions as $sess) {
            foreach ($reportdata->users as $user) {
                foreach($reportdata->statuses as $status) {
                    if (!empty($reportdata->sessionslog[$user->id][$sess->id])) {
                        if ($reportdata->sessionslog[$user->id][$sess->id]->statusid == $status->id) $sessionstats[$status->id]++;
                    }
                }
            }

            $statsoutput = '<br/>';
            foreach($reportdata->statuses as $status) {
                $statsoutput .= "$status->description:".$sessionstats[$status->id]." <br/>";
            }
            $statrow->cells[] = $statsoutput;

        }
        $table->data[] = $statrow;

        if ($bulkmessagecapability) { // Require that the user can bulk message users.
            // Display check boxes that will allow the user to send a message to the students that have been checked.
            $output = html_writer::empty_tag('input', array('name' => 'sesskey', 'type' => 'hidden', 'value' => sesskey()));
            $output .= html_writer::empty_tag('input', array('name' => 'formaction', 'type' => 'hidden', 'value' => 'messageselect.php'));
            $output .= html_writer::empty_tag('input', array('name' => 'id', 'type' => 'hidden', 'value' => $GLOBALS['COURSE']->id));
            $output .= html_writer::empty_tag('input', array('name' => 'returnto', 'type' => 'hidden', 'value' => s(me())));
            $output .= html_writer::table($table).html_writer::tag('div', get_string('users').': '.count($reportdata->users));;
            $output .= html_writer::tag('div',
                    html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('messageselectadd'))),
                    array('class' => 'buttons'));
            $url = new moodle_url('/user/action_redir.php');
            return html_writer::tag('form', $output, array('action' => $url->out(), 'method' => 'post'));
        } else {
            return html_writer::table($table).html_writer::tag('div', get_string('users').': '.count($reportdata->users));
        }
            
    }

    protected function render_attendance_preferences_data(attendance_preferences_data $prefdata) {
        $this->page->requires->js('/mod/attendance/module.js');

        $table = new html_table();
        $table->width = '100%';
        $table->head = array('#',
                             get_string('acronym', 'attendance'),
                             get_string('description'),
                             get_string('grade'),
                             get_string('action'));
        $table->align = array('center', 'center', 'center', 'center', 'center', 'center');

        $i = 1;
        foreach ($prefdata->statuses as $st) {
            $table->data[$i][] = $i;
            $table->data[$i][] = $this->construct_text_input('acronym['.$st->id.']', 2, 2, $st->acronym);
            $table->data[$i][] = $this->construct_text_input('description['.$st->id.']', 30, 30, $st->description);
            $table->data[$i][] = $this->construct_text_input('grade['.$st->id.']', 4, 4, $st->grade);
            $table->data[$i][] = $this->construct_preferences_actions_icons($st, $prefdata);

            $i++;
        }

        $table->data[$i][] = '*';
        $table->data[$i][] = $this->construct_text_input('newacronym', 2, 2);
        $table->data[$i][] = $this->construct_text_input('newdescription', 30, 30);
        $table->data[$i][] = $this->construct_text_input('newgrade', 4, 4);
        $table->data[$i][] = $this->construct_preferences_button(get_string('add', 'attendance'),
                                                                 att_preferences_page_params::ACTION_ADD);

        $o = html_writer::tag('h1', get_string('myvariables', 'attendance'));
        $o .= html_writer::table($table);
        $o .= html_writer::input_hidden_params($prefdata->url(array(), false));
        $o .= $this->construct_preferences_button(get_string('update', 'attendance'), att_preferences_page_params::ACTION_SAVE);
        $o = html_writer::tag('form', $o, array('id' => 'preferencesform', 'method' => 'post',
                                                'action' => $prefdata->url(array(), false)->out_omit_querystring()));
        $o = $this->output->container($o, 'generalbox attwidth');

        return $o;
    }

    private function construct_text_input($name, $size, $maxlength, $value='') {
        $attributes = array(
                'type'      => 'text',
                'name'      => $name,
                'size'      => $size,
                'maxlength' => $maxlength,
                'value'     => $value);
        return html_writer::empty_tag('input', $attributes);
    }

    private function construct_preferences_actions_icons($st, $prefdata) {
        global $OUTPUT;

        if ($st->visible) {
            $params = array(
                    'action' => att_preferences_page_params::ACTION_HIDE,
                    'statusid' => $st->id);
            $showhideicon = $OUTPUT->action_icon(
                    $prefdata->url($params),
                    new pix_icon("t/hide", get_string('hide')));
        } else {
            $params = array(
                    'action' => att_preferences_page_params::ACTION_SHOW,
                    'statusid' => $st->id);
            $showhideicon = $OUTPUT->action_icon(
                    $prefdata->url($params),
                    new pix_icon("t/show", get_string('show')));
        }
        if (!$st->haslogs) {
            $params = array(
                    'action' => att_preferences_page_params::ACTION_DELETE,
                    'statusid' => $st->id);
            $deleteicon = $OUTPUT->action_icon(
                    $prefdata->url($params),
                    new pix_icon("t/delete", get_string('delete')));
        } else {
            $deleteicon = '';
        }

        return $showhideicon . $deleteicon;
    }

    private function construct_preferences_button($text, $action) {
        $attributes = array(
                'type'      => 'submit',
                'value'     => $text,
                'onclick'   => 'M.mod_attendance.set_preferences_action('.$action.')');
        return html_writer::empty_tag('input', $attributes);
    }

}
