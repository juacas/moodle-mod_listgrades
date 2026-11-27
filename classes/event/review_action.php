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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Event triggered when a grade review action is recorded.
 *
 * @package mod_listgrades
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_listgrades\event;

/** Event for request, cancellation, student outcome, and teacher comment actions.
 *
 * @package mod_listgrades
 */
class review_action extends \core\event\base {
    /**
     * Create an event for a persisted review action.
     *
     * @param string $action Action identifier.
     * @param \context_module $context Module context.
     * @param int $courseid Course id.
     * @param int $listgradesid Activity id.
     * @param int $studentid Reviewed student id.
     * @param int $actorid Acting user id.
     * @param int $recordid Review record id.
     * @return self
     */
    public static function create_action(
        string $action,
        \context_module $context,
        int $courseid,
        int $listgradesid,
        int $studentid,
        int $actorid,
        int $recordid
    ): self {
        $event = self::create([
            'context' => $context,
            'courseid' => $courseid,
            'objectid' => $recordid,
            'userid' => $actorid,
            'relateduserid' => $studentid === $actorid ? null : $studentid,
            'other' => ['action' => $action, 'listgradesid' => $listgradesid, 'studentid' => $studentid],
        ]);
        return $event;
    }

    /** Initialise event metadata.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['objecttable'] = 'listgrades_review';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /** Return the event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventreviewaction', 'listgrades');
    }

    /** Return a description of the action.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' recorded review action '{$this->other['action']}' " .
            "for student with id '{$this->other['studentid']}' in activity '{$this->other['listgradesid']}'.";
    }

    /** Return the activity URL.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/listgrades/view.php', ['id' => $this->contextinstanceid]);
    }

    /** Return object ID mapping information.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'listgrades_review', 'restore' => 'reviewaction'];
    }
}
