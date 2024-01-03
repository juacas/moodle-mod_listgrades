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
 * @package   mod_listgrades
 * @category  backup
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_listgrades_activity_task
 */

/**
 * Structure step to restore one listgrades activity
 */
class restore_listgrades_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = [];
        $paths[] = new restore_path_element('listgrades', '/activity/listgrades');

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    protected function process_listgrades($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Check if the gradeitems exist in the course by checking the grade_item table.
        // If they don't exist clear the gradeitems field and show a warning.
        $gradeitems = [];
        $dataitems = unserialize($data->items);
        $itemids = array_keys($dataitems);
        // TODO: Check if the grade items exist in the course.
        $gradetreeroot = new grade_tree($this->get_courseid());
        $candidateitems = $gradetreeroot->get_items();

        foreach ($dataitems as $id => $item) {
            if ($item == '__COURSE__') {
                // Find the item with itemtype course.
                foreach ($candidateitems as $candidateitem) {
                    if ($candidateitem->itemtype == 'course') {
                        $gradeitems[$candidateitem->id] = '__COURSE__';
                        break;
                    }
                }
            } else {
                // Find the grade item with the same name as the one in the backup.
                foreach ($candidateitems as $candidateitem) {
                    if ($item == $candidateitem->itemname) {
                        $gradeitems[$candidateitem->id] = $candidateitem->itemname;
                        break;
                    }
                }
            }
        }
        $data->items = serialize($gradeitems);

        // Insert the listgrades record.
        $newitemid = $DB->insert_record('listgrades', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    protected function after_execute() {
        // Add listgrades related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_listgrades', 'intro', null);
        $this->add_related_files('mod_listgrades', 'footer', null);
    }
}
