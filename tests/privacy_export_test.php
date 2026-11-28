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
 * Review source privacy export tests.
 *
 * @package    mod_listgrades
 * @copyright  2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_listgrades;

// PHPUnit 11 attributes are not recognised by older Moodle coverage sniffs.
// phpcs:disable moodle.PHPUnit.TestCaseCovers.Missing
/**
 * Tests review source visibility in privacy exports.
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class privacy_export_test extends \core_privacy\tests\provider_testcase {
    /**
     * A hidden choice name is not exported to students, but is available to teachers.
     */
    public function test_choice_source_respects_viewer_visibility(): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/listgrades/locallib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $choice = $this->getDataGenerator()->create_module('choice', ['course' => $course->id, 'name' => 'Private choice']);
        $activity = $this->getDataGenerator()->create_module('listgrades', ['course' => $course->id]);
        $context = \context_module::instance($activity->cmid);
        set_coursemodule_visible($choice->cmid, 0);
        foreach (['requested', 'cancelled'] as $action) {
            $DB->insert_record('listgrades_review', (object) [
                'listgradesid' => $activity->id,
                'userid' => $student->id,
                'actorid' => $teacher->id,
                'action' => $action,
                'comment' => listgrades_serialize_choice_review_source((int) $choice->cmid, 'Private choice'),
                'timecreated' => time(),
            ]);
        }

        $this->setUser($student);
        $writer = \core_privacy\local\request\writer::with_context($context);
        \mod_listgrades\privacy\provider::export_user_data(
            new \core_privacy\local\request\approved_contextlist($student, 'mod_listgrades', [$context->id])
        );
        $data = $writer->get_data([get_string('reviewsection', 'listgrades')]);
        $this->assertNull($data->actions[0]['comment']);
        $this->assertNull($data->actions[1]['comment']);

        \core_privacy\local\request\writer::reset();
        $this->setUser($teacher);
        $writer = \core_privacy\local\request\writer::with_context($context);
        \mod_listgrades\privacy\provider::export_user_data(
            new \core_privacy\local\request\approved_contextlist($teacher, 'mod_listgrades', [$context->id])
        );
        $data = $writer->get_data([get_string('reviewsection', 'listgrades')]);
        $this->assertSame(get_string('modulename', 'choice') . ': Private choice', $data->actions[0]['comment']);
        $this->assertSame(get_string('modulename', 'choice') . ': Private choice', $data->actions[1]['comment']);
    }
}
