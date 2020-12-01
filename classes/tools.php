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
 * Class definition for mod_presence\tools
 *
 * @package    mod_presence
 * @author     Florian Metzger-Noel (github.com/flocko-motion)
 * @copyright  2020
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_presence;

defined('MOODLE_INTERNAL') || die();

class tools
{
    public static function lang_to_html($strid, $component = null) {
        echo '<DATA data-type="presence_str" data-key="'.htmlentities($strid).'" data-value="'.htmlentities(get_string($strid, $component)).'" />';
    }

    public static function var_val_to_html($var, $val) {
        echo '<DATA data-type="presence_var" data-key="'.htmlentities($var).'" data-value="'.htmlentities($val).'" />';
    }
}