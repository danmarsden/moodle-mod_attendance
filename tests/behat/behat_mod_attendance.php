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
 * Custom behat steps for attendance_mod
 * @author    Guy Thomas <gthomas@moodlerooms.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

class behat_mod_attendance extends behat_base {
    /**
     * Waits until the attendance table is visible.
     *
     * @Given /^I wait until attendance table is visible$/
     * @return void
     */
    public function i_wait_until_attendance_table_visible() {
        $this->ensure_element_is_visible('td.c3 input', 'css_element');
    }

    /**
     * Return xpath to make something lowercase (because xpath 1 does not have the lower-case function).
     * @param string $value
     * @return string
     */
    protected function lowercasexpath($value) {
        return "translate($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')";
    }

    /**
     * @param string $sessionstr
     *
     * @Given /^I take attendance for "(?P<sessionstr_string>(?:[^"]|\\")*)"$/
     * @return void
     */
    public function i_take_attendance_for($sessionstr) {
        $sessionstr = strtolower($sessionstr);
        $xpath = '//div[contains(@class, "attsessions_manage_table")]//tr/td/';
        $xpath .= '*[contains('.$this->lowercasexpath('text()').', "'.$sessionstr.'")]/';
        $xpath .= 'parent::td/parent::tr/td/a[contains(@href, "take.php")]';
        $this->execute('behat_general::i_click_on', [$xpath, 'xpath_element']);
    }

    /**
     * Check to make sure a session row is in a specific state, using xpath.
     * @param string $xpath
     * @param string $sessionstr
     */
    protected function check_session_row_with_xpath($xpath, $sessionstr) {

        try {
            $nodes = $this->find_all('xpath', $xpath);
        } catch (ElementNotFoundException $e) {
            throw new ExpectationException('"' . $sessionstr . '" session was not found in the page', $this->getSession());
        }

        // If we are not running javascript we have enough with the
        // element existing as we can't check if it is visible.
        if (!$this->running_javascript()) {
            return;
        }

        // We spin as we don't have enough checking that the element is there, we
        // should also ensure that the element is visible. Using microsleep as this
        // is a repeated step and global performance is important.
        $this->spin(
            function($context, $args) {

                foreach ($args['nodes'] as $node) {
                    if ($node->isVisible()) {
                        return true;
                    }
                }

                // If non of the nodes is visible we loop again.
                throw new ExpectationException('"' . $args['text'] . '" session was found but was not visible',
                    $context->getSession());
            },
            array('nodes' => $nodes, 'text' => $sessionstr),
            false,
            false,
            true
        );
    }

    /**
     * Checks that page contains specified attendance session.
     *
     * @Then /^I should see an attendance session of "(?P<text_string>(?:[^"]|\\")*)"$/
     * @throws ExpectationException
     * @param string $sessionstr
     */
    public function assert_page_contains_session($sessionstr) {

        $sessionstr = strtolower($sessionstr);

        // Looking for all the matching nodes without any other descendant matching the
        // same xpath (we are using contains(., ....).
        $xpathliteral = behat_context_helper::escape($sessionstr);
        $xpath = "/descendant-or-self::*[contains(".$this->lowercasexpath('text()').", $xpathliteral)]" .
            "[count(descendant::*[contains(".$this->lowercasexpath('text()').", $xpathliteral)]) = 0]";

        $this->check_session_row_with_xpath($xpath, $sessionstr);
    }

    /**
     * Assert that attendance session row contains text.
     *
     * @Given /^I see "(?P<element_string>(?:[^"]|\\")*)" in the "(?P<session_string>(?:[^"]|\\")*)" session row$/
     * @throws ElementNotFoundException Thrown by behat_base::find
     * @param string $text The text to look for
     * @param string $sessionstr The session string - e.g. 4am - 5am
     */
    public function assert_attendance_session_contains($text, $sessionstr) {
        // Get the container node.
        $xpathliteral = strtolower(behat_context_helper::escape($sessionstr));
        $xpath = '//div[contains(@class, "attsessions_manage_table")]//tr/td/';
        $xpath .= '*[contains('.$this->lowercasexpath('text()').', '.$xpathliteral.')]/';
        $xpath .= 'parent::td/parent::tr//*[contains(., '.behat_context_helper::escape($text).')]';

        $this->check_session_row_with_xpath($xpath, $sessionstr);
    }
}


