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
 * Manage student review sessions.
 *
 * @package mod_listgrades
 * @copyright 2026 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot . '/mod/listgrades/locallib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('listgrades', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$listgrades = $DB->get_record('listgrades', ['id' => $cm->instance], '*', MUST_EXIST);
require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/listgrades:view', $context);
require_capability('mod/listgrades:managereviews', $context);
if (!listgrades_review_is_enabled($listgrades) || (int) $listgrades->reviewdate <= 0) {
    throw new moodle_exception('reviewinvalidaction', 'listgrades');
}

$PAGE->set_url('/mod/listgrades/reviews.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($listgrades->name) . ': ' . get_string('reviewlist', 'listgrades'));
$PAGE->set_heading($course->fullname);
$PAGE->set_cm($cm, $course, $listgrades);
$PAGE->navbar->add(get_string('reviewbreadcrumb', 'listgrades'));
$renderer = $PAGE->get_renderer('mod_listgrades');
$reviewstates = listgrades_get_review_states((int) $listgrades->id);

echo $OUTPUT->header();
echo $renderer->reviews_page($cm, $course, $listgrades, $context, $reviewstates);
echo $OUTPUT->footer();
