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
 * Manage student review sessions.
 *
 * @package mod_listgrades
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/listgrades/locallib.php');
require_once($CFG->libdir . '/tablelib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('listgrades', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$listgrades = $DB->get_record('listgrades', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/listgrades:view', $context);
require_capability('mod/listgrades:managereviews', $context);
if (!listgrades_review_is_enabled($listgrades) || (int) $listgrades->reviewdate <= 0) {
    throw new moodle_exception('reviewinvalidaction', 'listgrades');
}

$PAGE->set_url('/mod/listgrades/reviews.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($listgrades->name) . ': ' . get_string('reviewlist', 'listgrades'));
$PAGE->set_heading($course->fullname);
$PAGE->set_cm($cm, $course, $listgrades);
$PAGE->navbar->add(get_string('reviewbreadcrumb', 'listgrades'));
$reviewurl = new moodle_url('/mod/listgrades/review.php', ['id' => $cm->id]);
$reviewstates = listgrades_get_review_states((int) $listgrades->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reviewlist', 'listgrades'));
echo $OUTPUT->box(get_string('reviewdate', 'listgrades') . ': ' . userdate($listgrades->reviewdate), 'generalbox');
echo html_writer::link(new moodle_url('/mod/listgrades/view.php', ['id' => $cm->id]), get_string('back'));
$requesttable = new flexible_table('mod-listgrades-reviews-' . $cm->id);
$requesttable->define_columns(['picture', 'firstname', 'lastname', 'requesttime', 'status', 'comments']);
$requesttable->define_headers([
    get_string('pictureofuser'),
    get_string('firstname'),
    get_string('lastname'),
    get_string('reviewrequested', 'listgrades'),
    get_string('reviewstatus', 'listgrades'),
    get_string('reviewcommenthistory', 'listgrades'),
]);
$requesttable->define_baseurl($PAGE->url);
$requesttable->set_attribute('class', 'generaltable table');
$requesttable->sortable(true, 'lastname', SORT_ASC);
$requesttable->no_sorting('picture');
$requesttable->no_sorting('comments');
$requesttable->setup();
$students = $reviewstates ? $DB->get_records_list('user', 'id', array_keys($reviewstates)) : [];
$rows = [];
foreach ($reviewstates as $userid => $state) {
    if (!$state['active'] && $state['lastaction'] !== 'cancelled') {
        continue;
    }
    if (!isset($students[$userid])) {
        continue;
    }
    $rows[] = ['student' => $students[$userid], 'state' => $state];
}
$sortcolumns = $requesttable->get_sort_columns();
usort($rows, static function (array $left, array $right) use ($sortcolumns): int {
    foreach ($sortcolumns as $column => $direction) {
        if ($column === 'firstname' || $column === 'lastname') {
            $comparison = strnatcasecmp($left['student']->$column, $right['student']->$column);
        } else if ($column === 'requesttime') {
            $comparison = $left['state']['requesttime'] <=> $right['state']['requesttime'];
        } else if ($column === 'status') {
            $comparison = strcmp($left['state']['lastaction'], $right['state']['lastaction']);
        } else {
            continue;
        }
        if ($comparison !== 0) {
            return $direction === SORT_DESC ? -$comparison : $comparison;
        }
    }
    return (int) $left['student']->id <=> (int) $right['student']->id;
});
foreach ($rows as $row) {
    $student = $row['student'];
    $userid = $student->id;
    $state = $row['state'];
    $status = get_string('reviewrequested', 'listgrades');
    if ($state['lastaction'] === 'validated') {
        $status = get_string('reviewoutcomevalidated', 'listgrades');
    } else if ($state['lastaction'] === 'commented') {
        $status = get_string('reviewoutcomecommented', 'listgrades');
    } else if ($state['lastaction'] === 'cancelled') {
        $status = get_string('reviewoutcomecancelled', 'listgrades');
    }
    $comments = [listgrades_render_review_history($state['comments'], $context)];
    if ($state['active']) {
        $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $reviewurl->out(false)]);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'teachercomment']);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetuserid', 'value' => $userid]);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'requestid', 'value' => $state['requestid']]);
        $form .= html_writer::tag('textarea', '', [
            'name' => 'comment', 'class' => 'form-control mb-2', 'rows' => 2, 'required' => 'required',
            'aria-label' => get_string('reviewteachercomment', 'listgrades'),
        ]);
        $form .= html_writer::tag('button', get_string('reviewaddcomment', 'listgrades'), [
            'type' => 'submit', 'class' => 'btn btn-secondary btn-sm',
        ]);
        $comments[] = $form . html_writer::end_tag('form');
    }
    if ($state['active'] && in_array($state['lastaction'], ['validated', 'commented'], true)) {
        $form = html_writer::start_tag('form', ['method' => 'post', 'action' => $reviewurl->out(false), 'class' => 'mt-2']);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'reopen']);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'targetuserid', 'value' => $userid]);
        $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'requestid', 'value' => $state['requestid']]);
        $form .= html_writer::tag('button', get_string('reviewreopen', 'listgrades'), [
            'type' => 'submit', 'class' => 'btn btn-secondary btn-sm',
        ]);
        $comments[] = $form . html_writer::end_tag('form');
    }
    $profileurl = new moodle_url('/user/view.php', ['id' => $userid, 'course' => $course->id]);
    $requesttable->add_data([
        $OUTPUT->user_picture($student, ['courseid' => $course->id]),
        html_writer::link($profileurl, s($student->firstname)),
        html_writer::link($profileurl, s($student->lastname)),
        userdate($state['requesttime']),
        $status . ' (' . userdate($state['lasttime']) . ')',
        implode('', $comments),
    ]);
}
if ($rows) {
    $requesttable->finish_output();
} else {
    echo $OUTPUT->notification(get_string('reviewnone', 'listgrades'), 'notifymessage');
}

echo $OUTPUT->footer();
