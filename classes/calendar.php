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
 * Class definition for mod_presence\calendar
 *
 * @package    mod_presence
 * @author     Florian Metzger-Noel (github.com/flocko-motion)
 * @copyright  2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_presence;

defined('MOODLE_INTERNAL') || die();

use mod_presence_structure;


class calendar
{
    private $presence;

    public $rooms;

    public function  __construct(mod_presence_structure $presence = null) {
        global $DB;

        $this->presence = $presence;

        $this->rooms = [];
        $this->rooms[0] = (object)[
            'id' => 0,
            'name' => get_string('noroom', 'presence'),
            'description' => '',
            'maxattendants' => 0,
            'bookable' => 1,
        ];
        $rows = $DB->get_records('presence_rooms', null, 'id ASC');
        foreach ($rows as $row) {
            $this->rooms[$row->id] = $row;
        }
    }

    public function get_rooms() {
        return $this->rooms;
    }

    /**
     * Calc dates of a repeated event
     * @param int $from unix timestamp <= first event
     * @param int $to unix timestamp >= last event
     * @param array $days array mo-su of 1/0 if that weekday has event
     * @param int $period number of weeks to next event
     * @return array list of unix timestamps of events
     */
    public function get_series_dates(int $from, int $to, array $days, int $period) : array {
        $dates = [];
        $periodcount = 0;
        for ($t = $from; $t < $to; $t += 3600 * 24) {
            $weekday = date('N', $t) - 1;
            if ($days[$weekday]) {
                $debug = date('D Y-m-d H:i:s', $t);
                $dates[] = $t;
            }
            $periodcount++;
            if ($periodcount == 7) {
                $periodcount = 0;
                $t += 3600 * 24 * 7 * max(0, $period - 1);
            }
        }
        return $dates;
    }

    public function get_room_planner_schedule() {
        global $DB;
        $schedule = $DB->get_records_sql('
            SELECT ps.id, CAST(TO_TIMESTAMP(ps.sessdate) as DATE) as date, ps.sessdate, ps.duration, ps.description, ps.roomid, c.fullname, c.shortname
            FROM {presence_sessions} ps
            JOIN {presence} p ON ps.presenceid = p.id
            JOIN {course} c ON p.course = c.id
            WHERE CAST(TO_TIMESTAMP(ps.sessdate) as DATE) >= CURRENT_DATE
            ORDER BY
                CAST(TO_TIMESTAMP(ps.sessdate) as DATE),
                ps.roomid ASC, 
                ps.sessdate ASC
        ');

        $roomplan = [];
        $prevroom = null;
        $prevsession = null;
        foreach ($schedule as $session) {
            if (!isset($roomplan[$session->date])) {
                $rooms = [];
                foreach ($this->rooms as $room) {
                    $roomcopy = clone $room;
                    $roomcopy->schedule = [];
                    $rooms[$room->id] = $roomcopy;
                }
                $roomplan[$session->date] = (object)[
                    'date' => $session->dateformat = userdate(strtotime($session->date), get_string('strftimedatefullshort', 'langconfig')),
                    'rooms' => $rooms,
                ];
            }
            $session->room = $this->rooms[$session->roomid];
            $session->from = userdate($session->sessdate, get_string('strftimetime', 'langconfig'));
            $session->to = userdate($session->sessdate + $session->duration, get_string('strftimetime', 'langconfig'));
            $session->coursename = $session->shortname ? $session->shortname : $session->fullname;
            if ($prevsession && $prevsession->roomid == $session->roomid) {
                $collisionduration = $prevsession->sessdate + $prevsession->duration - $session->sessdate;
                if ($collisionduration > 0) {
                    $session->collision = true;
                    $prevsession->collision = true;
                    $prevsession->collisionduration = userdate($collisionduration, get_string('strftimetime', 'langconfig'));
                }
            }
            $roomplan[$session->date]->rooms[$session->roomid]->schedule[] = $session;
            $prevsession = $session;
        }
        return $roomplan;
    }
}