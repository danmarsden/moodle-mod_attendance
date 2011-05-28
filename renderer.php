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
            $actions = '';
            $desc = empty($sess->description) ? get_string('nodescription', 'attforblock') : $sess->description;

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

            $table->data[$sess->id][] = $i;
            $table->data[$sess->id][] = $sess->groupid ? $sessdata->groups[$sess->groupid]->name : get_string('commonsession', 'attforblock');
            $table->data[$sess->id][] = $date;
            $table->data[$sess->id][] = $time;
            $table->data[$sess->id][] = $desc;
            $table->data[$sess->id][] = $actions;
            $table->data[$sess->id][] = html_writer::checkbox('sessid', $sess->id, false);
        }

        return html_writer::table($table);
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
        $controls = '';
        if ($takedata->pageparams->grouptype == attforblock::SESSION_COMMON and
                ($takedata->groupmode == VISIBLEGROUPS or
                ($takedata->groupmode and $takedata->perm->can_access_all_groups()))) {
            $controls .= groups_print_activity_menu($takedata->cm, $takedata->url(), true);
        }

        $controls = html_writer::tag('div', $controls);

        $table = $this->render_attforblock_take_list($takedata);
        $table .= html_writer::input_hidden_params($takedata->url());
        $params = array(
                'type'  => 'submit',
                'value' => get_string('save','attforblock'));
        $table .= html_writer::tag('center', html_writer::empty_tag('input', $params));
        $table = html_writer::tag('form', $table, array('method' => 'post', 'action' => $takedata->url_path()));
        return $table;
    }
    
    protected function render_attforblock_take_list(attforblock_take_data $takedata) {
        $table = new html_table();
        $table->width = '0%';
        $table->head = array(
                '#',
                $this->get_fullname_head($takedata)
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
        $table->attributes['class'] = 'generaltable taketable';

        $i = 0;
        foreach ($takedata->users as $user) {
            $i++;
            $row = new html_table_row();
            $row->cells[] = $i;
            $row->cells[] = $this->output->render(new user_picture($user)).fullname($user);
            if ($user->enrolmentstart > $takedata->sessioninfo->sessdate) {
                $cell = new html_table_cell(get_string('enrolmentstart', 'attforblock', userdate($user->enrolmentstart, '%d.%m.%Y')));
                $cell->colspan = count($takedata->statuses) + 1;
                $row->cells[] = $cell;
                $row->attributes['class'] = 'userwithoutenrol';
            }
            elseif ($user->enrolmentstatus == ENROL_USER_SUSPENDED) {
                $cell = new html_table_cell(get_string('enrolmentsuspended', 'attforblock'));
                $cell->colspan = count($takedata->statuses) + 1;
                $row->cells[] = $cell;
                $row->attributes['class'] = 'userwithoutenrol';
            }
            else {
                if ($takedata->updatemode and !array_key_exists($user->id, $takedata->sessionlog))
                    $row->attributes['class'] = 'userwithoutdata';

                foreach ($takedata->statuses as $st) {
                    $params = array(
                            'type'  => 'radio',
                            'name'  => 'user'.$user->id,
                            'class' => 'st'.$st->id,
                            'value' => $st->id);
                    if (array_key_exists($user->id, $takedata->sessionlog) and $st->id == $takedata->sessionlog[$user->id]->statusid)
                        $params['checked'] = '';
                    $row->cells[] = html_writer::empty_tag('input', $params);
                }
                $params = array(
                        'type'  => 'text',
                        'name'  => 'remarks'.$user->id);
                if (array_key_exists($user->id, $takedata->sessionlog))
                    $params['value'] = $takedata->sessionlog[$user->id]->remarks;
                $row->cells[] = html_writer::empty_tag('input', $params);
            }
            //$row->attributes['class'] =

            $table->data[] = $row;
        }
        return html_writer::table($table);
        
    }

    private function get_fullname_head(attforblock_take_data $takedata) {
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

}
?>
