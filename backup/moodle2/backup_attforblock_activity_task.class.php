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
 * Class {@link backup_attforblock_activity_task} definition
 *
 * @package    mod
 * @subpackage attforblock
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/attforblock/backup/moodle2/backup_attforblock_settingslib.php');
require_once($CFG->dirroot . '/mod/attforblock/backup/moodle2/backup_attforblock_stepslib.php');

/**
 * Provides all the settings and steps to perform one complete backup of attforblock activity
 */
class backup_attforblock_activity_task extends backup_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        $this->add_step(new backup_attforblock_activity_structure_step('attforblock_structure', 'attforblock.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot,"/");

        //Link to attforblock view by moduleid
        $search = "/(" . $base . "\/mod\/attforblock\/view.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@ATTFORBLOCKVIEWBYID*$2@$', $content);

        //Link to attforblock view by moduleid and studentid
        $search = "/(" . $base . "\/mod\/attforblock\/view.php\?id\=)([0-9]+)\&studentid\=([0-9]+)/";
        $content= preg_replace($search, '$@ATTFORBLOCKVIEWBYIDSTUD*$2*$3@$', $content);

        return $content;
    }
}
