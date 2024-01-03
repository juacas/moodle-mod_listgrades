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

namespace mod_listgrades;

/**
 * PHPUnit data generator testcase
 *
 * @package    mod_listgrades
 * @category   phpunit
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator_test extends \advanced_testcase {
    public function test_generator() {
        global $DB, $SITE;

        $this->resetAfterTest(true);

        $this->assertEquals(0, $DB->count_records('listgrades'));

        /** @var mod_listgrades_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_listgrades');
        $this->assertInstanceOf('mod_listgrades_generator', $generator);
        $this->assertEquals('listgrades', $generator->get_modulename());

        $generator->create_instance(['course' => $SITE->id]);
        $generator->create_instance(['course' => $SITE->id]);
        $page = $generator->create_instance(['course' => $SITE->id]);
        $this->assertEquals(3, $DB->count_records('listgrades'));

        $cm = get_coursemodule_from_instance('listgrades', $page->id);
        $this->assertEquals($page->id, $cm->instance);
        $this->assertEquals('listgrades', $cm->modname);
        $this->assertEquals($SITE->id, $cm->course);

        $context = \context_module::instance($cm->id);
        $this->assertEquals($page->cmid, $context->instanceid);
    }
}
