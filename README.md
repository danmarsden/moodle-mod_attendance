# ABOUT

## Try in Moodle Playground

Click the badge below to open the `MOODLE_501_STABLE` branch instantly in [Moodle Playground](https://moodle-playground.com) with `mod_attendance` pre-installed. The playground boots a full Moodle 5.1 site with a demo course (`ATTDEMO01`) containing a preloaded attendance activity, so you can add sessions and try the attendance workflow without any local setup. Every same-repo pull request also automatically generates a playground preview link appended to the PR description so reviewers can test changes in a live Moodle instance.

<a href="https://moodle-playground.com/?blueprint-url=https://raw.githubusercontent.com/danmarsden/moodle-mod_attendance/refs/heads/MOODLE_501_STABLE/blueprint.json" target="_blank" rel="noopener"><img src="https://raw.githubusercontent.com/ateeducacion/action-moodle-playground-pr-preview/refs/heads/main/assets/playground-preview-button.svg" alt="Preview in Moodle Playground" width="200"></a>

The PR preview links are produced by the [ateeducacion/action-moodle-playground-pr-preview](https://github.com/ateeducacion/action-moodle-playground-pr-preview) GitHub Action, configured via `blueprint.json` at the repository root.

The Attendance module is supported and maintained by Dan Marsden http://danmarsden.com

The Attendance module was previously developed by
    Dmitry Pupinin, Novosibirsk, Russia,
    Artem Andreev, Taganrog, Russia.

Branches
--------
The following git branches are supported:

| Moodle version        | Branch            |
|-----------------------|-------------------|
| Moodle 4.5            | MOODLE_405_STABLE |
| Moodle 5.0            | MOODLE_500_STABLE |
| Moodle 5.1            | MOODLE_501_STABLE |

# PURPOSE
The Attendance module allows teachers to maintain a record of attendance, replacing or supplementing a paper-based attendance register.
It is primarily used in blended-learning environments where students are required to attend classes, lectures and tutorials and allows
the teacher to track and optionally provide a grade for the students attendance.

Sessions can be configured to allow students to record their own attendance and a range of different reports are available.

# DOCUMENTATION
https://docs.moodle.org/en/Attendance_activity
