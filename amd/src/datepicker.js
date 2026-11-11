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
 * Date controls: the date button opens the browser's own date picker, and choosing a day
 * shows the sessions of that day, week or month.
 *
 * @module     mod_attendance/datepicker
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Set up the date button of the date controls.
 */
export const init = () => {
    const form = document.getElementById('currentdate');
    const button = document.getElementById('show');
    if (!form || !button) {
        return;
    }
    const picker = form.querySelector('input[type="date"]');

    button.addEventListener('click', () => picker.showPicker());

    // The form posts the chosen day as it is (yyyy-mm-dd), and the server reads it in the user's timezone.
    picker.addEventListener('change', () => {
        if (picker.value) {
            form.submit();
        }
    });
};
