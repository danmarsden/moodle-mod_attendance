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

    picker.addEventListener('change', () => {
        if (!picker.value) {
            return;
        }
        const [year, month, day] = picker.value.split('-').map(Number);
        // Midday of the chosen day, so that it is still that day if the browser and the user's
        // timezone in Moodle are not the same.
        form.elements.curdate.value = Math.floor(new Date(year, month - 1, day, 12).getTime() / 1000);
        form.submit();
    });
};
