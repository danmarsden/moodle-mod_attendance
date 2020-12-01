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
 * presence module renderering methods
 *
 * @package    mod_presence
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/renderables.php');
require_once(dirname(__FILE__).'/renderhelpers.php');
require_once($CFG->libdir.'/tablelib.php');
require_once($CFG->libdir.'/moodlelib.php');

/**
 * presence module renderer class
 *
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_presence_renderer extends plugin_renderer_base {
    // External API - methods to render presence renderable components.

    /**
     * Renders tabs for presence
     *
     * @param presence_tabs $presencetabs - tabs to display
     * @return string html code
     */
    protected function render_presence_tabs(presence_tabs $presencetabs) {
        return print_tabs($presencetabs->get_tabs(), $presencetabs->currenttab, null, null, true);
    }

    /**
     * Renders filter controls for presence
     *
     * @param presence_filter_controls $fcontrols - filter controls data to display
     * @return string html code
     */
    protected function render_presence_filter_controls(presence_filter_controls $fcontrols) {
        $filtertable = new html_table();
        $filtertable->attributes['class'] = ' ';
        $filtertable->width = '100%';
        $filtertable->align = array('left', 'center', 'right', 'right');

        $filtertable->data[0][] = $this->render_sess_group_selector($fcontrols);

        $filtertable->data[0][] = $this->render_curdate_controls($fcontrols);

        $filtertable->data[0][] = $this->render_paging_controls($fcontrols);

        $filtertable->data[0][] = $this->render_view_controls($fcontrols);

        $o = html_writer::table($filtertable);
        $o = $this->output->container($o, 'presencefiltercontrols');

        return $o;
    }

    /**
     * Render group selector
     *
     * @param presence_filter_controls $fcontrols
     * @return mixed|string
     */
    public function render_sess_group_selector(presence_filter_controls $fcontrols) {
        switch ($fcontrols->pageparams->selectortype) {
            case mod_presence_page_with_filter_controls::SELECTOR_SESS_TYPE:
                $sessgroups = $fcontrols->get_sess_groups_list();
                if ($sessgroups) {
                    $select = new single_select($fcontrols->url(), 'group', $sessgroups,
                                                $fcontrols->get_current_sesstype(), null, 'selectgroup');
                    $select->label = get_string('sessions', 'presence');
                    $output = $this->output->render($select);

                    return html_writer::tag('div', $output, array('class' => 'groupselector'));
                }
                break;
            case mod_presence_page_with_filter_controls::SELECTOR_GROUP:
                return groups_print_activity_menu($fcontrols->cm, $fcontrols->url(), true);
        }

        return '';
    }

    /**
     * Render paging controls.
     *
     * @param presence_filter_controls $fcontrols
     * @return string
     */
    public function render_paging_controls(presence_filter_controls $fcontrols) {
        $pagingcontrols = '';

        $group = 0;
        if (!empty($fcontrols->pageparams->group)) {
            $group = $fcontrols->pageparams->group;
        }

        $totalusers = count_enrolled_users(context_module::instance($fcontrols->cm->id), 'mod/presence:canbelisted', $group);

        if (empty($fcontrols->pageparams->page) || !$fcontrols->pageparams->page || !$totalusers ||
            empty($fcontrols->pageparams->perpage)) {

            return $pagingcontrols;
        }

        $numberofpages = ceil($totalusers / $fcontrols->pageparams->perpage);

        if ($fcontrols->pageparams->page > 1) {
            $pagingcontrols .= html_writer::link($fcontrols->url(array('curdate' => $fcontrols->curdate,
                                                                       'page' => $fcontrols->pageparams->page - 1)),
                                                                 $this->output->larrow());
        }
        $a = new stdClass();
        $a->page = $fcontrols->pageparams->page;
        $a->numpages = $numberofpages;
        $text = get_string('pageof', 'presence', $a);
        $pagingcontrols .= html_writer::tag('span', $text,
                                            array('class' => 'presencebtn'));
        if ($fcontrols->pageparams->page < $numberofpages) {
            $pagingcontrols .= html_writer::link($fcontrols->url(array('curdate' => $fcontrols->curdate,
                                                                       'page' => $fcontrols->pageparams->page + 1)),
                                                                 $this->output->rarrow());
        }

        return $pagingcontrols;
    }

    /**
     * Render date controls.
     *
     * @param presence_filter_controls $fcontrols
     * @return string
     */
    public function render_view_controls(presence_filter_controls $fcontrols) {
        die('depreceated');
    }

    /**
     * Render view controls.
     *
     * @param presence_filter_controls $fcontrols
     * @return string
     */
    public function render_curdate_controls(presence_filter_controls $fcontrols) {
        global $CFG, $OUTPUT;
        $this->page->requires->js('/mod/presence/calendar.js');
        $this->page->requires->strings_for_js(array('calclose', 'caltoday'), 'presence');

        $templatedata = [
            'views' => [],
            'script' => '',
        ];

        if ($fcontrols->curdatetxt) {
            $jsvals = array(
                'cal_months'    => explode(',', get_string('calmonths', 'presence')),
                'cal_week_days' => explode(',', get_string('calweekdays', 'presence')),
                'cal_start_weekday' => $CFG->calendar_startwday,
                'cal_cur_date'  => $fcontrols->curdate);
            $templatedata['script'] .= html_writer::script(js_writer::set_variable('M.presence', $jsvals));

            $templatedata['prev_url'] = $fcontrols->url(array('curdate' => $fcontrols->prevcur));
            $templatedata['next_url'] = $fcontrols->url(array('curdate' => $fcontrols->nextcur));

            $params = array(
                'title' => get_string('calshow', 'presence'),
                'id'    => 'show',
                'class' => 'btn btn-outline-secondary',
                'type'  => 'button');

            $buttonform .= html_writer::tag('button', $fcontrols->curdatetxt, $params);
            foreach ($fcontrols->url_params(array('curdate' => '')) as $name => $value) {
                $params = array(
                    'type'  => 'hidden',
                    'id'    => $name,
                    'name'  => $name,
                    'value' => $value);
                $buttonform .= html_writer::empty_tag('input', $params);
            }
            $params = array(
                'id'        => 'currentdate',
                'action'    => $fcontrols->url_path(),
                'method'    => 'post'
            );
            $buttonform = html_writer::tag('form', $buttonform, $params);
            $templatedata['datepicker'] = $buttonform;
        }



        $views[PRESENCE_VIEW_ALL] = get_string('all', 'presence');
        $views[PRESENCE_VIEW_ALLPAST] = get_string('allpast', 'presence');
        $views[PRESENCE_VIEW_ALLFUTURE] = get_string('allfuture', 'presence');
        $views[PRESENCE_VIEW_MONTHS] = get_string('months', 'presence');
        $views[PRESENCE_VIEW_WEEKS] = get_string('weeks', 'presence');
        $views[PRESENCE_VIEW_DAYS] = get_string('days', 'presence');

        $templatedata['curviewname'] = $views[$fcontrols->pageparams->view];
        foreach ($views as $key => $sview) {
            $templatedata['views'][] = [
                'viewtype' => $key,
                'viewname' => $sview,
                'link' => $fcontrols->url(array('view' => $key, 'mode' => $fcontrols->pageparams->mode)),
                'active' => ($key == $fcontrols->pageparams->view),
            ];
        }
        return $OUTPUT->render_from_template('mod_presence/controls_curdate', $templatedata);
    }

    /**
     * Renders presence sessions managing table
     *
     * @param presence_sessions_data $sessdata to display
     * @return string html code
     */
    protected function render_presence_manage_data(presence_sessions_data $sessdata) {
        $o = $this->render_sess_manage_table($sessdata) . $this->render_sess_manage_control($sessdata);
        $o = html_writer::tag('form', $o, array('method' => 'post', 'action' => $sessdata->url_sessions()->out()));
        $o = $this->output->container($o, 'generalbox presencewidth');
        $o = $this->output->container($o, 'presencesessions_manage_table');

        return $o;
    }

    /**
     * Render session manage table.
     *
     * @param presence_sessions_data $sessdata
     * @return string
     */
    protected function render_sess_manage_table(presence_sessions_data $sessdata) {
        $this->page->requires->js_init_call('M.mod_presence.init_manage');

        $table = new html_table();
        $table->width = '100%';
        $table->head = array_merge([
                '#',
                get_string('date', 'presence'),
                get_string('time', 'presence'),
                get_string('sessiontypeshort', 'presence'),
                get_string('description', 'presence'),
                get_string('room', 'presence'),
                get_string('roomattendants', 'presence'),
                get_string('actions'),
                html_writer::checkbox('cb_selector', 0, false, '', array('id' => 'cb_selector')),
            ]);
        $table->align = ['', 'right', '', '', 'left', 'left', 'right', 'right', 'center'];
        $table->size = ['1px', '1px', '1px', '', '*', '1px', '1px', '120px', '1px'];

        $i = 0;
        foreach ($sessdata->sessions as $key => $sess) {
            $i++;

            $dta = $this->construct_date_time_actions($sessdata, $sess);

            $table->data[$sess->id][] = $i;
            $table->data[$sess->id][] = $dta['date'];
            $table->data[$sess->id][] = $dta['time'];

            if ($sess->groupid) {
                if (empty($sessdata->groups[$sess->groupid])) {
                    $table->data[$sess->id][] = get_string('deletedgroup', 'presence');
                    // Remove actions and links on date/time.
                    $dta['actions'] = '';
                    $dta['date'] = userdate($sess->sessdate, get_string('strftimedmyw', 'presence'));
                    $dta['time'] = $this->construct_time($sess->sessdate, $sess->duration);
                } else {
                    $table->data[$sess->id][] = get_string('group') . ': ' . $sessdata->groups[$sess->groupid]->name;
                }
            } else {
                $table->data[$sess->id][] = get_string('commonsession', 'presence');
            }
            $table->data[$sess->id][] = $sess->description;
            $table->data[$sess->id][] = $sessdata->presence->get_room_name($sess->roomid);
            if ($sess->maxattendants) {
                $table->data[$sess->id][] = $sess->maxattendants ? $sess->bookings . ' / ' . $sess->maxattendants : '';
            } else if ($sess->bookings) {
                $table->data[$sess->id][] = $sess->bookings;
            } else {
                $table->data[$sess->id][] = '';
            }
            $table->data[$sess->id][] = $dta['actions'];
            $table->data[$sess->id][] = html_writer::checkbox('sessid[]', $sess->id, false, '',
                                                              array('class' => 'presencesesscheckbox'));
        }

        return html_writer::table($table);
    }

    /**
     * Implementation of user image rendering.
     *
     * @param presence_password_icon $helpicon A help icon instance
     * @return string HTML fragment
     */
    protected function render_presence_password_icon(presence_password_icon $helpicon) {
        return $this->render_from_template('presence/presence_password_icon', $helpicon->export_for_template($this));
    }
    /**
     * Construct date time actions.
     *
     * @param presence_sessions_data $sessdata
     * @param stdClass $sess
     * @return array
     */
    private function construct_date_time_actions(presence_sessions_data $sessdata, $sess) {
        $actions = '';
        if ((!empty($sess->studentpassword) || ($sess->includeqrcode == 1)) &&
            (has_capability('mod/presence:managepresences', $sessdata->presence->context) ||
            has_capability('mod/presence:takepresences', $sessdata->presence->context) ||
            has_capability('mod/presence:changepresences', $sessdata->presence->context))) {

            $icon = new presence_password_icon($sess->studentpassword, $sess->id);

            if ($sess->includeqrcode == 1||$sess->rotateqrcode == 1) {
                $icon->includeqrcode = 1;
            } else {
                $icon->includeqrcode = 0;
            }

            $actions .= $this->render($icon);
        }

        $date = userdate($sess->sessdate, get_string('strftimedmyw', 'presence'));
        $time = $this->construct_time($sess->sessdate, $sess->duration);
        if ($sess->lasttaken > 0) {
            if (has_capability('mod/presence:changepresences', $sessdata->presence->context)) {
                $url = $sessdata->url_take($sess->id, $sess->groupid);
                $title = get_string('changepresence', 'presence');

                $date = html_writer::link($url, $date, array('title' => $title));
                $time = html_writer::link($url, $time, array('title' => $title));

                $actions .= $this->output->action_icon($url, new pix_icon('redo', $title, 'presence'));
            } else {
                $date = '<i>' . $date . '</i>';
                $time = '<i>' . $time . '</i>';
            }
        } else {
            if (has_capability('mod/presence:takepresences', $sessdata->presence->context)) {
                $url = $sessdata->url_take($sess->id, $sess->groupid);
                $title = get_string('takepresence', 'presence');
                $actions .= $this->output->action_icon($url, new pix_icon('t/go', $title));
            }
        }

        if (has_capability('mod/presence:managepresences', $sessdata->presence->context)) {
            $url = $sessdata->url_sessions($sess->id, mod_presence_sessions_page_params::ACTION_UPDATE);
            $title = get_string('editsession', 'presence');
            $actions .= $this->output->action_icon($url, new pix_icon('t/edit', $title));

            $url = $sessdata->url_sessions($sess->id, mod_presence_sessions_page_params::ACTION_DELETE);
            $title = get_string('deletesession', 'presence');
            $actions .= $this->output->action_icon($url, new pix_icon('t/delete', $title));
        }

        return array('date' => $date, 'time' => $time, 'actions' => $actions);
    }

    /**
     * Render session manage control.
     *
     * @param presence_sessions_data $sessdata
     * @return string
     */
    protected function render_sess_manage_control(presence_sessions_data $sessdata) {
        $table = new html_table();
        $table->attributes['class'] = ' ';
        $table->width = '100%';
        $table->align = array('left', 'right');

        $table->data[0][] = $this->output->help_icon('hiddensessions', 'presence',
                get_string('hiddensessions', 'presence').': '.$sessdata->hiddensessionscount);

        if (has_capability('mod/presence:managepresences', $sessdata->presence->context)) {
            if ($sessdata->hiddensessionscount > 0) {
                $attributes = array(
                        'type'  => 'submit',
                        'name'  => 'deletehiddensessions',
                        'class' => 'btn btn-secondary',
                        'value' => get_string('deletehiddensessions', 'presence'));
                $table->data[1][] = html_writer::empty_tag('input', $attributes);
            }

            $options = array(mod_presence_sessions_page_params::ACTION_DELETE_SELECTED => get_string('delete'),
                mod_presence_sessions_page_params::ACTION_CHANGE_DURATION => get_string('changeduration', 'presence'));

            $controls = html_writer::select($options, 'action');
            $attributes = array(
                    'type'  => 'submit',
                    'name'  => 'ok',
                    'value' => get_string('ok'),
                    'class' => 'btn btn-secondary');
            $controls .= html_writer::empty_tag('input', $attributes);
        } else {
            $controls = get_string('youcantdo', 'presence'); // You can't do anything.
        }
        $table->data[0][] = $controls;

        return html_writer::table($table);
    }

    /**
     * Render take data.
     *
     * @param presence_take_data $takedata
     * @return string
     */
    protected function render_presence_take_data(presence_take_data $takedata) {
        user_preference_allow_ajax_update('mod_presence_statusdropdown', PARAM_TEXT);

        $controls = $this->render_presence_take_controls($takedata);
        $table = html_writer::start_div('no-overflow');
        if ($takedata->pageparams->viewmode == mod_presence_take_page_params::SORTED_LIST) {
            $table .= $this->render_presence_take_list($takedata);
        } else {
            $table .= $this->render_presence_take_grid($takedata);
        }
        $table .= html_writer::input_hidden_params($takedata->url(array('sesskey' => sesskey(),
                                                                        'page' => $takedata->pageparams->page,
                                                                        'perpage' => $takedata->pageparams->perpage)));
        $table .= html_writer::end_div();

        $params = array(
                'type'  => 'submit',
                'class' => 'btn btn-primary',
                'value' => get_string('save', 'presence'));
        $table .= html_writer::tag('center', html_writer::empty_tag('input', $params));

        $table = html_writer::tag('form', $table, array('method' => 'post', 'action' => $takedata->url_path(),
                                                        'id' => 'presencetakeform'));

        foreach ($takedata->statuses as $status) {
            $sessionstats[$status->id] = 0;
        }
        // Calculate the sum of statuses for each user.
        $sessionstats[] = array();
        foreach ($takedata->sessionlog as $userlog) {
            foreach ($takedata->statuses as $status) {
                if ($userlog->statusid == $status->id && in_array($userlog->studentid, array_keys($takedata->users))) {
                    $sessionstats[$status->id]++;
                }
            }
        }

        $statsoutput = '<br/>';
        foreach ($takedata->statuses as $status) {
            $statsoutput .= "$status->description = ".$sessionstats[$status->id]." <br/>";
        }

        return $controls.$table.$statsoutput;
    }

    /**
     * Render take controls.
     *
     * @param presence_take_data $takedata
     * @return string
     */
    protected function render_presence_take_controls(presence_take_data $takedata) {

        $urlparams = array('id' => $takedata->cm->id,
            'sessionid' => $takedata->pageparams->sessionid,
            'grouptype' => $takedata->pageparams->grouptype);
        $url = new moodle_url('/mod/presence/import/marksessions.php', $urlparams);
        $return = $this->output->single_button($url, get_string('uploadpresence', 'presence'));

        $table = new html_table();
        $table->attributes['class'] = ' ';

        $table->data[0][] = $this->construct_take_session_info($takedata);
        $table->data[0][] = $this->construct_take_controls($takedata);

        $return .= $this->output->container(html_writer::table($table), 'generalbox takecontrols');
        return $return;
    }

    /**
     * Construct take session info.
     *
     * @param presence_take_data $takedata
     * @return string
     */
    private function construct_take_session_info(presence_take_data $takedata) {
        $sess = $takedata->sessioninfo;
        $date = userdate($sess->sessdate, get_string('strftimedate'));
        $starttime = presence_strftimehm($sess->sessdate);
        $endtime = presence_strftimehm($sess->sessdate + $sess->duration);
        $time = html_writer::tag('nobr', $starttime . ($sess->duration > 0 ? ' - ' . $endtime : ''));
        $sessinfo = $date.' '.$time;
        $sessinfo .= html_writer::empty_tag('br');
        $sessinfo .= html_writer::empty_tag('br');
        $sessinfo .= $sess->description;

        return $sessinfo;
    }

    /**
     * Construct take controls.
     *
     * @param presence_take_data $takedata
     * @return string
     */
    private function construct_take_controls(presence_take_data $takedata) {

        $controls = '';
        $context = context_module::instance($takedata->cm->id);
        $group = 0;
        if ($takedata->pageparams->grouptype != mod_presence_structure::SESSION_COMMON) {
            $group = $takedata->pageparams->grouptype;
        } else {
            if ($takedata->pageparams->group) {
                $group = $takedata->pageparams->group;
            }
        }

        if (!empty($takedata->cm->groupingid)) {
            if ($group == 0) {
                $groups = array_keys(groups_get_all_groups($takedata->cm->course, 0, $takedata->cm->groupingid, 'g.id'));
            } else {
                $groups = $group;
            }
            $users = get_users_by_capability($context, 'mod/presence:canbelisted',
                            'u.id, u.firstname, u.lastname, u.email',
                            '', '', '', $groups,
                            '', false, true);
            $totalusers = count($users);
        } else {
            $totalusers = count_enrolled_users($context, 'mod/presence:canbelisted', $group);
        }
        $usersperpage = $takedata->pageparams->perpage;
        if (!empty($takedata->pageparams->page) && $takedata->pageparams->page && $totalusers && $usersperpage) {
            $controls .= html_writer::empty_tag('br');
            $numberofpages = ceil($totalusers / $usersperpage);

            if ($takedata->pageparams->page > 1) {
                $controls .= html_writer::link($takedata->url(array('page' => $takedata->pageparams->page - 1)),
                                                              $this->output->larrow());
            }
            $a = new stdClass();
            $a->page = $takedata->pageparams->page;
            $a->numpages = $numberofpages;
            $text = get_string('pageof', 'presence', $a);
            $controls .= html_writer::tag('span', $text,
                                          array('class' => 'presencebtn'));
            if ($takedata->pageparams->page < $numberofpages) {
                $controls .= html_writer::link($takedata->url(array('page' => $takedata->pageparams->page + 1,
                            'perpage' => $takedata->pageparams->perpage)), $this->output->rarrow());
            }
        }

        if ($takedata->pageparams->grouptype == mod_presence_structure::SESSION_COMMON and
                ($takedata->groupmode == VISIBLEGROUPS or
                ($takedata->groupmode and has_capability('moodle/site:accessallgroups', $context)))) {
            $controls .= groups_print_activity_menu($takedata->cm, $takedata->url(), true);
        }

        $controls .= html_writer::empty_tag('br');

        $options = array(
            mod_presence_take_page_params::SORTED_LIST   => get_string('sortedlist', 'presence'),
            mod_presence_take_page_params::SORTED_GRID   => get_string('sortedgrid', 'presence'));
        $select = new single_select($takedata->url(), 'viewmode', $options, $takedata->pageparams->viewmode, null);
        $select->set_label(get_string('viewmode', 'presence'));
        $select->class = 'singleselect inline';
        $controls .= $this->output->render($select);

        if ($takedata->pageparams->viewmode == mod_presence_take_page_params::SORTED_LIST) {
            $options = array(
                    0 => get_string('donotusepaging', 'presence'),
                   get_config('presence', 'resultsperpage') => get_config('presence', 'resultsperpage'));
            $select = new single_select($takedata->url(), 'perpage', $options, $takedata->pageparams->perpage, null);
            $select->class = 'singleselect inline';
            $controls .= $this->output->render($select);
        }

        if ($takedata->pageparams->viewmode == mod_presence_take_page_params::SORTED_GRID) {
            $options = array (1 => '1 '.get_string('column', 'presence'), '2 '.get_string('columns', 'presence'),
                                   '3 '.get_string('columns', 'presence'), '4 '.get_string('columns', 'presence'),
                                   '5 '.get_string('columns', 'presence'), '6 '.get_string('columns', 'presence'),
                                   '7 '.get_string('columns', 'presence'), '8 '.get_string('columns', 'presence'),
                                   '9 '.get_string('columns', 'presence'), '10 '.get_string('columns', 'presence'));
            $select = new single_select($takedata->url(), 'gridcols', $options, $takedata->pageparams->gridcols, null);
            $select->class = 'singleselect inline';
            $controls .= $this->output->render($select);
        }

        if (isset($takedata->sessions4copy) && count($takedata->sessions4copy) > 0) {
            $controls .= html_writer::empty_tag('br');
            $controls .= html_writer::empty_tag('br');

            $options = array();
            foreach ($takedata->sessions4copy as $sess) {
                $start = presence_strftimehm($sess->sessdate);
                $end = $sess->duration ? ' - '.presence_strftimehm($sess->sessdate + $sess->duration) : '';
                $options[$sess->id] = $start . $end;
            }
            $select = new single_select($takedata->url(array(), array('copyfrom')), 'copyfrom', $options);
            $select->set_label(get_string('copyfrom', 'presence'));
            $select->class = 'singleselect inline';
            $controls .= $this->output->render($select);
        }

        return $controls;
    }

    /**
     * get statusdropdown
     *
     * @return \single_select
     */
    private function statusdropdown() {

        $pref = get_user_preferences('mod_presence_statusdropdown');
        if (empty($pref)) {
            $pref = 'unselected';
        }
        $options = array_merge(
            [
                'all' => get_string('statusall', 'presence'),
                'unselected' => get_string('statusunselected', 'presence')
            ],
            $enablerooms ? [
                'booked' => get_string('statusbooked', 'presence'),
                'unbooked' => get_string('statusunbooked', 'presence'),
            ] : []
        );

        $select = new \single_select(new \moodle_url('/'), 'setallstatus-select', $options,
            $pref, null, 'setallstatus-select');
        $select->label = get_string('setallstatuses', 'presence');

        return $select;
    }

    /**
     * Render take list.
     *
     * @param presence_take_data $takedata
     * @return string
     */
    protected function render_presence_take_list(presence_take_data $takedata) {
        global $CFG;

        $table = new html_table();
        $table->width = '0%';
        $table->head = array_merge(
                ['#', ],
                $enablerooms ? ['Booked', ] : [],
                [$this->construct_fullname_head($takedata), ]
        );
        $table->align = ['left', 'center', 'left', ];

        $table->size = ['20px', '30px', '', ];
        $table->wrap[1] = 'nowrap';
        // Check if extra useridentity fields need to be added.
        $extrasearchfields = array();
        if (!empty($CFG->showuseridentity) && has_capability('moodle/site:viewuseridentity', $takedata->presence->context)) {
            $extrasearchfields = explode(',', $CFG->showuseridentity);
        }
        foreach ($extrasearchfields as $field) {
            $table->head[] = get_string($field);
            $table->align[] = 'left';
        }
        foreach ($takedata->statuses as $st) {
            $table->head[] = html_writer::link("#", $st->acronym, array('id' => 'checkstatus'.$st->id,
                'title' => get_string('setallstatusesto', 'presence', $st->description)));
            $table->align[] = 'center';
            $table->size[] = '20px';
            // JS to select all radios of this status and prevent default behaviour of # link.
            $this->page->requires->js_amd_inline("
                require(['jquery'], function($) {
                    $('#checkstatus".$st->id."').click(function(e) {
                     if ($('select[name=\"setallstatus-select\"] option:selected').val() == 'all') {
                            $('#presencetakeform').find('.st".$st->id."').prop('checked', true);
                            M.util.set_user_preference('mod_presence_statusdropdown','all');
                        }
                        else {
                            $('#presencetakeform').find('input:indeterminate.st".$st->id."').prop('checked', true);
                            M.util.set_user_preference('mod_presence_statusdropdown','unselected');
                        }
                        e.preventDefault();
                    });
                });");

        }

        $table->head[] = get_string('remarks', 'presence');
        $table->align[] = 'center';
        $table->size[] = '20px';
        $table->attributes['class'] = 'generaltable takelist';

        // Show a 'select all' row of radio buttons.
        $row = new html_table_row();
        $row->attributes['class'] = 'setallstatusesrow';
        $row->cells[] = '';
        foreach ($extrasearchfields as $field) {
            $row->cells[] = '';
        }

        $cell = new html_table_cell(html_writer::div($this->output->render($this->statusdropdown()), 'setallstatuses'));
        $cell->colspan = 2;
        $row->cells[] = $cell;
        foreach ($takedata->statuses as $st) {
            $attribs = array(
                'id' => 'radiocheckstatus'.$st->id,
                'type' => 'radio',
                'title' => get_string('setallstatusesto', 'presence', $st->description),
                'name' => 'setallstatuses',
                'class' => "st{$st->id}",
            );
            $row->cells[] = html_writer::empty_tag('input', $attribs);
            // Select all radio buttons of the same status.
            $this->page->requires->js_amd_inline("
                require(['jquery'], function($) {
                    $('#radiocheckstatus".$st->id."').click(function(e) {
                        console.log(".$st->id.");
                        var mode = $('select[name=\"setallstatus-select\"] option:selected').val();
                        M.util.set_user_preference('mod_presence_statusdropdown', mode);
                        if (mode == 'all') {
                            $('#presencetakeform').find('.st".$st->id."').prop('checked', true);
                        }
                        else if(mode == 'unselected') {
                            $('#presencetakeform').find('input:indeterminate.st".$st->id."').prop('checked', true);
                        }
                        else if(mode == 'booked') {
                            $('#presencetakeform').find('.st".$st->id."[data-booked=1]').prop('checked', true);
                        }
                        else if(mode == 'unbooked') {
                            $('#presencetakeform').find('.st".$st->id."[data-booked=0]').prop('checked', true);
                        }
                    });
                });");
        }
        $row->cells[] = '';
        $table->data[] = $row;

        $i = 0;
        foreach ($takedata->users as $user) {
            $i++;
            $row = new html_table_row();
            $row->cells[] = $i;
            if ($enablerooms) {
                $row->cells[] = $user->booked ? $this->output->pix_icon('t/check', $title) : '';
            }
            $fullname = html_writer::link($takedata->url_view(array('studentid' => $user->id)), fullname($user));
            $fullname = $this->user_picture($user).$fullname; // Show different picture if it is a temporary user.

            $ucdata = $this->construct_take_user_controls($takedata, $user);
            if (array_key_exists('warning', $ucdata)) {
                $fullname .= html_writer::empty_tag('br');
                $fullname .= $ucdata['warning'];
            }
            $row->cells[] = $fullname;
            foreach ($extrasearchfields as $field) {
                $row->cells[] = $user->$field;
            }

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

    /**
     * Render take grid.
     *
     * @param presence_take_data $takedata
     * @return string
     */
    protected function render_presence_take_grid(presence_take_data $takedata) {
        $table = new html_table();
        for ($i = 0; $i < $takedata->pageparams->gridcols; $i++) {
            $table->align[] = 'center';
            $table->size[] = '110px';
        }
        $table->attributes['class'] = 'generaltable takegrid';
        $table->headspan = $takedata->pageparams->gridcols;

        $head = array();
        $head[] = html_writer::div($this->output->render($this->statusdropdown()), 'setallstatuses');
        foreach ($takedata->statuses as $st) {
            $head[] = html_writer::link("#", $st->acronym, array('id' => 'checkstatus'.$st->id,
                                              'title' => get_string('setallstatusesto', 'presence', $st->description)));
            // JS to select all radios of this status and prevent default behaviour of # link.
            $this->page->requires->js_amd_inline("
                 require(['jquery'], function($) {
                     $('#checkstatus".$st->id."').click(function(e) {
                         var mode = $('select[name=\"setallstatus-select\"] option:selected').val();
                         M.util.set_user_preference('mod_presence_statusdropdown','unselected');
                         if (mode == 'unselected') {
                             $('#presencetakeform').find('input:indeterminate.st".$st->id."').prop('checked', true);
                         }
                         else if (mode == 'all') {
                             $('#presencetakeform').find('.st".$st->id."').prop('checked', true);
                         }
                         else if(mode == 'booked') {
                             $('#presencetakeform').find('.st".$st->id."[data-booked=1]').prop('checked', true);
                         }
                         else if(mode == 'unbooked') {
                             $('#presencetakeform').find('.st".$st->id."[data-booked=0]').prop('checked', true);
                         }
                         e.preventDefault();
                     });
                 });");
        }
        $table->head[] = implode('&nbsp;&nbsp;', $head);

        $i = 0;
        $row = new html_table_row();
        foreach ($takedata->users as $user) {
            $celltext = $this->user_picture($user, array('size' => 100));  // Show different picture if it is a temporary user.
            $celltext .= html_writer::empty_tag('br');
            $fullname = html_writer::link($takedata->url_view(array('studentid' => $user->id)), fullname($user));
            $celltext .= html_writer::tag('span', $fullname, array('class' => 'fullname'));
            if ($enablerooms) {
                $celltext .= $user->booked ? ' '.$this->output->pix_icon('t/check', $title) : '';
            }
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

    /**
     * Construct full name.
     *
     * @param stdClass $data
     * @return string
     */
    private function construct_fullname_head($data) {
        global $CFG;

        $url = $data->url();
        if ($data->pageparams->sort == PRESENCE_SORT_LASTNAME) {
            $url->param('sort', PRESENCE_SORT_FIRSTNAME);
            $firstname = html_writer::link($url, get_string('firstname'));
            $lastname = get_string('lastname');
        } else if ($data->pageparams->sort == PRESENCE_SORT_FIRSTNAME) {
            $firstname = get_string('firstname');
            $url->param('sort', PRESENCE_SORT_LASTNAME);
            $lastname = html_writer::link($url, get_string('lastname'));
        } else {
            $firstname = html_writer::link($data->url(array('sort' => PRESENCE_SORT_FIRSTNAME)), get_string('firstname'));
            $lastname = html_writer::link($data->url(array('sort' => PRESENCE_SORT_LASTNAME)), get_string('lastname'));
        }

        if ($CFG->fullnamedisplay == 'lastname firstname') {
            $fullnamehead = "$lastname / $firstname";
        } else {
            $fullnamehead = "$firstname / $lastname ";
        }

        return $fullnamehead;
    }

    /**
     * Construct take user controls.
     *
     * @param presence_take_data $takedata
     * @param stdClass $user
     * @return array
     */
    private function construct_take_user_controls(presence_take_data $takedata, $user) {
        $celldata = array();
        if ($user->enrolmentend and $user->enrolmentend < $takedata->sessioninfo->sessdate) {
            $celldata['text'] = get_string('enrolmentend', 'presence', userdate($user->enrolmentend, '%d.%m.%Y'));
            $celldata['colspan'] = count($takedata->statuses) + 1;
            $celldata['class'] = 'userwithoutenrol';
        } else if (!$user->enrolmentend and $user->enrolmentstatus == ENROL_USER_SUSPENDED) {
            // No enrolmentend and ENROL_USER_SUSPENDED.
            $celldata['text'] = get_string('enrolmentsuspended', 'presence');
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
                        'value' => $st->id,
                        'data-booked' => $user->booked);
                if (array_key_exists($user->id, $takedata->sessionlog) and $st->id == $takedata->sessionlog[$user->id]->statusid) {
                    $params['checked'] = '';
                }

                $input = html_writer::empty_tag('input', $params);

                if ($takedata->pageparams->viewmode == mod_presence_take_page_params::SORTED_GRID) {
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
                $celldata['warning'] = get_string('enrolmentstart', 'presence',
                                                  userdate($user->enrolmentstart, '%H:%M %d.%m.%Y'));
                $celldata['class'] = 'userwithoutenrol';
            }
        }

        return $celldata;
    }

    /**
     * Render header.
     *
     * @param mod_presence_header $header
     * @return string
     */
    protected function render_mod_presence_header(mod_presence_header $header) {
        if (!$header->should_render()) {
            return '';
        }

        $presence = $header->get_presence();

        $heading = format_string($header->get_title(), false, ['context' => $presence->context]);
        $o = $this->output->heading($heading);

//        $o .= $this->output->box_start('generalbox boxaligncenter', 'intro');
//        # $o .= format_module_intro('presence', $presence, $presence->cm->id);
//        $o .= $this->output->box_end();

        return $o;
    }

    /**
     * Render user data.
     *
     * @param presence_user_data $userdata
     * @return string
     */
    protected function render_presence_user_data(presence_user_data $userdata) {
        global $USER;

        // $o = $this->render_user_report_tabs($userdata);

        if ($USER->id == $userdata->user->id) {

            $o .= $this->construct_user_data($userdata);

        } else {

            $table = new html_table();

            $table->attributes['class'] = 'userinfobox';
            $table->colclasses = array('left side', '');
            // Show different picture if it is a temporary user.
            $table->data[0][] = $this->user_picture($userdata->user, array('size' => 100));
            $table->data[0][] = $this->construct_user_data($userdata);

            $o .= html_writer::table($table);
        }

        return $o;
    }

    /**
     * Render user report tabs.
     *
     * @param presence_user_data $userdata
     * @return string
     */
    protected function render_user_report_tabs(presence_user_data $userdata) {
        $tabs = array();

        $prefixresults = "";

        $tabs[] = new tabobject(mod_presence_view_page_params::MODE_THIS_BOOKING,
            $userdata->url()->out(true, array('mode' => mod_presence_view_page_params::MODE_THIS_BOOKING)),
            get_string('sessions', 'presence'));
        $prefixresults = get_string('results', 'presence') . ': ';

        /*$tabs[] = new tabobject(mod_presence_view_page_params::MODE_THIS_COURSE,
                        $userdata->url()->out(true, array('mode' => mod_presence_view_page_params::MODE_THIS_COURSE)),
                        $prefixresults. get_string('thiscourse', 'presence'));

        // Skip the 'all courses' tab for 'temporary' users.
        if ($userdata->user->type == 'standard') {
            $tabs[] = new tabobject(mod_presence_view_page_params::MODE_ALL_COURSES,
                            $userdata->url()->out(true, array('mode' => mod_presence_view_page_params::MODE_ALL_COURSES)),
                            $prefixresults . get_string('allcourses', 'presence'));
        }*/

        return print_tabs(array($tabs), $userdata->pageparams->mode, null, null, true);
    }

    /**
     * Construct user data.
     *
     * @param presence_user_data $userdata
     * @return string
     */
    private function construct_user_data(presence_user_data $userdata) {
        global $USER;
        $o = '';
        if ($USER->id <> $userdata->user->id) {
            $o = html_writer::tag('h2', fullname($userdata->user));
        }

        // $o .= $this->render_presence_filter_controls($userdata->filtercontrols);
        $o .= $this->construct_user_sessions_bookable($userdata);
        $o .= html_writer::empty_tag('hr');

        return $o;
    }

    /**
     * Construct user session booking list.
     *
     * @param presence_user_data $userdata
     * @return string
     */
    private function construct_user_sessions_bookable(presence_user_data $userdata) {
        global $USER;
        $context = context_module::instance($userdata->filtercontrols->cm->id);

        $table = new html_table();
        $table->attributes['class'] = 'generaltable presencewidth boxaligncenter';
        $table->head = array();
        $table->align = array();
        $table->size = array();
        $table->colclasses = array();

        $table->head[] = get_string('date');
        $table->head[] = get_string('description', 'presence');
        $table->head[] = get_string('room', 'presence');
        $table->head[] = get_string('sessionbookedspots', 'presence');
        $table->head[] = get_string('action');

        $table->align = array_merge($table->align, array('', 'left', 'left', 'center', 'center'));
        $table->colclasses = array_merge($table->colclasses, array('datecol', 'desccol', '', '', ''));
        $table->size = array_merge($table->size, array('1px', '*', '*', '*', '100px'));

        $bookedsessionids = presence_sessionsbooked();

        $i = 0;
        foreach ($userdata->sessionslog as $sess) {
            $i++;

            $row = new html_table_row();
            $row->cells[] = userdate($sess->sessdate, get_string('strftimedmyw', 'presence')) .
                " ". $this->construct_time($sess->sessdate, $sess->duration);
            $row->cells[] = $sess->description;
            $row->cells[] = $sess->roomname;
            $cellbookedspots = html_writer::tag('span', $sess->bookedspots, array('data-presence-book-session' => $sess->id));
            if ($sess->maxattendants > 0) {
                $cellbookedspots .= "  / {$sess->maxattendants}";
            }
            $row->cells[] = $cellbookedspots;
            $actions = '';
            if ($sess->sessdate > time()) {
                $actions .= html_writer::tag('button', get_string('sessionbook', 'presence'),
                        array('data-presence-book-session' => $sess->id,
                            'data-presence-book-action' => 1,
                            'type' => 'button',
                            'class' => 'btn btn-secondary' . (in_array($sess->id, $bookedsessionids) ? ' hidden' : '')));
                $actions .= html_writer::tag('button', get_string('sessionunbook', 'presence'),
                        array('data-presence-book-session' => $sess->id,
                            'data-presence-book-action' => -1,
                            'type' => 'button',
                            'class' => 'btn btn-primary' . (in_array($sess->id, $bookedsessionids) ? '' : ' hidden')));
            }
            $row->cells[] = $actions;

            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    /**
     * Construct user sessions log.
     *
     * @param presence_user_data $userdata
     * @return string
     */
    private function construct_user_sessions_log(presence_user_data $userdata) {
        global $USER;
        $context = context_module::instance($userdata->filtercontrols->cm->id);

        $shortform = false;
        if ($USER->id == $userdata->user->id) {
            // This is a user viewing their own stuff - hide non-relevant columns.
            $shortform = true;
        }

        $table = new html_table();
        $table->attributes['class'] = 'generaltable presencewidth boxaligncenter';
        $table->head = array();
        $table->align = array();
        $table->size = array();
        $table->colclasses = array();
        if (!$shortform) {
            $table->head[] = get_string('sessiontypeshort', 'presence');
            $table->align[] = '';
            $table->size[] = '1px';
            $table->colclasses[] = '';
        }
        $table->head[] = get_string('date');
        $table->head[] = get_string('description', 'presence');
        $table->head[] = get_string('status', 'presence');
        $table->head[] = get_string('points', 'presence');
        $table->head[] = get_string('remarks', 'presence');

        $table->align = array_merge($table->align, array('', 'left', 'center', 'center', 'center'));
        $table->colclasses = array_merge($table->colclasses, array('datecol', 'desccol', 'statuscol', 'pointscol', 'remarkscol'));
        $table->size = array_merge($table->size, array('1px', '*', '*', '1px', '*'));

        if (has_capability('mod/presence:takepresences', $context)) {
            $table->head[] = get_string('action');
            $table->align[] = '';
            $table->size[] = '';
        }

        $statussetmaxpoints = presence_get_statusset_maxpoints($userdata->statuses);

        $i = 0;
        foreach ($userdata->sessionslog as $sess) {
            $i++;

            $row = new html_table_row();
            if (!$shortform) {
                if ($sess->groupid) {
                    $sessiontypeshort = get_string('group') . ': ' . $userdata->groups[$sess->groupid]->name;
                } else {
                    $sessiontypeshort = get_string('commonsession', 'presence');
                }

                $row->cells[] = html_writer::tag('nobr', $sessiontypeshort);
            }
            $row->cells[] = userdate($sess->sessdate, get_string('strftimedmyw', 'presence')) .
             " ". $this->construct_time($sess->sessdate, $sess->duration);
            $row->cells[] = $sess->description;
            if (!empty($sess->statusid)) {
                $status = $userdata->statuses[$sess->statusid];
                $row->cells[] = $status->description;
                $row->cells[] = format_float($status->grade, 1, true, true) . ' / ' .
                                    format_float($statussetmaxpoints[$status->setnumber], 1, true, true);
                $row->cells[] = $sess->remarks;
            } else if (($sess->sessdate + $sess->duration) < $userdata->user->enrolmentstart) {
                $cell = new html_table_cell(get_string('enrolmentstart', 'presence',
                                            userdate($userdata->user->enrolmentstart, '%d.%m.%Y')));
                $cell->colspan = 3;
                $row->cells[] = $cell;
            } else if ($userdata->user->enrolmentend and $sess->sessdate > $userdata->user->enrolmentend) {
                $cell = new html_table_cell(get_string('enrolmentend', 'presence',
                                            userdate($userdata->user->enrolmentend, '%d.%m.%Y')));
                $cell->colspan = 3;
                $row->cells[] = $cell;
            } else {
                list($canmark, $reason) = presence_can_student_mark($sess, false);
                if ($canmark) {
                    if ($sess->rotateqrcode == 1) {
                        $url = new moodle_url('/mod/presence/presence.php');
                        $output = html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sessid',
                                'value' => $sess->id));
                        $output .= html_writer::empty_tag('input', array('type' => 'text', 'name' => 'qrpass',
                                'placeholder' => "Enter password"));
                        $output .= html_writer::empty_tag('input', array('type' => 'submit',
                                'value' => get_string('submit'),
                                'class' => 'btn btn-secondary'));
                        $cell = new html_table_cell(html_writer::tag('form', $output,
                            array('action' => $url->out(), 'method' => 'get')));
                    } else {
                        // Student can mark their own presence.
                        // URL to the page that lets the student modify their presence.
                        $url = new moodle_url('/mod/presence/presence.php',
                                array('sessid' => $sess->id, 'sesskey' => sesskey()));
                        $cell = new html_table_cell(html_writer::link($url, get_string('submitpresence', 'presence')));
                    }
                    $cell->colspan = 3;
                    $row->cells[] = $cell;
                } else { // Student cannot mark their own attendace.
                    $row->cells[] = '?';
                    $row->cells[] = '? / ' . format_float($statussetmaxpoints[$sess->statusset], 1, true, true);
                    $row->cells[] = '';
                }
            }

            if (has_capability('mod/presence:takepresences', $context)) {
                $params = array('id' => $userdata->filtercontrols->cm->id,
                    'sessionid' => $sess->id,
                    'grouptype' => $sess->groupid);
                $url = new moodle_url('/mod/presence/take.php', $params);
                $icon = $this->output->pix_icon('redo', get_string('changepresence', 'presence'), 'presence');
                $row->cells[] = html_writer::link($url, $icon);
            }

            $table->data[] = $row;
        }

        return html_writer::table($table);
    }

    /**
     * Construct time for display.
     *
     * @param int $datetime
     * @param int $duration
     * @return string
     */
    private function construct_time($datetime, $duration) {
        $time = html_writer::tag('nobr', presence_construct_session_time($datetime, $duration));

        return $time;
    }

    /**
     * Render report data.
     *
     * @param presence_report_data $reportdata
     * @return string
     */
    protected function render_presence_report_data(presence_report_data $reportdata) {
        global $COURSE;

        // Initilise Javascript used to (un)check all checkboxes.
        $this->page->requires->js_init_call('M.mod_presence.init_manage');

        $table = new html_table();
        $table->attributes['class'] = 'generaltable presencewidth presencereport';

        $userrows = $this->get_user_rows($reportdata);

        if ($reportdata->pageparams->view == PRESENCE_VIEW_SUMMARY) {
            $sessionrows = array();
        } else {
            $sessionrows = $this->get_session_rows($reportdata);
        }

        $setnumber = -1;
        $statusetcount = 0;
        foreach ($reportdata->statuses as $sts) {
            if ($sts->setnumber != $setnumber) {
                $statusetcount++;
                $setnumber = $sts->setnumber;
            }
        }

        $acronymrows = $this->get_acronym_rows($reportdata, true);
        $startwithcontrast = $statusetcount % 2 == 0;
        $summaryrows = $this->get_summary_rows($reportdata, $startwithcontrast);

        // Check if the user should be able to bulk send messages to other users on the course.
        $bulkmessagecapability = has_capability('moodle/course:bulkmessaging', $this->page->context);

        // Extract rows from each part and collate them into one row each.
        $sessiondetailsleft = $reportdata->pageparams->sessiondetailspos == 'left';
        foreach ($userrows as $index => $row) {
            $summaryrow = isset($summaryrows[$index]->cells) ? $summaryrows[$index]->cells : array();
            $sessionrow = isset($sessionrows[$index]->cells) ? $sessionrows[$index]->cells : array();
            if ($sessiondetailsleft) {
                $row->cells = array_merge($row->cells, $sessionrow, $acronymrows[$index]->cells, $summaryrow);
            } else {
                $row->cells = array_merge($row->cells, $acronymrows[$index]->cells, $summaryrow, $sessionrow);
            }
            $table->data[] = $row;
        }

        if ($bulkmessagecapability) { // Require that the user can bulk message users.
            // Display check boxes that will allow the user to send a message to the students that have been checked.
            $output = html_writer::empty_tag('input', array('name' => 'sesskey', 'type' => 'hidden', 'value' => sesskey()));
            $output .= html_writer::empty_tag('input', array('name' => 'id', 'type' => 'hidden', 'value' => $COURSE->id));
            $output .= html_writer::empty_tag('input', array('name' => 'returnto', 'type' => 'hidden', 'value' => s(me())));
            $output .= html_writer::start_div('presencereporttable');
            $output .= html_writer::table($table).html_writer::tag('div', get_string('users').': '.count($reportdata->users));
            $output .= html_writer::end_div();
            $output .= html_writer::tag('div',
                    html_writer::empty_tag('input', array('type' => 'submit',
                                                                   'value' => get_string('messageselectadd'),
                                                                   'class' => 'btn btn-secondary')),
                    array('class' => 'buttons'));
            $url = new moodle_url('/mod/presence/messageselect.php');
            return html_writer::tag('form', $output, array('action' => $url->out(), 'method' => 'post'));
        } else {
            return html_writer::table($table).html_writer::tag('div', get_string('users').': '.count($reportdata->users));
        }
    }

    /**
     * Build and return the rows that will make up the left part of the presence report.
     * This consists of student names, as well as header cells for these columns.
     *
     * @param presence_report_data $reportdata the report data
     * @return array Array of html_table_row objects
     */
    protected function get_user_rows(presence_report_data $reportdata) {
        $rows = array();

        $bulkmessagecapability = has_capability('moodle/course:bulkmessaging', $this->page->context);
        $extrafields = get_extra_user_fields($reportdata->presence->context);
        $showextrauserdetails = $reportdata->pageparams->showextrauserdetails;
        $params = $reportdata->pageparams->get_significant_params();
        $text = get_string('users');
        if ($extrafields) {
            if ($showextrauserdetails) {
                $params['showextrauserdetails'] = 0;
                $url = $reportdata->presence->url_report($params);
                $text .= $this->output->action_icon($url, new pix_icon('t/switch_minus',
                            get_string('hideextrauserdetails', 'presence')), null, null);
            } else {
                $params['showextrauserdetails'] = 1;
                $url = $reportdata->presence->url_report($params);
                $text .= $this->output->action_icon($url, new pix_icon('t/switch_plus',
                            get_string('showextrauserdetails', 'presence')), null, null);
                $extrafields = array();
            }
        }
        $usercolspan = count($extrafields);

        $row = new html_table_row();
        $cell = $this->build_header_cell($text, false, false);
        $cell->attributes['class'] = $cell->attributes['class'] . ' headcol';
        $row->cells[] = $cell;
        if (!empty($usercolspan)) {
            $row->cells[] = $this->build_header_cell('', false, false, $usercolspan);
        }
        $rows[] = $row;

        $row = new html_table_row();
        $text = '';
        if ($bulkmessagecapability) {
            $text .= html_writer::checkbox('cb_selector', 0, false, '', array('id' => 'cb_selector'));
        }
        $text .= $this->construct_fullname_head($reportdata);
        $cell = $this->build_header_cell($text, false, false);
        $cell->attributes['class'] = $cell->attributes['class'] . ' headcol';
        $row->cells[] = $cell;

        foreach ($extrafields as $field) {
            $row->cells[] = $this->build_header_cell(get_string($field), false, false);
        }

        $rows[] = $row;

        foreach ($reportdata->users as $user) {
            $row = new html_table_row();
            $text = '';
            if ($bulkmessagecapability) {
                $text .= html_writer::checkbox('user'.$user->id, 'on', false, '', array('class' => 'presencesesscheckbox'));
            }
            $text .= html_writer::link($reportdata->url_view(array('studentid' => $user->id)), fullname($user));
            $cell = $this->build_data_cell($text, false, false, null, null, false);
            $cell->attributes['class'] = $cell->attributes['class'] . ' headcol';
            $row->cells[] = $cell;

            foreach ($extrafields as $field) {
                $row->cells[] = $this->build_data_cell($user->$field, false, false);
            }
            $rows[] = $row;
        }

        $row = new html_table_row();
        $text = ($reportdata->pageparams->view == PRESENCE_VIEW_SUMMARY) ? '' : get_string('summary');
        $cell = $this->build_data_cell($text, false, true, $usercolspan);
        $cell->attributes['class'] = $cell->attributes['class'] . ' headcol';
        $row->cells[] = $cell;
        if (!empty($usercolspan)) {
            $row->cells[] = $this->build_header_cell('', false, false, $usercolspan);
        }
        $rows[] = $row;

        return $rows;
    }

    /**
     * Build and return the rows that will make up the summary part of the presence report.
     * This consists of countings for each status set acronyms, as well as header cells for these columns.
     *
     * @param presence_report_data $reportdata the report data
     * @param boolean $startwithcontrast true if the first column must start with contrast (bgcolor)
     * @return array Array of html_table_row objects
     */
    protected function get_acronym_rows(presence_report_data $reportdata, $startwithcontrast=false) {
        $rows = array();

        $summarycells = array();

        $row1 = new html_table_row();
        $row2 = new html_table_row();

        $setnumber = -1;
        $contrast = !$startwithcontrast;
        foreach ($reportdata->statuses as $sts) {
            if ($sts->setnumber != $setnumber) {
                $contrast = !$contrast;
                $setnumber = $sts->setnumber;
                $text = presence_get_setname($reportdata->presence->id, $setnumber, false);
                $cell = $this->build_header_cell($text, $contrast);
                $row1->cells[] = $cell;
            }
            $cell->colspan++;
            $sts->contrast = $contrast;
            $row2->cells[] = $this->build_header_cell($sts->acronym, $contrast);
            $summarycells[] = $this->build_data_cell('', $contrast);
        }

        $rows[] = $row1;
        $rows[] = $row2;

        foreach ($reportdata->users as $user) {
            if ($reportdata->pageparams->view == PRESENCE_VIEW_SUMMARY) {
                $usersummary = $reportdata->summary->get_all_sessions_summary_for($user->id);
            } else {
                $usersummary = $reportdata->summary->get_taken_sessions_summary_for($user->id);
            }

            $row = new html_table_row();
            foreach ($reportdata->statuses as $sts) {
                if (isset($usersummary->userstakensessionsbyacronym[$sts->setnumber][$sts->acronym])) {
                    $text = $usersummary->userstakensessionsbyacronym[$sts->setnumber][$sts->acronym];
                } else {
                    $text = 0;
                }
                $row->cells[] = $this->build_data_cell($text, $sts->contrast);
            }

            $rows[] = $row;
        }

        $rows[] = new html_table_row($summarycells);

        return $rows;
    }

    /**
     * Build and return the rows that will make up the summary part of the presence report.
     * This consists of counts and percentages for taken sessions (all sessions for summary report),
     * as well as header cells for these columns.
     *
     * @param presence_report_data $reportdata the report data
     * @param boolean $startwithcontrast true if the first column must start with contrast (bgcolor)
     * @return array Array of html_table_row objects
     */
    protected function get_summary_rows(presence_report_data $reportdata, $startwithcontrast=false) {
        $rows = array();

        $contrast = $startwithcontrast;
        $summarycells = array();

        $row1 = new html_table_row();
        $helpicon = $this->output->help_icon('oversessionstaken', 'presence');
        $row1->cells[] = $this->build_header_cell(get_string('oversessionstaken', 'presence') . $helpicon, $contrast, true, 3);

        $row2 = new html_table_row();
        $row2->cells[] = $this->build_header_cell(get_string('sessions', 'presence'), $contrast);
        $row2->cells[] = $this->build_header_cell(get_string('points', 'presence'), $contrast);
        $row2->cells[] = $this->build_header_cell(get_string('percentage', 'presence'), $contrast);
        $summarycells[] = $this->build_data_cell('', $contrast);
        $summarycells[] = $this->build_data_cell('', $contrast);
        $summarycells[] = $this->build_data_cell('', $contrast);

        if ($reportdata->pageparams->view == PRESENCE_VIEW_SUMMARY) {
            $contrast = !$contrast;

            $helpicon = $this->output->help_icon('overallsessions', 'presence');
            $row1->cells[] = $this->build_header_cell(get_string('overallsessions', 'presence') . $helpicon, $contrast, true, 3);

            $row2->cells[] = $this->build_header_cell(get_string('sessions', 'presence'), $contrast);
            $row2->cells[] = $this->build_header_cell(get_string('points', 'presence'), $contrast);
            $row2->cells[] = $this->build_header_cell(get_string('percentage', 'presence'), $contrast);
            $summarycells[] = $this->build_data_cell('', $contrast);
            $summarycells[] = $this->build_data_cell('', $contrast);
            $summarycells[] = $this->build_data_cell('', $contrast);

            $contrast = !$contrast;
            $helpicon = $this->output->help_icon('maxpossible', 'presence');
            $row1->cells[] = $this->build_header_cell(get_string('maxpossible', 'presence') . $helpicon, $contrast, true, 2);

            $row2->cells[] = $this->build_header_cell(get_string('points', 'presence'), $contrast);
            $row2->cells[] = $this->build_header_cell(get_string('percentage', 'presence'), $contrast);
            $summarycells[] = $this->build_data_cell('', $contrast);
            $summarycells[] = $this->build_data_cell('', $contrast);
        }

        $rows[] = $row1;
        $rows[] = $row2;

        foreach ($reportdata->users as $user) {
            if ($reportdata->pageparams->view == PRESENCE_VIEW_SUMMARY) {
                $usersummary = $reportdata->summary->get_all_sessions_summary_for($user->id);
            } else {
                $usersummary = $reportdata->summary->get_taken_sessions_summary_for($user->id);
            }

            $contrast = $startwithcontrast;
            $row = new html_table_row();
            $row->cells[] = $this->build_data_cell($usersummary->numtakensessions, $contrast);
            $row->cells[] = $this->build_data_cell($usersummary->pointssessionscompleted, $contrast);
            $row->cells[] = $this->build_data_cell(format_float($usersummary->takensessionspercentage * 100) . '%', $contrast);

            if ($reportdata->pageparams->view == PRESENCE_VIEW_SUMMARY) {
                $contrast = !$contrast;
                $row->cells[] = $this->build_data_cell($usersummary->numallsessions, $contrast);
                $text = $usersummary->pointsallsessions;
                $row->cells[] = $this->build_data_cell($text, $contrast);
                $row->cells[] = $this->build_data_cell($usersummary->allsessionspercentage, $contrast);

                $contrast = !$contrast;
                $text = $usersummary->maxpossiblepoints;
                $row->cells[] = $this->build_data_cell($text, $contrast);
                $row->cells[] = $this->build_data_cell($usersummary->maxpossiblepercentage, $contrast);
            }

            $rows[] = $row;
        }

        $rows[] = new html_table_row($summarycells);

        return $rows;
    }

    /**
     * Build and return the rows that will make up the presence report.
     * This consists of details for each selected session, as well as header and summary cells for these columns.
     *
     * @param presence_report_data $reportdata the report data
     * @param boolean $startwithcontrast true if the first column must start with contrast (bgcolor)
     * @return array Array of html_table_row objects
     */
    protected function get_session_rows(presence_report_data $reportdata, $startwithcontrast=false) {

        $rows = array();

        $row = new html_table_row();

        $showsessiondetails = $reportdata->pageparams->showsessiondetails;
        $text = get_string('sessions', 'presence');
        $params = $reportdata->pageparams->get_significant_params();
        if (count($reportdata->sessions) > 1) {
            if ($showsessiondetails) {
                $params['showsessiondetails'] = 0;
                $url = $reportdata->presence->url_report($params);
                $text .= $this->output->action_icon($url, new pix_icon('t/switch_minus',
                            get_string('hidensessiondetails', 'presence')), null, null);
                $colspan = count($reportdata->sessions);
            } else {
                $params['showsessiondetails'] = 1;
                $url = $reportdata->presence->url_report($params);
                $text .= $this->output->action_icon($url, new pix_icon('t/switch_plus',
                            get_string('showsessiondetails', 'presence')), null, null);
                $colspan = 1;
            }
        } else {
            $colspan = 1;
        }

        $params = $reportdata->pageparams->get_significant_params();
        if ($reportdata->pageparams->sessiondetailspos == 'left') {
            $params['sessiondetailspos'] = 'right';
            $url = $reportdata->presence->url_report($params);
            $text .= $this->output->action_icon($url, new pix_icon('t/right', get_string('moveright', 'presence')),
                null, null);
        } else {
            $params['sessiondetailspos'] = 'left';
            $url = $reportdata->presence->url_report($params);
            $text = $this->output->action_icon($url, new pix_icon('t/left', get_string('moveleft', 'presence')),
                    null, null) . $text;
        }

        $row->cells[] = $this->build_header_cell($text, '', true, $colspan);
        $rows[] = $row;

        $row = new html_table_row();
        if ($showsessiondetails && !empty($reportdata->sessions)) {
            foreach ($reportdata->sessions as $sess) {
                $sesstext = userdate($sess->sessdate, get_string('strftimedm', 'presence'));
                $sesstext .= html_writer::empty_tag('br');
                $sesstext .= presence_strftimehm($sess->sessdate);
                $capabilities = array(
                    'mod/presence:takepresences',
                    'mod/presence:changepresences'
                );
                if (is_null($sess->lasttaken) and has_any_capability($capabilities, $reportdata->presence->context)) {
                    $sesstext = html_writer::link($reportdata->url_take($sess->id, $sess->groupid), $sesstext,
                        array('class' => 'presencereporttakelink'));
                }
                $sesstext .= html_writer::empty_tag('br', array('class' => 'presencereportseparator'));
                if (!empty($sess->description) &&
                    !empty(get_config('presence', 'showsessiondescriptiononreport'))) {
                    $sesstext .= html_writer::tag('small', format_text($sess->description),
                        array('class' => 'presencereportcommon'));
                }
                if ($sess->groupid) {
                    if (empty($reportdata->groups[$sess->groupid])) {
                        $sesstext .= html_writer::tag('small', get_string('deletedgroup', 'presence'),
                            array('class' => 'presencereportgroup'));
                    } else {
                        $sesstext .= html_writer::tag('small', $reportdata->groups[$sess->groupid]->name,
                            array('class' => 'presencereportgroup'));
                    }

                } else {
                    $sesstext .= html_writer::tag('small', get_string('commonsession', 'presence'),
                        array('class' => 'presencereportcommon'));
                }

                $row->cells[] = $this->build_header_cell($sesstext, false, true, null, null, false);
            }
        } else {
            $row->cells[] = $this->build_header_cell('');
        }
        $rows[] = $row;

        foreach ($reportdata->users as $user) {
            $row = new html_table_row();
            if ($showsessiondetails && !empty($reportdata->sessions)) {
                $cellsgenerator = new user_sessions_cells_html_generator($reportdata, $user);
                foreach ($cellsgenerator->get_cells(true) as $cell) {
                    if ($cell instanceof html_table_cell) {
                        $cell->presenceributes['class'] .= ' center';
                        $row->cells[] = $cell;
                    } else {
                        $row->cells[] = $this->build_data_cell($cell);
                    }
                }
            } else {
                $row->cells[] = $this->build_data_cell('');
            }
            $rows[] = $row;
        }

        $row = new html_table_row();
        if ($showsessiondetails && !empty($reportdata->sessions)) {
            foreach ($reportdata->sessions as $sess) {
                $sessionstats = array();
                foreach ($reportdata->statuses as $status) {
                    if ($status->setnumber == $sess->statusset) {
                        $status->count = 0;
                        $sessionstats[$status->id] = $status;
                    }
                }

                foreach ($reportdata->users as $user) {
                    if (!empty($reportdata->sessionslog[$user->id][$sess->id])) {
                        $statusid = $reportdata->sessionslog[$user->id][$sess->id]->statusid;
                        if (isset($sessionstats[$statusid]->count)) {
                            $sessionstats[$statusid]->count++;
                        }
                    }
                }

                $statsoutput = '';
                foreach ($sessionstats as $status) {
                    $statsoutput .= "$status->description: {$status->count}<br/>";
                }
                $row->cells[] = $this->build_data_cell($statsoutput);
            }
        } else {
            $row->cells[] = $this->build_header_cell('');
        }
        $rows[] = $row;

        return $rows;
    }

    /**
     * Build and return a html_table_cell for header rows
     *
     * @param html_table_cell|string $cell the cell or a label for a cell
     * @param boolean $contrast true menans the cell must be shown with bgcolor contrast
     * @param boolean $center true means the cell text should be centered. Othersiwe it should be left-aligned.
     * @param int $colspan how many columns should cell spans
     * @param int $rowspan how many rows should cell spans
     * @param boolean $nowrap true means the cell text must be shown with nowrap option
     * @return html_table_cell a html table cell
     */
    protected function build_header_cell($cell, $contrast=false, $center=true, $colspan=null, $rowspan=null, $nowrap=true) {
        $classes = array('header', 'bottom');
        if ($center) {
            $classes[] = 'center';
            $classes[] = 'narrow';
        } else {
            $classes[] = 'left';
        }
        if ($contrast) {
            $classes[] = 'contrast';
        }
        if ($nowrap) {
            $classes[] = 'nowrap';
        }
        return $this->build_cell($cell, $classes, $colspan, $rowspan, true);
    }

    /**
     * Build and return a html_table_cell for data rows
     *
     * @param html_table_cell|string $cell the cell or a label for a cell
     * @param boolean $contrast true menans the cell must be shown with bgcolor contrast
     * @param boolean $center true means the cell text should be centered. Othersiwe it should be left-aligned.
     * @param int $colspan how many columns should cell spans
     * @param int $rowspan how many rows should cell spans
     * @param boolean $nowrap true means the cell text must be shown with nowrap option
     * @return html_table_cell a html table cell
     */
    protected function build_data_cell($cell, $contrast=false, $center=true, $colspan=null, $rowspan=null, $nowrap=true) {
        $classes = array();
        if ($center) {
            $classes[] = 'center';
            $classes[] = 'narrow';
        } else {
            $classes[] = 'left';
        }
        if ($nowrap) {
            $classes[] = 'nowrap';
        }
        if ($contrast) {
            $classes[] = 'contrast';
        }
        return $this->build_cell($cell, $classes, $colspan, $rowspan, false);
    }

    /**
     * Build and return a html_table_cell for header or data rows
     *
     * @param html_table_cell|string $cell the cell or a label for a cell
     * @param Array $classes a list of css classes
     * @param int $colspan how many columns should cell spans
     * @param int $rowspan how many rows should cell spans
     * @param boolean $header true if this should be a header cell
     * @return html_table_cell a html table cell
     */
    protected function build_cell($cell, $classes, $colspan=null, $rowspan=null, $header=false) {
        if (!($cell instanceof html_table_cell)) {
            $cell = new html_table_cell($cell);
        }
        $cell->header = $header;
        $cell->scope = 'col';

        if (!empty($colspan) && $colspan > 1) {
            $cell->colspan = $colspan;
        }

        if (!empty($rowspan) && $rowspan > 1) {
            $cell->rowspan = $rowspan;
        }

        if (!empty($classes)) {
            $classes = implode(' ', $classes);
            if (empty($cell->attributes['class'])) {
                $cell->attributes['class'] = $classes;
            } else {
                $cell->attributes['class'] .= ' ' . $classes;
            }
        }

        return $cell;
    }

    /**
     * Output the status set selector.
     *
     * @param presence_set_selector $sel
     * @return string
     */
    protected function render_presence_set_selector(presence_set_selector $sel) {
        $current = $sel->get_current_statusset();
        $selected = null;
        $opts = array();
        for ($i = 0; $i <= $sel->maxstatusset; $i++) {
            $url = $sel->url($i);
            $display = $sel->get_status_name($i);
            $opts[$url->out(false)] = $display;
            if ($i == $current) {
                $selected = $url->out(false);
            }
        }
        $newurl = $sel->url($sel->maxstatusset + 1);
        $opts[$newurl->out(false)] = get_string('newstatusset', 'mod_presence');
        if ($current == $sel->maxstatusset + 1) {
            $selected = $newurl->out(false);
        }

        return $this->output->url_select($opts, $selected, null);
    }

    /**
     * Render preferences data.
     *
     * @param stdClass $prefdata
     * @return string
     */
    protected function render_presence_preferences_data($prefdata) {
        $this->page->requires->js('/mod/presence/module.js');

        $table = new html_table();
        $table->width = '100%';
        $table->head = array('#',
                             get_string('acronym', 'presence'),
                             get_string('description'),
                             get_string('points', 'presence'));
        $table->align = array('center', 'center', 'center', 'center', 'center', 'center');

        $table->head[] = get_string('studentavailability', 'presence').
            $this->output->help_icon('studentavailability', 'presence');
        $table->align[] = 'center';

        $table->head[] = get_string('setunmarked', 'presence').
            $this->output->help_icon('setunmarked', 'presence');
        $table->align[] = 'center';

        $table->head[] = get_string('action');

        $i = 1;
        foreach ($prefdata->statuses as $st) {
            $emptyacronym = '';
            $emptydescription = '';
            if (isset($prefdata->errors[$st->id]) && !empty(($prefdata->errors[$st->id]))) {
                if (empty($prefdata->errors[$st->id]['acronym'])) {
                    $emptyacronym = $this->construct_notice(get_string('emptyacronym', 'mod_presence'), 'notifyproblem');
                }
                if (empty($prefdata->errors[$st->id]['description'])) {
                    $emptydescription = $this->construct_notice(get_string('emptydescription', 'mod_presence') , 'notifyproblem');
                }
            }
            $cells = array();
            $cells[] = $i;
            $cells[] = $this->construct_text_input('acronym['.$st->id.']', 2, 2, $st->acronym) . $emptyacronym;
            $cells[] = $this->construct_text_input('description['.$st->id.']', 30, 30, $st->description) .
                                 $emptydescription;
            $cells[] = $this->construct_text_input('grade['.$st->id.']', 4, 4, $st->grade);
            $checked = '';
            if ($st->setunmarked) {
                $checked = ' checked ';
            }
            $cells[] = $this->construct_text_input('studentavailability['.$st->id.']', 4, 5, $st->studentavailability);
            $cells[] = '<input type="radio" name="setunmarked" value="'.$st->id.'"'.$checked.'>';

            $cells[] = $this->construct_preferences_actions_icons($st, $prefdata);

            $table->data[$i] = new html_table_row($cells);
            $table->data[$i]->id = "statusrow".$i;
            $i++;
        }

        $table->data[$i][] = '*';
        $table->data[$i][] = $this->construct_text_input('newacronym', 2, 2);
        $table->data[$i][] = $this->construct_text_input('newdescription', 30, 30);
        $table->data[$i][] = $this->construct_text_input('newgrade', 4, 4);
        $table->data[$i][] = $this->construct_text_input('newstudentavailability', 4, 5);

        $table->data[$i][] = $this->construct_preferences_button(get_string('add', 'presence'),
            mod_presence_preferences_page_params::ACTION_ADD);

        $o = html_writer::table($table);
        $o .= html_writer::input_hidden_params($prefdata->url(array(), false));
        // We should probably rewrite this to use mforms but for now add sesskey.
        $o .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()))."\n";

        $o .= $this->construct_preferences_button(get_string('update', 'presence'),
                                                  mod_presence_preferences_page_params::ACTION_SAVE);
        $o = html_writer::tag('form', $o, array('id' => 'preferencesform', 'method' => 'post',
                                                'action' => $prefdata->url(array(), false)->out_omit_querystring()));
        $o = $this->output->container($o, 'generalbox presencewidth');

        return $o;
    }

    /**
     * Render default statusset.
     *
     * @param presence_default_statusset $prefdata
     * @return string
     */
    protected function render_presence_default_statusset(presence_default_statusset $prefdata) {
        return $this->render_presence_preferences_data($prefdata);
    }

    /**
     * Render preferences data.
     *
     * @param stdClass $prefdata
     * @return string
     */
    protected function render_presence_pref($prefdata) {

    }

    /**
     * Construct text input.
     *
     * @param string $name
     * @param integer $size
     * @param integer $maxlength
     * @param string $value
     * @return string
     */
    private function construct_text_input($name, $size, $maxlength, $value='') {
        $attributes = array(
                'type'      => 'text',
                'name'      => $name,
                'size'      => $size,
                'maxlength' => $maxlength,
                'value'     => $value,
                'class' => 'form-control');
        return html_writer::empty_tag('input', $attributes);
    }

    /**
     * Construct action icons.
     *
     * @param stdClass $st
     * @param stdClass $prefdata
     * @return string
     */
    private function construct_preferences_actions_icons($st, $prefdata) {
        $params = array('sesskey' => sesskey(),
                        'statusid' => $st->id);
        if ($st->visible) {
            $params['action'] = mod_presence_preferences_page_params::ACTION_HIDE;
            $showhideicon = $this->output->action_icon(
                    $prefdata->url($params),
                    new pix_icon("t/hide", get_string('hide')));
        } else {
            $params['action'] = mod_presence_preferences_page_params::ACTION_SHOW;
            $showhideicon = $this->output->action_icon(
                    $prefdata->url($params),
                    new pix_icon("t/show", get_string('show')));
        }
        if (empty($st->haslogs)) {
            $params['action'] = mod_presence_preferences_page_params::ACTION_DELETE;
            $deleteicon = $this->output->action_icon(
                    $prefdata->url($params),
                    new pix_icon("t/delete", get_string('delete')));
        } else {
            $deleteicon = '';
        }

        return $showhideicon . $deleteicon;
    }

    /**
     * Construct preferences button.
     *
     * @param string $text
     * @param string $action
     * @return string
     */
    private function construct_preferences_button($text, $action) {
        $attributes = array(
                'type'      => 'submit',
                'value'     => $text,
                'class'     => 'btn btn-secondary',
                'onclick'   => 'M.mod_presence.set_preferences_action('.$action.')');
        return html_writer::empty_tag('input', $attributes);
    }

    /**
     * Construct a notice message
     *
     * @param string $text
     * @param string $class
     * @return string
     */
    private function construct_notice($text, $class = 'notifymessage') {
        $attributes = array('class' => $class);
        return html_writer::tag('p', $text, $attributes);
    }

    /**
     * Show different picture if it is a temporary user.
     *
     * @param stdClass $user
     * @param array $opts
     * @return string
     */
    protected function user_picture($user, array $opts = null) {
        if ($user->type == 'temporary') {
            $attrib = array(
                'width' => '35',
                'height' => '35',
                'class' => 'userpicture defaultuserpic',
            );
            if (isset($opts['size'])) {
                $attrib['width'] = $attrib['height'] = $opts['size'];
            }
            return $this->output->pix_icon('ghost', '', 'mod_presence', $attrib);
        }

        return $this->output->user_picture($user, $opts);
    }
}
