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

require(__DIR__ . '/../../config.php');
require_once("$CFG->libdir/clilib.php");
require_once("$CFG->libdir/filelib.php");
require_once("$CFG->libdir/adminlib.php");

$usage = "This script copy the archive categories and their courses to archive instance
 
Usage:
    # php move_to_archive_instance.php --archiveinstance='https://newinstance.com' --archivemoodle='/var/www/html/newinstance --archivecat=34' 
    # php move_to_archive_instance.php [--help|-h]
 
Options:
    -h --help               Print this help.
    --archiveinstance=<value>     Enter the archive instance url example: https://newinstance.com.
    --archivemoodle=<value>     Enter the archive moodle cod directory: /var/www/html/newinstance.
    --archivecat=<number>      (optional) copy specific category
";

list($options, $unrecognised) = cli_get_params([
        'help' => false,
        'archiveinstance' => null,
        'archivemoodle' => null,
        'archivecat' => null
], [
        'h' => 'help'
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($usage);
    exit(2);
}

if (empty($options['archiveinstance'])) {
    cli_error('Missing mandatory argument archiveinstance.', 2);
}

if (empty($options['archivemoodle'])) {
    cli_error('Missing mandatory argument archivemoodle.', 2);
}

$archiveyears = get_config('local_auto_course_create', 'archive');
$archiveid = get_config('local_auto_course_create', 'archiveid');
$archivecat = !empty($options['archivecat']) ? $archivecat : null ;
if ($archivecat) {
    $categories = $DB->get_records_sql('SELECT * FROM {course_categories} where parent = ? order by idnumber DESC',
            [$archivecat]);
} else {
    $categories = $DB->get_records_sql('SELECT * FROM {course_categories} where parent = ? order by idnumber DESC',
            [$archiveid]);
    array_splice($categories, 0, $archiveyears);
}


$remotesite = $options['archiveinstance'];
$remotemoodle = $options['archivemoodle'];

$token = getenv('WSARCHIVETOKEN');
$skipcertverify = (get_config('local_remote_backup_provider', 'selfsignssl')) ? true : false;
$optionsver = array();
if ($skipcertverify) {
    $options['CURLOPT_SSL_VERIFYPEER'] = false;
    $options['CURLOPT_SSL_VERIFYHOST'] = false;
}
// Build the curl.
$urltosend = $remotesite . '/webservice/rest/server.php?wstoken=' . $token .
        '&wsfunction=core_course_create_categories&moodlewsrestformat=json';

function format_array_postdata($arraydata, $currentdata, &$data) {
    foreach ($arraydata as $k => $v) {
        $newcurrentdata = $currentdata;
        if (is_object($v)) {
            $v = (array) $v;
        }
        if (is_array($v)) { // The value is an array, call the function recursively.
            $newcurrentdata = $newcurrentdata . '[' . urlencode($k) . ']';
            format_array_postdata($v, $newcurrentdata, $data);
        } else { // Add the POST parameter to the $data array.
            $data[] = $newcurrentdata . '[' . urlencode($k) . ']=' . urlencode($v);
        }
    }
}

/**
 * Transform a PHP array into POST parameter
 * (see the recursive function format_array_postdata_for_curlcall)
 *
 * @param array $postdata
 * @return array containing all POST parameters  (1 row = 1 POST parameter)
 */
function format_postdata($postdata) {
    if (is_object($postdata)) {
        $postdata = (array) $postdata;
    }
    $data = array();
    foreach ($postdata as $k => $v) {
        if (is_object($v)) {
            $v = (array) $v;
        }
        if (is_array($v)) {
            $currentdata = urlencode($k);
            format_array_postdata($v, $currentdata, $data);
        } else {
            $data[] = urlencode($k) . '=' . urlencode($v);
        }
    }
    $convertedpostdata = implode('&', $data);
    return $convertedpostdata;
}

function copy_category($cat, $urltosend, $optionsver, $remotemoodle) {
    global $DB, $USER;
    if ($cat->parent == $archiveid = get_config('local_auto_course_create', 'archiveid')) {
        $categories [] = ['name' => $cat->name, 'parent' => 0, 'idnumber' => $cat->idnumber];
    } else {
        $categories [] = ['name' => $cat->name, 'parent' => $cat->parent, 'idnumber' => $cat->idnumber];
    }
    $params = array('categories' => $categories);

    $curl = new curl();

    $params = format_postdata($params);
    $resp = json_decode($curl->post($urltosend, $params, $optionsver));

    if ($resp) {
        cli_writeln('The category ' . $cat->name . ' created successfully in archive');

        $catcourses = $DB->get_records('course', ['category' => $cat->id]);

        foreach ($catcourses as $catcourse) {
            shell_exec('cd ' . $remotemoodle
                    . '&& moosh course-restore /backup/backup-moodle2-course-' . $catcourse->id . '-* '
                    . $resp[0]->id);
            cli_writeln('The course ' . $catcourse->fullname . ' created successfully in archive');
        }
    }
    return $resp[0]->id;
}

function move_all_categories($cat, $urltosend, $optionsver, $remotemoodle) {
    global $DB;
    if (!$cats = $DB->get_records_sql("SELECT id,name,parent,idnumber  FROM {course_categories} WHERE parent = ? ", [$cat->id])) {
        copy_category($cat, $urltosend, $optionsver, $remotemoodle);
        return;

    } else {
        $newparent = copy_category($cat, $urltosend, $optionsver, $remotemoodle);
        foreach ($cats as $cate) {
            $cate->parent = $newparent;
            move_all_categories($cate, $urltosend, $optionsver, $remotemoodle);
        }
    }
}

foreach ($categories as $category) {
    move_all_categories($category, $urltosend, $optionsver, $remotemoodle);
}
