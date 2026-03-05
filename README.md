# mod_attendance (custom version)

This repository contains a customized version of Moodle's `mod_attendance` plugin with extended warning logic for planned basis modes (planned hours/sessions).

For a Norwegian version of this documentation, see `README-NO.md`.

## What has been implemented

### 1) New warning basis options
- `Warning basis mode` now supports:
  - `Current sessions only` (original behavior)
  - `Planned total sessions`
  - `Planned total hours`
- New activity fields:
  - `Planned total sessions`
  - `Planned total hours`

### 2) Warning calculation for planned hours
- In `planned_hours`, warning percent is calculated as:
- `effective_percent = ((plannedtotalhours - absent_hours) / plannedtotalhours) * 100`
- To support different point scales, absent hours are converted proportionally when session duration exists:
- `absent_hours = taken_hours * (missing_points / max_points)`
- Fallback (when no duration is available): `1 point = 1 hour`.

### 3) Notify task improvements
- `mod_attendance\task\notify` uses the same effective percentage for both:
  - trigger decision
  - `%percent%` replacement in email templates
- Warning candidate selection now uses only the latest log entry per student/session (avoids double counting).
- Added protection against skewed aggregates from `attendance_warning_done`.
- Only actual sends are recorded in `attendance_warning_done`.

### 4) Manual warning task runner
- Added manual runner page:
- `/mod/attendance/run_notify_task.php`
- Useful when `Run now` is not visible in Scheduled tasks.
- Includes a `Clear sent warnings` action for retesting.

## Compatibility

- Default setup (`Current sessions only`) is preserved.
- Backward compatible for existing activities that do not use planned basis modes.
- On upgrade, Moodle must run normal plugin upgrade steps (DB fields for planned basis).

## How to use the plugin

### 1) Enable warnings globally
1. Go to Attendance plugin settings.
2. Set `Enable warnings` to `Yes`.

### 2) Configure an attendance activity
Inside the attendance activity:
1. Set `Warning basis mode`:
   - choose `Planned total hours` if you want to calculate against total planned teaching hours.
2. Set `Planned total hours` (for example `100`).
3. Save.

### 3) Create a warning rule
In the activity warning settings:
1. Set `Warn if percentage falls under` (for example `80`).
2. Set `Number of sessions taken before warning` (for example `1`).
3. Enable `Email user`.
4. Set subject/content.
5. Save.

### 4) Register attendance
- Register attendance per session as usual.
- Ensure sessions that should count have `Include in absentee report` enabled.

## Example calculation (planned total hours)

Scenario:
- `Planned total hours = 100`
- 5 sessions of 10 hours (`taken_hours = 50`)
- Point scale per session 0-100 (for example: 100, 50, 100, 25, 0)

Calculation:
- `points = 275`, `max_points = 500`, `missing_points = 225`
- `absent_hours = 50 * (225 / 500) = 22.5`
- `effective_percent = (100 - 22.5) / 100 * 100 = 77.5`

If warning threshold is 80%, a warning should be sent (`77.5 < 80`).

## Production operation (automatic sending)

Warnings are sent automatically by the scheduled task:
- `mod_attendance\task\notify`

Requirements:
- Moodle cron is running.
- The task is enabled in `Site administration -> Server -> Scheduled tasks`.

Manual fallback:
- Run: `/mod/attendance/run_notify_task.php`
- Use `Clear sent warnings` before a new test cycle.

## Troubleshooting

If emails are not sent:
1. Verify `Enable warnings` is enabled.
2. Verify the warning rule has `Email user` enabled.
3. Verify threshold and `Number of sessions taken before warning`.
4. Verify sessions are included in absentee report.
5. Verify cron and scheduled task status.
6. Purge caches after code updates.
7. For retesting, clear sent warnings in `run_notify_task.php`.

## Original project

The Attendance module is maintained by Dan Marsden: http://danmarsden.com

Previous developers:
- Dmitry Pupinin (Novosibirsk, Russia)
- Artem Andreev (Taganrog, Russia)

Official documentation:
- https://docs.moodle.org/en/Attendance_activity
