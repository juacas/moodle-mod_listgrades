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
 * Unit tests for mod_listgrades lib
 *
 * @package    mod_listgrades
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

namespace mod_listgrades;

// PHPUnit 11 attributes are not recognised by older releases of the Moodle coverage sniff.
// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Unit tests for mod_listgrades lib
 *
 * @package    mod_listgrades
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class lib_test extends \advanced_testcase {
    /**
     * Render history using the activity renderer.
     *
     * @param array $messages Review history entries.
     * @param \context $context Activity context.
     * @return string Rendered history.
     */
    private function render_review_history(array $messages, \context $context): string {
        global $PAGE;
        return $PAGE->get_renderer('mod_listgrades')->review_history($messages, $context);
    }

    /**
     * Prepares things before this test case is initialised
     * @return void
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/listgrades/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * The management table shows the current status date without a separate request-date column.
     */
    public function test_reviews_page_renderer(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('listgrades', [
            'course' => $course->id, 'reviewdate' => time() + DAYSECS,
        ]);
        $student = $this->getDataGenerator()->create_user(['firstname' => 'Example', 'lastname' => 'Student']);
        $cm = get_coursemodule_from_instance('listgrades', $instance->id, $course->id, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $requesttime = time() - DAYSECS;
        $responsetime = time() - HOURSECS;
        foreach (['requested' => $requesttime, 'commented' => $responsetime] as $action => $timestamp) {
            $DB->insert_record('listgrades_review', (object) [
                'listgradesid' => $instance->id, 'userid' => $student->id, 'actorid' => $student->id,
                'action' => $action, 'timecreated' => $timestamp,
            ]);
        }
        $PAGE->set_url('/mod/listgrades/reviews.php', ['id' => $cm->id]);
        $PAGE->set_context($context);
        $renderer = $PAGE->get_renderer('mod_listgrades');
        $html = $renderer->reviews_page($cm, $course, $instance, $context, listgrades_get_review_states($instance->id));
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $headers = [];
        foreach ($xpath->query('//table//th') as $header) {
            $headers[] = trim($header->textContent);
        }
        $this->assertNotContains(get_string('reviewrequested', 'listgrades'), $headers);
        $this->assertContains(get_string('reviewstatus', 'listgrades'), $headers);
        $this->assertStringContainsString(
            userdate($responsetime) . ' (' . get_string('reviewoutcomecommented', 'listgrades') . ')',
            $html
        );
        $this->assertStringContainsString('listgrades-review-history', $html);
        $this->assertStringContainsString('name="action" value="teachercomment"', $html);
        $this->assertStringContainsString('name="action" value="reopen"', $html);
        $this->assertStringContainsString('Example', $html);
    }

    /**
     * The activity chooser classifies List grades as an administration tool.
     */
    public function test_activity_chooser_purpose(): void {
        $this->assertSame(MOD_PURPOSE_ADMINISTRATION, listgrades_supports(FEATURE_MOD_PURPOSE));
    }

    /**
     * Grade snapshots follow the same publication window as the activity view.
     */
    public function test_publication_window(): void {
        $instance = (object) ['opendate' => 100, 'closedate' => 200];
        $this->assertFalse(listgrades_is_publication_open($instance, 100));
        $this->assertTrue(listgrades_is_publication_open($instance, 150));
        $this->assertFalse(listgrades_is_publication_open($instance, 200));
        $this->assertTrue(listgrades_is_publication_open((object) ['opendate' => 0, 'closedate' => 0], 150));
    }

    /**
     * Closed activities do not expose footer contents through file or export APIs.
     */
    public function test_closed_publication_hides_footer_contents(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $instance = $this->getDataGenerator()->create_module('listgrades', [
            'course' => $course->id,
            'closedate' => time() - 60,
        ]);
        $cm = get_coursemodule_from_id('listgrades', $instance->cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $this->setUser($student);
        $this->assertFalse(listgrades_pluginfile($course, $cm, $context, 'footer', ['index.html'], false));
        $this->assertSame([], listgrades_export_contents($cm, 'pluginfile.php'));
    }

    /**
     * Review history keeps cancellations and final student actions while exposing the current state.
     */
    public function test_review_history_state(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('listgrades', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $options = array_keys(listgrades_review_response_options());

        set_config('reviewprocess', 0, 'listgrades');
        $this->assertFalse(listgrades_review_is_enabled((object) ['reviewenabled' => 1]));
        set_config('reviewprocess', 1, 'listgrades');
        $this->assertTrue(listgrades_review_is_enabled((object) ['reviewenabled' => 1]));
        $this->assertFalse(listgrades_review_is_enabled((object) ['reviewenabled' => 0]));

        $DB->insert_record('listgrades_review', (object) [
            'listgradesid' => $instance->id,
            'userid' => $student->id,
            'actorid' => $student->id,
            'action' => 'requested',
            'comment' => null,
            'timecreated' => 100,
        ]);
        $DB->insert_record('listgrades_review', (object) [
            'listgradesid' => $instance->id,
            'userid' => $student->id,
            'actorid' => $student->id,
            'action' => 'cancelled',
            'comment' => null,
            'timecreated' => 110,
        ]);
        $DB->insert_record('listgrades_review', (object) [
            'listgradesid' => $instance->id,
            'userid' => $student->id,
            'actorid' => $student->id,
            'action' => 'requested',
            'comment' => null,
            'timecreated' => 120,
        ]);
        $this->assertSame('', listgrades_get_review_states($instance->id)[$student->id]['snapshot']);
        $DB->insert_record('listgrades_review', (object) [
            'listgradesid' => $instance->id,
            'userid' => $student->id,
            'actorid' => $student->id,
            'action' => 'commented',
            'comment' => listgrades_serialize_review_message([$options[0], $options[3]], 'The review took place.'),
            'snapshot' => '<table><tr><td>Published grade</td></tr></table>',
            'timecreated' => 130,
        ]);
        $DB->insert_record('listgrades_review', (object) [
            'listgradesid' => $instance->id,
            'userid' => $student->id,
            'actorid' => $teacher->id,
            'action' => 'teachercommented',
            'comment' => 'Please come to the review session.',
            'timecreated' => 140,
        ]);

        $states = listgrades_get_review_states($instance->id);
        $this->assertTrue($states[$student->id]['active']);
        $this->assertSame(120, $states[$student->id]['requesttime']);
        $this->assertSame('commented', $states[$student->id]['lastaction']);
        $this->assertStringContainsString('The review took place.', $states[$student->id]['lastcomment']);
        $this->assertStringContainsString(get_string('reviewseen', 'listgrades'), $states[$student->id]['lastcomment']);
        $this->assertSame(130, $states[$student->id]['lasttime']);
        $this->assertSame('<table><tr><td>Published grade</td></tr></table>', $states[$student->id]['snapshot']);
        $this->assertSame($states[$student->id]['snapshot'], $states[$student->id]['comments'][0]['snapshot']);
        $history = $this->render_review_history($states[$student->id]['comments'], \context_module::instance($instance->cmid));
        $this->assertStringContainsString(get_string('reviewpublishedgrade', 'listgrades'), $history);
        $this->assertStringContainsString('Published grade', $history);
        $this->assertStringNotContainsString('<table>', $history);
        $this->assertSame(1, substr_count($history, 'listgrades-review-chat-student'));
        $this->assertSame(1, substr_count($history, 'listgrades-review-chat-teacher'));
        $this->assertLessThan(strpos($history, 'Please come to the review session.'), strpos($history, 'Published grade'));
        $this->assertCount(2, $states[$student->id]['comments']);
        $this->assertSame((int) $student->id, $states[$student->id]['comments'][0]['actorid']);
        $this->assertSame((int) $teacher->id, $states[$student->id]['comments'][1]['actorid']);
        $this->assertSame(140, $states[$student->id]['comments'][1]['timecreated']);
        $this->assertSame(
            ['requested', 'cancelled', 'requested', 'commented', 'teachercommented'],
            array_column($states[$student->id]['history'], 'action')
        );
        $fullhistory = $this->render_review_history(
            $states[$student->id]['history'],
            \context_module::instance($instance->cmid)
        );
        $this->assertStringContainsString(get_string('reviewoutcomecancelled', 'listgrades'), $fullhistory);
        $this->assertStringContainsString('The review took place.', $fullhistory);

        $snapshot = '<p>Unwanted introduction</p><table><tr><th>Item header</th><th>Grade header</th></tr>' .
            '<tr><td>Class &amp; sessions</td><td>-/100</td><td>Feedback</td></tr></table><p>Unwanted date</p>';
        $this->assertSame('Class & sessions : -/100  Feedback', listgrades_format_review_snapshot($snapshot));

        $resetstatus = listgrades_reset_userdata((object) [
            'courseid' => $course->id,
            'reset_listgrades_reviews' => 1,
        ]);
        $this->assertCount(1, $resetstatus);
        $this->assertSame([], listgrades_get_review_states($instance->id));
    }

    /**
     * The review API stores the supplied action and emits its matching event.
     */
    public function test_record_review_action(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('listgrades', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $context = \context_module::instance($instance->cmid);
        $sink = $this->redirectEvents();
        $lock = \core\lock\lock_config::get_lock_factory('mod_listgrades_review')->get_lock(
            $instance->id . ':' . $student->id,
            10
        );
        try {
            $recordid = listgrades_record_review_action(
                $instance,
                $context,
                (int) $student->id,
                (int) $teacher->id,
                'commented',
                123456,
                'Comment',
                'Snapshot'
            );
        } finally {
            $lock->release();
        }
        $record = $DB->get_record('listgrades_review', ['id' => $recordid], '*', MUST_EXIST);
        $this->assertEquals($student->id, $record->userid);
        $this->assertEquals($teacher->id, $record->actorid);
        $this->assertSame('Comment', $record->comment);
        $this->assertSame('Snapshot', $record->snapshot);
        $this->assertEquals(123456, $record->timecreated);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(\mod_listgrades\event\review_action::class, $events[0]);
        $this->assertEquals($recordid, $events[0]->objectid);
        $this->assertEquals($student->id, $events[0]->relateduserid);
        $this->assertEquals($teacher->id, $events[0]->userid);
    }

    /**
     * Review statements support multiple selections and reject unexpected values.
     */
    public function test_review_response_statements(): void {
        $this->resetAfterTest();
        $options = array_keys(listgrades_review_response_options());
        $this->assertSame('', listgrades_serialize_review_message([], ''));
        $output = listgrades_serialize_review_message([$options[0], $options[2], $options[3]], 'My comment');
        $this->assertStringContainsString(get_string('reviewseen', 'listgrades'), $output);
        $this->assertStringContainsString(get_string('reviewcopyavailable', 'listgrades'), $output);
        $this->assertStringContainsString(get_string('reviewobservations', 'listgrades'), $output);
        $this->assertStringContainsString('My comment', $output);
        $this->assertStringNotContainsString(get_string('reviewcopyrequested', 'listgrades'), $output);
        $this->assertStringContainsString('{mlang es}', $output);
        set_config('reviewresponseoptions', '{mlang es}Nueva opción{mlang}{mlang other}New option{mlang}', 'listgrades');
        $newoptions = listgrades_review_response_options();
        $saved = listgrades_serialize_review_message(array_keys($newoptions), '');
        set_config('reviewresponseoptions', 'Changed option', 'listgrades');
        $this->assertStringContainsString('{mlang es}Nueva opción{mlang}', $saved);
        $this->assertStringContainsString('{mlang other}New option{mlang}', $saved);
        $this->expectException(\moodle_exception::class);
        listgrades_serialize_review_message(['invalid'], '');
    }

    /**
     * Recorded options retain filter tags and are filtered when the history is displayed.
     */
    public function test_review_history_filters_recorded_options(): void {
        global $SESSION, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        $SESSION->forcelang = 'en';
        $option = '<span lang="en" class="multilang">English option</span>' .
            '<span lang="es" class="multilang">Opción española</span>';
        set_config('reviewresponseoptions', $option, 'listgrades');
        $message = listgrades_serialize_review_message(array_keys(listgrades_review_response_options()), '');
        set_config('reviewresponseoptions', 'Another option', 'listgrades');
        $html = $this->render_review_history([[
            'actorid' => $USER->id,
            'action' => 'commented',
            'comment' => $message,
            'timecreated' => time(),
        ]], \context_system::instance());
        $this->assertStringContainsString($option, $message);
        $this->assertStringContainsString('English option', $html);
        $this->assertStringNotContainsString('Opción española', $html);
    }

    /**
     * Reopening a review retains each response and its selected statements in order.
     */
    public function test_reopened_review_preserves_response_history(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('listgrades', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $options = array_keys(listgrades_review_response_options());
        $insert = static function (
            string $action,
            int $actorid,
            ?string $comment,
            int $time
        ) use (
            $DB,
            $activity,
            $student
        ): int {
            return $DB->insert_record('listgrades_review', (object) [
                'listgradesid' => $activity->id, 'userid' => $student->id, 'actorid' => $actorid,
                'action' => $action, 'comment' => $comment, 'timecreated' => $time,
            ]);
        };
        $insert('requested', $student->id, null, 100);
        $insert('validated', $student->id, listgrades_serialize_review_message([$options[0]], ''), 110);
        $opportunityid = $insert('reopened', $teacher->id, null, 120);
        $pending = listgrades_get_review_states($activity->id, (int) $student->id)[$student->id];
        $this->assertSame('requested', $pending['lastaction']);
        $this->assertSame($opportunityid, $pending['requestid']);
        $this->assertCount(2, $pending['comments']);
        $insert('commented', $student->id, listgrades_serialize_review_message(
            [$options[1], $options[3]],
            'Second response'
        ), 130);

        $state = listgrades_get_review_states($activity->id, (int) $student->id)[$student->id];
        $this->assertSame('commented', $state['lastaction']);
        $this->assertCount(3, $state['comments']);
        $this->assertStringContainsString(get_string('reviewseen', 'listgrades'), $state['comments'][0]['comment']);
        $this->assertSame('reopened', $state['comments'][1]['action']);
        $this->assertStringContainsString(get_string('reviewobservations', 'listgrades'), $state['comments'][2]['comment']);
        $html = $this->render_review_history($state['comments'], \context_module::instance($activity->cmid));
        $this->assertStringContainsString(get_string('reviewseen', 'listgrades'), $html);
        $this->assertStringContainsString(get_string('reviewobservations', 'listgrades'), $html);
        $this->assertStringContainsString('Second response', $html);
        $this->assertStringContainsString('listgrades-review-chat-student', $html);
        $this->assertStringContainsString('listgrades-review-chat-teacher', $html);
        $this->assertStringContainsString('listgrades-review-chat-time', $html);
    }

    /**
     * Review sessions and comments remain scoped to their owner and latest request.
     */
    public function test_review_sessions_are_scoped_to_each_student(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('listgrades', ['course' => $course->id]);
        $first = $this->getDataGenerator()->create_user();
        $second = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $insert = static function (int $userid, int $actorid, string $action, ?string $comment) use ($DB, $instance): int {
            return $DB->insert_record('listgrades_review', (object) [
                'listgradesid' => $instance->id,
                'userid' => $userid,
                'actorid' => $actorid,
                'action' => $action,
                'comment' => $comment,
                'timecreated' => time(),
            ]);
        };
        $firstrequest = $insert($first->id, $first->id, 'requested', null);
        $secondrequest = $insert($second->id, $second->id, 'requested', null);
        $insert($first->id, $teacher->id, 'teachercommented', 'First student only');
        $insert($second->id, $teacher->id, 'teachercommented', 'Second student only');

        $firststates = listgrades_get_review_states($instance->id, (int) $first->id);
        $secondstates = listgrades_get_review_states($instance->id, (int) $second->id);
        $this->assertCount(1, $firststates);
        $this->assertCount(1, $secondstates);
        $this->assertSame($firstrequest, $firststates[$first->id]['requestid']);
        $this->assertSame($secondrequest, $secondstates[$second->id]['requestid']);
        $this->assertSame('First student only', $firststates[$first->id]['comments'][0]['comment']);
        $this->assertSame('Second student only', $secondstates[$second->id]['comments'][0]['comment']);
        $this->assertCount(2, listgrades_get_review_states($instance->id));

        $originalrecords = $DB->get_records('listgrades_review', ['listgradesid' => $instance->id]);
        $insert($first->id, $first->id, 'cancelled', null);
        $cancelled = listgrades_get_review_states($instance->id, (int) $first->id)[$first->id];
        $this->assertFalse($cancelled['active']);
        $this->assertSame(['requested', 'teachercommented', 'cancelled'], array_column($cancelled['history'], 'action'));
        $this->assertStringContainsString('First student only', $this->render_review_history(
            $cancelled['history'],
            \context_module::instance($instance->cmid)
        ));
        $newrequest = $insert($first->id, $first->id, 'requested', null);
        $newstate = listgrades_get_review_states($instance->id, (int) $first->id)[$first->id];
        $this->assertSame($newrequest, $newstate['requestid']);
        $this->assertNotSame($firstrequest, $newstate['requestid']);
        $this->assertSame([], $newstate['comments']);
        $this->assertSame(
            ['requested', 'teachercommented', 'cancelled', 'requested'],
            array_column($newstate['history'], 'action')
        );
        $historyhtml = $this->render_review_history($newstate['history'], \context_module::instance($instance->cmid));
        $this->assertStringContainsString('First student only', $historyhtml);
        $this->assertStringContainsString(get_string('reviewoutcomecancelled', 'listgrades'), $historyhtml);
        $this->assertStringNotContainsString('Second student only', $historyhtml);
        $this->assertSame(
            ['requested', 'teachercommented'],
            array_column(listgrades_get_review_states($instance->id, (int) $second->id)[$second->id]['history'], 'action')
        );
        foreach ($originalrecords as $recordid => $record) {
            $this->assertEquals($record, $DB->get_record('listgrades_review', ['id' => $recordid], '*', MUST_EXIST));
        }
    }

    /**
     * A later cancellation and request retain completed responses and grade snapshots.
     */
    public function test_review_history_keeps_prior_snapshots(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('listgrades', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_user();
        $snapshot = '<table><tr><td>Exam</td><td>8/10</td></tr></table>';
        foreach (['requested', 'commented', 'reopened', 'cancelled', 'requested'] as $index => $action) {
            $DB->insert_record('listgrades_review', (object) [
                'listgradesid' => $activity->id,
                'userid' => $student->id,
                'actorid' => $student->id,
                'action' => $action,
                'comment' => $action === 'commented' ? 'My observation' : null,
                'snapshot' => $action === 'commented' ? $snapshot : null,
                'timecreated' => 100 + $index,
            ]);
        }
        $state = listgrades_get_review_states((int) $activity->id, (int) $student->id)[$student->id];
        $this->assertTrue($state['active']);
        $this->assertSame('requested', $state['lastaction']);
        $this->assertSame($snapshot, $state['history'][1]['snapshot']);
        $history = $this->render_review_history($state['history'], \context_module::instance($activity->cmid));
        $this->assertStringContainsString('My observation', $history);
        $this->assertStringContainsString('Exam : 8/10', $history);
        $this->assertStringContainsString(get_string('reviewoutcomecancelled', 'listgrades'), $history);
    }

    /**
     * Choice answers create and cancel only the appropriate students' reservations.
     */
    public function test_choice_review_reservations(): void {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . '/mod/choice/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('reviewprocess', 1, 'listgrades');
        $course = $this->getDataGenerator()->create_course();
        $choice = $this->getDataGenerator()->create_module('choice', [
            'course' => $course->id, 'allowmultiple' => 1, 'name' => 'Choice & review',
        ]);
        $cm = get_coursemodule_from_instance('choice', $choice->id, $course->id, false, MUST_EXIST);
        $activity = $this->getDataGenerator()->create_module('listgrades', [
            'course' => $course->id, 'reviewenabled' => 1, 'reviewdate' => time() + DAYSECS,
            'reviewchoicecmid' => $cm->id,
        ]);
        $student = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $options = array_values($DB->get_fieldset_select('choice_options', 'id', 'choiceid = ?', [$choice->id]));
        $choice = $DB->get_record('choice', ['id' => $choice->id], '*', MUST_EXIST);
        choice_user_submit_response([$options[0], $options[1]], $choice, $student->id, $course, $cm);
        choice_user_submit_response([$options[0]], $choice, $other->id, $course, $cm);
        $this->assertCount(2, $DB->get_records('listgrades_review', ['listgradesid' => $activity->id]));
        choice_user_submit_response([$options[1], $options[2]], $choice, $student->id, $course, $cm);
        $this->assertCount(2, $DB->get_records('listgrades_review', ['listgradesid' => $activity->id]));
        $answers = $DB->get_records('choice_answers', ['choiceid' => $choice->id, 'userid' => $student->id]);
        choice_delete_responses(array_keys($answers), $choice, $cm, $course);
        $states = listgrades_get_review_states($activity->id);
        $this->assertFalse($states[$student->id]['active']);
        $this->assertTrue($states[$other->id]['active']);
        $request = $DB->get_record('listgrades_review', [
            'listgradesid' => $activity->id, 'userid' => $student->id, 'action' => 'requested',
        ], '*', MUST_EXIST);
        $requestsource = listgrades_get_choice_review_source($request->comment);
        $this->assertSame((int) $cm->id, $requestsource['cmid']);
        $this->assertSame($choice->name, $requestsource['name']);
        $requesthistory = $this->render_review_history(
            [$states[$student->id]['history'][0]],
            \context_module::instance($activity->cmid)
        );
        $this->assertStringContainsString(s(get_string('reviewrecordedbychoice', 'listgrades', (object) [
            'user' => fullname($USER), 'choice' => $choice->name,
        ])), $requesthistory);
        $history = $this->render_review_history(
            $states[$student->id]['history'],
            \context_module::instance($activity->cmid)
        );
        $this->assertStringContainsString(s(get_string('reviewcancelstudent', 'listgrades', fullname($student))), $history);
        $this->assertStringContainsString(s(get_string('reviewrecordedby', 'listgrades', fullname($USER))), $history);
        $this->assertStringContainsString(s($choice->name), $history);
        $this->assertStringContainsString(s(get_string('reviewrecordedbychoice', 'listgrades', (object) [
            'user' => fullname($USER), 'choice' => $choice->name,
        ])), $history);
        $cancel = $DB->get_record('listgrades_review', [
            'listgradesid' => $activity->id, 'userid' => $student->id, 'action' => 'cancelled',
        ], '*', MUST_EXIST);
        $source = listgrades_get_choice_review_source($cancel->comment);
        $this->assertSame((int) $cm->id, $source['cmid']);
        $this->assertSame($choice->name, $source['name']);
        $DB->set_field('choice', 'name', 'Renamed choice', ['id' => $choice->id]);
        $history = $this->render_review_history($states[$student->id]['history'], \context_module::instance($activity->cmid));
        $this->assertStringContainsString(s($choice->name), $history);
        $this->assertStringNotContainsString('Renamed choice', $history);
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        set_coursemodule_visible($cm->id, 0);
        $this->setUser($student);
        $history = $this->render_review_history($states[$student->id]['history'], \context_module::instance($activity->cmid));
        $this->assertStringNotContainsString(s($choice->name), $history);
        $this->assertFalse(listgrades_can_view_choice_review_source(
            $source,
            \context_module::instance($activity->cmid),
            $student->id
        ));
        $this->setAdminUser();
        $this->assertCount(3, $DB->get_records('listgrades_review', ['listgradesid' => $activity->id]));
        $DB->set_field('listgrades', 'reviewdate', time() - 1, ['id' => $activity->id]);
        choice_user_submit_response([$options[0]], $choice, $student->id, $course, $cm);
        $this->assertCount(3, $DB->get_records('listgrades_review', ['listgradesid' => $activity->id]));
    }

    /**
     * Linking a choice reconciles existing responses without duplicating or replacing completed reviews.
     */
    public function test_link_choice_syncs_existing_responses(): void {
        global $DB, $CFG, $USER;

        require_once($CFG->dirroot . '/mod/choice/lib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('reviewprocess', 1, 'listgrades');
        $course = $this->getDataGenerator()->create_course();
        $choice = $this->getDataGenerator()->create_module('choice', ['course' => $course->id, 'allowmultiple' => 1]);
        $choicecm = get_coursemodule_from_instance('choice', $choice->id, $course->id, false, MUST_EXIST);
        $choice = $DB->get_record('choice', ['id' => $choice->id], '*', MUST_EXIST);
        $options = array_values($DB->get_fieldset_select('choice_options', 'id', 'choiceid = ?', [$choice->id]));
        $student = $this->getDataGenerator()->create_user();
        $pendingstudent = $this->getDataGenerator()->create_user();
        $completedstudent = $this->getDataGenerator()->create_user();
        choice_user_submit_response([$options[0], $options[1]], $choice, $student->id, $course, $choicecm);
        $earliest = time() - 2 * DAYSECS;
        $DB->set_field('choice_answers', 'timemodified', $earliest, [
            'choiceid' => $choice->id, 'userid' => $student->id, 'optionid' => $options[0],
        ]);
        $DB->set_field('choice_answers', 'timemodified', $earliest + DAYSECS, [
            'choiceid' => $choice->id, 'userid' => $student->id, 'optionid' => $options[1],
        ]);
        $activity = $this->getDataGenerator()->create_module('listgrades', [
            'course' => $course->id, 'reviewenabled' => 1, 'reviewdate' => time() - HOURSECS,
        ]);
        $context = \context_module::instance($activity->cmid);
        $DB->insert_record('listgrades_review', (object) [
            'listgradesid' => $activity->id, 'userid' => $pendingstudent->id, 'actorid' => $pendingstudent->id,
            'action' => 'requested', 'timecreated' => $earliest,
        ]);
        $DB->insert_record('listgrades_review', (object) [
            'listgradesid' => $activity->id, 'userid' => $completedstudent->id, 'actorid' => $completedstudent->id,
            'action' => 'requested', 'timecreated' => $earliest,
        ]);
        $DB->insert_record('listgrades_review', (object) [
            'listgradesid' => $activity->id, 'userid' => $completedstudent->id, 'actorid' => $completedstudent->id,
            'action' => 'commented', 'comment' => 'Completed review', 'timecreated' => $earliest + HOURSECS,
        ]);
        $data = $DB->get_record('listgrades', ['id' => $activity->id], '*', MUST_EXIST);
        $data->items = array_keys((array) unserialize_array($data->items));
        $data->instance = $activity->id;
        $data->coursemodule = $activity->cmid;
        $data->revision = 1;
        $data->signature = ['format' => $data->footerformat, 'text' => $data->footer, 'itemid' => 0];
        $data->reviewchoicecmid = $choicecm->id;
        $sink = $this->redirectEvents();
        $syncstart = time();
        listgrades_update_instance($data, null);

        $states = listgrades_get_review_states((int) $activity->id);
        $this->assertTrue($states[$student->id]['active']);
        $this->assertSame($earliest, $states[$student->id]['requesttime']);
        $request = $DB->get_record('listgrades_review', [
            'listgradesid' => $activity->id, 'userid' => $student->id, 'action' => 'requested',
        ], '*', MUST_EXIST);
        $this->assertEquals($USER->id, $request->actorid);
        $requestsource = listgrades_get_choice_review_source($request->comment);
        $this->assertSame((int) $choicecm->id, $requestsource['cmid']);
        $this->assertSame($choice->name, $requestsource['name']);
        $this->assertGreaterThanOrEqual($syncstart, $requestsource['recordedat']);
        $requesthistory = $this->render_review_history($states[$student->id]['history'], $context);
        $this->assertStringContainsString(s(get_string('reviewcancelstudent', 'listgrades', fullname($student))), $requesthistory);
        $this->assertStringContainsString(s(get_string('reviewrecordedbychoice', 'listgrades', (object) [
            'user' => fullname($USER), 'choice' => $choice->name,
        ])), $requesthistory);
        $this->assertStringContainsString(userdate($requestsource['recordedat']), $requesthistory);
        $this->assertFalse($states[$pendingstudent->id]['active']);
        $this->assertSame('cancelled', $states[$pendingstudent->id]['lastaction']);
        $cancellation = $DB->get_record('listgrades_review', [
            'listgradesid' => $activity->id, 'userid' => $pendingstudent->id, 'action' => 'cancelled',
        ], '*', MUST_EXIST);
        $this->assertEquals($USER->id, $cancellation->actorid);
        $history = $this->render_review_history($states[$pendingstudent->id]['history'], $context);
        $this->assertStringContainsString(s(get_string('reviewcancelstudent', 'listgrades', fullname($pendingstudent))), $history);
        $this->assertStringContainsString(s(get_string('reviewrecordedby', 'listgrades', fullname($USER))), $history);
        $this->assertStringContainsString(s($choice->name), $history);
        $this->assertStringContainsString(s(get_string('reviewrecordedbychoice', 'listgrades', (object) [
            'user' => fullname($USER), 'choice' => $choice->name,
        ])), $history);
        $source = listgrades_get_choice_review_source($cancellation->comment);
        $this->assertSame((int) $choicecm->id, $source['cmid']);
        $this->assertSame($choice->name, $source['name']);
        $this->assertEquals($cancellation, $DB->get_record('listgrades_review', ['id' => $cancellation->id], '*', MUST_EXIST));
        $this->assertTrue($states[$completedstudent->id]['active']);
        $this->assertSame('commented', $states[$completedstudent->id]['lastaction']);
        $this->assertSame('Completed review', $states[$completedstudent->id]['lastcomment']);
        $events = array_values(array_filter($sink->get_events(), static fn($event): bool =>
            $event instanceof \mod_listgrades\event\review_action));
        $this->assertCount(2, $events);
        $cancellationevents = array_values(array_filter($events, static fn($event): bool =>
            $event->other['action'] === 'cancelled'));
        $this->assertCount(1, $cancellationevents);
        $this->assertEquals($USER->id, $cancellationevents[0]->userid);
        $this->assertEquals($pendingstudent->id, $cancellationevents[0]->relateduserid);
        $count = $DB->count_records('listgrades_review', ['listgradesid' => $activity->id]);
        $saved = $DB->get_record('listgrades', ['id' => $activity->id], '*', MUST_EXIST);
        listgrades_sync_choice_reservations($saved, $context);
        $saved->items = array_keys((array) unserialize_array($saved->items));
        $saved->instance = $activity->id;
        $saved->coursemodule = $activity->cmid;
        $saved->revision = 1;
        $saved->signature = ['format' => $saved->footerformat, 'text' => $saved->footer, 'itemid' => 0];
        listgrades_update_instance($saved, null);
        $this->assertEquals($count, $DB->count_records('listgrades_review', ['listgradesid' => $activity->id]));

        $newactivity = $this->getDataGenerator()->create_module('listgrades', [
            'course' => $course->id, 'reviewenabled' => 1, 'reviewdate' => time() - HOURSECS,
            'reviewchoicecmid' => $choicecm->id,
        ]);
        $newstates = listgrades_get_review_states((int) $newactivity->id);
        $this->assertTrue($newstates[$student->id]['active']);
        $this->assertSame($earliest, $newstates[$student->id]['requesttime']);

        $otherchoice = $this->getDataGenerator()->create_module('choice', ['course' => $course->id]);
        $othercm = get_coursemodule_from_instance('choice', $otherchoice->id, $course->id, false, MUST_EXIST);
        $otherchoice = $DB->get_record('choice', ['id' => $otherchoice->id], '*', MUST_EXIST);
        $otheroption = $DB->get_field_select('choice_options', 'id', 'choiceid = ?', [$otherchoice->id], IGNORE_MULTIPLE);
        choice_user_submit_response($otheroption, $otherchoice, $pendingstudent->id, $course, $othercm);
        $data = $DB->get_record('listgrades', ['id' => $activity->id], '*', MUST_EXIST);
        $data->items = array_keys((array) unserialize_array($data->items));
        $data->instance = $activity->id;
        $data->coursemodule = $activity->cmid;
        $data->revision = 1;
        $data->signature = ['format' => $data->footerformat, 'text' => $data->footer, 'itemid' => 0];
        $data->reviewchoicecmid = $othercm->id;
        listgrades_update_instance($data, null);
        $states = listgrades_get_review_states((int) $activity->id);
        $this->assertFalse($states[$student->id]['active']);
        $this->assertTrue($states[$pendingstudent->id]['active']);
        $this->assertSame('commented', $states[$completedstudent->id]['lastaction']);
    }

    /**
     * Erasing an author anonymises comments while removing that student's own history.
     */
    public function test_review_privacy_anonymises_authors(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $instance = $this->getDataGenerator()->create_module('listgrades', ['course' => $course->id]);
        $otherinstance = $this->getDataGenerator()->create_module('listgrades', ['course' => $course->id]);
        $context = \context_module::instance($instance->cmid);
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();
        $otherstudent = $this->getDataGenerator()->create_user();

        $create = static function ($activityid, $ownerid, $actorid, $action, $comment) use ($DB): int {
            return $DB->insert_record('listgrades_review', (object) [
                'listgradesid' => $activityid,
                'userid' => $ownerid,
                'actorid' => $actorid,
                'action' => $action,
                'comment' => $comment,
                'timecreated' => 12345,
            ]);
        };
        $requestid = $create($instance->id, $student->id, $student->id, 'requested', null);
        $commentid = $create($instance->id, $student->id, $teacher->id, 'teachercommented', 'Review comment');
        $commentid2 = $create($instance->id, $student->id, $teacher2->id, 'teachercommented', 'Second comment');
        $otherid = $create($otherinstance->id, $otherstudent->id, $teacher->id, 'teachercommented', 'Other activity');
        $ownid = $create($instance->id, $teacher->id, $teacher->id, 'requested', null);

        \mod_listgrades\privacy\provider::delete_data_for_user(
            new \core_privacy\local\request\approved_contextlist($teacher, 'mod_listgrades', [$context->id])
        );
        $comment = $DB->get_record('listgrades_review', ['id' => $commentid], '*', MUST_EXIST);
        $this->assertEquals(guest_user()->id, $comment->actorid);
        $this->assertSame('Review comment', $comment->comment);
        $this->assertEquals(12345, $comment->timecreated);
        $this->assertFalse($DB->record_exists('listgrades_review', ['id' => $ownid]));
        $this->assertEquals($teacher->id, $DB->get_field('listgrades_review', 'actorid', ['id' => $otherid]));
        $this->assertTrue($DB->record_exists('listgrades_review', ['id' => $requestid]));

        \mod_listgrades\privacy\provider::delete_data_for_users(
            new \core_privacy\local\request\approved_userlist($context, 'mod_listgrades', [$teacher2->id])
        );
        $this->assertEquals(guest_user()->id, $DB->get_field('listgrades_review', 'actorid', ['id' => $commentid2]));
        \mod_listgrades\privacy\provider::delete_data_for_users(
            new \core_privacy\local\request\approved_userlist($context, 'mod_listgrades', [$student->id])
        );
        $this->assertFalse($DB->record_exists('listgrades_review', ['id' => $requestid]));
        $this->assertFalse($DB->record_exists('listgrades_review', ['id' => $commentid]));
        $this->assertFalse($DB->record_exists('listgrades_review', ['id' => $commentid2]));
    }

    /**
     * Anonymised authors remain anonymous after a backup and restore with user data.
     */
    public function test_review_backup_restores_anonymous_author(): void {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $choice = $this->getDataGenerator()->create_module('choice', ['course' => $course->id]);
        $choicecm = get_coursemodule_from_instance('choice', $choice->id, $course->id, false, MUST_EXIST);
        $instance = $this->getDataGenerator()->create_module('listgrades', [
            'course' => $course->id, 'reviewchoicecmid' => $choicecm->id,
        ]);
        $options = array_keys(listgrades_review_response_options());
        $DB->insert_record('listgrades_review', (object) [
            'listgradesid' => $instance->id,
            'userid' => $student->id,
            'actorid' => guest_user()->id,
            'action' => 'teachercommented',
            'comment' => 'Anonymous comment',
            'timecreated' => 12345,
        ]);
        $DB->insert_record('listgrades_review', (object) [
            'listgradesid' => $instance->id,
            'userid' => $student->id,
            'actorid' => $student->id,
            'action' => 'commented',
            'comment' => listgrades_serialize_review_message([$options[0], $options[3]], 'Student comment'),
            'timecreated' => 12346,
        ]);
        $CFG->backup_file_logger_level = \backup::LOG_NONE;
        $backup = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backup->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $backup->get_plan()->get_setting('users')->set_value(true);
        $backupid = $backup->get_backupid();
        $backup->execute_plan();
        $backup->destroy();

        $newcourseid = \restore_dbops::create_new_course($course->fullname, $course->shortname . '_restored', $course->category);
        $restore = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $restore->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $restore->get_plan()->get_setting('users')->set_value(true);
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $restore->destroy();
        $restoredinstance = $DB->get_record('listgrades', ['course' => $newcourseid], '*', MUST_EXIST);
        $restoredchoice = $DB->get_record('choice', ['course' => $newcourseid], '*', MUST_EXIST);
        $restoredchoicecm = get_coursemodule_from_instance('choice', $restoredchoice->id, $newcourseid, false, MUST_EXIST);
        $this->assertEquals($restoredchoicecm->id, $restoredinstance->reviewchoicecmid);
        $record = $DB->get_record('listgrades_review', [
            'listgradesid' => $restoredinstance->id,
            'action' => 'teachercommented',
        ], '*', MUST_EXIST);
        $this->assertEquals($student->id, $record->userid);
        $this->assertEquals(guest_user()->id, $record->actorid);
        $this->assertSame('Anonymous comment', $record->comment);
        $response = $DB->get_record('listgrades_review', [
            'listgradesid' => $restoredinstance->id,
            'action' => 'commented',
        ], '*', MUST_EXIST);
        $this->assertStringContainsString(get_string('reviewseen', 'listgrades'), $response->comment);
        $this->assertStringContainsString('Student comment', $response->comment);
    }

    /**
     * Selected feedback ids are folded into their grade item without changing order.
     */
    public function test_get_selected_gradeitem_ids(): void {
        $this->assertSame(
            [8, -8, -3, 5],
            listgrades_build_item_selection(
                [8, 3, 5],
                [8 => true, 3 => false, 5 => true],
                [8 => true, 3 => true, 5 => false]
            )
        );
        $this->assertSame([8, 3, 5], listgrades_get_selected_gradeitem_ids([-8, 3, 8, -3, 5]));
        $this->assertSame([3, 8, 5], listgrades_get_selected_grade_ids([-8, 3, 8, -3, 5, 8]));
    }

    /**
     * Feedback-only selections do not expose the corresponding grade.
     */
    public function test_individual_feedback_without_grade(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $item = new \grade_item();
        $item->courseid = $course->id;
        $item->itemtype = 'manual';
        $item->itemname = 'Feedback-only item';
        $item->gradetype = GRADE_TYPE_VALUE;
        $item->grademin = 0;
        $item->grademax = 100;
        $item->insert();

        $grade = (object) [
            'finalgrade' => 72.0,
            'feedback' => 'Private feedback text',
            'feedbackformat' => FORMAT_PLAIN,
        ];
        $context = \context_course::instance($course->id);
        $arguments = [
            [$item->id => $item],
            [$item->id => $item->itemname],
            [$item->id => $grade],
            $context,
            $user,
        ];

        $feedbackonly = listgrades_render_individual_table([-($item->id)], ...$arguments);
        $this->assertStringContainsString('Private feedback text', $feedbackonly);
        $this->assertStringNotContainsString('72/100', $feedbackonly);
        $this->assertStringNotContainsString('>' . get_string('grade', 'listgrades') . '<', $feedbackonly);

        $gradeandfeedback = listgrades_render_individual_table([$item->id, -($item->id)], ...$arguments);
        $this->assertStringContainsString('Private feedback text', $gradeandfeedback);
        $this->assertStringContainsString('72/100', $gradeandfeedback);
    }

    /**
     * Feedback is cleaned while retaining safe formatting and plain text.
     */
    public function test_format_feedback_for_listing(): void {
        $this->resetAfterTest();
        $context = \context_system::instance();
        $html = (object) [
            'feedback' => '<strong>Good work</strong><img src="x" onerror="alert(1)"><script>alert(2)</script>',
            'feedbackformat' => FORMAT_HTML,
        ];
        $output = listgrades_format_feedback($html, $context);
        $this->assertStringContainsString('<strong>Good work</strong>', $output);
        $this->assertStringNotContainsString('onerror', $output);
        $this->assertStringNotContainsString('<script', $output);

        $plain = (object) ['feedback' => '<script>alert(1)</script>', 'feedbackformat' => FORMAT_PLAIN];
        $this->assertStringNotContainsString('<script', listgrades_format_feedback($plain, $context));
        $this->assertSame('--', listgrades_format_feedback((object) ['feedback' => ''], $context));
    }

    /**
     * The review snapshot contains only the selected published grade and feedback.
     */
    public function test_review_snapshot_uses_selected_item_ids(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $item = new \grade_item();
        $item->courseid = $course->id;
        $item->itemtype = 'manual';
        $item->itemname = 'Reviewed exam';
        $item->gradetype = GRADE_TYPE_VALUE;
        $item->grademin = 0;
        $item->grademax = 100;
        $item->insert();
        $item->update_final_grade($student->id, 72, 'gradebook', 'Review feedback', FORMAT_PLAIN);
        $instance = $this->getDataGenerator()->create_module('listgrades', [
            'course' => $course->id,
            'items' => [$item->id, -$item->id],
        ]);
        $snapshottime = 1795788000;
        $snapshot = listgrades_build_review_snapshot(
            $instance,
            \context_module::instance($instance->cmid),
            $student->id,
            $snapshottime
        );
        $this->assertStringContainsString('Reviewed exam', $snapshot);
        $this->assertStringContainsString('72/100', $snapshot);
        $this->assertStringContainsString('Review feedback', $snapshot);
        $this->assertStringContainsString(userdate($snapshottime, '%d/%m/%Y %H:%M'), $snapshot);

        $instance->items = serialize([-$item->id => '__FEEDBACK__Reviewed exam']);
        $feedbackonly = listgrades_build_review_snapshot($instance, \context_module::instance($instance->cmid), $student->id);
        $this->assertStringContainsString('Review feedback', $feedbackonly);
        $this->assertStringNotContainsString('72/100', $feedbackonly);

        $emptyitem = new \grade_item();
        $emptyitem->courseid = $course->id;
        $emptyitem->itemtype = 'manual';
        $emptyitem->itemname = 'Class sessions';
        $emptyitem->gradetype = GRADE_TYPE_VALUE;
        $emptyitem->grademin = 0;
        $emptyitem->grademax = 100;
        $emptyitem->insert();
        $instance->items = serialize([$emptyitem->id => 'Class sessions']);
        $emptygrade = listgrades_build_review_snapshot($instance, \context_module::instance($instance->cmid), $student->id);
        $this->assertStringContainsString('Class sessions', $emptygrade);
        $this->assertStringContainsString('-/100', $emptygrade);
    }

    /**
     * Percentiles use linear interpolation and support empty and single-value sets.
     */
    public function test_percentile(): void {
        $this->assertNull(listgrades_percentile([], .5));
        $this->assertSame(4.0, listgrades_percentile([4], .25));
        $this->assertSame(2.5, listgrades_percentile([1, 2, 3, 4], .5));
        $this->assertSame(1.75, listgrades_percentile([1, 2, 3, 4], .25));
    }

    /**
     * Grade positions are clamped to the chart range.
     */
    public function test_grade_position(): void {
        $this->assertSame(50.0, listgrades_grade_position(5, 0, 10));
        $this->assertSame(0.0, listgrades_grade_position(-1, 0, 10));
        $this->assertSame(100.0, listgrades_grade_position(11, 0, 10));
        $this->assertSame(50.0, listgrades_grade_position(5, 5, 5));
    }

    /**
     * Chart axes show both ends of the complete item range.
     */
    public function test_render_chart_axis(): void {
        $axis = listgrades_render_chart_axis('0.00', '100.00');

        $this->assertStringContainsString('listgrades-chart-axis-min', $axis);
        $this->assertStringContainsString('listgrades-chart-axis-max', $axis);
        $this->assertStringContainsString('0.00', $axis);
        $this->assertStringContainsString('100.00', $axis);
    }

    /**
     * Site policy restricts or defaults the activity display mode.
     */
    public function test_resolve_display_mode(): void {
        $this->assertSame(LISTGRADES_DISPLAY_LIST, listgrades_resolve_display_mode(
            LISTGRADES_DISPLAY_INDIVIDUAL,
            LISTGRADES_DISPLAY_LIST
        ));
        $this->assertSame(LISTGRADES_DISPLAY_INDIVIDUAL, listgrades_resolve_display_mode(
            LISTGRADES_DISPLAY_LIST,
            LISTGRADES_DISPLAY_INDIVIDUAL
        ));
        $this->assertSame(LISTGRADES_DISPLAY_LIST, listgrades_resolve_display_mode(
            LISTGRADES_DISPLAY_LIST,
            LISTGRADES_ALLOWED_BOTH,
            LISTGRADES_DISPLAY_INDIVIDUAL
        ));
        $this->assertSame(LISTGRADES_DISPLAY_INDIVIDUAL, listgrades_resolve_display_mode(
            null,
            LISTGRADES_ALLOWED_BOTH,
            LISTGRADES_DISPLAY_INDIVIDUAL
        ));
    }

    /**
     * Default content templates expand course data and preserve unknown placeholders.
     */
    public function test_expand_course_template(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course([
            'fullname' => 'Template full name',
            'shortname' => 'Template short name',
            'idnumber' => 'COURSE-42',
            'summary' => '<p>Template summary</p>',
            'summaryformat' => FORMAT_HTML,
        ]);
        $template = implode('|', [
            '{logourl}',
            '{logocompacturl}',
            '{coursefullname}',
            '{courseshortname}',
            '{courseidnumber}',
            '{courseurl}',
            '{courseimage}',
            '{coursesummary}',
            '{unknown}',
        ]);

        $expanded = listgrades_expand_course_template($template, $course);

        $this->assertStringNotContainsString('{logourl}', $expanded);
        $this->assertStringNotContainsString('{logocompacturl}', $expanded);
        $this->assertStringContainsString('Template full name', $expanded);
        $this->assertStringContainsString('Template short name', $expanded);
        $this->assertStringContainsString('COURSE-42', $expanded);
        $this->assertStringContainsString('/course/view.php?id=' . $course->id, $expanded);
        $this->assertStringContainsString('/course/generated/course.svg', $expanded);
        $this->assertStringContainsString('Template summary', $expanded);
        $this->assertStringContainsString('{unknown}', $expanded);
    }

    /**
     * Preview grades stay within the observed range and preserve fixed values.
     */
    public function test_random_preview_grade(): void {
        $this->assertNull(listgrades_random_preview_grade([], 2));
        $this->assertSame(7.5, listgrades_random_preview_grade([7.5, 7.5], 2));

        $grade = listgrades_random_preview_grade([10, 20], 2);
        $this->assertGreaterThanOrEqual(10, $grade);
        $this->assertLessThanOrEqual(20, $grade);
        $this->assertEquals(round($grade, 2), $grade);
    }

    /**
     * Individual grades include their numeric maximum or all textual scale options.
     */
    public function test_format_grade_with_reference(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        grade_set_setting($course->id, 'decimalpoints', 1);

        $numericitem = new \grade_item();
        $numericitem->courseid = $course->id;
        $numericitem->itemtype = 'manual';
        $numericitem->itemname = 'Numeric item';
        $numericitem->gradetype = GRADE_TYPE_VALUE;
        $numericitem->grademin = 0;
        $numericitem->grademax = 100;
        $numericitem->insert();
        $numericgrade = listgrades_format_grade_with_reference(72.5, $numericitem);
        $this->assertStringContainsString('72.5/100', $numericgrade);
        $this->assertStringContainsString('72/100', listgrades_format_grade_with_reference(72.04, $numericitem));
        $this->assertSame('72', listgrades_format_numeric_grade_value(72.0, $numericitem));
        $this->assertSame('72.5', listgrades_format_numeric_grade_value(72.5, $numericitem));

        $scale = $this->getDataGenerator()->create_scale([
            'courseid' => $course->id,
            'scale' => 'Beginning,Developing,Achieved',
        ]);
        $scaleitem = new \grade_item();
        $scaleitem->courseid = $course->id;
        $scaleitem->itemtype = 'manual';
        $scaleitem->itemname = 'Scale item';
        $scaleitem->gradetype = GRADE_TYPE_SCALE;
        $scaleitem->scaleid = $scale->id;
        $scaleitem->insert();
        $scalegrade = listgrades_format_grade_with_reference(2, $scaleitem);
        $this->assertStringContainsString('Developing', $scalegrade);
        $this->assertStringContainsString('(Beginning; Developing; Achieved)', $scalegrade);
    }

    /**
     * Histogram bins preserve every value and use individual bins for small integer ranges.
     */
    public function test_build_histogram(): void {
        $bins = listgrades_build_histogram([0, 0, 1, 2, 2, 2]);
        $this->assertCount(3, $bins);
        $this->assertSame([2, 1, 3], array_column($bins, 'count'));
        $this->assertEqualsWithDelta(100.0, array_sum(array_column($bins, 'percentage')), .001);

        $singlebin = listgrades_build_histogram([7, 7]);
        $this->assertCount(1, $singlebin);
        $this->assertSame(2, $singlebin[0]['count']);
        $this->assertSame(100.0, $singlebin[0]['percentage']);
        $this->assertSame([], listgrades_build_histogram([]));

        $widerange = listgrades_build_histogram(range(0, 20));
        $this->assertLessThanOrEqual(10, count($widerange));

        $passinggradebins = listgrades_build_histogram(range(0, 100, 10), 50);
        $this->assertLessThanOrEqual(10, count($passinggradebins));
        $boundaries = array_column($passinggradebins, 'upper');
        array_pop($boundaries);
        $closestboundary = min(array_map(static fn(float $boundary): float => abs($boundary - 50), $boundaries));
        $this->assertEqualsWithDelta(0, $closestboundary, .001);

        $configuredrange = listgrades_build_histogram([40, 60], 50, 0, 100);
        $this->assertSame(0.0, $configuredrange[0]['lower']);
        $this->assertSame(100.0, $configuredrange[array_key_last($configuredrange)]['upper']);
        $this->assertSame(2, array_sum(array_column($configuredrange, 'count')));

        $decimalgrades = [];
        for ($index = 0; $index < 80; $index++) {
            $decimalgrades[] = ($index + .5) / 8;
        }
        $roundbins = listgrades_build_histogram($decimalgrades, null, 0, 10);
        $this->assertCount(10, $roundbins);
        $this->assertEquals(range(1, 10), array_column($roundbins, 'upper'));

        $smallrange = listgrades_build_histogram([3, 4, 5, 6, 8, 9, 10], null, 0, 10);
        $this->assertCount(10, $smallrange);
        $this->assertEquals(range(1, 10), array_column($smallrange, 'upper'));

        $coursetotal = listgrades_build_histogram([22, 30, 40, 45, 50, 70, 90], null, 0, 110);
        $this->assertCount(6, $coursetotal);
        $this->assertSame([20.0, 40.0, 60.0, 80.0, 100.0, 110.0], array_column($coursetotal, 'upper'));

        $twentyfivepoints = listgrades_build_histogram([5, 10, 15, 20, 25], null, 0, 25);
        $this->assertCount(5, $twentyfivepoints);
        $this->assertSame([5.0, 10.0, 15.0, 20.0, 25.0], array_column($twentyfivepoints, 'upper'));
    }

    /**
     * Numeric grades use the grade letters configured by Moodle for the course.
     */
    public function test_build_letter_distribution(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $item = new \grade_item();
        $item->courseid = $course->id;
        $item->itemtype = 'manual';
        $item->itemname = 'Letter item';
        $item->gradetype = GRADE_TYPE_VALUE;
        $item->grademin = 0;
        $item->grademax = 100;
        $item->insert();

        $distribution = listgrades_build_letter_distribution($item, [95, 91, 50]);
        $counts = array_combine($distribution['labels'], $distribution['counts']);

        $this->assertSame('F', reset($distribution['labels']));
        $this->assertSame('A', end($distribution['labels']));
        $this->assertSame(1, $counts['A']);
        $this->assertSame(1, $counts['A-']);
        $this->assertSame(1, $counts['F']);
    }

    /**
     * Site statistics settings control summaries and numeric chart formats.
     */
    public function test_statistics_settings(): void {
        $this->resetAfterTest();
        set_config('privacyconditions', 0, 'listgrades');
        set_config('showglobalpercentage', 1, 'listgrades');
        set_config('showboxplot', 0, 'listgrades');
        set_config('showhistogram', 1, 'listgrades');
        set_config('numericgradesasletters', 0, 'listgrades');
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $item = new \grade_item();
        $item->courseid = $course->id;
        $item->itemtype = 'manual';
        $item->itemname = 'Configurable statistics';
        $item->gradetype = GRADE_TYPE_VALUE;
        $item->grademin = 0;
        $item->grademax = 100;
        $item->gradepass = 50;
        $item->insert();

        $grades = [];
        $studentids = [];
        foreach ([20, 40, 55, 65, 80, 95] as $index => $value) {
            $userid = $index === 0 ? $student->id : $this->getDataGenerator()->create_user()->id;
            $studentids[] = $userid;
            $grades[$userid][$item->id] = (object) ['finalgrade' => (float) $value];
        }
        $arguments = [
            [$item->id],
            [$item->id => $item],
            [$item->id => $item->itemname],
            $grades,
            $studentids,
            $student,
            $course->id,
            false,
        ];

        $numericoutput = listgrades_render_statistics(...$arguments);
        $this->assertStringContainsString('listgrades-global-summary', $numericoutput);
        $this->assertStringContainsString('listgrades-histogram', $numericoutput);
        $this->assertStringNotContainsString('class="listgrades-boxplot"', $numericoutput);

        set_config('showboxplot', 1, 'listgrades');
        set_config('numericgradesasletters', 1, 'listgrades');
        $letteroutput = listgrades_render_statistics(...$arguments);
        $this->assertStringContainsString('listgrades-letter-chart', $letteroutput);
        $this->assertStringContainsString('class="listgrades-boxplot"', $letteroutput);
        $this->assertStringContainsString('listgrades-histogram', $letteroutput);

        set_config('showhistogram', 0, 'listgrades');
        $withouthistogram = listgrades_render_statistics(...$arguments);
        $this->assertStringContainsString('listgrades-letter-chart', $withouthistogram);
        $this->assertStringContainsString('class="listgrades-boxplot"', $withouthistogram);
        $this->assertStringNotContainsString('listgrades-histogram', $withouthistogram);
    }

    /**
     * Empty statistical settings are treated as disabled booleans.
     */
    public function test_empty_statistics_settings_are_disabled(): void {
        $this->resetAfterTest();
        set_config('privacyconditions', 0, 'listgrades');
        set_config('showglobalpercentage', '', 'listgrades');
        set_config('showboxplot', '', 'listgrades');
        set_config('showhistogram', '', 'listgrades');
        set_config('numericgradesasletters', '', 'listgrades');
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $item = new \grade_item();
        $item->courseid = $course->id;
        $item->itemtype = 'manual';
        $item->itemname = 'Disabled statistics';
        $item->gradetype = GRADE_TYPE_VALUE;
        $item->grademin = 0;
        $item->grademax = 100;
        $item->insert();
        $grades = [$student->id => [$item->id => (object) ['finalgrade' => 50.0]]];

        $output = listgrades_render_statistics(
            [$item->id],
            [$item->id => $item],
            [$item->id => $item->itemname],
            $grades,
            [$student->id],
            $student,
            $course->id
        );

        $this->assertStringNotContainsString('listgrades-global-summary', $output);
        $this->assertStringNotContainsString('listgrades-boxplot', $output);
        $this->assertStringNotContainsString('listgrades-histogram', $output);
        $this->assertStringNotContainsString('listgrades-letter-chart', $output);
    }

    /**
     * Privacy conditions require a minimum cohort and more than one populated grade band.
     */
    public function test_privacy_conditions(): void {
        $this->resetAfterTest();
        unset_config('privacyconditions', 'listgrades');
        $this->assertFalse(listgrades_privacy_conditions_enabled());
        set_config('privacyconditions', 0, 'listgrades');
        $this->assertFalse(listgrades_privacy_conditions_enabled());
        set_config('privacyconditions', 1, 'listgrades');
        $this->assertTrue(listgrades_privacy_conditions_enabled());

        $this->assertFalse(listgrades_privacy_allows_aggregates(5, true));
        $this->assertTrue(listgrades_privacy_allows_aggregates(6, true));
        $this->assertTrue(listgrades_privacy_allows_aggregates(3, false));
        $this->assertTrue(listgrades_privacy_allows_aggregates(0, true));

        $this->assertFalse(listgrades_privacy_allows_distribution([0, 6, 0], true));
        $this->assertTrue(listgrades_privacy_allows_distribution([1, 5, 0], true));
        $this->assertTrue(listgrades_privacy_allows_distribution([0, 6, 0], false));

        $this->assertFalse(listgrades_privacy_allows_numeric_statistics([0, 0, 0, 0, 0, 0], 5, true));
        $this->assertTrue(listgrades_privacy_allows_numeric_statistics([0, 0, 5], 5, true));
        $this->assertTrue(listgrades_privacy_allows_numeric_statistics([0, 0, 0], 5, false));
        $this->assertTrue(listgrades_privacy_allows_numeric_statistics([0, 0, 0], null, true));

        $this->assertFalse(listgrades_privacy_allows_global_percentage([5, 6, 7], 5, true));
        $this->assertFalse(listgrades_privacy_allows_global_percentage([1, 2, 3], 5, true));
        $this->assertTrue(listgrades_privacy_allows_global_percentage([1, 5, 7], 5, true));
        $this->assertTrue(listgrades_privacy_allows_global_percentage([5, 6, 7], 5, false));
        $this->assertTrue(listgrades_privacy_allows_global_percentage([1, 2, 3], null, true));
    }

    /**
     * Privacy explanations are visible to teachers and completely omitted for students.
     */
    public function test_privacy_explanations_are_teacher_only(): void {
        $this->resetAfterTest();
        set_config('privacyconditions', 1, 'listgrades');
        set_config('showboxplot', 1, 'listgrades');
        set_config('showhistogram', 1, 'listgrades');
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $item = new \grade_item();
        $item->courseid = $course->id;
        $item->itemtype = 'manual';
        $item->itemname = 'All failed item';
        $item->gradetype = GRADE_TYPE_VALUE;
        $item->grademin = 0;
        $item->grademax = 10;
        $item->gradepass = 5;
        $item->insert();

        $grades = [];
        $studentids = [];
        for ($index = 0; $index < 6; $index++) {
            $userid = $index === 0 ? $student->id : $this->getDataGenerator()->create_user()->id;
            $studentids[] = $userid;
            $grades[$userid][$item->id] = (object) ['finalgrade' => 0.0];
        }
        $arguments = [
            [$item->id],
            [$item->id => $item],
            [$item->id => $item->itemname],
            $grades,
            $studentids,
            $student,
            $course->id,
        ];

        $this->assertSame('', listgrades_render_statistics(...[...$arguments, false]));

        $teacheroutput = listgrades_render_statistics(...[...$arguments, true]);
        $this->assertStringContainsString(get_string('privacyallfailedhidden', 'listgrades'), $teacheroutput);
        $this->assertStringContainsString(get_string('privacyteachernotice', 'listgrades'), $teacheroutput);
        $this->assertStringContainsString('listgrades-boxplot', $teacheroutput);
        $this->assertStringContainsString('listgrades-histogram', $teacheroutput);

        set_config('privacyconditions', 0, 'listgrades');
        $unrestrictedoutput = listgrades_render_statistics(...[...$arguments, false]);
        $this->assertStringContainsString('listgrades-boxplot', $unrestrictedoutput);
        $this->assertStringContainsString('listgrades-histogram', $unrestrictedoutput);
    }

    /**
     * Numeric charts are completely hidden when every grade belongs to the same histogram band.
     */
    public function test_numeric_charts_are_hidden_for_a_single_populated_band(): void {
        $this->resetAfterTest();
        set_config('privacyconditions', 1, 'listgrades');
        set_config('showboxplot', 1, 'listgrades');
        set_config('showhistogram', 1, 'listgrades');
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $item = new \grade_item();
        $item->courseid = $course->id;
        $item->itemtype = 'manual';
        $item->itemname = 'Single band item';
        $item->gradetype = GRADE_TYPE_VALUE;
        $item->grademin = 0;
        $item->grademax = 100;
        $item->gradepass = 0;
        $item->insert();

        $grades = [];
        $studentids = [];
        for ($index = 0; $index < 6; $index++) {
            $userid = $index === 0 ? $student->id : $this->getDataGenerator()->create_user()->id;
            $studentids[] = $userid;
            $grades[$userid][$item->id] = (object) ['finalgrade' => 0.0];
        }
        $arguments = [
            [$item->id],
            [$item->id => $item],
            [$item->id => $item->itemname],
            $grades,
            $studentids,
            $student,
            $course->id,
        ];

        $this->assertSame('', listgrades_render_statistics(...[...$arguments, false]));
        $teacheroutput = listgrades_render_statistics(...[...$arguments, true]);
        $this->assertStringContainsString(get_string('privacydistributionhidden', 'listgrades'), $teacheroutput);
        $this->assertStringContainsString('listgrades-boxplot', $teacheroutput);
        $this->assertStringContainsString('listgrades-histogram', $teacheroutput);
    }

    /**
     * Grade items without grades remain visible and are not treated as privacy restrictions.
     */
    public function test_statistics_show_items_without_grades(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $item = new \grade_item();
        $item->courseid = $course->id;
        $item->itemtype = 'manual';
        $item->itemname = 'Empty grade item';
        $item->gradetype = GRADE_TYPE_VALUE;
        $item->grademin = 0;
        $item->grademax = 100;
        $item->insert();
        $arguments = [
            [$item->id],
            [$item->id => $item],
            [$item->id => $item->itemname],
            [],
            [],
            $teacher,
            $course->id,
            true,
        ];

        set_config('privacyconditions', 0, 'listgrades');
        $unrestrictedoutput = listgrades_render_statistics(...$arguments);
        $this->assertStringContainsString($item->itemname, $unrestrictedoutput);
        $this->assertStringContainsString(get_string('itemnogrades', 'listgrades'), $unrestrictedoutput);
        $studentarguments = [...$arguments];
        $studentarguments[7] = false;
        $this->assertSame('', listgrades_render_statistics(...$studentarguments));

        set_config('privacyconditions', 1, 'listgrades');
        $restrictedoutput = listgrades_render_statistics(...$arguments);
        $this->assertStringContainsString($item->itemname, $restrictedoutput);
        $this->assertStringContainsString(get_string('itemnogrades', 'listgrades'), $restrictedoutput);
        $this->assertStringNotContainsString(get_string('privacydistributionhidden', 'listgrades'), $restrictedoutput);
        $this->assertSame('', listgrades_render_statistics(...$studentarguments));
    }

    /**
     * Test listgrades_view
     * @return void
     */
    public function test_listgrades_view(): void {
        global $CFG;

        $CFG->enablecompletion = 1;
        $this->resetAfterTest();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $listgrades = $this->getDataGenerator()->create_module(
            'listgrades',
            ['course' => $course->id],
            ['completion' => 2, 'completionview' => 1]
        );
        $context = \context_module::instance($listgrades->cmid);
        $cm = get_coursemodule_from_instance('listgrades', $listgrades->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $this->setAdminUser();
        listgrades_view($listgrades, $course, $cm, $context);

        $events = $sink->get_events();
        // 2 additional events thanks to completion.
        $this->assertCount(3, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_listgrades\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodleurl = new \moodle_url('/mod/listgrades/view.php', ['id' => $cm->id]);
        $this->assertEquals($moodleurl, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Check completion status.
        $completion = new \completion_info($course);
        $completiondata = $completion->get_data($cm);
        $this->assertEquals(1, $completiondata->completionstate);
    }

    public function test_listgrades_core_calendar_provide_event_action(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create the activity.
        $course = $this->getDataGenerator()->create_course();
        $listgrades = $this->getDataGenerator()->create_module('listgrades', ['course' => $course->id]);

        // Create a calendar event.
        $event = $this->create_action_event(
            $course->id,
            $listgrades->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED
        );

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_listgrades_core_calendar_provide_event_action($event, $factory);

        // Confirm the event was decorated.
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent);
        $this->assertEquals(get_string('view'), $actionevent->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent->get_url());
        $this->assertEquals(1, $actionevent->get_item_count());
        $this->assertTrue($actionevent->is_actionable());
    }

    public function test_listgrades_core_calendar_provide_event_action_already_completed(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $CFG->enablecompletion = 1;

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $listgrades = $this->getDataGenerator()->create_module(
            'listgrades',
            ['course' => $course->id],
            ['completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS]
        );

        // Get some additional data.
        $cm = get_coursemodule_from_instance('listgrades', $listgrades->id);

        // Create a calendar event.
        $event = $this->create_action_event(
            $course->id,
            $listgrades->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED
        );

        // Mark the activity as completed.
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_listgrades_core_calendar_provide_event_action($event, $factory);

        // Ensure result was null.
        $this->assertNull($actionevent);
    }

    /**
     * Test mod_listgrades_core_calendar_provide_event_action with user override
     */
    public function test_listgrades_core_calendar_provide_event_action_user_override(): void {
        global $CFG, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $CFG->enablecompletion = 1;

        // Create the activity.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $listgrades = $this->getDataGenerator()->create_module(
            'listgrades',
            ['course' => $course->id],
            ['completion' => 2, 'completionview' => 1, 'completionexpected' => time() + DAYSECS]
        );

        // Get some additional data.
        $cm = get_coursemodule_from_instance('listgrades', $listgrades->id);

        // Create a calendar event.
        $event = $this->create_action_event(
            $course->id,
            $listgrades->id,
            \core_completion\api::COMPLETION_EVENT_TYPE_DATE_COMPLETION_EXPECTED
        );

        // Mark the activity as completed.
        $completion = new \completion_info($course);
        $completion->set_module_viewed($cm);

        // Create an action factory.
        $factory = new \core_calendar\action_factory();

        // Decorate action event.
        $actionevent = mod_listgrades_core_calendar_provide_event_action($event, $factory, $USER->id);

        // Decorate action with a userid override.
        $actionevent2 = mod_listgrades_core_calendar_provide_event_action($event, $factory, $user->id);

        // Ensure result was null because it has been marked as completed for the associated user.
        // Logic was brought across from the "_already_completed" function.
        $this->assertNull($actionevent);

        // Confirm the event was decorated.
        $this->assertNotNull($actionevent2);
        $this->assertInstanceOf('\core_calendar\local\event\value_objects\action', $actionevent2);
        $this->assertEquals(get_string('view'), $actionevent2->get_name());
        $this->assertInstanceOf('moodle_url', $actionevent2->get_url());
        $this->assertEquals(1, $actionevent2->get_item_count());
        $this->assertTrue($actionevent2->is_actionable());
    }

    /**
     * Creates an action event.
     *
     * @param int $courseid The course id.
     * @param int $instanceid The instance id.
     * @param string $eventtype The event type.
     * @return bool|calendar_event
     */
    private function create_action_event($courseid, $instanceid, $eventtype) {
        $event = new \stdClass();
        $event->name = 'Calendar event';
        $event->modulename  = 'listgrades';
        $event->courseid = $courseid;
        $event->instance = $instanceid;
        $event->type = CALENDAR_EVENT_TYPE_ACTION;
        $event->eventtype = $eventtype;
        $event->timestart = time();

        return \calendar_event::create($event);
    }
}
// phpcs:enable moodle.PHPUnit.TestCaseCovers.Missing
