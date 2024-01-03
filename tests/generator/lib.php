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
 * mod_listgrades data generator
 *
 * @package    mod_listgrades
 * @category   test
 * @copyright  2023 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Page module data generator class
 *
 * @package    mod_listgrades
 * @category   test
 * @copyright  2023 Juan Pablo de Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_listgrades_generator extends testing_module_generator {

    public function create_instance($record = null, array $options = null) {
        global $CFG;
        require_once($CFG->dirroot . '/lib/resourcelib.php');

        $record = (object)(array)$record;

        if (!isset($record->footer)) {
            $record->footer = 'Test listgrades content';
        }
        if (!isset($record->footerformat)) {
            $record->footerformat = FORMAT_MOODLE;
        }
        $record->items = 'a:1:{i:322;s:10:"__COURSE__";}';

        $instance  = parent::create_instance($record, (array)$options);

        // Insert files for the 'footer' file area.
        $instance = $this->insert_files(
            $instance,
            $record,
            'listgrades',
            \context_module::instance($instance->cmid),
            'mod_listgrades',
            'footer',
            0
        );

        return $instance;
    }
}
