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
 * Private page module utility functions
 *
 * @package mod_listgrades
 * @copyright  2023 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/filelib.php");
require_once("$CFG->libdir/resourcelib.php");
require_once("$CFG->dirroot/mod/listgrades/lib.php");
require_once($CFG->dirroot . '/grade/report/grader/lib.php');
class grade_report_listing extends grade_report_grader {
    public function get_gradeitems() {
        $items = $this->gtree->get_items();
        $allgradeitems = array_filter($items, function ($item) {
            return $item->gradetype != GRADE_TYPE_NONE;
        });
        return $items;
    }
    public function get_grades() {
        return $this->grades;
    }
    public function get_users() {
        return $this->users;
    }
    public function get_students_per_page(): int {
        return PHP_INT_MAX;
    }
}
// Mask the field of the user using the mask code:
// *: Show the digit.
// X: Hide the digit with a X.
// -: Omit the character.
function listgrades_mask($userfield, $mask) {
    $maskeduserfield = '';
    $i = 0;
    $userfield = str_pad($userfield, strlen($mask), ' ', STR_PAD_RIGHT);
    while ($i < strlen($mask)) {
        if ($mask[$i] == '*') {
            $maskeduserfield .= $userfield[$i];
        } else if ($mask[$i] == 'X') {
            $maskeduserfield .= 'X';
        } else if ($mask[$i] == '-') {
            $maskeduserfield .= '';
        }
        $i++;
    }
    return $maskeduserfield;
}

/**
 * Find items matching by name and id
 * @param array $gradeitems ids
 * @return array of grade_items with id -> name
 */
function listgrades_get_gradeitems($items) {
    global $DB;
    // Query db for grade items.
    $items = $DB->get_records_list('grade_items', 'id', $items);
    $gradeitems = [];
    foreach ($items as $item) {
        if ($item->itemtype == 'course') {
            $gradeitems[$item->id] = '__COURSE__';
        } else {
            $gradeitems[$item->id] = $item->itemname;
        }
    }
    return $gradeitems;
}
/**
 * File browsing support class
 */
class listgrades_content_file_info extends file_info_stored {
    public function get_parent() {
        if ($this->lf->get_filepath() === '/' && $this->lf->get_filename() === '.') {
            return $this->browser->get_file_info($this->context);
        }
        return parent::get_parent();
    }
    public function get_visible_name() {
        if ($this->lf->get_filepath() === '/' && $this->lf->get_filename() === '.') {
            return $this->topvisiblename;
        }
        return parent::get_visible_name();
    }
}

function listgrades_get_editor_options($context) {
    global $CFG;
    return ['subdirs' => 1, 'maxbytes' => $CFG->maxbytes, 'maxfiles' => -1,
        'changeformat' => 1, 'context' => $context, 'noclean' => 1, 'trusttext' => 0];
}
/**
 * Update the calendar entries for this listgradesment.
 *
 * @param int $coursemoduleid - Required to pass this in because it might
 *                              not exist in the database yet.
 * @return bool
 */
function listgrades_update_calendar($instance) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/calendar/lib.php');

    // Start with creating the event.
    $event = new stdClass();
    $event->modulename = 'listgrades';
    $event->courseid = $instance->course;
    $event->groupid = 0;
    $event->userid = 0;
    $event->instance = $instance->id;
    $event->type = CALENDAR_EVENT_TYPE_ACTION;

    // Convert the links to pluginfile. It is a bit hacky but at this stage the files
    // might not have been saved in the module area yet.
    $intro = $instance->intro;
    if ($draftid = file_get_submitted_draft_itemid('introeditor')) {
        $intro = file_rewrite_urls_to_pluginfile($intro, $draftid);
    }

    // We need to remove the links to files as the calendar is not ready
    // to support module events with file areas.
    $intro = strip_pluginfile_content($intro);
    $event->description = [
        'text' => $intro,
        'format' => $instance->introformat,
    ];

    $eventtype = "datestart";
    if ($instance->opendate) {
        $event->name = get_string('opendate', 'listgrades', $instance->name);
        $event->eventtype = $eventtype;
        $event->timestart = $instance->opendate;
        $event->timesort = $instance->opendate;
        $select = "modulename = :modulename
                       AND instance = :instance
                       AND eventtype = :eventtype
                       AND groupid = 0
                       AND courseid <> 0";
        $params = ['modulename' => 'listgrades', 'instance' => $instance->id, 'eventtype' => $eventtype];
        $event->id = $DB->get_field_select('event', 'id', $select, $params);

        // Now process the event.
        if ($event->id) {
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            calendar_event::create($event, false);
        }
    } else {
        $DB->delete_records('event', ['modulename' => 'listgrades', 'instance' => $instance->id,
            'eventtype' => $eventtype]);
    }

    $eventtype = "dateend";
    if ($instance->closedate) {
        $event->name = get_string('closedate', 'listgrades', $instance->name);
        $event->eventtype = $eventtype;
        $event->timestart = $instance->closedate;
        $event->timesort = $instance->closedate;
        $event->id = $DB->get_field('event', 'id', array('modulename' => 'listgrades',
            'instance' => $instance->id, 'eventtype' => $event->eventtype));

        // Now process the event.
        if ($event->id) {
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, false);
        } else {
            calendar_event::create($event, false);
        }
    } else {
        $DB->delete_records('event', ['modulename' => 'listgrades', 'instance' => $instance->id,
            'eventtype' => $eventtype]);
    }

    return true;
}
