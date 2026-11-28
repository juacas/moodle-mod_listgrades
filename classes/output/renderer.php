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
 * Review interface renderer.
 *
 * @package mod_listgrades
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_listgrades\output;

use context;
use context_module;
use html_writer;
use moodle_url;
use stdClass;

/**
 * Renders review histories and the review management page.
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the recorded actions and comments in a conversation.
     *
     * @param array $messages Review history entries.
     * @param context $context Activity context.
     * @return string Rendered history.
     */
    public function review_history(array $messages, context $context): string {
        global $DB, $USER;

        $entries = [];
        foreach ($messages as $message) {
            $author = $DB->get_record('user', ['id' => $message['actorid']], '*', IGNORE_MISSING);
            $name = $author && (int) $author->id !== (int) guest_user()->id && empty($author->deleted)
                ? fullname($author) : get_string('deleteduser');
            $action = $message['action'] ?? '';
            $recordedbyother = in_array($action, ['requested', 'cancelled'], true) && isset($message['userid']) &&
                (int) $message['actorid'] !== (int) $message['userid'];
            if ($recordedbyother) {
                $student = $DB->get_record('user', ['id' => $message['userid']], '*', IGNORE_MISSING);
                $studentname = $student && (int) $student->id !== (int) guest_user()->id && empty($student->deleted)
                    ? fullname($student) : get_string('deleteduser');
                $heading = get_string('reviewcancelstudent', 'listgrades', $studentname);
            } else {
                $heading = $name;
            }
            if ($action === 'reopened') {
                $body = get_string('reviewreopened', 'listgrades');
            } else if ($action === 'requested') {
                $body = get_string('reviewrequested', 'listgrades');
            } else if ($action === 'cancelled') {
                $body = get_string('reviewoutcomecancelled', 'listgrades');
            } else if ($action === 'validated' && empty($message['comment'])) {
                $body = get_string('reviewoutcomevalidated', 'listgrades');
            } else if ($action === 'commented' && empty($message['comment'])) {
                $body = get_string('reviewoutcomecommented', 'listgrades');
            } else {
                $body = format_text(nl2br($message['comment']), FORMAT_HTML, ['context' => $context, 'para' => false]);
            }
            $recordedby = '';
            if (in_array($action, ['requested', 'cancelled'], true)) {
                $source = listgrades_get_choice_review_source($message['comment'] ?? null);
                $sourcevisible = $source && $context instanceof context_module &&
                    listgrades_can_view_choice_review_source($source, $context, (int) $USER->id);
                if ($recordedbyother || $sourcevisible) {
                    if ($sourcevisible) {
                        $parameters = (object) [
                            'user' => $name,
                            'choice' => format_string($source['name'], true, ['context' => $context, 'escape' => false]),
                        ];
                        $recordedby = get_string('reviewrecordedbychoice', 'listgrades', $parameters);
                    } else {
                        $recordedby = get_string('reviewrecordedby', 'listgrades', $name);
                    }
                    if ($source && isset($source['recordedat']) && is_int($source['recordedat'])) {
                        $recordedby .= ' (' . userdate($source['recordedat']) . ')';
                    }
                }
            }
            if ($body === '' && empty($message['snapshot'])) {
                continue;
            }
            $entries[] = [
                'heading' => $heading,
                'date' => userdate($message['timecreated']),
                'datetime' => gmdate('c', $message['timecreated']),
                'body' => $body,
                'recordedby' => $recordedby,
                'snapshot' => empty($message['snapshot']) ? '' : nl2br(s(listgrades_format_review_snapshot($message['snapshot']))),
                'student' => in_array($action, ['validated', 'commented'], true) ||
                    (in_array($action, ['requested', 'cancelled'], true) && isset($message['userid']) &&
                        (int) $message['actorid'] === (int) $message['userid']),
            ];
        }
        if (!$entries) {
            return '';
        }
        return $this->render_from_template('mod_listgrades/review_history', ['messages' => $entries, 'hasmessages' => true]);
    }

    /**
     * Render the teacher's sortable review management page.
     *
     * @param stdClass $cm Activity course module.
     * @param stdClass $course Course.
     * @param stdClass $instance Activity instance.
     * @param context_module $context Activity context.
     * @param array $states Review states keyed by student id.
     * @return string Rendered page content.
     */
    public function reviews_page(
        stdClass $cm,
        stdClass $course,
        stdClass $instance,
        context_module $context,
        array $states
    ): string {
        global $CFG, $DB;

        require_once($CFG->libdir . '/tablelib.php');
        $table = new \flexible_table('mod-listgrades-reviews-' . $cm->id);
        $table->define_columns(['picture', 'firstname', 'lastname', 'status', 'comments']);
        $table->define_headers([
            get_string('pictureofuser'),
            get_string('firstname'),
            get_string('lastname'),
            get_string('reviewstatus', 'listgrades'),
            get_string('reviewcommenthistory', 'listgrades'),
        ]);
        $table->define_baseurl($this->page->url);
        $table->set_attribute('class', 'generaltable table');
        $table->sortable(true, 'lastname', SORT_ASC);
        $table->no_sorting('picture');
        $table->no_sorting('comments');
        $table->setup();
        $students = $states ? $DB->get_records_list('user', 'id', array_keys($states)) : [];
        $rows = [];
        foreach ($states as $userid => $state) {
            if ((!$state['active'] && $state['lastaction'] !== 'cancelled') || !isset($students[$userid])) {
                continue;
            }
            $rows[] = ['student' => $students[$userid], 'state' => $state];
        }
        $sortcolumns = $table->get_sort_columns();
        usort($rows, static function (array $left, array $right) use ($sortcolumns): int {
            foreach ($sortcolumns as $column => $direction) {
                if ($column === 'firstname' || $column === 'lastname') {
                    $comparison = strnatcasecmp($left['student']->$column, $right['student']->$column);
                } else if ($column === 'status') {
                    $comparison = $left['state']['lasttime'] <=> $right['state']['lasttime'];
                } else {
                    continue;
                }
                if ($comparison !== 0) {
                    return $direction === SORT_DESC ? -$comparison : $comparison;
                }
            }
            return (int) $left['student']->id <=> (int) $right['student']->id;
        });
        $reviewurl = new moodle_url('/mod/listgrades/review.php', ['id' => $cm->id]);
        if ($rows) {
            ob_start();
        }
        foreach ($rows as $row) {
            $student = $row['student'];
            $state = $row['state'];
            $statuskey = match ($state['lastaction']) {
                'validated' => 'reviewoutcomevalidated',
                'commented' => 'reviewoutcomecommented',
                'cancelled' => 'reviewoutcomecancelled',
                default => 'reviewrequested',
            };
            $actions = $this->render_from_template('mod_listgrades/review_actions', [
                'history' => $this->review_history($state['history'], $context),
                'active' => $state['active'],
                'canreopen' => $state['active'] && in_array($state['lastaction'], ['validated', 'commented'], true),
                'url' => $reviewurl->out(false),
                'sesskey' => sesskey(),
                'userid' => $student->id,
                'requestid' => $state['requestid'],
            ]);
            $profileurl = new moodle_url('/user/view.php', ['id' => $student->id, 'course' => $course->id]);
            $table->add_data([
                $this->output->user_picture($student, ['courseid' => $course->id]),
                html_writer::link($profileurl, s($student->firstname)),
                html_writer::link($profileurl, s($student->lastname)),
                userdate($state['lasttime']) . ' (' . get_string($statuskey, 'listgrades') . ')',
                $actions,
            ]);
        }
        if ($rows) {
            $table->finish_output();
            $tablehtml = ob_get_clean();
        } else {
            $tablehtml = $this->output->notification(get_string('reviewnone', 'listgrades'), 'notifymessage');
        }
        return $this->render_from_template('mod_listgrades/reviews', [
            'heading' => $this->output->heading(get_string('reviewlist', 'listgrades')),
            'datebox' => $this->output->box(
                get_string('reviewdate', 'listgrades') . ': ' . userdate($instance->reviewdate),
                'generalbox'
            ),
            'backurl' => (new moodle_url('/mod/listgrades/view.php', ['id' => $cm->id]))->out(false),
            'table' => $tablehtml,
        ]);
    }
}
