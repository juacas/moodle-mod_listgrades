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
require_once($CFG->dirroot . '/mod/listgrades/lib.php');
require_once($CFG->dirroot . '/mod/listgrades/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.
$requesteduserid = optional_param('userid', 0, PARAM_INT);


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

$activityheader = [
    'hidecompletion' => false,
    // The introduction is rendered explicitly below so it behaves consistently across themes.
    'description' => '',
];

$PAGE->add_body_class('limitedwidth');
$PAGE->set_title($course->shortname . ': ' . $listgrades->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_activity_record($listgrades);
if (!$PAGE->activityheader->is_title_allowed()) {
    $activityheader['title'] = "";
}

$PAGE->activityheader->set_attrs($activityheader);
echo $OUTPUT->header();
// Check if time is between opendate and closedate if set.
$isopen = listgrades_is_publication_open($listgrades, time());
// Check if user can manage the listgrades.
if (!$isopen) {
    echo $OUTPUT->box_start('generalbox center clearfix');
    echo $OUTPUT->notification(get_string('notopen', 'listgrades'), 'notifyproblem');
    echo $OUTPUT->box_end();
}
if ($isopen || has_capability('moodle/course:manageactivities', $context)) {
    echo $OUTPUT->box(
        format_module_intro('listgrades', $listgrades, $cm->id),
        'generalbox center clearfix'
    );

    // Group mode.
    $groupid = groups_get_activity_group($cm, true) ?: null;

    // Use grader report to get the grades of the students.
    $grader = new grade_report_listing($course->id, null, $context);
    $grader->load_users();
    $grader->load_final_grades();

    $config = get_config('listgrades');
    $mask = $config->userfieldmask;

    $items = array_keys((array) unserialize_array($listgrades->items));

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
        if ($itemid > 0) {
            $headertext .= " (" . $item->get_formatted_range() . ")";
        }
        $headers[] = $headertext;
    }

    $grades = $grader->get_grades() ?? [];
    // Get users to be graded.
    $users = $grader->get_users();
    $canviewallgrades = has_capability('moodle/grade:viewall', context_course::instance($course->id));
    $selecteduserid = $USER->id;
    if ($canviewallgrades && $requesteduserid && array_key_exists($requesteduserid, $users)) {
        $selecteduserid = $requesteduserid;
    }

    // Display the group and searchable student selectors together. The student selector is the
    // same component used by the Moodle user grade report.
    $viewurl = new moodle_url('/mod/listgrades/view.php', ['id' => $cm->id]);
    if ($selecteduserid !== $USER->id) {
        $viewurl->param('userid', $selecteduserid);
    }
    $selectors = [];
    $groupselector = groups_print_activity_menu($cm, $viewurl, true);
    if ($groupselector !== '') {
        $selectors[] = $groupselector;
    }
    if ($canviewallgrades) {
        $baseurl = new moodle_url('/mod/listgrades/view.php', ['id' => $cm->id]);
        if ($groupid !== null) {
            $baseurl->param('group', $groupid);
        }
        $PAGE->requires->js_call_amd('gradereport_user/user', 'init', [$baseurl->out(false)]);
        $userselector = new \core_course\output\actionbar\user_selector(
            course: $course,
            resetlink: new moodle_url($baseurl),
            userid: $selecteduserid !== $USER->id ? $selecteduserid : null,
            groupid: $groupid
        );
        $courserenderer = $PAGE->get_renderer('core', 'course');
        $selectors[] = $courserenderer->render($userselector);
    }
    if ($selectors) {
        echo html_writer::div(implode('', $selectors), 'd-flex flex-wrap align-items-end gap-3 mb-3');
    }

    $displaymode = listgrades_resolve_display_mode(
        $listgrades->displaymode ?? null,
        $config->alloweddisplaymodes ?? LISTGRADES_ALLOWED_BOTH,
        $config->defaultdisplaymode ?? LISTGRADES_DISPLAY_INDIVIDUAL
    );
    $currentuser = $DB->get_record('user', ['id' => $selecteduserid], '*', MUST_EXIST);
    if ($displaymode !== LISTGRADES_DISPLAY_LIST) {
        echo listgrades_render_individual_table(
            $items,
            $gradeitems,
            $itemnames,
            $grades[$selecteduserid] ?? null,
            $context,
            $currentuser
        );
    } else {
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
            [$sqlin, $params] = $DB->get_in_or_equal($ids);
            // To Lower case.
            $userfield = strtolower($userfield);
            // Join the user_info_data table with the user table on userid and fieldid.
            $sql = "SELECT d.userid, d.data as $userfield
                     FROM {user_info_data} d
                     WHERE d.fieldid = ?
                       AND d.userid $sqlin
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
        $group = $groupid === null ? [] : groups_get_members($groupid, 'u.id', 'u.id');
        $namecollisions = [];
        // Compute full names.
        foreach ($users as $user) {
            $user->fullname = "{$user->lastname}, {$user->firstname}";
        }
        // If showuserfield is onlyifnamecollide, get the users that have the same username.
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
                $id = "Student { $user->id } with no ID";
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
                    $gradestr = listgrades_format_feedback($gradevalue, $context);
                } else {
                    $gradevalue = $grade[$item->id];
                    $gradestr = $gradevalue->finalgrade !== null
                        ? format_float($gradevalue->finalgrade, $gradevalue->grade_item->get_decimals()) : '--';
                }
                if ($USER->id == $user->id && $itemid > 0) {
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
    }

    if (!empty($listgrades->showstatistics)) {
        $statuserids = array_keys($users);
        if ($groupid !== null) {
            $groupmembers = groups_get_members($groupid, 'u.id', 'u.id');
            $statuserids = array_values(array_intersect($statuserids, array_keys($groupmembers)));
        }
        echo listgrades_render_statistics(
            $items,
            $gradeitems,
            $itemnames,
            $grades,
            $statuserids,
            $currentuser,
            $course->id,
            has_capability('moodle/course:manageactivities', $context)
        );
    }

    $signature = file_rewrite_pluginfile_urls($listgrades->footer, 'pluginfile.php', $context->id, 'mod_listgrades', 'footer', 0);
    $formatoptions = new stdClass();
    $formatoptions->noclean = true;
    $formatoptions->overflowdiv = true;
    $formatoptions->context = $context;
    $signature = format_text($signature, $listgrades->footerformat, $formatoptions);
    echo $OUTPUT->box($signature, "generalbox center clearfix");
}

// Review actions are available independently of grade-publication dates.
if (listgrades_review_is_enabled($listgrades) && (int) $listgrades->reviewdate > 0) {
    $reviewstates = listgrades_get_review_states((int) $listgrades->id, (int) $USER->id);
    $reviewstate = $reviewstates[$USER->id] ?? [
        'active' => false,
        'requesttime' => 0,
        'requestid' => 0,
        'lastaction' => '',
        'lastcomment' => '',
        'lasttime' => 0,
        'snapshot' => '',
        'comments' => [],
    ];
    $reviewurl = new moodle_url('/mod/listgrades/review.php', ['id' => $cm->id]);
    $reviewdate = (int) $listgrades->reviewdate;
    $reviewopen = time() < $reviewdate;
    $canmanagereviews = has_capability('mod/listgrades:managereviews', $context);

    echo $OUTPUT->heading(get_string('reviewsection', 'listgrades'), 3);
    echo $OUTPUT->box(get_string('reviewdate', 'listgrades') . ': ' . userdate($reviewdate), 'generalbox');

    if ($canmanagereviews) {
        $allreviewstates = listgrades_get_review_states((int) $listgrades->id);
        $reviewcount = count(array_filter($allreviewstates, static fn(array $state): bool => $state['active']));
        $countstring = $reviewcount === 1 ? 'reviewrequestcountone' : 'reviewrequestcount';
        echo $OUTPUT->notification(get_string($countstring, 'listgrades', $reviewcount), 'info');
        $manageurl = new moodle_url('/mod/listgrades/reviews.php', ['id' => $cm->id]);
        echo html_writer::link($manageurl, get_string('reviewmanage', 'listgrades'), ['class' => 'btn btn-primary']);
    } else {
        echo $OUTPUT->box_start('generalbox listgrades-review-student');
        if (!$reviewstate['active']) {
            if ($reviewopen) {
                echo html_writer::tag('p', get_string('reviewnotrequested', 'listgrades'));
                echo html_writer::start_tag('form', ['method' => 'post', 'action' => $reviewurl->out(false)]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
                echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'request']);
                echo html_writer::tag('button', get_string('reviewrequest', 'listgrades'), [
                    'type' => 'submit',
                    'class' => 'btn btn-primary',
                ]);
                echo html_writer::end_tag('form');
            } else {
                echo html_writer::tag('p', get_string('reviewnotrequested', 'listgrades'));
            }
        } else if ($reviewopen && $reviewstate['lastaction'] === 'requested') {
            echo html_writer::tag('p', get_string('reviewrequestedmessage', 'listgrades', userdate($reviewstate['requesttime'])));
            echo html_writer::start_tag('form', ['method' => 'post', 'action' => $reviewurl->out(false)]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'cancel']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'requestid', 'value' => $reviewstate['requestid']]);
            echo html_writer::tag('button', get_string('reviewcancel', 'listgrades'), [
                'type' => 'submit',
                'class' => 'btn btn-secondary',
            ]);
            echo html_writer::end_tag('form');
        } else if (in_array($reviewstate['lastaction'], ['validated', 'commented'], true)) {
            $outcome = $reviewstate['lastaction'] === 'validated'
                ? get_string('reviewoutcomevalidated', 'listgrades')
                : get_string('reviewoutcomecommented', 'listgrades');
            echo html_writer::tag('p', $outcome . ' (' . userdate($reviewstate['lasttime']) . ')');
        }
        if ($reviewstate['comments']) {
            echo html_writer::tag('h4', get_string('reviewcommenthistory', 'listgrades'));
            echo listgrades_render_review_history($reviewstate['comments'], $context);
        } else if ($reviewopen && $reviewstate['active'] && $reviewstate['lastaction'] === 'requested') {
            echo html_writer::tag('p', get_string('reviewwaiting', 'listgrades'));
        }
        if ($reviewstate['active'] && !$reviewopen && $reviewstate['lastaction'] === 'requested') {
            $draft = $SESSION->listgradesreviewdrafts[$cm->id] ?? null;
            unset($SESSION->listgradesreviewdrafts[$cm->id]);
            $draftvalid = $draft && $draft['userid'] === (int) $USER->id &&
                $draft['requestid'] === $reviewstate['requestid'];
            echo html_writer::tag('p', get_string('reviewresult_help', 'listgrades'));
            echo html_writer::start_tag('form', [
                'method' => 'post', 'action' => $reviewurl->out(false), 'id' => 'review-response',
            ]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'complete']);
            echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'requestid', 'value' => $reviewstate['requestid']]);
            echo html_writer::start_tag('fieldset', ['class' => 'mb-3']);
            echo html_writer::tag('legend', get_string('reviewresult', 'listgrades'), ['class' => 'h6']);
            if ($draftvalid) {
                echo $OUTPUT->notification(get_string('reviewselectionrequired', 'listgrades'), 'notifyproblem');
            }
            foreach (listgrades_review_response_options() as $identifier => $label) {
                $inputid = 'review-statement-' . $identifier;
                echo html_writer::start_div('form-check');
                echo html_writer::empty_tag('input', [
                    'type' => 'checkbox', 'name' => 'statements[]', 'value' => $identifier,
                    'id' => $inputid, 'class' => 'form-check-input',
                ]);
                echo html_writer::tag('label', format_text($label, FORMAT_HTML, [
                    'context' => $context, 'para' => false,
                ]), [
                    'for' => $inputid, 'class' => 'form-check-label',
                ]);
                echo html_writer::end_div();
            }
            echo html_writer::end_tag('fieldset');
            echo html_writer::tag('label', get_string('comments'), ['for' => 'review-comment']);
            echo html_writer::tag('textarea', $draftvalid ? s($draft['comment']) : '', [
                'id' => 'review-comment',
                'name' => 'comment',
                'class' => 'form-control mb-2',
                'rows' => 3,
            ]);
            echo html_writer::tag('button', get_string('reviewrecordcompletion', 'listgrades'), [
                'type' => 'submit',
                'class' => 'btn btn-primary',
            ]);
            echo html_writer::end_tag('form');
        }
        echo $OUTPUT->box_end();
    }
}

echo $OUTPUT->footer();
