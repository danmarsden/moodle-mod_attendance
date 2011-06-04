<?php

/**
 * Attendance module renderering methods are defined here
 *
 * @package    mod
 * @subpackage attforblock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/renderables.php');

/**
 * Attendance module renderer class
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_attforblock_renderer extends plugin_renderer_base {

    ////////////////////////////////////////////////////////////////////////////
    // External API - methods to render attendance renderable components
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Renders tabs for attendance
     *
     * @param atttabs - tabs to display
     * @return string html code
     */
    protected function render_attforblock_tabs(attforblock_tabs $atttabs) {
        return print_tabs($atttabs->get_tabs(), $atttabs->currenttab, NULL, NULL, true);
    }

    /**
     * Renders filter controls for attendance
     *
     * @param fcontrols - filter controls data to display
     * @return string html code
     */
    protected function render_attforblock_filter_controls(attforblock_filter_controls $fcontrols) {
        $filtertable = new html_table();
        $filtertable->attributes['class'] = ' ';
        $filtertable->width = '100%';
        $filtertable->align = array('left', 'center', 'right');

        $filtertable->data[0][] = $this->render_sess_group_selector($fcontrols);

        $filtertable->data[0][] = $this->render_curdate_controls($fcontrols);

        $filtertable->data[0][] = $this->render_view_controls($fcontrols);

        $o = html_writer::table($filtertable);
        $o = $this->output->container($o, 'attfiltercontrols attwidth');

        return $o;
    }

    protected function render_sess_group_selector(attforblock_filter_controls $fcontrols) {
        if ($fcontrols->get_group_mode() == NOGROUPS)
            return '';
        
        $select = new single_select($fcontrols->url(), 'group', $fcontrols->get_sess_groups_list(),
                                    $fcontrols->get_current_group(), null, 'selectgroup');
        $select->label = get_string('sessions', 'attforblock');
        $output = $this->output->render($select);

        return html_writer::tag('div', $output, array('class' => 'groupselector'));
    }
    
    protected function render_curdate_controls(attforblock_filter_controls $fcontrols) {
        global $CFG;

        $curdate_controls = '';
        if ($fcontrols->curdatetxt) {
            $this->page->requires->strings_for_js(array('calclose', 'caltoday'), 'attforblock');
            $jsvals = array(
                    'cal_months'    => explode(',', get_string('calmonths','attforblock')),
                    'cal_week_days' => explode(',', get_string('calweekdays','attforblock')),
                    'cal_start_weekday' => $CFG->calendar_startwday,
                    'cal_cur_date'  => $fcontrols->curdate);
            $curdate_controls = html_writer::script(js_writer::set_variable('M.attforblock', $jsvals));

            $this->page->requires->yui2_lib('container');
            $this->page->requires->yui2_lib('calendar');
            $this->page->requires->js('/mod/attforblock/calendar.js');

            $curdate_controls .= html_writer::link($fcontrols->url(array('curdate' => $fcontrols->prevcur)), $this->output->larrow());
            $params = array(
                    'title' => get_string('calshow', 'attforblock'),
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

            $curdate_controls .= html_writer::link($fcontrols->url(array('curdate' => $fcontrols->nextcur)), $this->output->rarrow());
        }

        return $curdate_controls;
    }

    protected function render_view_controls(attforblock_filter_controls $fcontrols) {
        $views[att_manage_page_params::VIEW_ALL] = get_string('all', 'attforblock');
        $views[att_manage_page_params::VIEW_ALLTAKEN] = get_string('alltaken', 'attforblock');
        $views[att_manage_page_params::VIEW_MONTHS] = get_string('months', 'attforblock');
        $views[att_manage_page_params::VIEW_WEEKS] = get_string('weeks', 'attforblock');
        $views[att_manage_page_params::VIEW_DAYS] = get_string('days', 'attforblock');
        $viewcontrols = '';
        foreach ($views as $key => $sview) {
            if ($key != $fcontrols->view) {
                $link = html_writer::link($fcontrols->url(array('view' => $key)), $sview);
                $viewcontrols .= html_writer::tag('span', $link, array('class' => 'attbtn'));
            }
            else
                $viewcontrols .= html_writer::tag('span', $sview, array('class' => 'attcurbtn'));
        }

        return html_writer::tag('nobr', $viewcontrols);
    }
    
    /**
     * Renders attendance sessions managing table
     *
     * @param attforblock_sessions_manage_data $sessdata to display
     * @return string html code
     */
    protected function render_attforblock_sessions_manage_data(attforblock_sessions_manage_data $sessdata) {
        // TODO: nosessionexists
        // TODO: log
        $o = $this->render_sess_manage_table($sessdata) . $this->render_sess_control_table($sessdata);
        $o = html_writer::tag('form', $o, array('method' => 'post', 'action' => $sessdata->url_sessions()->out()));
        $o = $this->output->container($o, 'generalbox attwidth');
        $o = $this->output->container($o, 'attsessions_manage_table');

        return $o;
    }

    protected function render_sess_manage_table(attforblock_sessions_manage_data $sessdata) {
        $table = new html_table();
        $table->width = '100%';
        $table->head = array(
                '#',
                get_string('sessiontypeshort', 'attforblock'),
                get_string('date'),
                get_string('time'),
                get_string('description','attforblock'),
                get_string('actions'),
                get_string('select')
            );
        $table->align = array('', '', '', '', 'center', 'center', 'center');
        $table->size = array('1px', '', '1px', '1px', '*', '1px', '1px');

        $i = 0;
        foreach ($sessdata->sessions as $key => $sess) {
            $i++;

            $dta = $this->construct_date_time_actions($sessdata, $sess);

            $table->data[$sess->id][] = $i;
            $table->data[$sess->id][] = $sess->groupid ? $sessdata->groups[$sess->groupid]->name : get_string('commonsession', 'attforblock');
            $table->data[$sess->id][] = $dta['date'];
            $table->data[$sess->id][] = $dta['time'];
            $table->data[$sess->id][] = empty($sess->description) ? get_string('nodescription', 'attforblock') : $sess->description;
            $table->data[$sess->id][] = $dta['actions'];
            $table->data[$sess->id][] = html_writer::checkbox('sessid', $sess->id, false);
        }

        return html_writer::table($table);
    }

    private function construct_date_time_actions(attforblock_sessions_manage_data $sessdata, $sess) {
        $actions = '';

        $date = userdate($sess->sessdate, get_string('strftimedmyw', 'attforblock'));
        $starttime = userdate($sess->sessdate, get_string('strftimehm', 'attforblock'));
        $endtime = userdate($sess->sessdate + $sess->duration, get_string('strftimehm', 'attforblock'));
        $time = html_writer::tag('nobr', $starttime . ($sess->duration > 0 ? ' - ' . $endtime : ''));
        if($sess->lasttaken > 0)
        {
            if ($sessdata->perm->can_change()) {
                $url = $sessdata->url_take($sess->id, $sess->groupid);
                $title = get_string('changeattendance','attforblock');

                $date = html_writer::link($url, $date, array('title' => $title));
                $time = html_writer::link($url, $time, array('title' => $title));
            } else {
                $date = '<i>' . $date . '</i>';
                $time = '<i>' . $time . '</i>';
            }
        } else {
            if ($sessdata->perm->can_take()) {
                $url = $sessdata->url_take($sess->id, $sess->groupid);
                $title = get_string('takeattendance','attforblock');
                $actions = $this->output->action_icon($url, new pix_icon('t/go', $title));
            }
        }
        if($sessdata->perm->can_manage()) {
            $url = $sessdata->url_sessions($sess->id, att_sessions_page_params::ACTION_UPDATE);
            $title = get_string('editsession','attforblock');
            $actions .= $this->output->action_icon($url, new pix_icon('t/edit', $title));

            $url = $sessdata->url_sessions($sess->id, att_sessions_page_params::ACTION_DELETE);
            $title = get_string('deletesession','attforblock');
            $actions .= $this->output->action_icon($url, new pix_icon('t/delete', $title));
        }

        return array('date' => $date, 'time' => $time, 'actions' => $actions);
    }

    protected function render_sess_control_table(attforblock_sessions_manage_data $sessdata) {
        $table = new html_table();
        $table->attributes['class'] = ' ';
        $table->width = '100%';
        $table->align = array('left', 'right');

        $table->data[0][] = $this->output->help_icon('hiddensessions', 'attforblock',
                get_string('hiddensessions', 'attforblock').': '.$sessdata->hiddensessionscount);

        $controls = html_writer::link('javascript:checkall();', get_string('selectall')).' / '.
                html_writer::link('javascript:checknone();', get_string('deselectall')).
                html_writer::empty_tag('br');
        if ($sessdata->perm->can_manage()) {
            $options = array('deleteselected' => get_string('delete'),
                    'changeduration' => get_string('changeduration', 'attforblock'));
            $controls .= html_writer::select($options, 'action');
            $attributes = array(
                    'type'  => 'submit',
                    'name'  => 'ok',
                    'value' => get_string('ok'));
            $controls .= html_writer::empty_tag('input', $attributes);
        } else {
            $controls .= get_string('youcantdo', 'attforblock'); //You can't do anything
        }
        $table->data[0][] = $controls;

        return html_writer::table($table);
    }

    protected function render_attforblock_take_data(attforblock_take_data $takedata) {
        $controls = $this->render_attforblock_take_controls($takedata);

        if ($takedata->pageparams->viewmode == att_take_page_params::SORTED_LIST)
            $table = $this->render_attforblock_take_list($takedata);
        else
            $table = $this->render_attforblock_take_grid($takedata);
        $table .= html_writer::input_hidden_params($takedata->url());
        $params = array(
                'type'  => 'submit',
                'value' => get_string('save','attforblock'));
        $table .= html_writer::tag('center', html_writer::empty_tag('input', $params));
        $table = html_writer::tag('form', $table, array('method' => 'post', 'action' => $takedata->url_path()));
        
        return $controls.$table;
    }
    
    protected function render_attforblock_take_controls(attforblock_take_data $takedata) {
        $table = new html_table();
        $table->attributes['class'] = ' ';

        $table->data[0][] = $this->construct_take_session_info($takedata);
        $table->data[0][] = $this->construct_take_controls($takedata);

        return $this->output->container(html_writer::table($table), 'generalbox takecontrols');
    }

    private function construct_take_session_info(attforblock_take_data $takedata) {
        $sess = $takedata->sessioninfo;
        $date = userdate($sess->sessdate, get_string('strftimedate'));
        $starttime = userdate($sess->sessdate, get_string('strftimehm', 'attforblock'));
        $endtime = userdate($sess->sessdate + $sess->duration, get_string('strftimehm', 'attforblock'));
        $time = html_writer::tag('nobr', $starttime . ($sess->duration > 0 ? ' - ' . $endtime : ''));
        $sessinfo = $date.' '.$time;
        $sessinfo .= html_writer::empty_tag('br');
        $sessinfo .= html_writer::empty_tag('br');
        $sessinfo .= empty($sess->description) ? get_string('nodescription', 'attforblock') : $sess->description;

        return $sessinfo;
    }

    private function construct_take_controls(attforblock_take_data $takedata) {
        $controls = '';
        if ($takedata->pageparams->grouptype == attforblock::SESSION_COMMON and
                ($takedata->groupmode == VISIBLEGROUPS or
                ($takedata->groupmode and $takedata->perm->can_access_all_groups()))) {
            $controls .= groups_print_activity_menu($takedata->cm, $takedata->url(), true);
        }

        $controls .= html_writer::empty_tag('br');

        $options = array(
                att_take_page_params::SORTED_LIST   => get_string('sortedlist','attforblock'),
                att_take_page_params::SORTED_GRID   => get_string('sortedgrid','attforblock'));
        $select = new single_select($takedata->url(), 'viewmode', $options, $takedata->pageparams->viewmode, NULL);
        $select->set_label(get_string('viewmode','attforblock'));
        $select->class = 'singleselect inline';
        $controls .= $this->output->render($select);

        if ($takedata->pageparams->viewmode == att_take_page_params::SORTED_GRID) {
            $options = array (1 => '1 '.get_string('column','attforblock'),'2 '.get_string('columns','attforblock'),'3 '.get_string('columns','attforblock'),
                                   '4 '.get_string('columns','attforblock'),'5 '.get_string('columns','attforblock'),'6 '.get_string('columns','attforblock'),
                                   '7 '.get_string('columns','attforblock'),'8 '.get_string('columns','attforblock'),'9 '.get_string('columns','attforblock'),
                                   '10 '.get_string('columns','attforblock'));
            $select = new single_select($takedata->url(), 'gridcols', $options, $takedata->pageparams->gridcols, NULL);
            $select->class = 'singleselect inline';
            $controls .= $this->output->render($select);
        }

        if (count($takedata->sessions4copy) > 1) {
            $controls .= html_writer::empty_tag('br');
            $controls .= html_writer::empty_tag('br');

            $options = array();
            foreach ($takedata->sessions4copy as $sess) {
                $start = userdate($sess->sessdate, get_string('strftimehm', 'attforblock'));
                $end = $sess->duration ? ' - '.userdate($sess->sessdate + $sess->duration, get_string('strftimehm', 'attforblock')) : '';
                $options[$sess->id] = $start . $end;
            }
            $select = new single_select($takedata->url(array(), array('copyfrom')), 'copyfrom', $options);
            $select->set_label(get_string('copyfrom','attforblock'));
            $select->class = 'singleselect inline';
            $controls .= $this->output->render($select);
        }
        
        return $controls;
    }

    protected function render_attforblock_take_list(attforblock_take_data $takedata) {
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
            $table->head[] = html_writer::link("javascript:select_all_in(null, 'st" . $st->id . "', null);", $st->acronym, array('title' => get_string('setallstatusesto', 'attforblock', $st->description)));
            $table->align[] = 'center';
            $table->size[] = '20px';
        }
        $table->head[] = get_string('remarks', 'attforblock');
        $table->align[] = 'center';
        $table->size[] = '20px';
        $table->attributes['class'] = 'generaltable takelist';

        $i = 0;
        foreach ($takedata->users as $user) {
            $i++;
            $row = new html_table_row();
            $row->cells[] = $i;
            $fullname = html_writer::link($takedata->url_view(array('student' => $user->id)), fullname($user));
            $row->cells[] = $this->output->render(new user_picture($user)).$fullname;

            $celldata = $this->construct_take_user_controls($takedata, $user);
            if (array_key_exists('colspan', $celldata)) {
                $cell = new html_table_cell($celldata['text']);
                $cell->colspan = $celldata['colspan'];
                $row->cells[] = $cell;
            }
            else
                $row->cells = array_merge($row->cells, $celldata['text']);

            if (array_key_exists('class', $celldata)) $row->attributes['class'] = $celldata['class'];

            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    protected function render_attforblock_take_grid(attforblock_take_data $takedata) {
        $table = new html_table();
        for ($i=0; $i < $takedata->pageparams->gridcols; $i++) {
            $table->align[] = 'center';
            $table->size[] = '110px';
        }
        $table->attributes['class'] = 'generaltable takegrid';
        $table->headspan = $takedata->pageparams->gridcols;
        $head = array();
        foreach ($takedata->statuses as $st) {
            $head[] = html_writer::link("javascript:select_all_in(null, 'st" . $st->id . "', null);", $st->acronym, array('title' => get_string('setallstatusesto', 'attforblock', $st->description)));
        }
        $table->head[] = implode('&nbsp;&nbsp;', $head);

        $i = 0;
        $row = new html_table_row();
        foreach($takedata->users as $user) {
            $userpict = new user_picture($user);
            $userpict->size = 100;
            $celltext = $this->output->render($userpict);
            $celltext .= html_writer::empty_tag('br');
            $fullname = html_writer::link($takedata->url_view(array('student' => $user->id)), fullname($user));
            $celltext .= html_writer::tag('span', $fullname, array('class' => 'fullname'));
            $celltext .= html_writer::empty_tag('br');
            $celldata = $this->construct_take_user_controls($takedata, $user);
            $celltext .= is_array($celldata['text']) ? implode('', $celldata['text']) : $celldata['text'];

            $cell = new html_table_cell($celltext);
            if (array_key_exists('class', $celldata)) $cell->attributes['class'] = $celldata['class'];
            $row->cells[] = $cell;

            $i++;
            if ($i % $takedata->pageparams->gridcols == 0) {
                $table->data[] = $row;
                $row = new html_table_row();
            }
        }
        if ($i % $takedata->pageparams->gridcols > 0) $table->data[] = $row;
        
        return html_writer::table($table);
    }

    private function construct_fullname_head(attforblock_take_data $takedata) {
        global $CFG;

        if ($takedata->pageparams->sort == att_take_page_params::SORT_LASTNAME)
            $firstname = html_writer::link($takedata->url(array('sort' => att_take_page_params::SORT_FIRSTNAME)), get_string('firstname'));
        else
            $firstname = get_string('firstname');

        if ($takedata->pageparams->sort == att_take_page_params::SORT_FIRSTNAME)
            $lastname = html_writer::link($takedata->url(array('sort' => att_take_page_params::SORT_LASTNAME)), get_string('lastname'));
        else
            $lastname = get_string('lastname');

        if ($CFG->fullnamedisplay == 'lastname firstname') {
            $fullnamehead = "$lastname / $firstname";
        } else {
            $fullnamehead = "$firstname / $lastname";
        }

        return $fullnamehead;
    }

    private function construct_take_user_controls(attforblock_take_data $takedata, $user) {
        $celldata = array();
        if ($user->enrolmentstart > $takedata->sessioninfo->sessdate) {
            $celldata['text'] = get_string('enrolmentstart', 'attforblock', userdate($user->enrolmentstart, '%d.%m.%Y'));
            $celldata['colspan'] = count($takedata->statuses) + 1;
            $celldata['class'] = 'userwithoutenrol';
        }
        elseif ($user->enrolmentstatus == ENROL_USER_SUSPENDED) {
            $celldata['text'] = get_string('enrolmentsuspended', 'attforblock');
            $celldata['colspan'] = count($takedata->statuses) + 1;
            $celldata['class'] = 'userwithoutenrol';
        }
        else {
            if ($takedata->updatemode and !array_key_exists($user->id, $takedata->sessionlog))
                $celldata['class'] = 'userwithoutdata';

            $celldata['text'] = array();
            foreach ($takedata->statuses as $st) {
                $params = array(
                        'type'  => 'radio',
                        'name'  => 'user'.$user->id,
                        'class' => 'st'.$st->id,
                        'value' => $st->id);
                if (array_key_exists($user->id, $takedata->sessionlog) and $st->id == $takedata->sessionlog[$user->id]->statusid)
                    $params['checked'] = '';

                $input = html_writer::empty_tag('input', $params);
                if ($takedata->pageparams->viewmode == att_take_page_params::SORTED_LIST)
                    $celldata['text'][] = $input;
                else {
                    $celldata['text'][] = html_writer::tag('nobr', $input . $st->acronym);
                }
            }
            $params = array(
                    'type'  => 'text',
                    'name'  => 'remarks'.$user->id);
            if (array_key_exists($user->id, $takedata->sessionlog))
                $params['value'] = $takedata->sessionlog[$user->id]->remarks;
            $celldata['text'][] = html_writer::empty_tag('input', $params);
        }

        return $celldata;
    }

}
?>
