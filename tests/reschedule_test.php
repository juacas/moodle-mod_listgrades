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
 * Optional Activity Rescheduler integration tests.
 *
 * @package    mod_listgrades
 * @copyright  2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_listgrades;

// PHPUnit 11 attributes are not recognised by older Moodle coverage sniffs.
// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Tests the adapter when local_reschedule is available.
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class reschedule_test extends \advanced_testcase {
    /**
     * The review milestone is independent and publication changes refresh calendar events.
     */
    public function test_review_and_publication_dates(): void {
        global $DB;

        if (!class_exists(\local_reschedule\adapter\core\base_date_adapter::class)) {
            $this->markTestSkipped('local_reschedule is not installed.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
        $start = time() + DAYSECS;
        $close = $start + DAYSECS;
        $review = $close + DAYSECS;
        $course = $this->getDataGenerator()->create_course([
            'startdate' => $start - DAYSECS, 'enddate' => $review + 3 * DAYSECS,
        ]);
        $activity = $this->getDataGenerator()->create_module('listgrades', [
            'course' => $course->id, 'opendate' => $start, 'closedate' => $close,
            'reviewdate' => $review, 'reviewenabled' => 1,
        ]);
        set_config('reviewprocess', 1, 'listgrades');
        $cm = get_fast_modinfo($course)->instances['listgrades'][$activity->id];
        $adapter = \local_reschedule\adapter_manager::get_adapter('listgrades', $course, ['table' => 'listgrades']);
        $this->assertInstanceOf(\mod_listgrades\local\reschedule\adapter::class, $adapter);
        $fields = $adapter->get_date_fields($cm);
        $this->assertSame(['opendate', 'closedate', 'reviewdate'], array_keys($fields));
        $this->assertTrue($fields['reviewdate']['optional']);
        $this->assertSame(['reviewdate'], $adapter->get_point_date_fields($cm));
        $this->assertArrayHasKey('reviewdate', $adapter->validate_date_fields($cm, ['reviewdate' => 0]));

        $items = array_column(\local_reschedule\manager::get_course_items($course->id), null, 'id');
        $parentid = 'main_listgrades_' . $activity->id;
        $reviewid = 'milestone_listgrades_' . $activity->id . '_reviewdate';
        $this->assertArrayHasKey($parentid, $items);
        $this->assertArrayHasKey($reviewid, $items);
        $this->assertSame($review, $items[$reviewid]['datestart']);

        $newreview = $review + HOURSECS;
        $result = \local_reschedule\manager::save_schedule($course->id, [[
            'id' => $reviewid, 'datestart' => $newreview, 'dateend' => $newreview,
            'startenabled' => true, 'endenabled' => true,
        ]]);
        $this->assertTrue($result['success'], $result['message']);
        $this->assertEquals($newreview, $DB->get_field('listgrades', 'reviewdate', ['id' => $activity->id]));
        $this->assertEquals($close, $DB->get_field('listgrades', 'closedate', ['id' => $activity->id]));

        $newclose = $close + HOURSECS;
        $result = \local_reschedule\manager::save_schedule($course->id, [[
            'id' => $parentid, 'datestart' => $start, 'dateend' => $newclose,
            'startenabled' => true, 'endenabled' => true,
        ]]);
        $this->assertTrue($result['success'], $result['message']);
        $this->assertEquals($newclose, $DB->get_field('listgrades', 'closedate', ['id' => $activity->id]));
        $this->assertEquals($newclose, $DB->get_field('event', 'timestart', [
            'modulename' => 'listgrades', 'instance' => $activity->id, 'eventtype' => 'dateend',
        ]));

        $disable = [[
            'id' => $reviewid, 'datestart' => 0, 'dateend' => 0,
            'startenabled' => false, 'endenabled' => false,
        ]];
        $result = \local_reschedule\manager::save_schedule($course->id, $disable);
        $this->assertFalse($result['success']);
        $this->assertEquals($newreview, $DB->get_field('listgrades', 'reviewdate', ['id' => $activity->id]));

        set_config('reviewprocess', 0, 'listgrades');
        $this->assertTrue($adapter->get_date_fields($cm)['reviewdate']['optional']);
        $this->assertSame([], $adapter->validate_date_fields($cm, ['reviewdate' => 0]));
        $result = \local_reschedule\manager::save_schedule($course->id, $disable);
        $this->assertTrue($result['success'], $result['message']);
        $this->assertEquals(0, $DB->get_field('listgrades', 'reviewdate', ['id' => $activity->id]));
        $result = \local_reschedule\manager::save_schedule($course->id, [[
            'id' => $reviewid, 'datestart' => $newreview, 'dateend' => $newreview,
            'startenabled' => true, 'endenabled' => true,
        ]]);
        $this->assertTrue($result['success'], $result['message']);
        $this->assertEquals($newreview, $DB->get_field('listgrades', 'reviewdate', ['id' => $activity->id]));

        $result = \local_reschedule\manager::save_schedule($course->id, [[
            'id' => $parentid, 'datestart' => 0, 'dateend' => $newclose,
            'startenabled' => false, 'endenabled' => true,
        ]]);
        $this->assertTrue($result['success'], $result['message']);
        $this->assertEquals(0, $DB->get_field('listgrades', 'opendate', ['id' => $activity->id]));
        $this->assertFalse($DB->record_exists('event', [
            'modulename' => 'listgrades', 'instance' => $activity->id, 'eventtype' => 'datestart',
        ]));
    }
}
