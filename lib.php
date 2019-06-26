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
 * File : lib.php
 * Functions library
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/coursecatlib.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/group/lib.php');

function local_commonspaces_createspaces() {

    global $CFG, $DB;
    $now = time();
    $foldermodule = $DB->get_record('modules', array('name' => 'folder'));
    $studentrole = $DB->get_record('role', array('shortname' => 'student'));

    $facultycodes = array('1', '2', '3', '4', '5', '7', 'A', 'B');

    foreach ($facultycodes as $facultycode) {

        echo "$facultycode<br>";
        $facultycategory = $DB->get_record('course_categories', array('idnumber' => "$CFG->yearprefix-$facultycode"));
        $course = local_commonspaces_createcourse($facultycode, $facultycategory);
        $levelsgrouping = local_commonspaces_grouping('levels', $course);
        $trainingsgrouping = local_commonspaces_grouping('trainings', $course);
        $levelcategories = $DB->get_records('course_categories', array('parent' => $facultycategory->id), 'name');

        foreach ($levelcategories as $levelcategory) {

            $levelcohort = local_commonspaces_levelcohort($levelcategory);
            $levelgroup = local_commonspaces_group($levelsgrouping, $levelcategory);

            if ($levelcohort && $levelgroup) {

                local_commonspaces_groupcohort($levelcohort, $levelgroup, $studentrole, $course);
            }

            $trainingcategories = $DB->get_records('course_categories', array('parent' => $levelcategory->id), 'name');

            if ($trainingcategories) {

                //Trainings' groups and cohorts are created inside this function.
                //~ if ($levelcategory->idnumber == 'Y2018-1L1') {
                local_commonspaces_section($course, $levelcategory, $trainingcategories, $trainingsgrouping,
                        $now, $foldermodule->id, $studentrole);
            //~ }
            }
        }
        $levelsforum = local_commonspaces_newsforum($course, $levelsgrouping);
        $trainingsforum = local_commonspaces_newsforum($course, $trainingsgrouping);
    }
}

function local_commonspaces_groupcohort($cohort, $group, $role, $course) {

    global $DB;
    $enrolmethod = $DB->get_record('enrol',
            array('enrol' => 'cohort', 'courseid' => $course->id, 'customint1' => $cohort->id));

    if (!$enrolmethod) {

        $cohortplugin = enrol_get_plugin('cohort');
        $cohortplugin->add_instance($course,
                array('customint1' => $cohort->id, 'roleid' => $role->id, 'customint2' => $group->id));
        $trace = new null_progress_trace();
        enrol_cohort_sync($trace, $course->id);
        $trace->finished();
        $cohortmembers = $DB->get_records('cohort_members', array('cohortid' => $cohort->id));

        foreach ($cohortmembers as $cohortmember) {

            $groupmember = $DB->get_record('groups_members',
                    array('groupid' => $group->id, 'userid' => $cohortmember->userid));

            if (!$groupmember) {

                $groupmember = new stdClass();
                $groupmember->groupid = $group->id;
                $groupmember->userid = $cohortmember->userid;
                $groupmember->timeadded = $now;
                $groupmember->id = $DB->insert_record('groups_members', $groupmember);
            }
        }
    }
}

function local_commonspaces_levelcohort($levelcategory) {

    global $CFG, $DB;
    $levelidnumberarray = explode('-', $levelcategory->idnumber);
    $cohortidnumber = $CFG->yearprefix.'-S-'.$levelidnumberarray[1];
    $cohort = $DB->get_record('cohort', array('idnumber' => $cohortidnumber));
    return $cohort;
}

function local_commonspaces_createcourse($facultycode, $facultycategory) {

    global $CFG, $DB;
    $facultyname = explode(':', $facultycategory->name);
    $levelidnumber = "$CFG->yearprefix-$facultycode".'AU';
    $levelcategory = $DB->get_record('course_categories',
            array('idnumber' => $levelidnumber, 'parent' => $facultycategory->id));
    $vetidnumber = "$CFG->yearprefix-$facultycode".'COMMON';
    $vetcategory = $DB->get_record('course_categories',
            array('idnumber' => $vetidnumber, 'parent' => $levelcategory->id));

    if (!$vetcategory) {

        $vetname = get_string('commonspacefor', 'local_commonspaces').substr($facultyname[1], 1);
        echo "Création de la catégorie $vetname<br>";
        $vetdata = array('name' => $vetname, 'idnumber' => $vetidnumber, 'parent' => $levelcategory->id, 'visible' => 1);
        $vetcategory = coursecat::create($vetdata);
    }

    $courseidnumber = $vetidnumber.'-'.$facultycode.'MSG';
    $course = $DB->get_record('course', array('category' => $vetcategory->id, 'idnumber' => $courseidnumber));

    if (!$course) {


        $coursename = get_string('studentmessages', 'local_commonspaces').substr($facultyname[1], 1);
        echo "Création du cours $coursename<br>";
        $coursename = local_commonspaces_tryshortname($coursename, 0);
        $coursedata = new stdClass();
        $coursedata->fullname = $coursename;
        $coursedata->shortname = $coursename;
        $coursedata->category = $vetcategory->id;
        $coursedata->idnumber = $courseidnumber;
        $coursedata->visible = 0;
        $coursedata->format = 'topics';
        $course = create_course($coursedata);
        $firstsection = $DB->get_record('course_sections', array('course' => $course->id, 'section' => 0));
        $firstsection->name = get_string('importantmessages', 'local_commonspaces');
        $DB->update_record('course_sections', $firstsection);
    }

    return $course;
}

function local_commonspaces_tryshortname ($coursename, $i) {

    global $DB;

    $newshortname = $coursename;

    if ($i) {

        $newshortname .= "_$i";
    }

    $already = $DB->record_exists('course', array('shortname' => $newshortname));

    if ($already) {

        return local_commonspaces_tryshortname($coursename, $i + 1);
    } else {

        return $newshortname;
    }
}

function local_commonspaces_grouping($type, $course) {

    global $DB;

    $groupingname = get_string($type, 'local_commonspaces');
    $grouping = $DB->get_record('groupings', array('courseid' => $course->id, 'name' => $groupingname));

    if (!$grouping) {

        echo "Création du groupement $groupingname<br>";
        $grouping = new stdClass();
        $grouping->courseid = $courseid;
        $grouping->name = $groupingname;
        $grouping->description = '';
        $grouping->id = groups_create_grouping($grouping);
    }

    return $grouping;
}

function local_commonspaces_group($grouping, $category) {

    global $DB;

    $group = $DB->get_record('groups', array('idnumber' => $category->idnumber));

    if (!$group) {

        $group = new stdClass();
        $group->courseid = $grouping->courseid;
        $group->name = $category->name;
        $group->idnumber = $category->idnumber;
        $group->id = groups_create_group($group);
        groups_assign_grouping($grouping->id, $group->id);
    }

    return $group;
}

//~ function local_commonspaces_cohorts($courseid, $facultycode, $type) {
	//~ global $CFG, $DB;

    //~ $groupingname = get_string($type, 'local_commonspaces');
    //~ $grouping = $DB->get_record('groupings', array('courseid' => $courseid, 'name' => $groupingname));
    //~ if (!$grouping) {
		//~ echo "Création du groupement $groupingname<br>";
        //~ $grouping = new stdClass();
        //~ $grouping->courseid = $courseid;
        //~ $grouping->name = $groupingname;
        //~ $grouping->description = '';
        //~ $grouping->id = groups_create_grouping($grouping);
	//~ }

    //~ if ('type' == 'levels') {
	    //~ $pattern = "$CFG->yearprefix-S$facultycode%";
	    //~ $typecohort = 'niveau';
	//~ } else {
		//~ $pattern = "$CFG->yearprefix-$facultycode%";
		//~ $typecohort = 'vet';
	//~ }
    //~ $sqlcohorts = "SELECT *
	               //~ FROM {cohort} c, {local_cohortmanager_info} lci
	               //~ WHERE c.idnumber LIKE '$pattern'
	               //~ AND c.id = lci.cohortid
	               //~ AND lci.typecohort = '$typecohort'";
	//~ $cohorts = $DB->get_records_sql($sqlcohorts);

	//~ foreach ($cohorts as $cohort) {
		//~ $cohortenrol = $DB->get_record('enrol', array('enrol' => 'cohort', 'courseid' => $courseid, 'customint1' => $cohort->id));
		//~ if (!$cohortenrol) {
			//~ echo "Création du groupe $cohort->name<br>";
		    //~ $newgroup = new stdClass();
            //~ $newgroup->courseid = $courseid;
            //~ $newgroup->name = $cohort->name;
            //~ $newgroupid = groups_create_group($newgroup);
            //~ groups_assign_grouping($grouping->id, $newgroupid);
            //~ $studentrole = $DB->get_record('role', array('shortname' => 'student'));
            //~ $cohortplugin = enrol_get_plugin('cohort');
            //~ $cohortplugin->add_instance($course, array('customint1' => $cohort->id, 'roleid' => $studentrole->id,
                //~ 'customint2' => $newgroupid));
            //~ $trace = new null_progress_trace();
            //~ enrol_cohort_sync($trace, $course->id);
            //~ $trace->finished();
	    //~ }
	//~ }

	//~ return $grouping;
//~ }

function local_commonspaces_newsforum($course, $grouping) {

    global $DB;

    $title = get_string('toallstudents', 'local_commonspaces');
    $module = $DB->get_record('modules', array('name' => 'forum'));
    $groupingcmdata = array('course' => $course->id, 'module' => $module->id, 'groupmode' => 1,
        'groupingid' => $grouping->id); // 1 : "groupes séparés".
    $groupingcms = $DB->get_records('course_modules', $groupingcmdata);
    // Forums linked to the grouping (maybe not news forums).
    $groupingnewsforum = null;

    foreach ($groupingcms as $groupingcm) {

        $groupingforum = $DB->get_record('forum', array('id' => $groupingcm->instance));

        if ($groupingforum->type == 'news') {

            $groupingnewsforum = $groupingforum;

            if ($groupingnewsforum->name != $title) {

                $DB->set_field('forum', 'name', $title, array('id' => $groupingnewsforum->id));
            }
        }
    }
    if (!$groupingnewsforum) {

        echo "Création d'un forum pour $grouping->name<br>";
        $moduleinfo = new stdClass();
        $moduleinfo->modulename = 'forum';
        $moduleinfo->name = $title;
        $moduleinfo->course = $course->id;
        $moduleinfo->groupmode = 1;
        $moduleinfo->groupingid = $grouping->id;
        $moduleinfo->grade = 100;
        $moduleinfo->cmidnumber = '';
        $moduleinfo->section = 0;
        $moduleinfo->visible = 1;
        $moduleinfo->introeditor = array('text' => '', 'format' => 0, 'itemid' => 0);
        $moduleinfo->page_after_submit_editor = array('text' => '', 'format' => 0, 'itemid' => 0);
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->page_after_submit = '';
        $moduleinfo->type = 'news';
        $moduleinfo->forcesubscribe = 1;
        $groupingnewsforum = create_module($moduleinfo);
    }

    return $groupingnewsforum;
}

function local_commonspaces_section($course, $levelcategory, $trainingcategories, $trainingsgrouping,
        $now, $foldermoduleid, $studentrole) {

    global $DB;
    $sectionname = get_string('documentsfor', 'local_commonspaces').' '.$levelcategory->name;
    $section = $DB->get_record('course_sections', array('course' => $course->id, 'name' => $sectionname));

    if (!$section) {

        $lastsql = "SELECT MAX(section) AS last FROM {course_sections} WHERE course = $course->id";
        $lastsection = $DB->get_record_sql($lastsql);
        $nextsection = $lastsection->last + 1;
        echo "Création de la section $levelcategory->name ($nextsection)<br>";
        $section = new stdClass();
        $section->course = $course->id;
        $section->section = $nextsection;
        $section->name = $sectionname;
        $section->summary = '';
        $section->summaryformat = 1;
        $section->sequence = '';
        $section->visible = 1;
        $section->timemodified = $now;
        $section->id = $DB->insert_record('course_sections', $section);
    }

    // Create one group and one folder for each training category
    foreach ($trainingcategories as $trainingcategory) {

        // Skip category "Common space for ...".
        $needed = false;
        $commonspaceprefix = get_string('commonspacefor', 'local_commonspaces');

        if (strpos($trainingcategory->name, $commonspaceprefix) === false) {

            $needed = true;
        }

        if ($needed) {

            $traininggroup = local_commonspaces_group($trainingsgrouping, $trainingcategory);
            $trainingcohort = $DB->get_record('cohort', array('idnumber' => $trainingcategory->idnumber));

            if ($trainingcohort) {

                local_commonspaces_groupcohort($trainingcohort, $traininggroup, $studentrole, $course);
            }

            $foldercmid = local_commonspaces_folder($course, $section, $trainingcategory, $traininggroup, $foldermoduleid);
            // Restrict folder access to this group.
            local_commonspaces_restrict($foldercmid, $traininggroup);
        }
    }
}

//~ function local_commonspaces_groupcohort($traininggroup) {
	//~ //TODO
	//~ global $DB;
	//~ $enrolmethod = $DB->get_record('enrol', array('enrol' => 'cohort', 'courseid' => $traininggroup->courseid, 'customint2' => $traininggroup->id));
	//~ if (!$enrolmethod) {
		//~ $cohort = $DB->get_record('cohort', array('idnumber' => $traininggroup->idnumber));
		//~ if ($cohort) {
			//~ $studentrole = $DB->get_record('role', array('shortname' => 'student'));
            //~ $cohortplugin = enrol_get_plugin('cohort');
            //~ $cohortplugin->add_instance($course, array('customint1' => $cohort->id, 'roleid' => $studentrole->id,
                                                       //~ 'customint2' => $traininggroup->id));
            //~ $trace = new null_progress_trace();
            //~ enrol_cohort_sync($trace, $course->id);
            //~ $trace->finished();
		//~ }
	//~ }
//~ }

function local_commonspaces_folder($course, $section, $trainingcategory, $traininggroup, $foldermoduleid) {

    global $DB;
    $folder = $DB->get_record('folder', array('course' => $course->id, 'name' => $trainingcategory->name));

    if ($folder) {

        $foldercm = $DB->get_record('course_modules', array('module' => $foldermoduleid, 'instance' => $folder->id));
        $foldercmid = $foldercm->id;
    } else {

        echo "Création d'un dossier pour $trainingcategory->name dans la section $section->name<br>";
        $moduleinfo = new stdClass();
        $moduleinfo->modulename = 'folder';
        $moduleinfo->name = $trainingcategory->name;
        $moduleinfo->course = $course->id;
        $moduleinfo->section = $section->section;
        $moduleinfo->visible = 1;
        $moduleinfo->introeditor = array('text' => '', 'format' => 0, 'itemid' => 0);
        $moduleinfo->page_after_submit_editor = array('text' => '', 'format' => 0, 'itemid' => 0);
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->page_after_submit = '';
        $moduleinfo->type = 'news';
        $moduleinfo->forcesubscribe = 1;
        $moduleinfo = create_module($moduleinfo);
        $foldercmid = $moduleinfo->coursemodule;
    }

    return $foldercmid;
}

function local_commonspaces_restrict($foldercmid, $traininggroup) {

    global $DB;
    $availability = '{"op":"&","c":[{"type":"group","id":';
    $availability .= $traininggroup->id;
    $availability .= '}],"showc":[false]}';
    $DB->set_field('course_modules', 'availability', $availability, array('id' => $foldercmid));
}
