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
 * Synchronise choice answers with review reservations.
 *
 * @package mod_listgrades
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_listgrades;

/**
 * Handles choice response events.
 */
class observer {
    /**
     * Record a reservation for a new answer.
     *
     * @param \mod_choice\event\answer_created $event Choice event.
     */
    public static function choice_answer_created(\mod_choice\event\answer_created $event): void {
        self::sync_choice($event, false);
    }

    /**
     * Cancel a reservation after the last answer is removed.
     *
     * @param \mod_choice\event\answer_deleted $event Choice event.
     */
    public static function choice_answer_deleted(\mod_choice\event\answer_deleted $event): void {
        self::sync_choice($event, true);
    }

    /**
     * Apply a choice event to every linked activity.
     *
     * @param \core\event\base $event Choice event.
     * @param bool $deleted Whether an answer was deleted.
     */
    private static function sync_choice(\core\event\base $event, bool $deleted): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/listgrades/locallib.php');
        listgrades_process_choice_review_event($event, $deleted);
    }
}
