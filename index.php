<?PHP

/// This page lists all the instances of attforblock in a particular course
/// Replace attforblock with the name of your module

    require_once('../../config.php');

    $id = required_param('id', PARAM_INT);                 // Course id

    if (! $course = $DB->get_record('course', array('id'=> $id))) {
        error('Course ID is incorrect');
    }

	if ($att = array_pop(get_all_instances_in_course('attforblock', $course, NULL, true))) {
    	redirect("view.php?id=$att->coursemodule");
	} else {
		print_error('notfound', 'attforblock');
	}

?>
