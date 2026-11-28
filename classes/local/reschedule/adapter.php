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
 * Optional Activity Rescheduler integration.
 *
 * @package    mod_listgrades
 * @copyright  2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_listgrades\local\reschedule;

/**
 * Exposes publication and review dates to local_reschedule.
 */
class adapter extends \local_reschedule\adapter\core\base_date_adapter {
    /**
     * Return the activity module name.
     *
     * @return string
     */
    protected function get_module_name(): string {
        return 'listgrades';
    }

    /**
     * Describe publication dates and the independent review milestone.
     *
     * @return array
     */
    protected function get_field_definitions(): array {
        return [
            'opendate' => ['label' => 'opendate', 'optional' => true],
            'closedate' => ['label' => 'closedate', 'optional' => true],
            'reviewdate' => ['label' => 'reviewdate', 'optional' => true],
        ];
    }

    /**
     * Pair the publication dates without constraining the review milestone.
     *
     * @return array
     */
    protected function get_date_relationships(): array {
        return [['opendate', 'closedate', 'timeclose']];
    }

    /**
     * Mark the review date as a point, not a publication interval endpoint.
     *
     * @param \cm_info $cm Course module.
     * @return string[]
     */
    public function get_point_date_fields(\cm_info $cm): array {
        return ['reviewdate'];
    }

    /**
     * Validate the same required review date as the activity form.
     *
     * @param \cm_info $cm Course module.
     * @param array $dates Proposed date values.
     * @return array
     */
    public function validate_date_fields(\cm_info $cm, array $dates): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/listgrades/locallib.php');
        $errors = parent::validate_date_fields($cm, $dates);
        $record = $DB->get_record('listgrades', ['id' => $cm->instance], '*', MUST_EXIST);
        if (listgrades_review_is_enabled($record) && array_key_exists('reviewdate', $dates) && !$dates['reviewdate']) {
            $errors['reviewdate'] = get_string('reviewdaterequired', 'listgrades');
        }
        return $errors;
    }

    /**
     * Save dates and update the activity calendar events.
     *
     * @param \cm_info $cm Course module.
     * @param array $dates Final date values.
     */
    public function save_date_fields(\cm_info $cm, array $dates): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/listgrades/locallib.php');
        $record = $DB->get_record('listgrades', ['id' => $cm->instance], '*', MUST_EXIST);
        foreach (array_keys($this->get_field_definitions()) as $field) {
            if (array_key_exists($field, $dates)) {
                $record->{$field} = (int) $dates[$field];
            }
        }
        $record->timemodified = time();
        $DB->update_record('listgrades', $record);
        listgrades_update_calendar($record);
        $this->trigger_cm_updated($cm);
    }
}
