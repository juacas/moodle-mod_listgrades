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
     * Prepares things before this test case is initialised
     * @return void
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/listgrades/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * The activity chooser classifies List grades as an administration tool.
     */
    public function test_activity_chooser_purpose(): void {
        $this->assertSame(MOD_PURPOSE_ADMINISTRATION, listgrades_supports(FEATURE_MOD_PURPOSE));
    }

    /**
     * Selected feedback ids are folded into their grade item without changing order.
     */
    public function test_get_selected_gradeitem_ids(): void {
        $this->assertSame([8, 3, 5], listgrades_get_selected_gradeitem_ids([-8, 3, 8, -3, 5]));
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
