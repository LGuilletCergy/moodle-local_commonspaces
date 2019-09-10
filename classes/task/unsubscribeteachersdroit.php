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
 * Initially developped for :
 * Université de Cergy-Pontoise
 * 33, boulevard du Port
 * 95011 Cergy-Pontoise cedex
 * FRANCE
 *
 * Creates common courses (one for each faculty).
 *
 * @package   local_commonspaces
 * @copyright 2018 Brice Errandonea <brice.errandonea@u-cergy.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * File : classes/task/unsubscribeteachersdroit.php
 * Cron task unsubscribing from forums teachers of UFR Droit.
 */
namespace local_commonspaces\task;

defined('MOODLE_INTERNAL') || die();

class unsubscribeteachersdroit extends \core\task\scheduled_task {

    public function get_name() {

	$name = get_string('unsubscribeteachersdroit', 'local_commonspaces');
        return $name;
    }

    public function execute() {

        global $DB, $CFG;

        $idnumber = $CFG->yearprefix.'-1COMMON-1MSG';
        $course = $DB->get_record('course', array('idnumber' => $idnumber));
        $contextid = \context_course::instance($course->id)->id;

        $roleteacherid = $DB->get_record('role', array('shortname' => 'teacher'))->id;
        $listteachersassignments = $DB->get_records('role_assignments',
                array('roleid' => $roleteacherid, 'contextid' => $contextid));

        $roleappuiadminid = $DB->get_record('role', array('shortname' => 'appuiadmin'))->id;
        $listappuiadminsassignments = $DB->get_records('role_assignments',
                array('roleid' => $roleappuiadminid, 'contextid' => $contextid));

        $listforums = $DB->get_records('forum', array('courseid' => $course->id));

        foreach ($listforums as $forum) {

            foreach ($listteachersassignments as $teacherassignment) {

                $userid = $teacherassignment->userid;

                $DB->delete_records('forum_subscriptions', array('userid' => $userid, 'forum' => $forum->id));
            }

            foreach ($listappuiadminsassignments as $appuiadminassignment) {

                $userid = $appuiadminassignment->userid;

                $DB->delete_records('forum_subscriptions', array('userid' => $userid, 'forum' => $forum->id));
            }
        }
    }
}