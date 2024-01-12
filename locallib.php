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
    /**
     * Constructor to override the calculation of grade_tree (avoid removing collapsed categories).
     */
    public function __construct($courseid, $gpr, $context, $page=null, $sortitemid='lastname') {
        global $CFG;
        parent::__construct($courseid, $gpr, $context, $page);

        // Don't collapse categories.
        $this->collapsed =  ['aggregatesonly' => [], 'gradesonly' => []];

        if (empty($CFG->enableoutcomes)) {
            $nooutcomes = false;
        } else {
            $nooutcomes = get_user_preferences('grade_report_shownooutcomes');
        }

        // if user report preference set or site report setting set use it, otherwise use course or site setting
        $switch = $this->get_pref('aggregationposition');
        if ($switch == '') {
            $switch = grade_get_setting($this->courseid, 'aggregationposition', $CFG->grade_aggregationposition);
        }
        // Grab the grade_tree for this course
        $this->gtree = new grade_tree($this->courseid, true, $switch, $this->collapsed, $nooutcomes);
        $this->sortitemid = $sortitemid;    
    }
    
    /**
     * Gets the gradetree object.
     */
    public function get_gradetree() {
        return $this->gtree;
    }
    public function get_item_names() {
        $items = [];
        // Get grade category names.
        $categories = $this->get_category_names();
        foreach ($this->get_gradeitems() as $key => $item) {
            if ($item->itemtype == 'course') {
                $items[$key] = $item->get_name();
            } else if ($item->itemtype == 'category') {
                $items[$key] = get_string('total') . ' ' . $categories[$item->iteminstance];
            } else {
                $items[$key] = $item->get_name();
            }
        }
        return $items;
    }
    public function get_category_names() {
        $categories = [];
        array_walk_recursive($this->gtree->top_element, function($item, $key) use (&$categories) {
            if ($item instanceof grade_category) {
                $categories[$item->id] = $item->get_name();
            }
        });
        return $categories;
    }
    public function get_gradeitems() {
        $items = $this->gtree->get_items();
        $allgradeitems = array_filter($items, function ($item) {
            return $item->gradetype != GRADE_TYPE_NONE;
        });
        return $allgradeitems;
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
        if ($mask[$i] == '+') {
            $maskeduserfield .= $userfield[$i];
        } else if ($mask[$i] == '*') {
            $maskeduserfield .= '*';
        } else if ($mask[$i] == '-') {
            $maskeduserfield .= '';
        }
        $i++;
    }
    return $maskeduserfield;
}
/** Mask identifiers according to AEPD rules.
 * - Dado un DNI con formato 12345678X, se publicarán los dígitos que en el 
 * formato que ocupen las posiciones cuarta, quinta, sexta y séptima. En el 
 * ejemplo: ***4567**.
 * • Dado un NIE con formato L1234567X, se publicarán los dígitos que en el 
 * formato ocupen las posiciones, evitando el primer carácter alfabéticos, 
 * cuarta, quinta, sexta y séptima. En el ejemplo: ****4567*.
 * • Dado un pasaporte con formato ABC123456, al tener sólo seis cifras, se 
 * publicarán los dígitos que en el formato ocupen las posiciones, evitando los 
 * tres caracteres alfabéticos, tercera, cuarta, quinta y sexta. En el ejemplo: *****3456.
 * • Dado otro tipo de identificación, siempre que esa identificación contenga al 
 * menos 7 dígitos numéricos, se numerarán dichos dígitos de izquierda a 
 * derecha, evitando todos los caracteres alfabéticos, y se seguirá el 
 * procedimiento de publicar aquellos caracteres numéricos que ocupen las 
 * posiciones cuarta, quinta, sexta y séptima. Por ejemplo, en el caso de una 
 * identificación como: XY12345678AB, la publicación sería: *****4567***
 * Si ese tipo de identificación es distinto de un pasaporte y tiene menos de 7
 * dígitos numéricos, se numerarán todos los caracteres, alfabéticos incluidos, 
 * con el mismo procedimiento anterior y se seleccionarán aquellos que ocupen 
 * las cuatro últimas posiciones. Por ejemplo, en el caso de una identificación 
 * como: ABCD123XY, la publicación sería: *****23XY
 * 
 * DNI format allows an optional, initial "E" character (local UVa requirement).
 * @param string $userfield
 */
function listgrades_mask_identifier_aepd($userfield) {
    $userfield = strtoupper($userfield);
    $maskeduserfield = '';
    $i = 0;
    $matches = [];

    if (preg_match('/^E?([0-9]{8}[A-Z])$/', $userfield)) {
        // DNI
        // Remove first character if it is an E.
        if ($userfield[0] == 'E') {
            $userfield = substr($userfield, 1);
        }
        $maskeduserfield = '***' . substr($userfield, 3, 4) . '**';
    } else if (preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $userfield)) {
        // NIE
        $maskeduserfield = '****' . substr($userfield, 4, 4) . '*';
    } else if (preg_match('/^[A-Z]{3}[0-9]{6}$/', $userfield)) {
        // Pasaporte
        $maskeduserfield = '*****' . substr($userfield, 5, 4);
    } else if (preg_match('/[0-9]{7,}/', $userfield, $matches, PREG_OFFSET_CAPTURE)){
        // Otro tipo de identificación.
        $offset = $matches[0][1];
        $numbers = $matches[0][0];
        $length = strlen($numbers);
        $maskeduserfield = listgrades_asterisks($offset + 3)
                            . substr($numbers, 3, 4)
                            . listgrades_asterisks(strlen($userfield) - $offset -3 - 4);
    } else {
        // Otro tipo de identificación
        $maskeduserfield = listgrades_asterisks(strlen($userfield) - 4) . substr($userfield, -4);
    }
    return $maskeduserfield;
}
/**
 * Create a string of N '*'.
 * @param int $n >= 0
 * @return string
 */
function listgrades_asterisks($n) {
    if ($n == 0) {
        return '';
    } else {
        return str_repeat('*', $n);
    }
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
