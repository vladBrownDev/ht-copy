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

/**
 * ${PLUGINNAME} file description here.
 *
 * @package    ${PLUGINNAME}
 * @copyright  2021 SysBind Ltd. <service@sysbind.co.il>
 * @auther     chen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once("$CFG->libdir/clilib.php");
require_once("$CFG->libdir/adminlib.php");

$usage = "This script remove duplicated course (for now just compare with two instances)
 
Usage:
    # php remove_duplicate_courses.php  --diff=true --catsrc=categoryidsrc --catdest=categoryiddest 
    --dbname=dbname  --dbuser=dbuser --dbpass=dbpass --justcompare=true
    # php remove_duplicate_courses.php [--help|-h]
 
Options:
    -h --help               Print this help.
    --diff=<value>          true if compare with two instances, false if not (false while execute later)
    --catsrc=<number>       (optional) The category id of the source instance
    --catdest=<number>      The category id of the source instance or the category id we want to check
    --dbname=<value>        (optional) source database name
    --dbuser=<value>        (optional) source database user
    --dbpass=<value>        (optional) source database password
    --justcompare<value>    (optional) ture if you want only compare
";

list($options, $unrecognised) = cli_get_params([
    'help' => false,
    'diff' => false,
    'catsrc' => null,
    'catdest' => null,
    'dbname' => null,
    'dbuser' => null,
    'dbpass' => null,
    'justcompare' => false

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

if (!empty($options['diff']) && $options['diff']) {

    if (empty($options['dbname'])) {
        cli_error('Missing mandatory argument dbname.', 2);
    }

    if (empty($options['dbpass'])) {
        cli_error('Missing mandatory argument dbpass.', 2);
    }

    if (empty($options['dbuser'])) {
        cli_error('Missing mandatory argument dbuser.', 2);
    }

}

if (empty($options['catdest'])) {
    cli_error('Missing mandatory argument catdest.', 2);
}

$catdest = $options['catdest'];

if ($options['diff']) {
    $catsrc = empty($options['catsrc']) ? null : $options['catsrc'];
    $srccfg = new stdClass();
    $srccfg->dbname = $options['dbname'];
    $srccfg->dbpass = $options['dbpass'];
    $srccfg->dbuser = $options['dbuser'];
    $justcompare = $options['justcompare'];
    $dbsrc = \moodle_database::get_driver_instance($CFG->dbtype, $CFG->dblibrary, true);
    $dbsrc->connect($CFG->dbhost, $srccfg->dbuser, $srccfg->dbpass,
        $srccfg->dbname, $CFG->prefix, $CFG->dboptions);
    if (!empty($catsrc)) {
        $srccourses = $dbsrc->get_records_sql("SELECT c.shortname, c.id FROM mdl_course c INNER JOIN mdl_course_categories cc 
                    ON cc.id = c.category WHERE (" . $dbsrc->sql_like('cc.path', ':catid')
            . ' OR ' . $dbsrc->sql_like('cc.path', ':catid1') . ')',
            ['catid1' => '%/' . $catsrc . '?', 'catid' => '%/' . $catsrc . '/%']);
    } else {
        $srccourses = $dbsrc->get_records_sql("SELECT shortname, id FROM mdl_course");
    }
    if (!$justcompare) {
        $destcourses = $DB->get_records_sql("SELECT c.id, c.shortname, c.category FROM mdl_course c INNER JOIN mdl_course_categories cc ON
            cc.id = c.category WHERE (" . $dbsrc->sql_like('cc.path', ':catid')
            . ' OR ' . $dbsrc->sql_like('cc.path', ':catid1') . ')',
            ['catid1' => '%/' . $catdest , 'catid' => '%/' . $catdest . '/%']);

        foreach ($destcourses as $destcours) {
            if (!array_key_exists($destcours->shortname, $srccourses)) {
                cli_writeln('Start deleting course id ' . $destcours->id . ' short name: ' . $destcours->shortname .
                    ' From category ' . $destcours->category);
                $delete = delete_course($destcours->id, true);
                cli_writeln('Status: ' . $delete);
            }
        }
        cli_writeln('Done!');
    } else {
        $destcourses = $DB->get_records_sql("SELECT c.shortname,c.id, c.category FROM mdl_course c INNER JOIN mdl_course_categories cc ON
            cc.id = c.category WHERE (" . $dbsrc->sql_like('cc.path', ':catid')
            . ' OR ' . $dbsrc->sql_like('cc.path', ':catid1') . ')',
            ['catid1' => '%/' . $catdest, 'catid' => '%/' . $catdest . '/%']);
        foreach ($srccourses as $srccoure) {
            if (!array_key_exists($srccoure->shortname, $destcourses)) {
                cli_writeln('The course id ' . $srccoure->id . ' short name: ' . $srccoure->shortname
                    . " doesn't exist in destenation");
            }
        }
        cli_writeln('----------------------------------------------------- ');
        foreach ($destcourses as $destcours) {
            if (!array_key_exists($destcours->shortname, $srccourses)) {
                cli_writeln('The course with id ' . $destcours->id . ' short name: ' . $destcours->shortname .
                    ' From category ' . $destcours->category . ' is\'nt exist in source');
            }
        }
    }
}
