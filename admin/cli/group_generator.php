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
 * Page to handle actions associated with badges management.
 *
 * @package    CLI
 * @copyright  2019 SysBind Ltd {@link https://sysbind.co.il/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Chen chen@sysbind.co.il
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once("$CFG->libdir/clilib.php");
require_once("$CFG->libdir/adminlib.php");

global $DB;

$schools = $DB->get_records('moereports_reports', null, '', '*', 0, 10);
$numgroup = 20;
$year = get_config('local_auto_course_create', 'year');
$count = 1;
$groupsnames = array();
foreach ($schools as $school) {
    for ($i = 1; $i <= $numgroup; $i++) {
        $group = new stdClass();
        $group->id = $count;
        $schoolinf = new stdClass();
        $schoolinf->schoolCode = $school->symbol;
        $schoolinf->schoolname = $school->name;
        $schoolinf->region = 'חיפה';
        $schoolinf->educationtype = 2;
        $schoolinf->sector = 1;
        $schoolinf->educationauthority = $school->educationauthority;
        $schoolinf->ownership = $school->ownership;
        $group->school = $schoolinf;
        $group->year = $year;
        $group->name = "מתמטיקה ח2-ח3 - דוגמה ".(string)$i;
        $group->subjectCode = 87;
        $group->users = null;
        $group->level = null;
        $group->created = "2019-09-10T09:39:21";
        $group->modified = "2019-11-11T09:18:06";
        $groupsnames[] = $group;
        $count ++;
    }
}

$fp = fopen('/data/html/groups_names.json', 'w');
fwrite($fp, json_encode($groupsnames));
fclose($fp);
foreach ($groupsnames as $groupsname) {
    $users = array();
    for ($j = 0; $j < 26; $j++) {
        $userinfo = new stdClass();
        if ($j < 10) {
            $userinfo->username = '123456789' . (string)$j;
        } else {
            $userinfo->username = '12345678' . (string)$j;
        }
        $userinfo->firstName = 'תלמיד';
        $userinfo->lastName = (string) $j;
        $userinfo->userClass = 8;
        $userinfo->level = 2;
        $userinfo->role = 'student';
        $userinfo->created = "2019-09-10T09:44:03";
        $userinfo->finishDate = "2100-11-11T09:18:06";
        $users[] = $userinfo;
    }
    $groupsname->users = $users;
    $fp = fopen('/data/html/group_info'.$groupsname->id.'.json', 'w');
    fwrite($fp, json_encode($groupsname));
    fclose($fp);
}
