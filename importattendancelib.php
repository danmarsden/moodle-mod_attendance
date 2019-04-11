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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package   mod_attendance
 * @copyright 2019 Jonathan Chan <jonathan.chan@sta.uwi.edu>
 * @copyright based on work by 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

/**
 * Class for the CSV file importer.
 *
 * @package   mod_attendance
 * @copyright 2019 Jonathan Chan <jonathan.chan@sta.uwi.edu>
 * @copyright based on work by 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later */

class attendance_importer {
    /** @var string $importid - unique id for this import operation - must be passed between requests */
    public $importid;

    /** @var csv_import_reader $csvreader - the csv importer class */
    private $csvreader;

    /** @var mod_attendance_structure $att - the mod_attendance_structure class */
    private $att;

    /** @var int $idnumindex the column index containing the student's id number */
    private $idnumindex = 0;

    /** @var int $encodingindex the column index containing the student's id number */
    private $encodingindex = 1;

    /** @var int $gradeindex the column index containing the grades */
    private $scantimeindex = 2;

    /** @var int $modifiedindex the column index containing the last modified time */
    private $scandateindex = 3;

    /** @var boolean $idnumcolempty checks if the student id column is empty. */
    public $idnumcolempty = false;

    /** @var boolean $encodingcolempty checks if the encoding column is empty. */
    public $encodingcolempty = false;

    /** @var boolean $scantimecolempty checks if the scan time column is empty. */
    public $scantimecolempty = false;

    /** @var boolean $scandatecolempty checks if the scan date column is empty. */
    public $scandatecolempty = false;

    /** @var boolean $scantimeformaterr checks if the scantime column is formatted properly. */
    public $scantimeformaterr = false;

    /** @var boolean $scandateformaterr checks if the scandate column is formatted properly. */
    public $scandateformaterr = false;

    /** @var boolean $incompatsessdate checks scandate column for records with scan dates that do not match the session date. */
    public $incompatsessdate = false;

    /** @var boolean $incompatsesstime checks scantime column for records with scan times that are not within the session time. */
    public $incompatsesstime = false;

    /** @var boolean $multipledays checks scandate column for multiple days of records. */
    public $multipledays = false;

    /** @var boolean $scantimeerr triggers if the file is suspected of being affected by DST or the wrong file was uploaded. */
    public $scantimeerr = false;

    /** @var array $validusers only the enrolled users with the correct capability in this course */
    private $validusers;

    /** @var string $encoding Encoding to use when reading the csv file. Defaults to utf-8. */
    private $encoding;

    /** @var string $separator How each bit of information is separated in the file. Defaults to comma separated. */
    private $separator;

    /**
     * Constructor
     *
     * @param string $importid A unique id for this import
     * @param assign $assignment The current assignment
     */
    public function __construct($importid, mod_attendance_structure $att, $encoding = 'utf-8', $separator = 'comma') {
        $this->importid = $importid;
        $this->att = $att;
        $this->encoding = $encoding;
        $this->separator = $separator;
    }

    /**
     * Parse a csv file and save the content to a temp file.
     * Should be called before init().
     *
     * @param string $csvdata The csv data
     * @return bool false is a failed import
     */
    public function parsecsv($csvdata) {
        $this->csvreader = new csv_import_reader($this->importid, 'attendance');
        $this->csvreader->load_csv_content($csvdata, $this->encoding, $this->separator);
    }

    /**
     * Initialise the import reader and locate the column indexes.
     *
     * @return bool false is a failed import
     */
    public function init() {
        GLOBAL $CFG, $USER;

        if ($this->csvreader == null) {
            $this->csvreader = new csv_import_reader($this->importid, 'attendance');
        }
        $this->csvreader->init();

        $sessioninfo = $this->att->get_session_info($this->att->pageparams->sessionid);

        $filename = $CFG->tempdir.'/csvimport/'.'attendance'.'/'.$USER->id.'/'.$this->importid;
        $tempfilename = $CFG->tempdir.'/csvimport/'.'attendance'.'/'.$USER->id.'/'.'temp.csv';
        $header = ['ID Number', 'Symbology', 'Scan Time', 'Scan Date', 'Current Time', 'Current Date', 'Serial Number'];
        $mainfile = fopen($filename, 'r+');
        $tempfile = fopen($tempfilename, 'w+');

        // Need to insert a header row so that the csv reader will not skip the first row of attendance data.
        while (!feof($mainfile)) {
            // Copy the contents of the main file to a temp file.
            $line = fgetcsv($mainfile);
            if ($line != false) {
                fputcsv($tempfile, $line);
            }
        }

        rewind($mainfile); // Resetting the file pointer of both files.
        rewind($tempfile);

        fputcsv($mainfile, $header); // Placing the header at the top of the main file.

        while (!feof($tempfile)) {
            // Copy the contents of the temp file to below the headings of the main file.
            $line = fgetcsv($tempfile);
            if ($line != false) {
                fputcsv($mainfile, $line);
            }
        }

        fclose($mainfile); // Finished with boths files, therefore closing them.
        fclose($tempfile);

        $this->validusers = $this->att->get_users($this->att->pageparams->grouptype, 0);

        // Fixing bugs here with a precheck of the file before processing.
        $this->csvreader->init();

        $sessiondate = date('n/j/Y', $sessioninfo->sessdate);
        $firstrecord = $this->csvreader->next();

        $scandate = strtotime($firstrecord[$this->scandateindex]);
        $scandate = date('n/j/Y', $scandate);

        $earliest = $sessioninfo->sessdate - 1800;
        $latest = $sessioninfo->sessdate + $sessioninfo->duration;

        $pcount = 0; // Number of students marked present.
        $lcount = 0; // Number of students marked late.
        $ascount = 0; // Number of students marked as absent with scan.

        $studentcount = count($this->validusers);

        $this->restart();

        while ($record = $this->csvreader->next()) {

            // Flag an error if the studentid, encoding, scan time or scan date column is missing.
            if (empty($record[$this->idnumindex])) {
                $this->idnumcolempty = true;
            }
            if (empty($record[$this->encodingindex])) {
                $this->encodingcolempty = true;
            }
            if (empty($record[$this->scantimeindex])) {
                $this->scantimecolempty = true;
            }
            if (empty($record[$this->scandateindex])) {
                $this->scandatecolempty = true;
            }

            // Flag an error if the scan time column is not formatted as a time.
            $t = strtotime($record[$this->scantimeindex]);
            if (!($record[$this->scantimeindex] == date('g:i:s A', $t) ||
                  $record[$this->scantimeindex] == date('h:i:s A', $t))) {
                $this->scantimeformaterr = true;
            }

            // Flag an error if the scan date column is not formatted as a time.
            $d = strtotime($record[$this->scandateindex]);
            if (!($record[$this->scandateindex] !== date('n/j/Y', $d) ||
                  $record[$this->scandateindex] !== date('m/d/Y', $d))) {
                $this->scandateformaterr = true;
            }

            // Flag an error if the file contains records with scan dates that do not match the session date.
            if ($sessiondate !== date('n/j/Y', $d)) {
                $this->incompatsessdate = true;
            }

            // Flag an error if the file contains multiple days of attendance records.
            if ($scandate !== date('n/j/Y', $d)) {
                $this->multipledays = true;
            }

            // Prechecking the file for how many students would be marked as Present, Late and Absent(with scan).
            $scantime = $record[$this->scandateindex].' '.$record[$this->scantimeindex];
            $scantime = strtotime($scantime);
            $scantime = (int) $scantime;

            $idstr = $record[$this->idnumindex];
            $encoding = $record[$this->encodingindex];
            if ($encoding == 'UPC-A') {
                $idstr = substr($idstr, 1, -2);
            }
            if ($userid = $this->att->get_user_id_from_idnumber($idstr)) {
                if (!empty($this->validusers[$userid])) {
                    if ($scantime >= $earliest && $scantime <= $sessioninfo->sessdate + 900) {
                        $pcount += 1;
                    }
                    if ($scantime > $sessioninfo->sessdate + 900 && $scantime <= $latest) {
                        $lcount += 1;
                    }
                    if ($scantime < $earliest || $scantime > $latest) {
                        $ascount += 1;
                    }

                    if ($studentcount > 10 || ($lcount + $ascount) >= (0.1 * $studentcount)) {
                        if ($pcount == 0 && ($lcount + $ascount) > (0.4 * $studentcount)) {
                            $this->scantimeerr = true;
                        }
                        if ($ascount > ($pcount + $lcount)) {
                            $this->scantimeerr = true;
                        }
                    }
                }
            }
        }

        if ($this->idnumcolempty == true     || $this->encodingcolempty == true  || $this->scantimecolempty == true  ||
            $this->scandatecolempty == true  || $this->scantimeformaterr == true || $this->scandateformaterr == true ||
            $this->incompatsessdate == true  || $this->multipledays == true      || $this->scantimeerr == true) {
            return false;
        }

        $this->restart();

        return true;
    }

    /**
     * Return the encoding for this csv import.
     *
     * @return string The encoding for this csv import.
     */
    public function get_encoding() {
        return $this->encoding;
    }

    /**
     * Return the separator for this csv import.
     *
     * @return string The separator for this csv import.
     */
    public function get_separator() {
        return $this->separator;
    }

    /**
     * Get the next row of data from the csv file (only the columns we care about)
     *
     * @return stdClass or false The stdClass is an object containing user id, scan time and scan date.
     */
    public function next() {

        $result = new stdClass();

        while ($record = $this->csvreader->next()) {
            $idstr = $record[$this->idnumindex];
            $encoding = $record[$this->encodingindex];
            if ($encoding == 'UPC-A') {
                $idstr = substr($idstr, 1, -2);
            }
            if ($userid = $this->att->get_user_id_from_idnumber($idstr)) {
                $result->user = $this->validusers[$userid];
                $result->scandate = strtotime($record[$this->scandateindex]);
                $result->scantime = $record[$this->scandateindex].' '.$record[$this->scantimeindex];
                $result->scantime = strtotime($result->scantime);
                $result->scantime = (int) $result->scantime;
                return $result;
            }
        }

        // If we got here the csvreader had no more rows.
        return false;
    }

    /**
     * Restart the csv importer so that it can begin reading from the start of the csv file again.
     */
    public function restart() {
        $this->csvreader->init();
    }

    /**
     * Close the attendance importer file and optionally delete any temp files
     *
     * @param bool $delete
     */
    public function close($delete) {
        $this->csvreader->close();
        if ($delete) {
            $this->csvreader->cleanup();
        }
    }
}
