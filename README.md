# ABOUT [![Build Status](https://travis-ci.org/flocko-motion/moodle-mod_attendance.svg?branch=master)](https://travis-ci.org/flocko-motion/moodle-mod_attendance)

The Attendance module is supported and maintained by Dan Marsden http://danmarsden.com

The Attendance module was previously developed by
    Dmitry Pupinin, Novosibirsk, Russia,
    Artem Andreev, Taganrog, Russia.
    
# GOAL OF THIS FORK

This fork is work in progress and aims at extending the original plugin with these features:
1) Adding "rooms" to the plugin, so that a session can have a property
 in which physical location it takes place. Rooms can be defined in settings
 and feature a max capacity of people that fit into that room.
2) When setting up a session in a room, the plugin should check for
double bookings of that room and emit a warning in that case.
3) Sessions can define an allowed maximum of attendants. Students can book
these slots (= announce that they will attend this session). Booked slots
appear in the students calendar as events.
4) When filling out the attendance form (take.php) the form should be 
optionally prepopulated with the students who booked a slot in the session
or with all students who are enrolled in the course. 
5) If a student attended a session but isn't enrolled (or even not registered in moodle)
it should be easy to add him/her to the session. The plugin will automatically
enroll them in the course or even register them in moodle as new users.


Branches
--------
The git branches here support the following versions.

| Moodle version     | Branch      |
| ----------------- | ----------- |
| Mooodle 3.5   | MOODLE_35_STABLE |
| Mooodle 3.6   | MOODLE_36_STABLE |
| Moodle 3.7 | MOODLE_37_STABLE |
| Moodle 3.8 and higher | main |

# PURPOSE
The Attendance module allows teachers to maintain a record of attendance, replacing or supplementing a paper-based attendance register.
It is primarily used in blended-learning environments where students are required to attend classes, lectures and tutorials and allows
the teacher to track and optionally provide a grade for the students attendance.

Sessions can be configured to allow students to record their own attendance and a range of different reports are available.

# DOCUMENTATION
https://docs.moodle.org/en/Attendance_activity
