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
 * Tests for warning basis logic in locallib.
 *
 * @package    mod_attendance
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_attendance;

use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/attendance/classes/structure.php');
require_once($CFG->dirroot . '/mod/attendance/locallib.php');

/**
 * Tests for warning basis calculations and templating.
 *
 * @group mod_attendance
 *
 * @covers ::attendance_normalise_warning_basis_fields
 * @covers ::attendance_warning_effective_percent
 * @covers ::attendance_warning_basismode_allows_trigger
 * @covers ::attendance_template_variables
 */
final class locallib_test extends \advanced_testcase {
    /**
     * Verify invalid warning mode falls back to current_sessions.
     */
    public function test_normalise_warning_basis_fields_defaults_to_current(): void {
        $attendance = (object) [
            'warningbasismode' => 'invalid_mode',
            'plannedtotalsessions' => 0,
            'plannedtotalhours' => 0,
        ];

        \attendance_normalise_warning_basis_fields($attendance);

        $this->assertSame('current_sessions', $attendance->warningbasismode, 'Invalid mode must be normalised to current_sessions.');
        $this->assertNull($attendance->plannedtotalsessions, 'Zero plannedtotalsessions must be normalised to null.');
        $this->assertNull($attendance->plannedtotalhours, 'Zero plannedtotalhours must be normalised to null.');
    }

    /**
     * Verify missing warningbasismode and missing planned fields are normalised.
     */
    public function test_normalise_warning_basis_fields_with_missing_mode_and_fields(): void {
        $attendance = (object) [];

        \attendance_normalise_warning_basis_fields($attendance);

        $this->assertSame('current_sessions', $attendance->warningbasismode, 'Missing warningbasismode must default to current_sessions.');
        $this->assertNull($attendance->plannedtotalsessions, 'Missing plannedtotalsessions must be normalised to null.');
        $this->assertNull($attendance->plannedtotalhours, 'Missing plannedtotalhours must be normalised to null.');
    }

    /**
     * Verify planned_sessions mode is kept while invalid totals are normalised.
     */
    public function test_normalise_warning_basis_fields_planned_sessions_with_zero_or_null_totals(): void {
        $attendancezero = (object) [
            'warningbasismode' => 'planned_sessions',
            'plannedtotalsessions' => 0,
            'plannedtotalhours' => 2.5,
        ];
        \attendance_normalise_warning_basis_fields($attendancezero);

        $this->assertSame('planned_sessions', $attendancezero->warningbasismode, 'Valid planned_sessions mode should be preserved.');
        $this->assertNull($attendancezero->plannedtotalsessions, 'Zero plannedtotalsessions must become null.');
        $this->assertSame(2.5, (float) $attendancezero->plannedtotalhours, 'Positive plannedtotalhours should be preserved.');

        $attendancenull = (object) [
            'warningbasismode' => 'planned_sessions',
            'plannedtotalsessions' => null,
            'plannedtotalhours' => null,
        ];
        \attendance_normalise_warning_basis_fields($attendancenull);

        $this->assertSame('planned_sessions', $attendancenull->warningbasismode, 'Valid planned_sessions mode should be preserved.');
        $this->assertNull($attendancenull->plannedtotalsessions, 'Null plannedtotalsessions should remain null.');
        $this->assertNull($attendancenull->plannedtotalhours, 'Null plannedtotalhours should remain null.');
    }

    /**
     * Verify effective percent is based on planned total sessions.
     */
    public function test_warning_effective_percent_planned_sessions(): void {
        $ctx = (object) [
            'basismode' => 'planned_sessions',
            'plannedtotalsessions' => 20,
            'num_absent_sessions' => 5,
            'percent' => 10.0,
        ];

        $result = \attendance_warning_effective_percent($ctx);

        $this->assertEquals(75.0, $result, 'Planned sessions effective percent should be (planned-absent)/planned.');
    }

    /**
     * Verify effective percent falls back to current percent when planned sessions total is null/zero.
     */
    public function test_warning_effective_percent_planned_sessions_without_planned_total_falls_back(): void {
        $ctxnull = (object) [
            'basismode' => 'planned_sessions',
            'plannedtotalsessions' => null,
            'num_absent_sessions' => 5,
            'percent' => 44.4,
        ];
        $ctxzero = (object) [
            'basismode' => 'planned_sessions',
            'plannedtotalsessions' => 0,
            'num_absent_sessions' => 5,
            'percent' => 33.3,
        ];

        $this->assertEquals(
            44.4,
            \attendance_warning_effective_percent($ctxnull),
            'When plannedtotalsessions is null, percent should fall back to ctx->percent.'
        );
        $this->assertEquals(
            33.3,
            \attendance_warning_effective_percent($ctxzero),
            'When plannedtotalsessions is zero, percent should fall back to ctx->percent.'
        );
    }

    /**
     * Verify effective percent reaches 0 when all planned sessions are absent.
     */
    public function test_warning_effective_percent_planned_sessions_boundary_all_absent(): void {
        $ctx = (object) [
            'basismode' => 'planned_sessions',
            'plannedtotalsessions' => 8,
            'num_absent_sessions' => 8,
            'percent' => 99.9,
        ];

        $result = \attendance_warning_effective_percent($ctx);

        $this->assertEquals(0.0, $result, 'When absent sessions equal planned total, effective percent should be 0.');
    }

    /**
     * Verify effective percent is based on planned total hours.
     */
    public function test_warning_effective_percent_planned_hours(): void {
        $ctx = (object) [
            'basismode' => 'planned_hours',
            'plannedtotalhours' => 100.0,
            'absent_hours' => 22.5,
            'percent' => 10.0,
        ];

        $result = \attendance_warning_effective_percent($ctx);

        $this->assertEquals(77.5, $result, 'Planned hours effective percent should be (planned-absent)/planned.');
    }

    /**
     * Verify effective percent falls back when planned total hours is null/zero.
     */
    public function test_warning_effective_percent_planned_hours_without_planned_total_falls_back(): void {
        $ctxnull = (object) [
            'basismode' => 'planned_hours',
            'plannedtotalhours' => null,
            'absent_hours' => 10.0,
            'percent' => 45.6,
        ];
        $ctxzero = (object) [
            'basismode' => 'planned_hours',
            'plannedtotalhours' => 0.0,
            'absent_hours' => 10.0,
            'percent' => 12.3,
        ];

        $this->assertEquals(
            45.6,
            \attendance_warning_effective_percent($ctxnull),
            'When plannedtotalhours is null, percent should fall back to ctx->percent.'
        );
        $this->assertEquals(
            12.3,
            \attendance_warning_effective_percent($ctxzero),
            'When plannedtotalhours is zero, percent should fall back to ctx->percent.'
        );
    }

    /**
     * Verify effective percent reaches 0 when absent hours equal planned hours.
     */
    public function test_warning_effective_percent_planned_hours_boundary_all_absent(): void {
        $ctx = (object) [
            'basismode' => 'planned_hours',
            'plannedtotalhours' => 12.0,
            'absent_hours' => 12.0,
            'percent' => 87.6,
        ];

        $result = \attendance_warning_effective_percent($ctx);

        $this->assertEquals(0.0, $result, 'When absent hours equal planned total, effective percent should be 0.');
    }

    /**
     * Verify current mode keeps the provided percent unchanged.
     */
    public function test_warning_effective_percent_current_sessions_uses_ctx_percent(): void {
        $ctx = (object) [
            'basismode' => 'current_sessions',
            'percent' => 64.2,
        ];

        $result = \attendance_warning_effective_percent($ctx);

        $this->assertEquals(64.2, $result, 'Current mode should return ctx->percent unchanged.');
    }

    /**
     * Verify planned-hours trigger still respects warnafter (sessions taken).
     */
    public function test_warning_basismode_allows_trigger_planned_hours_respects_warnafter(): void {
        /** @var \mod_attendance_structure $att */
        $att = $this->getMockBuilder(\mod_attendance_structure::class)
            ->disableOriginalConstructor()
            ->getMock();

        $warning = (object) ['warnafter' => 3];
        $ctxnotenough = (object) [
            'basismode' => 'planned_hours',
            'plannedtotalhours' => 100.0,
            'numtakensessions' => 2,
        ];
        $ctxenough = (object) [
            'basismode' => 'planned_hours',
            'plannedtotalhours' => 100.0,
            'numtakensessions' => 3,
        ];

        $this->assertFalse(
            \attendance_warning_basismode_allows_trigger($att, $ctxnotenough, $warning),
            'Planned hours mode should not trigger before warnafter sessions are taken.'
        );
        $this->assertTrue(
            \attendance_warning_basismode_allows_trigger($att, $ctxenough, $warning),
            'Planned hours mode should trigger once warnafter sessions are taken.'
        );
    }

    /**
     * Verify current_sessions mode keeps original warnafter gate behaviour.
     */
    public function test_warning_basismode_allows_trigger_current_sessions_behaves_like_legacy_logic(): void {
        /** @var \mod_attendance_structure $att */
        $att = $this->getMockBuilder(\mod_attendance_structure::class)
            ->disableOriginalConstructor()
            ->getMock();

        $warning = (object) ['warnafter' => 4];
        $ctxbelow = (object) [
            'basismode' => 'current_sessions',
            'numtakensessions' => 3,
        ];
        $ctxat = (object) [
            'basismode' => 'current_sessions',
            'numtakensessions' => 4,
        ];

        $this->assertFalse(
            \attendance_warning_basismode_allows_trigger($att, $ctxbelow, $warning),
            'Current sessions mode should not trigger below warnafter.'
        );
        $this->assertTrue(
            \attendance_warning_basismode_allows_trigger($att, $ctxat, $warning),
            'Current sessions mode should trigger at warnafter.'
        );
    }

    /**
     * Verify unknown basismode falls back to current-sessions behavior.
     */
    public function test_warning_basismode_allows_trigger_unknown_mode_falls_back_to_warnafter_gate(): void {
        /** @var \mod_attendance_structure $att */
        $att = $this->getMockBuilder(\mod_attendance_structure::class)
            ->disableOriginalConstructor()
            ->getMock();

        $warning = (object) ['warnafter' => 2];
        $ctxbelow = (object) [
            'basismode' => 'not_a_real_mode',
            'numtakensessions' => 1,
        ];
        $ctxat = (object) [
            'basismode' => 'not_a_real_mode',
            'numtakensessions' => 2,
        ];

        $this->assertFalse(
            \attendance_warning_basismode_allows_trigger($att, $ctxbelow, $warning),
            'Unknown mode should safely fall back to warnafter gating.'
        );
        $this->assertTrue(
            \attendance_warning_basismode_allows_trigger($att, $ctxat, $warning),
            'Unknown mode fallback should trigger at warnafter.'
        );
    }

    /**
     * Verify %percent% placeholder uses effective percent when provided.
     */
    public function test_template_variables_uses_effective_percent_for_percent_placeholder(): void {
        $record = $this->build_template_record();
        $record->emailsubject = 'Warning %percent%';
        $record->emailcontent = 'P=%percent%; mode=%warningbasismode%; ps=%plannedtotalsessions%; ph=%plannedtotalhours%';
        $record->effectivepercent = 77.5;
        $record->percent = 0.5;

        $result = \attendance_template_variables($record);

        $this->assertStringContainsString('Warning 77.5', $result->emailsubject, 'Effective percent should replace %percent% in subject.');
        $this->assertStringContainsString('P=77.5', $result->emailcontent, 'Effective percent should replace %percent% in content.');
        $this->assertStringContainsString('mode=planned_hours', $result->emailcontent, 'Mode placeholder should be replaced.');
        $this->assertStringContainsString('ps=20', $result->emailcontent, 'Planned sessions placeholder should be replaced.');
        $this->assertStringContainsString('ph=100.0', $result->emailcontent, 'Planned hours placeholder should be replaced.');
    }

    /**
     * Verify %percent% uses raw percent when effectivepercent is absent.
     */
    public function test_template_variables_uses_raw_percent_when_effective_percent_is_not_set(): void {
        $record = $this->build_template_record();
        $record->emailsubject = 'Warning %percent%';
        $record->emailcontent = 'P=%percent%';
        $record->percent = 0.23456;
        unset($record->effectivepercent);

        $result = \attendance_template_variables($record);

        $this->assertStringContainsString('Warning 23.5', $result->emailsubject, 'Raw percent must be used when effectivepercent is missing.');
        $this->assertStringContainsString('P=23.5', $result->emailcontent, 'Raw percent must be used when effectivepercent is missing.');
    }

    /**
     * Verify null/empty planned fields and basis mode are rendered as expected.
     */
    public function test_template_variables_with_empty_planned_values_and_mode(): void {
        $record = $this->build_template_record();
        $record->emailsubject = 'mode=%warningbasismode%';
        $record->emailcontent = 'ps=%plannedtotalsessions%; ph=%plannedtotalhours%; mode=%warningbasismode%';
        $record->plannedtotalsessions = '';
        $record->plannedtotalhours = null;
        $record->warningbasismode = '';
        $record->percent = 0.8;

        $result = \attendance_template_variables($record);

        $this->assertStringContainsString('mode=', $result->emailsubject, 'Empty warningbasismode should render as empty string.');
        $this->assertStringContainsString('ps=', $result->emailcontent, 'Empty plannedtotalsessions should render as empty string.');
        $this->assertStringContainsString(
            'ph=%plannedtotalhours%',
            $result->emailcontent,
            'Null plannedtotalhours is not replaced by current implementation and should remain literal.'
        );
        $this->assertStringContainsString('mode=', $result->emailcontent, 'Empty warningbasismode should render as empty string.');
    }

    /**
     * Build a complete template record with required placeholders.
     *
     * @return stdClass
     */
    private function build_template_record(): stdClass {
        $record = (object) [
            'coursename' => 'Course A',
            'courseid' => 2,
            'userid' => 10,
            'warningpercent' => 80,
            'aname' => 'Attendance A',
            'cmid' => 55,
            'numtakensessions' => 5,
            'points' => 275,
            'maxpoints' => 500,
            'plannedtotalsessions' => 20,
            'plannedtotalhours' => 100.0,
            'warningbasismode' => 'planned_hours',
            'emailsubject' => '',
            'emailcontent' => '',
        ];

        foreach (\core_user\fields::get_name_fields() as $namefield) {
            if (!isset($record->{$namefield})) {
                $record->{$namefield} = 'name';
            }
        }

        return $record;
    }
}
