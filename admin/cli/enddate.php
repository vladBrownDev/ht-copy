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

// This script close all course in archive categories to specfied date
// @package core
// @since Moodle 3.5
// @copyright 2019 SysBind Ltd.
// @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir.'/clilib.php');

list ($options, $unrecognized) = cli_get_params([
        'help' => false,
        'year' => null,
        'date' => '',
    ],
    [
        'h' => 'help',
        'y' => 'year',
        'd' => 'date',
    ]
);
if ($options['help']) {
    $help = <<<EOL
Perform duplicate of course into category n times.

Options:
--year=INTEGER            If the archive sort by years you can specify which year actegory to 
                          set course end date 
--date=STRING             The date to set to course close date in format of YYYYmmdd
-h, --help                Print out this help.

Example:
\$sudo -u www-data /usr/bin/php admin/cli/enddate.php --date=20200701 --year=2020 \n
EOL;

    echo $help;
    die;
}
$date = empty($options['date'] && is_number($options['date'])) ? time() : strtotime($options['date']);

global $DB;
if (!empty($options['year'] && is_number($options['year']))) {
    $archives = $DB->get_records('course_categories',['idnumber' => $options['year']]);
} else {
    $archives = $DB->get_records_sql('SELECT id FROM {course_categories} WHERE name LIKE "%ארכיון%"');
}
foreach ($archives as $archive) {
    $all = '';
    $allcat = get_all_cat($archive->id, $all);
    $DB->execute("UPDATE {course} set enddate=$date WHERE category in ($allcat)");
}

function get_all_cat( $cat, $all ) {
    global $DB;

    if (!$cats = $DB->get_fieldset_sql("SELECT id FROM {course_categories} WHERE parent = ? ", [$cat])) {
        if (empty($all)) {
            return $cat;
        }
        return $all.','.$cat;
    } else {
        if (empty($all)) {
            $all .= $cat;
        } else {
            $all .= ','.$cat;
        }
        foreach ($cats as $cate) {
            $all = get_all_cat($cate, $all);
        }
    }
    return $all;
}
