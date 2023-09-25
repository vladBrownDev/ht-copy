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

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

// now get cli options
list ($options, $unrecognized) = cli_get_params(array(
    'help' => false,
    'course' => null,
    'category' => 1,
    'copies' => 1,
), array(
    'h' => 'help',
    'c' => 'course',
    't' => 'category',
    'n' => 'copies',
));
if ($options['help'] || !($options['course'])) {
    $help = <<<EOL
Perform duplicate of course into category n times.

Options:
--course=INTEGER          Course ID for backup.
--category=INTEGER        Categroy to restore corse into.
--copies=INTEGER          The number of copies to make from the course.
-h, --help                Print out this help.

Example:
\$sudo -u www-data /usr/bin/php admin/cli/duplicatecourse.php --course=2 --category=3 --copies=10\n
EOL;

    echo $help;
    die;
}
// Check that the course exists.
if ($options['course']) {
    $course = $DB->get_record('course', array('id' => $options['course']), '*', MUST_EXIST);
} else {
    mtrace("The template course not exist in the DB please check the course id");
    die;
}

$admin = get_admin();
if (!$admin) {
    mtrace("Error: No admin account was found");
    die;
}

cli_heading('Performing backup...');
$bc = new backup_controller(backup::TYPE_1COURSE, $course->id, backup::FORMAT_MOODLE,
    backup::INTERACTIVE_YES, backup::MODE_GENERAL, $admin->id);

// Set the default filename.
$format = $bc->get_format();
$type = $bc->get_type();
$id = $bc->get_id();
$users = $bc->get_plan()->get_setting('users')->get_value();
$anonymised = $bc->get_plan()->get_setting('anonymize')->get_value();
$filename = backup_plan_dbops::get_default_backup_filename($format, $type, $id, $users, $anonymised);
$bc->get_plan()->get_setting('filename')->set_value($filename);

// Execution.
$bc->finish_ui();
$bc->execute_plan();
$results = $bc->get_results();
$file = $results['backup_destination'];

// Extract to a temp folder.
$context = context_course::instance($course->id);
$filepath = md5(time() . '-' . $context->id . '-'. $admin->id . '-'. random_string(20));
$fb = get_file_packer('application/vnd.moodle.backup');
$extracttopath = $CFG->tempdir . '/backup/' . $filepath . '/';

cli_heading('Performing restore...');
for ($counter = 1; $counter <= $options['copies']; $counter++) {
    $extractedbackup = $fb->extract_to_pathname($file, $extracttopath);
    list($fullname, $shortname) = restore_dbops::calculate_course_names(0, $course->fullname, $course->shortname);
    $courseid = restore_dbops::create_new_course($fullname, $shortname, $options['category']);
    $rc = new restore_controller($filepath, $courseid, backup::INTERACTIVE_NO,
            backup::MODE_GENERAL, $admin->id, backup::TARGET_NEW_COURSE);
    // Check if the format conversion must happen first.
    if ($rc->get_status() == backup::STATUS_REQUIRE_CONV) {
        $rc->convert();
    }
    if ($rc->execute_precheck()) {
        // Start restore (import).
        $rc->execute_plan();

    } else {
        echo get_string('errorwhilerestoringthecourse', 'tool_uploadcourse');
    }
    $rc->destroy();
    unset($rc);
    mtrace("End backup course copy $counter");
}
