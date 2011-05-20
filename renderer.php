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
        $sesstable = new html_table();
        $sesstable->width = '100%';
        $sesstable->head = array(
                '#',
                get_string('sessiontypeshort', 'attforblock'),
                get_string('date'), get_string('from'),
                ($sessdata->showendtime == '0') ? get_string('duration', 'attforblock') : get_string('to'),
                get_string('description','attforblock'),
                get_string('actions'),
                get_string('select')
            );
        $sesstable->align = array('', '', '', 'right', 'left', 'center', 'center');
        $sesstable->size = array('1px', '', '1px', '1px', '1px', '*', '1px', '1px');

        $i = 0;
        foreach ($sessdata->sessions as $key => $sess) {
            $i++;
            $actions = '';
            $desctext = empty($sess->description) ? get_string('nodescription', 'attforblock') : $sess->description;
            if($sess->lasttaken > 0)	//attendance has taken
            {
                if ($sessdata->perm->can_change()) {
                    $url = $sessdata->url_take($sess->id, $sess->groupid);
                    $title = get_string('changeattendance','attforblock');

                    $desc = html_writer::link($url, $desctext, array('title' => $title));
                } else {
                    $desc = '<i>' . $desctext . '</i>';
                }
            } else {
                $desc = $desctext;
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

            $sesstable->data[$sess->id][] = $i;
            $sesstable->data[$sess->id][] = $sess->groupid ? $sessdata->groups[$sess->groupid]->name : get_string('commonsession', 'attforblock');
            $sesstable->data[$sess->id][] = userdate($sess->sessdate, get_string('strftimedmyw', 'attforblock'));
            $sesstable->data[$sess->id][] = userdate($sess->sessdate, get_string('strftimehm', 'attforblock'));
            $hours = floor($sess->duration / HOURSECS);
            $mins = floor(($sess->duration - $hours * HOURSECS) / MINSECS);
            $mins = $mins < 10 ? "0$mins" : "$mins";
            $duration = "{$mins}&nbsp;" . get_string('min');
            if ($hours)
                $duration = "{$hours}&nbsp;" . get_string('hours') . "&nbsp;" . $duration;
            $endtime = userdate($sess->sessdate+$sess->duration, get_string('strftimehm', 'attforblock'));
            $sesstable->data[$sess->id][] =  ($sessdata->showendtime == 0) ? $duration : $endtime;
            $sesstable->data[$sess->id][] = $desc;
            $sesstable->data[$sess->id][] = $actions;
            $sesstable->data[$sess->id][] = html_writer::checkbox('sessid', $sess->id, false);
        }

        return html_writer::table($sesstable);
    }

    protected function render_sess_control_table(attforblock_sessions_manage_data $sessdata) {
        $controltable = new html_table();
        $controltable->attributes['class'] = ' ';
        $controltable->width = '100%';
        $controltable->align = array('left', 'right');

        $controltable->data[0][] = $this->output->help_icon('hiddensessions', 'attforblock',
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
        $controltable->data[0][] = $controls;

        return html_writer::table($controltable);
    }

}
?>
