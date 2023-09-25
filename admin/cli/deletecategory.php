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
require_once($CFG->dirroot.'/course/lib.php');


// now get cli options
list ($options, $unrecognized) = cli_get_params(array(
    'help' => false,
    'category' => null,
    'newcategory' => null,
    'mode' => 'full',
), array(
    'h' => 'help',
    't' => 'category',
    'n' => 'newcategory',
    'm' => 'mode',
));
if ($options['help'] || !($options['category'])
    || (($options['mode'] == 'move') && !$options['newcategory'])) {
    $help = <<<EOL
Perform duplicate of course into category n times.

Options:
--category=INTEGER        Categroy to restore corse into.
--mode=full/move          The number of copies to make from the course.
-h, --help                Print out this help.

Example:
\$sudo -u www-data /usr/bin/php admin/cli/deletecategory.php --category=2 --mode=full \n
\$sudo -u www-data /usr/bin/php admin/cli/deletecategory.php --category=2 --mode=move --newcategory=4 \n
EOL;

    echo $help;
    die;
}
global $USER;
$USER = get_admin();
$category = core_course_category::get($options['category']);
$context = context_coursecat::instance($category->id);
if (!$category->can_delete()) {
    throw new moodle_exception('permissiondenied', 'error', '', null, 'coursecat::can_resort');
}
if ($options['mode'] == 'full' && $category->can_delete_full()) {
    mtrace(get_string('coursecategorydeleted', '', $category->get_formatted_name()));
    $deletedcourses = $category->delete_full(true);
    foreach ($deletedcourses as $course) {
        echo mtrace(get_string('coursedeleted', '', $course->shortname));
    }
} elseif ($options['mode']== 'move' && $category->can_move_content_to($options['newcategory'])) {
    $category->delete_move($options['newcategory'], true);
}
