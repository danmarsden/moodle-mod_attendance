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
 * Class definition for mod_attendance_manage_page_params
 *
 * @package   mod_attendance
 * @copyright  2016 Dan Marsden http://danmarsden.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * stores constants/data passed depending on view.
 *
 * @copyright  2016 Dan Marsden http://danmarsden.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance\import;

use csv_import_reader;
use mod_attendance_notifyqueue;
use mod_attendance_structure;
use stdClass;

/**
 * Bulk import attendance sessions across multiple sessions.
 *
 * @package   mod_attendance
 * @copyright 2025 Mohammad Nabil <mohammad@smartlearn.education>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulkmarksessions {

    /** @var string $error Error message from parsing */
    protected $error = '';

    /** @var array $sessions Grouped sessions data: sessionid => [userid => log object] */
    protected $sessions = [];

    /** @var int The id of the csv import */
    protected $importid = 0;

    /** @var csv_import_reader|null $importer */
    protected $importer = null;

    /** @var array $foundheaders */
    protected $foundheaders = [];

    /** @var bool $useprogressbar Control whether importing should use progress bars or not */
    protected $useprogressbar = false;

    /** @var \core\progress\display_if_slow|null $progress The progress bar instance */
    protected $progress = null;

    /** @var mod_attendance_structure $att The mod_attendance_structure instance */
    private $att;

    /**
     * Store an error message.
     *
     * @param string $msg
     * @return bool
     */
    public function fail($msg) {
        $this->error = $msg;
        return false;
    }

    /**
     * Get the CSV import id.
     *
     * @return int The import id.
     */
    public function get_importid() {
        return $this->importid;
    }

    /**
     * Get the list of headers found in the import.
     *
     * @return array
     */
    public function list_found_headers() {
        return $this->foundheaders;
    }

    /**
     * Read mapping data from confirmation form.
     *
     * @param stdClass $data
     * @return array
     */
    protected function read_mapping_data($data) {
        if ($data) {
            return [
                'sessionfrom' => $data->sessionfrom,
                'sessionto'   => $data->sessionto,
                'user'        => $data->userfrom,
                'userto'      => $data->userto,
                'scantime'    => $data->scantime,
                'status'      => $data->status,
                'remarks'     => isset($data->remarks) ? $data->remarks : -1,
            ];
        }
        return [
            'sessionfrom' => 0,
            'sessionto'   => 'sessiondate',
            'user'        => 1,
            'userto'      => 'email',
            'scantime'    => -1,
            'status'      => 2,
            'remarks'     => -1,
        ];
    }

    /**
     * Get column value from row.
     *
     * @param array $row
     * @param int $index
     * @return string
     */
    protected function get_column_data($row, $index) {
        if ($index < 0) {
            return '';
        }
        return isset($row[$index]) ? trim($row[$index]) : '';
    }

    /**
     * Constructor - parses the raw CSV text.
     *
     * @param mod_attendance_structure $att
     * @param string|null $text
     * @param string|null $encoding
     * @param string|null $delimiter
     * @param int $importid
     * @param stdClass|null $mappingdata
     * @param bool $useprogressbar
     */
    public function __construct($att, $text = null, $encoding = null, $delimiter = null, $importid = 0,
                                $mappingdata = null, $useprogressbar = false) {
        global $CFG, $DB, $USER;

        require_once($CFG->libdir . '/csvlib.class.php');

        $type = 'bulkmarksessions';
        $this->att = $att;

        if (!$importid) {
            if ($text === null) {
                return;
            }
            $this->importid = csv_import_reader::get_new_iid($type);
            $this->importer = new csv_import_reader($this->importid, $type);

            if (!$this->importer->load_csv_content($text, $encoding, $delimiter)) {
                $this->fail(get_string('invalidimportfile', 'attendance'));
                $this->importer->cleanup();
                return;
            }
        } else {
            $this->importid = $importid;
            $this->importer = new csv_import_reader($this->importid, $type);
        }

        if (!$this->importer->init()) {
            $this->fail(get_string('invalidimportfile', 'attendance'));
            $this->importer->cleanup();
            return;
        }

        $this->foundheaders = $this->importer->get_columns();
        $this->useprogressbar = $useprogressbar;

        if (empty($mappingdata)) {
            // Mapping not submitted yet, waiting for user confirmation step.
            return;
        }

        $mapping = $this->read_mapping_data($mappingdata);

        // Load all sessions for this attendance activity.
        $dbsessions = $DB->get_records('attendance_sessions', ['attendanceid' => $this->att->id], 'sessdate ASC');
        $sessionsbyid = [];
        $sessionsbydate = [];

        foreach ($dbsessions as $s) {
            $sessionsbyid[$s->id] = $s;

            // Index by Y-m-d, Y-m-d H:i, and d/m/Y formats.
            $daykey = date('Y-m-d', $s->sessdate);
            $datetimekey = date('Y-m-d H:i', $s->sessdate);
            $altdaykey = date('d/m/Y', $s->sessdate);

            if (!isset($sessionsbydate[$daykey])) {
                $sessionsbydate[$daykey] = $s;
            }
            if (!isset($sessionsbydate[$datetimekey])) {
                $sessionsbydate[$datetimekey] = $s;
            }
            if (!isset($sessionsbydate[$altdaykey])) {
                $sessionsbydate[$altdaykey] = $s;
            }
        }

        // Load all course users.
        $validusers = $this->att->get_users(0, 0);
        $users = [];
        if ($mapping['userto'] !== 'id') {
            foreach ($validusers as $u) {
                if (!empty($u->{$mapping['userto']})) {
                    $users[strtolower(trim($u->{$mapping['userto']}))] = $u;
                }
            }
        } else {
            $users = $validusers;
        }

        // Load status sets for each session.
        $statussets = [];

        $sesslog = [];
        $rownum = 1;

        while ($row = $this->importer->next()) {
            $rownum++;

            // 1. Resolve Session.
            $extsession = $this->get_column_data($row, $mapping['sessionfrom']);
            if ($extsession === '') {
                continue;
            }

            $matchedsession = null;
            if ($mapping['sessionto'] === 'sessionid') {
                if (isset($sessionsbyid[$extsession])) {
                    $matchedsession = $sessionsbyid[$extsession];
                }
            } else {
                // Match by session date/datetime.
                if (isset($sessionsbydate[$extsession])) {
                    $matchedsession = $sessionsbydate[$extsession];
                } else {
                    $ts = strtotime($extsession);
                    if ($ts !== false) {
                        $parsedday = date('Y-m-d', $ts);
                        $parseddatetime = date('Y-m-d H:i', $ts);
                        if (isset($sessionsbydate[$parseddatetime])) {
                            $matchedsession = $sessionsbydate[$parseddatetime];
                        } else if (isset($sessionsbydate[$parsedday])) {
                            $matchedsession = $sessionsbydate[$parsedday];
                        }
                    }
                }
            }

            if (!$matchedsession) {
                mod_attendance_notifyqueue::notify_problem(
                    get_string('error:sessionnotfound', 'attendance', $extsession)
                );
                continue;
            }

            $sessionid = $matchedsession->id;

            // 2. Resolve User.
            $extuser = strtolower($this->get_column_data($row, $mapping['user']));
            if (empty($users[$extuser])) {
                $a = new stdClass();
                $a->extuser = $extuser;
                $a->userfield = $mapping['userto'];
                mod_attendance_notifyqueue::notify_problem(
                    get_string('error:usernotfound', 'attendance', $a)
                );
                continue;
            }

            $userid = $users[$extuser]->id;

            if (isset($sesslog[$sessionid][$userid])) {
                $a = new stdClass();
                $a->extuser = $extuser;
                $a->session = $extsession;
                mod_attendance_notifyqueue::notify_problem(
                    get_string('error:sessionuserduplicate', 'attendance', $a)
                );
                continue;
            }

            // 3. Resolve Status set.
            $statussetid = $matchedsession->statusset;
            if (!isset($statussets[$statussetid])) {
                $statuses = $this->att->get_statuses($statussetid);
                $map = [];
                foreach ($statuses as $st) {
                    $map[strtoupper(trim($st->acronym))] = $st->id;
                }
                $statussets[$statussetid] = $map;
            }
            $statusmap = $statussets[$statussetid];

            $log = new stdClass();
            $log->studentid = $userid;
            $log->statusset = implode(",", array_values($statusmap));
            $log->sessionid = $sessionid;
            $log->timetaken = time();
            $log->takenby = $USER->id;

            // Remarks.
            $log->remarks = '';
            if ($mapping['remarks'] >= 0) {
                $log->remarks = $this->get_column_data($row, $mapping['remarks']);
            }

            // Status or Scantime.
            $scantime = $this->get_column_data($row, $mapping['scantime']);
            if (!empty($scantime)) {
                $t = strtotime($scantime);
                if ($t === false) {
                    $a = new stdClass();
                    $a->extuser = $extuser;
                    $a->scantime = $scantime;
                    mod_attendance_notifyqueue::notify_problem(
                        get_string('error:timenotreadable', 'attendance', $a)
                    );
                    continue;
                }
                $log->statusid = attendance_session_get_highest_status($this->att, $matchedsession, $t);
            } else {
                $status = strtoupper($this->get_column_data($row, $mapping['status']));
                if (!empty($statusmap[$status])) {
                    $log->statusid = $statusmap[$status];
                } else {
                    $a = new stdClass();
                    $a->extuser = $extuser;
                    $a->status = $status;
                    mod_attendance_notifyqueue::notify_problem(
                        get_string('error:statusnotfound', 'attendance', $a)
                    );
                    continue;
                }
            }

            if (!isset($sesslog[$sessionid])) {
                $sesslog[$sessionid] = [];
            }
            $sesslog[$sessionid][$userid] = $log;
        }

        $this->sessions = $sesslog;
        $this->importer->close();

        if (empty($sesslog)) {
            $this->fail(get_string('invalidimportfile', 'attendance'));
        }
    }

    /**
     * Get parse error.
     *
     * @return string
     */
    public function get_error() {
        return $this->error;
    }

    /**
     * Save attendance logs across all parsed sessions.
     *
     * @return array Summary of import with session and record counts.
     */
    public function import() {
        global $DB, $USER;

        $totalrecords = 0;
        $alluserids = [];

        foreach ($this->sessions as $sessionid => $userlogs) {
            $dbsesslog = $this->att->get_session_log($sessionid);

            foreach ($userlogs as $log) {
                if (!empty($log->statusid) || !empty($log->remarks)) {
                    if (array_key_exists($log->studentid, $dbsesslog)) {
                        if ($dbsesslog[$log->studentid]->remarks <> $log->remarks ||
                            $dbsesslog[$log->studentid]->statusid <> $log->statusid ||
                            $dbsesslog[$log->studentid]->statusset <> $log->statusset) {

                            $log->id = $dbsesslog[$log->studentid]->id;
                            $DB->update_record('attendance_log', $log);
                        }
                    } else {
                        $DB->insert_record('attendance_log', $log, false);
                    }
                    $totalrecords++;
                    $alluserids[$log->studentid] = $log->studentid;
                }
            }

            // Update session last taken metadata.
            $session = $this->att->get_session_info($sessionid);
            $session->lasttaken = time();
            $session->lasttakenby = $USER->id;
            $DB->update_record('attendance_sessions', $session);

            // Trigger attendance_taken event.
            $params = [
                'sessionid' => $sessionid,
                'grouptype' => $session->groupid,
            ];
            $event = \mod_attendance\event\attendance_taken::create([
                'objectid' => $this->att->id,
                'context' => $this->att->context,
                'other' => $params,
            ]);
            $event->add_record_snapshot('course_modules', $this->att->cm);
            $event->add_record_snapshot('attendance_sessions', $session);
            $event->trigger();
        }

        // Update grades for all affected students.
        if ($this->att->grade != 0 && !empty($alluserids)) {
            $this->att->update_users_grade(array_values($alluserids));
        }

        $a = new stdClass();
        $a->records = $totalrecords;
        $a->sessions = count($this->sessions);
        mod_attendance_notifyqueue::notify_success(
            get_string('bulkimportsuccess', 'attendance', $a)
        );

        return [
            'sessions' => count($this->sessions),
            'records'  => $totalrecords,
        ];
    }
}
