********************************************************************************************* 
****** WARNING: THIS MODULE IS IN DEVELOPMENT. USE WITH CAUTION ****** 
*********************************************************************************************

--------
ABOUT
--------
This is version 2.1.x of the "Attendance" module (attforblock). It is still IN DEVELOPMENT 
and should not be considered a stable release unless otherwise noted. 
It has been tested on Moodle 1.9+, MySQL and PHP 5.2+.

The "Attendance" module is developed by Dmitry Pupinin, Novosibirsk, Russia.

This block may be distributed under the terms of the General Public License
(see http://www.gnu.org/licenses/gpl.txt for details)

-----------
PURPOSE
-----------
The attendance module and block are designed to allow instructors of a course keep an attendance log of the students in their courses. The instructor will setup the frequency of his classes (# of days per week & length of course) and the attendance is ready for use. To take attendance, the instructor clicks on the "Update Attendance" button and is presented with a list of all the students in that course, along with 4 options: Present, Absent, Late & Excused, with a Remarks textbox. Instructors can download the attendance for their course in Excel format or text format.
Only the instructor can update the attendance data. However, a student gets to see his attendance record.

----------------
INSTALLATION
----------------
The attendance follows standard installation procedures. Place the "attendance" directory in your blocks directory, "attforblock" directory in your mod directory. Please delete old language files from your moodledata/lang/en directory if you are upgrading the module. Then visit the Admin page in Moodle to activate it.
