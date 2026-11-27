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
 * Record a student action in a grade review process.
 *
 * @package mod_listgrades
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/listgrades/locallib.php');

$cmid = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new moodle_exception('invalidsesskey');
}

$cm = get_coursemodule_from_id('listgrades', $cmid, 0, false, MUST_EXIST);
$listgrades = $DB->get_record('listgrades', ['id' => $cm->instance], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/listgrades:view', $context);
if (isguestuser()) {
    throw new moodle_exception('noguest');
}
require_sesskey();

if (!listgrades_review_is_enabled($listgrades)) {
    throw new moodle_exception('reviewinvalidaction', 'listgrades');
}

$now = time();
$reviewdate = (int) $listgrades->reviewdate;
if ($reviewdate <= 0) {
    throw new moodle_exception('reviewinvalidaction', 'listgrades');
}

if (in_array($action, ['teachercomment', 'reopen'], true)) {
    require_capability('mod/listgrades:managereviews', $context);
    $targetuserid = required_param('targetuserid', PARAM_INT);
} else {
    $targetuserid = (int) $USER->id;
}
$lockfactory = \core\lock\lock_config::get_lock_factory('mod_listgrades_review');
$lock = $lockfactory->get_lock($listgrades->id . ':' . $targetuserid, 10);
if (!$lock) {
    throw new moodle_exception('locktimeout');
}
try {
    $states = listgrades_get_review_states((int) $listgrades->id, $targetuserid);
    $state = $states[$targetuserid] ?? [
        'active' => false,
        'lastaction' => '',
    ];
    if ($action !== 'request' && required_param('requestid', PARAM_INT) !== ($state['requestid'] ?? 0)) {
        throw new moodle_exception('reviewinvalidaction', 'listgrades');
    }
    $eventaction = '';
    $comment = null;
    $actorid = (int) $USER->id;
    $snapshot = null;

    if ($action === 'reopen') {
        if (empty($state['active']) || !in_array($state['lastaction'], ['validated', 'commented'], true)) {
            throw new moodle_exception('reviewinvalidaction', 'listgrades');
        }
        $eventaction = 'reopened';
    } else if ($action === 'teachercomment') {
        if (empty($state['active'])) {
            throw new moodle_exception('reviewinvalidaction', 'listgrades');
        }
        $eventaction = 'teachercommented';
        $comment = trim(clean_param(optional_param('comment', '', PARAM_RAW), PARAM_TEXT));
        if ($comment === '') {
            throw new moodle_exception('reviewcommentrequired', 'listgrades');
        }
    } else if ($action === 'request' && $now < $reviewdate && empty($state['active'])) {
        $eventaction = 'requested';
    } else if (
        $action === 'cancel' && $now < $reviewdate && !empty($state['active']) &&
        ($state['lastaction'] ?? '') === 'requested'
    ) {
        $eventaction = 'cancelled';
    } else if (
        $now >= $reviewdate && !empty($state['active']) &&
        ($state['lastaction'] ?? '') === 'requested' && $action === 'complete'
    ) {
        $selected = optional_param_array('statements', [], PARAM_ALPHANUMEXT);
        $comment = trim(clean_param(optional_param('comment', '', PARAM_RAW), PARAM_TEXT));
        if (!$selected) {
            $SESSION->listgradesreviewdrafts[$cm->id] = [
                'userid' => (int) $USER->id,
                'requestid' => $state['requestid'],
                'comment' => $comment,
            ];
            $url = new moodle_url('/mod/listgrades/view.php', ['id' => $cm->id], 'review-response');
            $lock->release();
            $lock = null;
            redirect($url);
        }
        unset($SESSION->listgradesreviewdrafts[$cm->id]);
        $eventaction = $comment === '' ? 'validated' : 'commented';
        $comment = listgrades_serialize_review_message($selected, $comment);
        if (listgrades_is_publication_open($listgrades, $now) || has_capability('moodle/course:manageactivities', $context)) {
            $snapshot = listgrades_build_review_snapshot($listgrades, $context, (int) $USER->id, $now);
        }
    } else {
        throw new moodle_exception('reviewinvalidaction', 'listgrades');
    }

    $recordid = $DB->insert_record('listgrades_review', (object) [
        'listgradesid' => $listgrades->id,
        'userid' => $targetuserid,
        'actorid' => $actorid,
        'action' => $eventaction,
        'comment' => $comment,
        'snapshot' => $snapshot,
        'timecreated' => $now,
    ]);

    $event = \mod_listgrades\event\review_action::create_action(
        $eventaction,
        $context,
        (int) $course->id,
        (int) $listgrades->id,
        (int) $targetuserid,
        $actorid,
        $recordid
    );
    $event->trigger();
} finally {
    if ($lock) {
        $lock->release();
    }
}

$messages = [
    'requested' => 'reviewrequestedmessage',
    'cancelled' => 'reviewcancelled',
    'validated' => 'reviewsubmitted',
    'commented' => 'reviewsubmitted',
    'teachercommented' => 'reviewsubmitted',
    'reopened' => 'reviewreopenedmessage',
];
redirect(
    new moodle_url(
        in_array($eventaction, ['teachercommented', 'reopened'], true)
            ? '/mod/listgrades/reviews.php' : '/mod/listgrades/view.php',
        ['id' => $cm->id]
    ),
    get_string($messages[$eventaction], 'listgrades', $eventaction === 'requested' ? userdate($now) : null),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
