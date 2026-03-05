# Changelog

All notable changes in this custom `mod_attendance` fork are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [2026030402] - 2026-03-04

### Fixed
- Planned-hours warning calculation now supports different point scales (for example 0-10 and 0-100) by converting missing points proportionally to taken session hours.
- Fixed incorrect `0%` warning emails in planned-hours scenarios caused by overestimated missing hours.
- Fixed duplicate aggregation in warning candidate selection by pre-aggregating `attendance_warning_done` before join.
- Fixed status-set alignment in warning SQL by linking max grade to the logged status set (not only the session set).
- Fixed warning candidate query to include only taken sessions (`lasttaken != 0`) in notify calculations.
- Fixed notify history behavior so `attendance_warning_done` is written only when a warning is actually sent.
- Fixed percent placeholder rendering to use the computed effective percent value in email templates.

### Changed
- Planned-hours warning logic now calculates:
  - `effective_percent = ((plannedtotalhours - absent_hours) / plannedtotalhours) * 100`
  - with `absent_hours` derived proportionally from point deficit when duration is available.
- Improved notify task diagnostics with clearer trace output for planned-hours computation during troubleshooting.
- Improved robustness of percent aggregation in SQL with denominator guards (`CASE WHEN SUM(maxgrade) > 0 ...`).

---

## [2026030401] - 2026-03-04

### Added
- Added manual warning task runner page:
  - `/mod/attendance/run_notify_task.php`
- Added UI action to clear previously sent warnings for retesting:
  - clears records from `attendance_warning_done`.
- Added language strings and help text for manual notify execution and warning-basis guidance.

### Changed
- Scheduled warning task candidate window expanded to include sessions from the last 7 days (for notify runs), improving reliability when task frequency and session timing do not align within 24 hours.
- README updated with full usage documentation and troubleshooting guidance for planned warning modes.

---

## [2026012701] - 2026-01-27

### Added
- Warning basis configuration on attendance activities:
  - `plannedtotalsessions`
  - `plannedtotalhours`
  - `warningbasismode`
- Warning basis modes:
  - `current_sessions` (default)
  - `planned_sessions`
  - `planned_hours`
- Warning template placeholders:
  - `%plannedtotalsessions%`
  - `%plannedtotalhours%`
  - `%warningbasismode%`
- Warning basis summary shown in warnings UI.

### Changed
- Warning trigger logic supports planned basis modes while preserving default behavior for existing activities.

---

## [2026012700] - 2026-01-27

### Added
- Base release for this custom branch (Moodle 5.1 baseline).
