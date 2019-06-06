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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/csvlib.class.php');

/**
 * Class for the CSV file importer.
 *
 * @package   mod_attendance
 * @copyright 2019 Jonathan Chan <jonathan.chan@sta.uwi.edu>
 * @copyright based on work by 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_attendance_importer {
    /** @var string $importid - unique id for this import operation - must be passed between requests */
    public $importid;

    /** @var csv_import_reader $csvreader - the csv importer class */
    private $csvreader;

    /** @var mod_attendance_structure $att - the mod_attendance_structure class */
    private $att;

    /** @var string $mapto the database field to map the student column to */
    private $mapto;

    /** @var int $studentindex the column index containing the student's id number */
    private $studentindex;

    /** @var int $encodingindex the column index containing the student's id number */
    private $encodingindex;

    /** @var int $gradeindex the column index containing the grades */
    private $scantimeindex;

    /** @var int $modifiedindex the column index containing the last modified time */
    private $scandateindex;

    /** @var boolean $studentcolempty checks if the student id column is empty. */
    public $studentcolempty = false;

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

    /** @var stdClass $highestgradedstatus stores the highest graded status available to assign students. */
    public $highestgradedstatus;

    /** @var stdClass $lowestgradedstatus stores the lowest graded status available to assign students. */
    public $lowestgradedstatus;

    /** @var array $validusers only the enrolled users with the correct capability in this course */
    private $validusers;

    /** @var string $encoding Encoding to use when reading the csv file. Defaults to utf-8. */
    private $encoding;

    /** @var string $separator How each bit of information is separated in the file. Defaults to comma separated. */
    private $separator;

    /** @var boolean $noheader triggers if no header row is detected in the uploaded csv file. */
    private $noheader = false;

    /** @var array $headers Column names for the data. */
    protected $headers;

    /** @var array $previewdata A subsection of the csv imported data. */
    protected $previewdata;

    /**
     * Constructor
     *
     * @param string $importid A unique id for this import
     * @param mod_attendance_structure $att The current assignment
     * @param string $encoding contains the encoding format of the csv file
     * @param string $separator identifies the type of separator used in the csv file.
     */
    public function __construct($importid, $att, $encoding = 'utf-8', $separator = 'comma') {
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
     */
    public function parsecsv($csvdata) {
        $this->csvreader = new csv_import_reader($this->importid, 'attendance');
        $this->csvreader->load_csv_content($csvdata, $this->encoding, $this->separator);
    }

    /**
     * Preview the first few rows of the csv file.
     *
     * @param int $previewrows The number of rows the user wants to preview.
     */
    public function preview($previewrows) {
        GLOBAL $CFG, $USER;

        if ($this->csvreader == null) {
            $this->csvreader = new csv_import_reader($this->importid, 'attendance');
        }
        $this->csvreader->init();

        // Checking to see if the entire first row is a header row.
        $this->headers = $this->csvreader->get_columns();
        foreach ($this->headers as $value) {
            if (is_numeric($value)) {
                $this->noheader = true;
            }
        }
        // If a header row doesn't already exist, insert one.
        if ($this->noheader == true) {
            $filename = $CFG->tempdir.'/csvimport/'.'attendance'.'/'.$USER->id.'/'.$this->importid;
            $tempfilename = $CFG->tempdir.'/csvimport/'.'attendance'.'/'.$USER->id.'/'.'temp.csv';
            $header = ['Column 1', 'Column 2', 'Column 3', 'Column 4', 'Column 5', 'Column 6', 'Column 7'];
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
        }
        // Get the column headers.
        $this->headers = $this->get_headers($this->importid);

        // Get the preview data.
        $this->csvreader->init();
        $this->previewdata = array();

        for ($numoflines = 0; $numoflines <= $previewrows; $numoflines++) {
            $lines = $this->csvreader->next();
            if ($lines) {
                $this->previewdata[] = $lines;
            }
        }
    }

    /**
     * Initialises the import reader and prechecks the uploaded csv file.
     *
     * @return bool false is a failed import
     */
    public function init() {
        GLOBAL $CFG, $DB, $USER;

        if ($this->csvreader == null) {
            $this->csvreader = new csv_import_reader($this->importid, 'attendance');
        }

        $sessioninfo = $this->att->get_session_info($this->att->pageparams->sessionid);

        $this->validusers = $this->att->get_users($this->att->pageparams->grouptype, 0);

        // Precheck the uploaded file for any errors before processing.
        $this->csvreader->init();

        $sessiondate = date('n/j/Y', $sessioninfo->sessdate);
        $firstrecord = $this->csvreader->next();

        $scandate = strtotime($firstrecord[$this->scandateindex]);
        $scandate = date('n/j/Y', $scandate);

        // Setting up the precheck statuses.
        $precheckstatus = '';
        $statuses = $this->att->get_statuses();
        foreach ($statuses as $status) {
            $sessionstats[$status->id] = 0;
        }

        $this->restart();

        while ($record = $this->csvreader->next()) {

            // Flag an error if the studentid, encoding, scan time or scan date column is missing.
            if (empty($record[$this->studentindex])) {
                $this->studentcolempty = true;
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
            if (!($record[$this->scandateindex] == date('n/j/Y', $d) ||
                  $record[$this->scandateindex] == date('m/d/Y', $d) ||
                  $record[$this->scandateindex] == date('n/j/y', $d) ||
                  $record[$this->scandateindex] == date('m/d/y', $d))) {
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

            // Prechecking the file for how many students would be marked each attendance status.
            $scantime = $record[$this->scandateindex].' '.$record[$this->scantimeindex];
            $scantime = strtotime($scantime);
            $scantime = (int) $scantime;

            $student = $record[$this->studentindex];
            $encoding = $record[$this->encodingindex];
            if ($encoding == 'UPC-A') {
                $student = substr($student, 1, -2);
            }
            if ($userrecord = $DB->get_record('user', array($this->mapto => $student), 'id', IGNORE_MISSING)) {
                $userid = $userrecord->id;
                if (!empty($this->validusers[$userid])) {
                    $precheckstatus = attendance_session_get_highest_status($this->att, $sessioninfo, $fromcsv = true, $scantime);
                    foreach ($statuses as $status) {
                        if ($precheckstatus == $status->id) {
                            $sessionstats[$status->id]++;
                        }
                    }
                }
            }
        }

        // Using how many students were allocated to each status to determine whether there were any scantime issues.
        // These may include an uncalibrated scanner or DST.
        // This criteria can also be used to identify if the wrong file was uploaded.
        $this->highestgradedstatus = new stdClass;
        $this->highestgradedstatus->grade = 0;

        $this->lowestgradedstatus = new stdClass;
        $this->lowestgradedstatus->grade = 0;

        foreach ($statuses as $status) {
            if ($status->grade > $this->highestgradedstatus->grade) {
                $this->highestgradedstatus = $status;
            }
            if ($status->grade <= $this->lowestgradedstatus->grade) {
                $this->lowestgradedstatus = $status;
            }
        }

        $studentcount = count($this->validusers);

        // If the class size is less than 10 students, it doesn't make sense to run these checks.
        // The attendance records for a class this small can be adjust with little hassle.
        if ($studentcount > 10) {
            // If the status with the highest grade does not contain majority of the class, there must be an issue such as DST.
            if ($this->highestgradedstatus->id != array_search(max($sessionstats), $sessionstats)) {
                $this->scantimeerr = true;
            }
        }

        if ($this->studentcolempty == true   || $this->encodingcolempty == true  || $this->scantimecolempty == true  ||
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
     * Returns the header row.
     *
     * @param string $importid A unique id for this import
     * @return array returns headers parameter for this class.
     */
    public function get_headers($importid) {
        global $CFG, $USER;

        $filename = $CFG->tempdir.'/csvimport/'.'attendance'.'/'.$USER->id.'/'.$importid;
        $fp = fopen($filename, "r");
        $headers = fgetcsv($fp);
        fclose($fp);
        if ($headers === false) {
            return false;
        }

        return $headers;
    }

    /**
     * Returns the preview data.
     *
     * @return array returns previewdata parameter for this class.
     */
    public function get_previewdata() {
        return $this->previewdata;
    }

    /**
     * Set the index mapping of the student column in the csv file.
     *
     * @param int $index The column index to map this field to
     */
    public function set_studentindex($index) {
        $this->studentindex = $index;
    }

    /**
     * Set the index mapping of the encoding column in the csv file.
     *
     * @param int $index The column index to map this field to
     */
    public function set_encodingindex($index) {
        $this->encodingindex = $index;
    }

    /**
     * Set the index mapping of the scantime column in the csv file.
     *
     * @param int $index The column index to map this field to
     */
    public function set_scantimeindex($index) {
        $this->scantimeindex = $index;
    }

    /**
     * Set the index mapping of the scandate column in the csv file.
     *
     * @param int $index The column index to map this field to
     */
    public function set_scandateindex($index) {
        $this->scandateindex = $index;
    }

    /**
     * Set the database field to map the student column to.
     *
     * @param string $mapto The database field to map the student column to
     */
    public function set_mapto($mapto) {
        switch($mapto){
            case 'userid';
                $this->mapto = 'id';
                break;
            case 'username':
                $this->mapto = 'username';
                break;
            case 'useridnumber':
                $this->mapto = 'idnumber';
                break;
            case 'useremail':
                $this->mapto = 'email';
                break;
            default:
                $this->mapto = null;
        }
    }

    /**
     * Get the next row of data from the csv file (only the columns we care about)
     *
     * @return stdClass or false The stdClass is an object containing user id, scan time and scan date.
     */
    public function next() {
        GLOBAL $DB;

        $result = new stdClass();

        while ($record = $this->csvreader->next()) {
            $student = $record[$this->studentindex];
            $encoding = $record[$this->encodingindex];
            if ($encoding == 'UPC-A') {
                $student = substr($student, 1, -2);
            }
            if ($userrecord = $DB->get_record('user', array($this->mapto => $student), 'id', IGNORE_MISSING)) {
                $userid = $userrecord->id;
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
