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
 * Page module version information
 *
 * @package     mod_listgrades
 * @copyright   2023 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/mod/listgrades/lib.php');
require_once($CFG->dirroot.'/mod/listgrades/locallib.php');
require_once($CFG->libdir.'/completionlib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.


if (!$cm = get_coursemodule_from_id('listgrades', $id)) {
    throw new \moodle_exception('invalidcoursemodule');
}
$listgrades = $DB->get_record('listgrades', ['id' => $cm->instance], '*', MUST_EXIST);

$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/listgrades:view', $context);

// Completion and trigger events.
listgrades_view($listgrades, $course, $cm, $context);

$PAGE->set_url('/mod/listgrades/view.php', ['id' => $cm->id]);

$options = empty($listgrades->displayoptions) ? [] : (array) unserialize_array($listgrades->displayoptions);

$activityheader = ['hidecompletion' => false];
if (empty($options['printintro'])) {
    $activityheader['description'] = '';
}

$PAGE->add_body_class('limitedwidth');
$PAGE->set_title($course->shortname.': '.$listgrades->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($listgrades);
if (!$PAGE->activityheader->is_title_allowed()) {
    $activityheader['title'] = "";
}

$PAGE->activityheader->set_attrs($activityheader);
echo $OUTPUT->header();
// Check if time is between opendate and closedate if set.
$isopen = ($listgrades->opendate == 0 || $listgrades->opendate < time())
        && ($listgrades->closedate == 0 || $listgrades->closedate > time());
// Check if user can manage the listgrades.
if (!$isopen) {
    echo $OUTPUT->box_start('generalbox center clearfix');
    echo $OUTPUT->notification(get_string('notopen', 'listgrades'), 'notifyproblem');
    echo $OUTPUT->box_end();
}
if ($isopen || has_capability('moodle/course:manageactivities', $context)) {
    // Grdoup mode.
    groups_print_activity_menu($cm, $PAGE->url);
    $groupid = groups_get_activity_group($cm, true) ?: null;

    // Print intro text.
    echo $OUTPUT->box_start('generalbox center clearfix');
    echo format_module_intro('listgrades', $listgrades, $cm->id);
    echo $OUTPUT->box_end();

    // Use grader report to get the grades of the students.
    $grader = new grade_report_listing($course->id, null, $context);
    $grader->load_users();
    $grader->load_final_grades();

    $config = get_config('listgrades');
    $mask = $config->userfieldmask;

    $items = array_keys(unserialize($listgrades->items));

    // Get the items that are to be published.
    // Create the headers.
    $headers = [];
    if ($config->showusername) {
        $headers[] = get_string('fullnameuser');
    }
    if ($config->showuserfield == 'always') {
        $headers[] = 'ID';
    }
    $gradeitems = $grader->get_gradeitems();
    $itemnames = $grader->get_item_names();

    $listeditems = [];
    // Extract the items preserving the order of gradetree.
    foreach ($items as $itemid) {
        $item = $gradeitems[abs($itemid)];
        $listeditems[$itemid] = $item;
        if ($itemid < 0) {
            $headertext = get_string('feedbackforgradeitems', 'grades', $itemnames[-$itemid]);
        } else {
            $headertext = $itemnames[$itemid];
        }
        // Add the range of the grade item formatted to $gradeitem->get_decimals() decimals.
        if ($itemid > 0 ) {
            $headertext .= " (" . $item->get_formatted_range() . ")";
        }
        $headers[] = $headertext;
    }

    $grades = $grader->get_grades();
    // Get users to be graded.
    $users = $grader->get_users();
    // Print a table with the grades.
    // Create table object.
    $table = new html_table();
    $table->head = $headers;

    $table->data = [];
    // Get if the userfield is a custom field.
    $userfield = $config->userfield;
    $customfields = profile_get_custom_fields();
    $iscustomfield = false;
    foreach ($customfields as $field) {
        if ($userfield == $field->shortname) {
            $iscustomfield = true;
            $fieldid = $field->id;
            break;
        }
    }
    // Load full user records.
    $users = $DB->get_records_list('user', 'id', array_keys($users));

    // Get users with userfield.
    if ($iscustomfield) {
        // Get custom field values.
        $ids = array_keys($users);
        [$sql_in, $params] = $DB->get_in_or_equal($ids);
        // To Lower case.
        $userfield = strtolower($userfield);
        // Join the user_info_data table with the user table on userid and fieldid.
        $sql = "SELECT d.userid, d.data as $userfield
                 FROM {user_info_data} d
                 WHERE d.fieldid = ?
                   AND d.userid $sql_in
                 ORDER BY d.data";
        $paramssql = [$fieldid];
        $paramssql = array_merge($paramssql, $params);
        $customfields = $DB->get_records_sql($sql, $paramssql);
        // Reset custom field.
        foreach ($ids as $userid) {
            $users[$userid]->$userfield = '';
        }
        // Add custom field to users.
        foreach ($customfields as $userid => $customfield) {
            $users[$userid]->$userfield = $customfield->$userfield;
        }  
    }
    
    $graderurl = new moodle_url('/grade/report/user/index.php', ['id' => $course->id]);
    $group = groups_get_members($groupid, 'u.id', 'u.id');
    $namecollisions = [];
    // Compute full names.
    foreach ($users as $user) {
        $user->fullname = "{$user->lastname}, {$user->firstname}";
    }
    // if showuserfield is onlyifnamecollide, get the users that have the same username.
    if ($config->showuserfield == 'onlyifnamecollide') {
        // Get the list of users that have the same fullname.
        $namecollisions = array_keys(array_count_values(array_column($users, 'fullname')), 2);
    }
    // Iterate over the users.
    foreach ($users as $user) {
        if ($groupid != null && !array_key_exists($user->id, $group)) {
            continue;
        }
        $row = [];
        // Get the grade of the student.
        $grade = $grades[$user->id];
        $id = $user->$userfield;
        if ($id == null) {
            debugging("User $user->id does not have a $userfield");
        }
        if ($config->aepdmethod) {
            $maskeduserfield = listgrades_mask_identifier_aepd($id);
        } else {
            $maskeduserfield = listgrades_mask($id, $mask);
        }
        $graderurl->param('userid', $user->id);
        // Print user fullname.
        if ($config->showusername) {
            $nametoshow = $user->fullname;
            if ($config->showuserfield == 'onlyifnamecollide' && in_array($user->fullname, $namecollisions)) {
                $nametoshow .= " ($maskeduserfield)";
            }
            $row[] = $nametoshow;
        }
        if ($config->showuserfield == 'always') {
            $row[] = $maskeduserfield;
        }
        // Collect the gradeitems.
        foreach ($listeditems as $itemid => $item) {
            if ($itemid < 0) {
                $gradevalue = $grade[$item->id];
                $gradestr = $gradevalue->feedback ? $gradevalue->feedback : '--';
            } else {
                $gradevalue = $grade[$item->id];
                $gradestr = $gradevalue->finalgrade ? format_float($gradevalue->finalgrade, $gradevalue->grade_item->get_decimals()) : '--';
            }
            if ($USER->id == $user->id) {
                $row[] = html_writer::link($graderurl, $gradestr);
            } else {
                $row[] = $gradestr;
            }
        }

        // Add the userfield and the grades to the table.
        $table->data[] = $row;
    }
    // Sort the table by first column.
    array_multisort($table->data);

    // Print the table.
    echo html_writer::table($table);

    $signature = file_rewrite_pluginfile_urls($listgrades->footer, 'pluginfile.php', $context->id, 'mod_listgrades', 'footer', 0);
    $formatoptions = new stdClass;
    $formatoptions->noclean = true;
    $formatoptions->overflowdiv = true;
    $formatoptions->context = $context;
    $signature = format_text($signature, $listgrades->footerformat, $formatoptions);
    echo $OUTPUT->box($signature, "generalbox center clearfix");

}

echo $OUTPUT->footer();
