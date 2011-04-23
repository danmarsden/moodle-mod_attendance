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

        $filtertable->data[0][] = '';

        $curdatecontrols = '';
        if ($fcontrols->curdatetxt) {
            $curdatecontrols = html_writer::link($fcontrols->url(array('curdate' => $fcontrols->prevcur)), $this->output->larrow());
            $curdatecontrols .= $fcontrols->curdatetxt;
            $curdatecontrols .= html_writer::link($fcontrols->url(array('curdate' => $fcontrols->nextcur)), $this->output->rarrow());
            //plug_yui_calendar($current);
        }
        $filtertable->data[0][] = $curdatecontrols;

        $views[attforblock::VIEW_ALL] = get_string('all', 'attforblock');
        $views[attforblock::VIEW_ALLTAKEN] = get_string('alltaken', 'attforblock');
        $views[attforblock::VIEW_MONTHS] = get_string('months', 'attforblock');
        $views[attforblock::VIEW_WEEKS] = get_string('weeks', 'attforblock');
        $views[attforblock::VIEW_DAYS] = get_string('days', 'attforblock');
        $viewcontrols = '';
        foreach ($views as $key => $sview) {
            if ($key != $fcontrols->view) {
                $link = html_writer::link($fcontrols->url(array('view' => $key)), $sview);
                $viewcontrols .= html_writer::tag('span', $link, array('class' => 'attbtn'));
            }
            else
                $viewcontrols .= html_writer::tag('span', $sview, array('class' => 'attcurbtn'));
        }
        $filtertable->data[0][] = html_writer::tag('nobr', $viewcontrols);

        $o = html_writer::table($filtertable);
        $o = $this->output->container($o, 'attfiltercontrols attwidth');

        return $o;
    }

    /**
     * Renders attendance sessions managing table
     *
     * @param attforblock_sessions_manage_data $sessdata to display
     * @return string html code
     */
    protected function render_attforblock_sessions_manage_data(attforblock_sessions_manage_data $sessdata) {
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
                $url = $sessdata->url_sessions($sess->id, 'update');
                $title = get_string('editsession','attforblock');
                $actions .= $this->output->action_icon($url, new pix_icon('t/edit', $title));

                $url = $sessdata->url_sessions($sess->id, 'delete');
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

        $o = html_writer::table($sesstable) . html_writer::table($controltable);
        $o = html_writer::tag('form', $o, array('method' => 'post', 'action' => $sessdata->url_sessions()->out()));
        $o = $this->output->container($o, 'generalbox attwidth');
        $o = $this->output->container($o, 'attsessions_manage_table');

        return $o;
    }
}
?>
