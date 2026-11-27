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
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/report/grader/lib.php');

define('LISTGRADES_DISPLAY_LIST', 'list');
define('LISTGRADES_DISPLAY_INDIVIDUAL', 'individual');
define('LISTGRADES_ALLOWED_BOTH', 'both');

/**
 * Check whether the activity's grade publication window is open.
 *
 * @param stdClass $instance Listgrades activity.
 * @param int $timestamp Time to check.
 * @return bool Whether grades are published at this time.
 */
function listgrades_is_publication_open(stdClass $instance, int $timestamp): bool {
    return (empty($instance->opendate) || $instance->opendate < $timestamp) &&
        (empty($instance->closedate) || $instance->closedate > $timestamp);
}

/**
 * Return the default multilingual review statements, one per line.
 *
 * @return string Default statements.
 */
function listgrades_default_review_response_options(): string {
    return implode("\n", [
        '{mlang es}He visto mis pruebas y recibido las explicaciones.{mlang}' .
            '{mlang other}I have seen my assessment work and received explanations.{mlang}',
        '{mlang es}He reclamado una copia de mis pruebas de evaluación.{mlang}' .
            '{mlang other}I have requested a copy of my assessment work.{mlang}',
        '{mlang es}Tengo acceso online o a una copia de mis pruebas de evaluación.{mlang}' .
            '{mlang other}I have online access to or a copy of my assessment work.{mlang}',
        '{mlang es}Tengo observaciones que hacer sobre el proceso de revisión.{mlang}' .
            '{mlang other}I have observations about the review process.{mlang}',
    ]);
}

/**
 * Return configured review statements indexed by a hash of their unfiltered text.
 *
 * @return array Statement identifiers and text.
 */
function listgrades_review_response_options(): array {
    $configured = get_config('listgrades', 'reviewresponseoptions');
    if ($configured === false) {
        $configured = listgrades_default_review_response_options();
    }
    $options = [];
    foreach (preg_split('/\R/u', $configured) as $line) {
        $line = trim($line);
        if ($line !== '') {
            $options[hash('sha256', $line)] = $line;
        }
    }
    return $options;
}

/**
 * Serialize selected statements and an optional comment as readable message text.
 *
 * @param array $selected Selected statement identifiers.
 * @param string $comment Student comment.
 * @return string Message containing the statements and comment.
 */
function listgrades_serialize_review_message(array $selected, string $comment): string {
    $lines = [];
    $options = listgrades_review_response_options();
    foreach (array_unique($selected) as $identifier) {
        if (!isset($options[$identifier])) {
            throw new moodle_exception('reviewinvalidaction', 'listgrades');
        }
        $lines[] = '- ' . $options[$identifier];
    }
    if ($comment !== '') {
        $lines[] = get_string('comments') . ': ' . $comment;
    }
    return implode("\n", $lines);
}

/**
 * Format the recorded grade table as one plain-text line per item.
 *
 * @param string $snapshot Recorded HTML snapshot.
 * @return string Published items and their values.
 */
function listgrades_format_review_snapshot(string $snapshot): string {
    $document = new DOMDocument();
    $document->loadHTML('<meta charset="utf-8">' . $snapshot, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $lines = [];
    foreach ($xpath->query('//table[1]/tbody/tr | //table[1]/tr') as $row) {
        $cells = [];
        foreach ($xpath->query('./td', $row) as $cell) {
            $cells[] = trim(preg_replace('/\s+/u', ' ', html_to_text($document->saveHTML($cell), 0, false)));
        }
        if ($cells) {
            $name = array_shift($cells);
            $values = array_filter($cells, static fn(string $value): bool => $value !== '');
            $lines[] = $name . ($values ? ' : ' . implode('  ', $values) : '');
        }
    }
    return implode("\n", $lines);
}

/**
 * Render review messages and recorded statements with authors and timestamps.
 *
 * @param array $messages Review history entries.
 * @param context $context Module context for text filters.
 * @return string HTML history.
 */
function listgrades_render_review_history(array $messages, context $context): string {
    global $DB;

    $output = '';
    foreach ($messages as $message) {
        $author = $DB->get_record('user', ['id' => $message['actorid']], '*', IGNORE_MISSING);
        $name = $author && (int) $author->id !== (int) guest_user()->id && empty($author->deleted)
            ? fullname($author) : get_string('deleteduser');
        $metadata = html_writer::tag('strong', s($name), ['class' => 'listgrades-review-chat-author']);
        $metadata .= html_writer::tag('time', userdate($message['timecreated']), [
            'class' => 'listgrades-review-chat-time',
            'datetime' => gmdate('c', $message['timecreated']),
        ]);
        if (($message['action'] ?? '') === 'reopened') {
            $body = get_string('reviewreopened', 'listgrades');
        } else {
            $body = format_text(nl2br($message['comment']), FORMAT_HTML, [
                'context' => $context,
                'para' => false,
            ]);
        }
        if (!empty($message['snapshot'])) {
            $snapshot = html_writer::tag('strong', get_string('reviewpublishedgrade', 'listgrades'));
            $snapshot .= html_writer::tag('div', nl2br(s(listgrades_format_review_snapshot($message['snapshot']))), [
                'class' => 'listgrades-review-chat-body',
            ]);
            $body .= html_writer::tag('div', $snapshot, ['class' => 'listgrades-review-chat-snapshot mt-2']);
        }
        if ($body !== '') {
            $content = html_writer::tag('div', $metadata, ['class' => 'listgrades-review-chat-meta']);
            $content .= html_writer::tag('div', $body, ['class' => 'listgrades-review-chat-body']);
            $student = in_array($message['action'] ?? '', ['validated', 'commented'], true);
            $class = 'listgrades-review-chat-message ' .
                ($student ? 'listgrades-review-chat-student' : 'listgrades-review-chat-teacher');
            $output .= html_writer::tag('div', $content, ['class' => $class]);
        }
    }
    return $output === '' ? '' : html_writer::tag('div', $output, ['class' => 'listgrades-review-chat']);
}

/**
 * Check whether the review process is enabled both globally and for an activity.
 *
 * @param stdClass $instance Listgrades instance.
 * @return bool
 */
function listgrades_review_is_enabled(stdClass $instance): bool {
    $globallyenabled = get_config('listgrades', 'reviewprocess');
    return ($globallyenabled === '1' || $globallyenabled === 1) && !empty($instance->reviewenabled);
}

/**
 * Build each user's current review state from the append-only action history.
 *
 * @param int $listgradesid Activity instance id.
 * @param int|null $userid Restrict the history to one student, or null for all students.
 * @return array States keyed by user id.
 */
function listgrades_get_review_states(int $listgradesid, ?int $userid = null): array {
    global $DB;

    $states = [];
    $conditions = ['listgradesid' => $listgradesid];
    if ($userid !== null) {
        $conditions['userid'] = $userid;
    }
    foreach ($DB->get_records('listgrades_review', $conditions, 'id ASC') as $event) {
        $userid = (int) $event->userid;
        if (!isset($states[$userid])) {
            $states[$userid] = [
                'active' => false,
                'requesttime' => 0,
                'requestid' => 0,
                'lastaction' => '',
                'lastcomment' => '',
                'lasttime' => 0,
                'snapshot' => '',
                'comments' => [],
            ];
        }
        if ($event->action === 'requested') {
            $states[$userid]['active'] = true;
            $states[$userid]['requesttime'] = (int) $event->timecreated;
            $states[$userid]['requestid'] = (int) $event->id;
            $states[$userid]['lastaction'] = 'requested';
            $states[$userid]['lastcomment'] = '';
            $states[$userid]['lasttime'] = (int) $event->timecreated;
            $states[$userid]['snapshot'] = '';
            $states[$userid]['comments'] = [];
        } else if ($event->action === 'cancelled') {
            $states[$userid]['active'] = false;
            $states[$userid]['lastaction'] = 'cancelled';
            $states[$userid]['lasttime'] = (int) $event->timecreated;
        } else if ($states[$userid]['active'] && $event->action === 'reopened') {
            $states[$userid]['requestid'] = (int) $event->id;
            $states[$userid]['lastaction'] = 'requested';
            $states[$userid]['lasttime'] = (int) $event->timecreated;
            $states[$userid]['comments'][] = [
                'actorid' => (int) $event->actorid,
                'comment' => '',
                'timecreated' => (int) $event->timecreated,
                'action' => 'reopened',
            ];
        } else if ($states[$userid]['active'] && in_array($event->action, ['validated', 'commented'], true)) {
            $states[$userid]['lastaction'] = $event->action;
            $states[$userid]['lastcomment'] = $event->comment ?? '';
            $states[$userid]['lasttime'] = (int) $event->timecreated;
            $states[$userid]['snapshot'] = $event->snapshot ?? '';
            if (($event->comment !== null && $event->comment !== '') || !empty($event->snapshot)) {
                $states[$userid]['comments'][] = [
                    'actorid' => (int) ($event->actorid ?? $userid),
                    'comment' => $event->comment ?? '',
                    'timecreated' => (int) $event->timecreated,
                    'action' => $event->action,
                    'snapshot' => $event->snapshot ?? '',
                ];
            }
        } else if ($states[$userid]['active'] && $event->action === 'teachercommented') {
            $states[$userid]['comments'][] = [
                'actorid' => (int) ($event->actorid ?? 0),
                'comment' => $event->comment ?? '',
                'timecreated' => (int) $event->timecreated,
                'action' => $event->action,
            ];
        }
    }
    return $states;
}

/**
 * Create an immutable copy of the grade table published to one student.
 *
 * @param stdClass $instance Listgrades activity.
 * @param context $context Module context.
 * @param int $userid Student id.
 * @param int|null $timestamp Snapshot time, or null for the current time.
 * @return string Snapshot HTML.
 */
function listgrades_build_review_snapshot(stdClass $instance, context $context, int $userid, ?int $timestamp = null): string {
    global $DB;

    $course = $DB->get_record('course', ['id' => $instance->course], '*', MUST_EXIST);
    $grader = new grade_report_listing($course->id, null, $context);
    $gradeitems = $grader->get_gradeitems();
    $itemnames = $grader->get_item_names();
    $usergrades = [];
    $sql = 'SELECT gg.* FROM {grade_grades} gg
              JOIN {grade_items} gi ON gi.id = gg.itemid
             WHERE gi.courseid = :courseid AND gg.userid = :userid';
    foreach ($DB->get_records_sql($sql, ['courseid' => $course->id, 'userid' => $userid]) as $record) {
        $usergrades[$record->itemid] = new grade_grade($record, false);
    }
    return listgrades_render_individual_table(
        array_keys((array) unserialize_array($instance->items)),
        $gradeitems,
        $itemnames,
        $usergrades,
        $context,
        $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST),
        $timestamp ?? time()
    );
}

/**
 * Expand the placeholders available in the default intro and footer templates.
 *
 * Unknown placeholders are intentionally preserved so that templates can be
 * extended without losing their source when loaded in the activity form.
 *
 * @param string $template template configured by the site administrator
 * @param stdClass $course course record
 * @return string expanded template
 */
function listgrades_expand_course_template(string $template, stdClass $course): string {
    global $OUTPUT;

    $context = context_course::instance($course->id);
    $logourl = $OUTPUT->get_logo_url();
    $logourl = $logourl ? $logourl->out() : '';
    $compactlogourl = $OUTPUT->get_compact_logo_url();
    $compactlogourl = $compactlogourl ? $compactlogourl->out() : '';
    $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
    $courseimage = \core_course\external\course_summary_exporter::get_course_image($course);
    if (!$courseimage) {
        $courseimage = $OUTPUT->get_generated_url_for_course($context);
    }
    $coursesummary = format_text($course->summary, $course->summaryformat, ['context' => $context]);

    $values = [
        'logourl' => $logourl,
        'logocompacturl' => $compactlogourl,
        'coursefullname' => format_string($course->fullname, true, ['context' => $context]),
        'courseshortname' => format_string($course->shortname, true, ['context' => $context]),
        'courseidnumber' => s($course->idnumber),
        'courseurl' => $courseurl->out(),
        'courseimage' => $courseimage,
        'coursesummary' => $coursesummary,
    ];
    $replacements = [];
    foreach ($values as $identifier => $value) {
        $replacements['{' . $identifier . '}'] = $value;
    }

    return strtr($template, $replacements);
}

/**
 * Resolve the activity display mode according to the site-wide policy.
 *
 * @param string|null $activitymode mode stored in the activity
 * @param string|null $allowedmodes site-wide allowed modes
 * @param string|null $defaultmode site-wide default when both modes are allowed
 * @return string resolved display mode
 */
function listgrades_resolve_display_mode(
    ?string $activitymode,
    ?string $allowedmodes,
    ?string $defaultmode = null
): string {
    if ($allowedmodes === LISTGRADES_DISPLAY_LIST) {
        return LISTGRADES_DISPLAY_LIST;
    }
    if ($allowedmodes === LISTGRADES_DISPLAY_INDIVIDUAL) {
        return LISTGRADES_DISPLAY_INDIVIDUAL;
    }

    $validmodes = [LISTGRADES_DISPLAY_LIST, LISTGRADES_DISPLAY_INDIVIDUAL];
    if (in_array($activitymode, $validmodes, true)) {
        return $activitymode;
    }
    if (in_array($defaultmode, $validmodes, true)) {
        return $defaultmode;
    }
    return LISTGRADES_DISPLAY_INDIVIDUAL;
}

/**
 * Grader report adapted for grade publication.
 */
class grade_report_listing extends grade_report_grader {
    /**
     * Constructor to override the calculation of grade_tree (avoid removing collapsed categories).
     *
     * @param int $courseid Course id.
     * @param grade_plugin_return|null $gpr Grade report return information.
     * @param context $context Report context.
     * @param int|null $page Current page.
     * @param string|int $sortitemid Sort field or grade item id.
     */
    public function __construct($courseid, $gpr, $context, $page = null, $sortitemid = 'lastname') {
        global $CFG;
        parent::__construct($courseid, $gpr, $context, $page);

        // Don't collapse categories.
        $this->collapsed = ['aggregatesonly' => [], 'gradesonly' => []];

        if (empty($CFG->enableoutcomes)) {
            $nooutcomes = false;
        } else {
            $nooutcomes = get_user_preferences('grade_report_shownooutcomes');
        }

        // Use the user report preference first, then the course or site setting.
        $switch = $this->get_pref('aggregationposition');
        if ($switch == '') {
            $switch = grade_get_setting($this->courseid, 'aggregationposition', $CFG->grade_aggregationposition);
        }
        // Grab the grade tree for this course.
        $this->gtree = new grade_tree($this->courseid, true, $switch, $this->collapsed, $nooutcomes);
        $this->sortitemid = $sortitemid;
    }

    /**
     * Gets the gradetree object.
     */
    public function get_gradetree() {
        return $this->gtree;
    }

    /**
     * Returns display names for grade items.
     *
     * @return array
     */
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

    /**
     * Returns category names keyed by category id.
     *
     * @return array
     */
    public function get_category_names() {
        $categories = [];
        array_walk_recursive($this->gtree->top_element, function ($item, $key) use (&$categories) {
            if ($item instanceof grade_category) {
                $categories[$item->id] = $item->get_name();
            }
        });
        return $categories;
    }

    /**
     * Returns gradeable items from the report tree.
     *
     * @return array
     */
    public function get_gradeitems() {
        $items = $this->gtree->get_items();
        $allgradeitems = array_filter($items, function ($item) {
            return $item->gradetype != GRADE_TYPE_NONE;
        });
        return $allgradeitems;
    }

    /**
     * Returns the loaded grades.
     *
     * @return array
     */
    public function get_grades() {
        return $this->grades;
    }

    /**
     * Returns the loaded users.
     *
     * @return array
     */
    public function get_users() {
        return $this->users;
    }

    /**
     * Disables pagination for publication output.
     *
     * @return int
     */
    public function get_students_per_page(): int {
        return PHP_INT_MAX;
    }
}

/**
 * Build the stored item selection from the grade and feedback checkbox columns.
 *
 * Positive ids select grades and negative ids select feedback. Items are returned
 * in gradebook order, with the grade before its feedback when both are selected.
 *
 * @param int[] $ordereditemids grade item ids in gradebook order
 * @param array $grades grade checkbox values keyed by item id
 * @param array $feedback feedback checkbox values keyed by item id
 * @return int[] selected positive and negative ids
 */
function listgrades_build_item_selection(array $ordereditemids, array $grades, array $feedback): array {
    $selecteditems = [];
    foreach ($ordereditemids as $itemid) {
        $itemid = (int) $itemid;
        if (!empty($grades[$itemid])) {
            $selecteditems[] = $itemid;
        }
        if (!empty($feedback[$itemid])) {
            $selecteditems[] = -$itemid;
        }
    }
    return $selecteditems;
}

/**
 * Return the selected grade item ids once, preserving their configured order.
 *
 * Negative ids select feedback for the corresponding positive grade item.
 *
 * @param array $selecteditems selected ids
 * @return array positive grade item ids
 */
function listgrades_get_selected_gradeitem_ids(array $selecteditems): array {
    $itemids = [];
    foreach ($selecteditems as $itemid) {
        $itemid = abs((int) $itemid);
        if ($itemid && !in_array($itemid, $itemids, true)) {
            $itemids[] = $itemid;
        }
    }
    return $itemids;
}

/**
 * Return only explicitly selected grade item ids.
 *
 * Negative ids select feedback and must not expose the corresponding grade.
 *
 * @param array $selecteditems selected ids
 * @return array positive grade item ids
 */
function listgrades_get_selected_grade_ids(array $selecteditems): array {
    $gradeids = [];
    foreach ($selecteditems as $itemid) {
        $itemid = (int) $itemid;
        if ($itemid > 0 && !in_array($itemid, $gradeids, true)) {
            $gradeids[] = $itemid;
        }
    }
    return $gradeids;
}

/**
 * Calculate a percentile using linear interpolation between adjacent values.
 *
 * @param array $values numeric values
 * @param float $percentile percentile between 0 and 1
 * @return float|null
 */
function listgrades_percentile(array $values, float $percentile): ?float {
    if (!$values) {
        return null;
    }
    sort($values, SORT_NUMERIC);
    $position = max(0, min(1, $percentile)) * (count($values) - 1);
    $lower = (int) floor($position);
    $upper = (int) ceil($position);
    if ($lower === $upper) {
        return (float) $values[$lower];
    }
    $weight = $position - $lower;
    return (float) ($values[$lower] + (($values[$upper] - $values[$lower]) * $weight));
}

/**
 * Format a numeric grade using gradebook decimals, omitting a zero-only decimal part.
 *
 * @param float $value grade value
 * @param grade_item $item grade item
 * @return string
 */
function listgrades_format_numeric_grade_value(float $value, grade_item $item): string {
    $decimals = $item->get_decimals();
    $roundedvalue = round($value, $decimals);
    if ($roundedvalue == round($roundedvalue)) {
        return format_float($roundedvalue, 0);
    }
    return grade_format_gradevalue($value, $item, true, GRADE_DISPLAY_TYPE_REAL);
}

/**
 * Format an individual grade together with its maximum or textual scale reference.
 *
 * @param float|null $finalgrade final grade, or null when it has not been assigned
 * @param grade_item $item grade item
 * @return string
 */
function listgrades_format_grade_with_reference(?float $finalgrade, grade_item $item): string {
    $formattedgrade = grade_format_gradevalue($finalgrade, $item, true, GRADE_DISPLAY_TYPE_REAL);
    if ($item->gradetype == GRADE_TYPE_SCALE) {
        $scale = $item->load_scale();
        if (!$scale || !$scale->scale_items) {
            return $formattedgrade;
        }
        $scaleitems = array_map('format_string', $scale->scale_items);
        return get_string('scalegradewithreference', 'listgrades', (object) [
            'grade' => $formattedgrade,
            'scale' => implode('; ', $scaleitems),
        ]);
    }
    if ($item->gradetype == GRADE_TYPE_VALUE) {
        if ($finalgrade !== null) {
            $formattedgrade = listgrades_format_numeric_grade_value($finalgrade, $item);
        }
        $maximum = listgrades_format_numeric_grade_value((float) $item->grademax, $item);
        return get_string('numericgradewithmaximum', 'listgrades', (object) [
            'grade' => $formattedgrade,
            'maximum' => $maximum,
        ]);
    }
    return $formattedgrade;
}

/**
 * Render the current user's selected grades.
 *
 * @param array $selecteditems selected ids, with negative ids representing feedback
 * @param array $gradeitems grade_item objects keyed by id
 * @param array $itemnames item names keyed by id
 * @param array|null $usergrades current user's grades keyed by item id
 * @param context $context module context
 * @param stdClass $user current user
 * @param int|null $snapshottime Snapshot timestamp, or null for a date-only display.
 * @return string
 */
function listgrades_render_individual_table(
    array $selecteditems,
    array $gradeitems,
    array $itemnames,
    ?array $usergrades,
    context $context,
    stdClass $user,
    ?int $snapshottime = null
): string {
    $showgrade = [];
    $showfeedback = [];
    foreach ($selecteditems as $itemid) {
        if ($itemid < 0) {
            $showfeedback[abs($itemid)] = true;
        } else if ($itemid > 0) {
            $showgrade[$itemid] = true;
        }
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable listgrades-individual-table';
    $table->head = [get_string('gradeitem', 'listgrades')];
    if ($showgrade) {
        $table->head[] = get_string('grade', 'listgrades');
    }
    if ($showfeedback) {
        $table->head[] = get_string('feedback', 'listgrades');
    }

    $itemcount = 0;
    foreach (listgrades_get_selected_gradeitem_ids($selecteditems) as $itemid) {
        if (!isset($gradeitems[$itemid])) {
            continue;
        }
        $itemcount++;
        $item = $gradeitems[$itemid];
        $grade = $usergrades[$itemid] ?? null;
        $finalgrade = $grade ? $grade->finalgrade : null;
        $row = [format_string($itemnames[$itemid])];

        if ($showgrade) {
            $row[] = !empty($showgrade[$itemid])
                ? listgrades_format_grade_with_reference($finalgrade === null ? null : (float) $finalgrade, $item)
                : '';
        }

        if ($showfeedback) {
            if (!empty($showfeedback[$itemid]) && $grade && $grade->feedback !== null && $grade->feedback !== '') {
                $format = $grade->feedbackformat ?? FORMAT_MOODLE;
                $row[] = format_text($grade->feedback, $format, ['context' => $context]);
            } else {
                $row[] = '-';
            }
        }
        $table->data[] = $row;
    }

    $introstring = $itemcount === 1 ? 'individualgradeintro' : 'individualgradesintro';
    $intro = html_writer::tag('p', get_string($introstring, 'listgrades', fullname($user)), [
        'class' => 'listgrades-individual-intro',
    ]);
    $dateformat = $snapshottime === null ? '%d/%m/%Y' : '%d/%m/%Y %H:%M';
    $date = html_writer::tag('p', get_string('individualgradedate', 'listgrades', userdate($snapshottime ?? time(), $dateformat)), [
        'class' => 'listgrades-individual-date',
    ]);

    return $intro . html_writer::table($table) . $date;
}

/**
 * Format grade feedback safely for publication.
 *
 * @param object $grade Grade record with feedback and feedbackformat.
 * @param context $context Module context.
 * @return string Filtered feedback, or a placeholder when empty.
 */
function listgrades_format_feedback(object $grade, context $context): string {
    if ($grade->feedback === null || $grade->feedback === '') {
        return '--';
    }
    return format_text($grade->feedback, $grade->feedbackformat ?? FORMAT_MOODLE, [
        'context' => $context,
        'noclean' => false,
        'trusted' => false,
    ]);
}

/**
 * Convert a grade to a percentage position in the item's range.
 *
 * @param float $value grade
 * @param float $minimum range minimum
 * @param float $maximum range maximum
 * @return float percentage from 0 to 100
 */
function listgrades_grade_position(float $value, float $minimum, float $maximum): float {
    if ($maximum <= $minimum) {
        return 50.0;
    }
    return max(0, min(100, (($value - $minimum) / ($maximum - $minimum)) * 100));
}

/**
 * Generate an illustrative grade within the observed range.
 *
 * This is used only to show the marker when a teacher previews the activity and
 * does not have a grade of their own. The generated value is never persisted.
 *
 * @param array $values observed numeric grades
 * @param int $decimals number of decimal places to retain
 * @return float|null illustrative grade, or null when there are no grades
 */
function listgrades_random_preview_grade(array $values, int $decimals): ?float {
    if (!$values) {
        return null;
    }

    $minimum = (float) min($values);
    $maximum = (float) max($values);
    if ($minimum === $maximum) {
        return $minimum;
    }

    $fraction = random_int(0, 10000) / 10000;
    return round($minimum + (($maximum - $minimum) * $fraction), max(0, $decimals));
}

/**
 * Render the current user's picture as a chart marker.
 *
 * @param grade_item $item grade item
 * @param float $usergrade current user's grade
 * @param stdClass $user current user
 * @param int $courseid course id
 * @param float $position horizontal percentage position
 * @param bool $illustrative whether this is an illustrative teacher preview
 * @param string|null $displaygrade formatted grade displayed in the marker tooltip
 * @return string
 */
function listgrades_render_user_marker(
    grade_item $item,
    float $usergrade,
    stdClass $user,
    int $courseid,
    float $position,
    bool $illustrative,
    ?string $displaygrade = null
): string {
    global $OUTPUT;

    $picture = $OUTPUT->user_picture($user, [
        'courseid' => $courseid,
        'size' => 35,
        'link' => false,
        'alttext' => true,
    ]);
    if ($displaygrade !== null) {
        $gradelabel = $displaygrade;
    } else if ($item->gradetype == GRADE_TYPE_VALUE) {
        $gradelabel = listgrades_format_numeric_grade_value($usergrade, $item);
    } else {
        $gradelabel = grade_format_gradevalue($usergrade, $item, true, GRADE_DISPLAY_TYPE_REAL);
    }
    $markerlabel = $illustrative ? get_string('illustrativegrade', 'listgrades') : get_string('grade', 'listgrades');
    return html_writer::span($picture, 'listgrades-user-marker', [
        'style' => 'left:' . max(0, min(100, $position)) . '%',
        'title' => $markerlabel . ': ' . $gradelabel,
    ]);
}

/**
 * Build a distribution using the course grade letter boundaries.
 *
 * @param grade_item $item numeric grade item
 * @param array $values numeric grades
 * @return array labels and counts in the order configured by Moodle
 */
function listgrades_build_letter_distribution(grade_item $item, array $values): array {
    $context = context_course::instance($item->courseid);
    $labels = array_reverse(array_values(grade_get_letters($context)));
    $counts = array_fill(0, count($labels), 0);

    foreach ($values as $value) {
        $label = grade_format_gradevalue((float) $value, $item, true, GRADE_DISPLAY_TYPE_LETTER);
        $index = array_search($label, $labels, true);
        if ($index === false) {
            $labels[] = $label;
            $counts[] = 1;
        } else {
            $counts[$index]++;
        }
    }

    return ['labels' => $labels, 'counts' => $counts];
}

/**
 * Render numeric grades as a horizontal grade-letter distribution.
 *
 * @param grade_item $item numeric grade item
 * @param array $values cohort grades
 * @param float|null $usergrade current user's grade
 * @param stdClass $user current user
 * @param int $courseid course id
 * @param bool $illustrative whether this is an illustrative teacher preview
 * @return string
 */
function listgrades_render_letter_chart(
    grade_item $item,
    array $values,
    ?float $usergrade,
    stdClass $user,
    int $courseid,
    bool $illustrative = false
): string {
    $distribution = listgrades_build_letter_distribution($item, $values);
    $labels = $distribution['labels'];
    $counts = $distribution['counts'];
    $total = array_sum($counts);
    if (!$total || !$labels) {
        return '';
    }

    $bar = '';
    $legend = '';
    $legendtexts = [];
    $userposition = null;
    $userlabel = null;
    $cumulativepercentage = 0.0;
    if ($usergrade !== null) {
        $userlabel = grade_format_gradevalue($usergrade, $item, true, GRADE_DISPLAY_TYPE_LETTER);
    }
    foreach ($labels as $index => $label) {
        $percentage = ($counts[$index] / $total) * 100;
        if ($label === $userlabel) {
            $userposition = $cumulativepercentage + ($percentage / 2);
        }
        if ($counts[$index]) {
            $segmenttext = format_string($label) . ': ' . $counts[$index] .
                ' (' . format_float($percentage, 1) . '%)';
            $bar .= html_writer::span(format_float($percentage, 1) . '%', 'listgrades-scale-segment', [
                'style' => 'width:' . $percentage . '%',
                'title' => $segmenttext,
            ]);
        } else {
            $segmenttext = format_string($label) . ': 0 (0.0%)';
        }
        $legendtexts[] = $segmenttext;
        $legend .= html_writer::tag('li', $segmenttext, ['class' => 'listgrades-scale-legend-item']);
        $cumulativepercentage += $percentage;
    }

    $letterbar = html_writer::div($bar, 'listgrades-scale-bar', [
        'role' => 'img',
        'aria-label' => implode('; ', array_map('strip_tags', $legendtexts)),
    ]);
    if ($usergrade !== null && $userposition !== null) {
        $letterbar .= listgrades_render_user_marker(
            $item,
            $usergrade,
            $user,
            $courseid,
            $userposition,
            $illustrative,
            $userlabel
        );
    }
    $output = html_writer::div($letterbar, 'listgrades-scale-plot');
    $output .= listgrades_render_chart_axis(format_string(reset($labels)), format_string(end($labels)));
    $output .= html_writer::tag('ul', $legend, ['class' => 'list-unstyled listgrades-scale-legend']);
    return html_writer::div($output, 'listgrades-stat-chart listgrades-letter-chart');
}

/**
 * Render the complete horizontal domain of a statistical chart.
 *
 * @param string $minimum formatted lower boundary
 * @param string $maximum formatted upper boundary
 * @return string
 */
function listgrades_render_chart_axis(string $minimum, string $maximum): string {
    $labels = html_writer::span($minimum, 'listgrades-chart-axis-min');
    $labels .= html_writer::span($maximum, 'listgrades-chart-axis-max');
    return html_writer::div($labels, 'listgrades-chart-axis', ['aria-hidden' => 'true']);
}

/**
 * Render a numeric grade item as a box plot.
 *
 * @param grade_item $item grade item
 * @param array $values cohort grades
 * @param float|null $usergrade current user's grade
 * @param stdClass $user current user
 * @param int $courseid course id
 * @param bool $illustrative whether the marker is an illustrative teacher preview
 * @return string
 */
function listgrades_render_box_plot(
    grade_item $item,
    array $values,
    ?float $usergrade,
    stdClass $user,
    int $courseid,
    bool $illustrative = false
): string {
    if (!$values) {
        return '';
    }
    sort($values, SORT_NUMERIC);
    $stats = (object) [
        'min' => (float) reset($values),
        'q1' => listgrades_percentile($values, .25),
        'median' => listgrades_percentile($values, .5),
        'q3' => listgrades_percentile($values, .75),
        'max' => (float) end($values),
    ];
    // Use the complete configured range of the grade item as the horizontal domain.
    $rangemin = (float) $item->grademin;
    $rangemax = (float) $item->grademax;
    if (!is_finite($rangemin) || !is_finite($rangemax) || $rangemax <= $rangemin) {
        $rangemin = $stats->min;
        $rangemax = $stats->max;
        if ($usergrade !== null) {
            $rangemin = min($rangemin, $usergrade);
            $rangemax = max($rangemax, $usergrade);
        }
    }
    if ($rangemin === $rangemax) {
        $padding = max(1, abs($rangemin) * .1);
        $rangemin -= $padding;
        $rangemax += $padding;
    }
    $position = static fn(float $value): float => listgrades_grade_position($value, $rangemin, $rangemax);

    $plot = html_writer::span('', 'listgrades-boxplot-whisker', [
        'style' => 'left:' . $position($stats->min) . '%;width:' .
            ($position($stats->max) - $position($stats->min)) . '%',
    ]);
    $plot .= html_writer::span('', 'listgrades-boxplot-cap', ['style' => 'left:' . $position($stats->min) . '%']);
    $plot .= html_writer::span('', 'listgrades-boxplot-cap', ['style' => 'left:' . $position($stats->max) . '%']);
    $plot .= html_writer::span('', 'listgrades-boxplot-box', [
        'style' => 'left:' . $position($stats->q1) . '%;width:' .
            ($position($stats->q3) - $position($stats->q1)) . '%',
    ]);
    $plot .= html_writer::span('', 'listgrades-boxplot-median', [
        'style' => 'left:' . $position($stats->median) . '%',
    ]);

    if ($usergrade !== null) {
        $plot .= listgrades_render_user_marker(
            $item,
            $usergrade,
            $user,
            $courseid,
            $position($usergrade),
            $illustrative
        );
    }

    $summary = (object) [];
    foreach (['min', 'q1', 'median', 'q3', 'max'] as $stat) {
        $summary->$stat = listgrades_format_numeric_grade_value($stats->$stat, $item);
    }
    $axis = listgrades_render_chart_axis(
        listgrades_format_numeric_grade_value($rangemin, $item),
        listgrades_format_numeric_grade_value($rangemax, $item)
    );

    $output = html_writer::div($plot, 'listgrades-boxplot', [
        'role' => 'img',
        'aria-label' => get_string('boxplotsummary', 'listgrades', $summary),
    ]);
    $output .= $axis;
    $output .= html_writer::div(
        get_string('boxplotsummary', 'listgrades', $summary),
        'small text-muted listgrades-stat-summary'
    );
    return html_writer::div($output, 'listgrades-stat-chart');
}

/**
 * Build histogram bins for numeric grades.
 *
 * Small integer ranges get one bin per value. Other distributions use between 5
 * and 10 bins. Widths of 1, 2, 5, 10, 20, 25, 50 or 100 take priority,
 * choosing the smallest width that does not exceed 10 bins. Proportionally
 * scaled round widths and the sample size are used only as fallbacks. The final
 * bin may be shorter to preserve the configured range.
 *
 * @param array $values numeric values
 * @param float|null $gradepass passing grade
 * @param float|null $domainminimum configured domain minimum
 * @param float|null $domainmaximum configured domain maximum
 * @return array list of bins containing lower, upper, count and percentage
 */
function listgrades_build_histogram(
    array $values,
    ?float $gradepass = null,
    ?float $domainminimum = null,
    ?float $domainmaximum = null
): array {
    if (!$values) {
        return [];
    }

    $values = array_map('floatval', $values);
    sort($values, SORT_NUMERIC);
    $observedminimum = (float) reset($values);
    $observedmaximum = (float) end($values);
    $hasconfigureddomain = $domainminimum !== null && $domainmaximum !== null && $domainmaximum > $domainminimum;
    $minimum = $hasconfigureddomain ? $domainminimum : $observedminimum;
    $maximum = $hasconfigureddomain ? $domainmaximum : $observedmaximum;
    $total = count($values);

    if ($minimum === $maximum) {
        return [[
            'lower' => $minimum,
            'upper' => $maximum,
            'count' => $total,
            'percentage' => 100.0,
        ]];
    }

    $allintegers = count(array_filter($values, static fn(float $value): bool => floor($value) === $value)) === $total;
    $integerrange = (int) round($maximum - $minimum);
    $integerbins = $allintegers && $integerrange <= 9;
    if ($integerbins) {
        $bincount = $integerrange + 1;
        $binwidth = 1.0;
    } else {
        $suggestedbincount = max(5, min(10, (int) ceil(sqrt($total))));
        $range = $maximum - $minimum;
        $baseexponent = (int) floor(log10($range));
        $bincount = $suggestedbincount;
        $binwidth = $range / $bincount;
        $roundwidthfound = false;
        foreach ([1.0, 2.0, 5.0, 10.0, 20.0, 25.0, 50.0, 100.0] as $candidatewidth) {
            $candidatecount = (int) ceil(($range / $candidatewidth) - 1.0e-12);
            if ($candidatecount < 5 || $candidatecount > 10) {
                continue;
            }
            $bincount = $candidatecount;
            $binwidth = $candidatewidth;
            $roundwidthfound = true;
            break;
        }
        if (!$roundwidthfound) {
            foreach (range($baseexponent - 2, $baseexponent + 1) as $exponent) {
                $magnitude = 10 ** $exponent;
                foreach ([1.0, 2.0, 2.5, 5.0] as $multiplier) {
                    $candidatewidth = $multiplier * $magnitude;
                    $candidatecount = (int) ceil(($range / $candidatewidth) - 1.0e-12);
                    if ($candidatecount < 5 || $candidatecount > 10) {
                        continue;
                    }
                    $bincount = $candidatecount;
                    $binwidth = $candidatewidth;
                    break 2;
                }
            }
        }
    }

    $bins = [];
    for ($index = 0; $index < $bincount; $index++) {
        $lower = $minimum + ($index * $binwidth);
        if ($integerbins) {
            $upper = $lower;
        } else {
            $upper = $index === $bincount - 1 ? $maximum : $minimum + (($index + 1) * $binwidth);
        }
        $bins[$index] = ['lower' => $lower, 'upper' => $upper, 'count' => 0, 'percentage' => 0.0];
    }

    foreach ($values as $value) {
        if ($integerbins) {
            $index = (int) round($value - $minimum);
        } else {
            $index = max(0, min($bincount - 1, (int) floor(($value - $minimum) / $binwidth)));
        }
        $bins[$index]['count']++;
    }
    foreach ($bins as &$bin) {
        $bin['percentage'] = ($bin['count'] / $total) * 100;
    }
    unset($bin);

    return $bins;
}

/**
 * Render numeric grades as a histogram.
 *
 * @param grade_item $item grade item
 * @param array $values cohort grades
 * @param float|null $usergrade current user's grade
 * @param stdClass $user current user
 * @param int $courseid course id
 * @param bool $illustrative whether this is an illustrative teacher preview
 * @return string
 */
function listgrades_render_histogram(
    grade_item $item,
    array $values,
    ?float $usergrade,
    stdClass $user,
    int $courseid,
    bool $illustrative = false
): string {
    $gradepass = empty($item->gradepass) ? null : (float) $item->gradepass;
    $bins = listgrades_build_histogram(
        $values,
        $gradepass,
        (float) $item->grademin,
        (float) $item->grademax
    );
    if (!$bins) {
        return '';
    }

    $maximumcount = max(array_column($bins, 'count'));
    $bars = '';
    $summary = [];
    foreach ($bins as $bin) {
        if ($bin['lower'] === $bin['upper']) {
            $label = listgrades_format_numeric_grade_value($bin['lower'], $item);
        } else {
            $label = listgrades_format_numeric_grade_value($bin['lower'], $item) . '–' .
                listgrades_format_numeric_grade_value($bin['upper'], $item);
        }
        $description = get_string('histogrambin', 'listgrades', (object) [
            'range' => $label,
            'count' => $bin['count'],
            'percentage' => format_float($bin['percentage'], 1),
        ]);
        $height = $maximumcount ? ($bin['count'] / $maximumcount) * 100 : 0;
        $bar = html_writer::span((string) $bin['count'], 'listgrades-histogram-count');
        $bar .= html_writer::span('', 'listgrades-histogram-column', ['style' => 'height:' . $height . '%']);
        $bar .= html_writer::span($label, 'listgrades-histogram-label');
        $bars .= html_writer::span($bar, 'listgrades-histogram-bin', ['title' => $description]);
        $summary[] = $description;
    }

    if ($usergrade !== null) {
        $minimum = (float) $item->grademin;
        $maximum = (float) $item->grademax;
        $position = listgrades_grade_position($usergrade, $minimum, $maximum);
        $bars .= listgrades_render_user_marker(
            $item,
            $usergrade,
            $user,
            $courseid,
            $position,
            $illustrative
        );
    }

    $output = html_writer::div($bars, 'listgrades-histogram', [
        'role' => 'img',
        'aria-label' => implode('; ', $summary),
    ]);
    $output .= listgrades_render_chart_axis(
        listgrades_format_numeric_grade_value((float) $item->grademin, $item),
        listgrades_format_numeric_grade_value((float) $item->grademax, $item)
    );
    return html_writer::div($output, 'listgrades-stat-chart listgrades-histogram-chart');
}

/**
 * Render a scale grade item as a horizontal stacked distribution.
 *
 * @param grade_item $item grade item
 * @param array $values cohort grades
 * @param float|null $usergrade current user's grade
 * @param stdClass $user current user
 * @param int $courseid course id
 * @param bool $illustrative whether this is an illustrative teacher preview
 * @return string
 */
function listgrades_render_scale_chart(
    grade_item $item,
    array $values,
    ?float $usergrade,
    stdClass $user,
    int $courseid,
    bool $illustrative = false
): string {
    $scale = $item->load_scale();
    if (!$scale || !$values) {
        return '';
    }

    $counts = array_fill(0, count($scale->scale_items), 0);
    foreach ($values as $value) {
        $index = (int) round($item->bounded_grade($value)) - 1;
        if (isset($counts[$index])) {
            $counts[$index]++;
        }
    }
    $total = array_sum($counts);
    if (!$total) {
        return '';
    }

    $bar = '';
    $legend = '';
    $legendtexts = [];
    $userposition = null;
    $cumulativepercentage = 0.0;
    $userindex = $usergrade === null ? null : (int) round($item->bounded_grade($usergrade)) - 1;
    foreach ($scale->scale_items as $index => $label) {
        $percentage = ($counts[$index] / $total) * 100;
        if ($index === $userindex) {
            $userposition = $cumulativepercentage + ($percentage / 2);
        }
        if ($counts[$index]) {
            $bar .= html_writer::span(format_float($percentage, 1) . '%', 'listgrades-scale-segment', [
                'style' => 'width:' . $percentage . '%',
                'title' => format_string($label) . ': ' . $counts[$index] . ' (' . format_float($percentage, 1) . '%)',
            ]);
        }
        $legendtext = format_string($label) . ': ' . $counts[$index] . ' (' . format_float($percentage, 1) . '%)';
        $legendtexts[] = $legendtext;
        $legend .= html_writer::tag('li', $legendtext, ['class' => 'listgrades-scale-legend-item']);
        $cumulativepercentage += $percentage;
    }

    $scalebar = html_writer::div($bar, 'listgrades-scale-bar', [
        'role' => 'img',
        'aria-label' => implode('; ', array_map('strip_tags', $legendtexts)),
    ]);
    if ($usergrade !== null && $userposition !== null) {
        $scalebar .= listgrades_render_user_marker(
            $item,
            $usergrade,
            $user,
            $courseid,
            $userposition,
            $illustrative
        );
    }
    $output = html_writer::div($scalebar, 'listgrades-scale-plot');
    $output .= listgrades_render_chart_axis(
        format_string($scale->scale_items[array_key_first($scale->scale_items)]),
        format_string($scale->scale_items[array_key_last($scale->scale_items)])
    );
    $output .= html_writer::tag('ul', $legend, ['class' => 'list-unstyled listgrades-scale-legend']);
    return html_writer::div($output, 'listgrades-stat-chart');
}

/**
 * Decide whether aggregate statistics may be published for an item.
 *
 * An item without grades is left to the normal empty-statistics handling. When
 * privacy conditions are enabled, aggregates require more than five grades.
 *
 * @param int $gradecount number of students with a grade
 * @param bool $privacyconditions whether the site privacy conditions are enabled
 * @return bool
 */
function listgrades_privacy_allows_aggregates(int $gradecount, bool $privacyconditions): bool {
    return !$privacyconditions || $gradecount === 0 || $gradecount > 5;
}

/**
 * Decide whether a distribution across grade bands may be published.
 *
 * @param array $counts number of grades in each band
 * @param bool $privacyconditions whether the site privacy conditions are enabled
 * @return bool
 */
function listgrades_privacy_allows_distribution(array $counts, bool $privacyconditions): bool {
    if (!$privacyconditions) {
        return true;
    }
    $populatedbands = array_filter($counts, static fn($count): bool => (int) $count > 0);
    return count($populatedbands) > 1;
}

/**
 * Decide whether numeric statistics may be published when a passing grade exists.
 *
 * @param array $values numeric grades
 * @param float|null $gradepass passing grade, or null when it is not configured
 * @param bool $privacyconditions whether the site privacy conditions are enabled
 * @return bool
 */
function listgrades_privacy_allows_numeric_statistics(
    array $values,
    ?float $gradepass,
    bool $privacyconditions
): bool {
    if (!$privacyconditions || !$values || $gradepass === null) {
        return true;
    }
    foreach ($values as $value) {
        if ((float) $value >= $gradepass) {
            return true;
        }
    }
    return false;
}

/**
 * Decide whether pass totals and percentages may be published.
 *
 * @param array $values grades from assessed students
 * @param float|null $gradepass passing grade, or null when it is not configured
 * @param bool $privacyconditions whether the site privacy conditions are enabled
 * @return bool
 */
function listgrades_privacy_allows_global_percentage(
    array $values,
    ?float $gradepass,
    bool $privacyconditions
): bool {
    if (!$privacyconditions || !$values || $gradepass === null) {
        return true;
    }
    $passed = count(array_filter(
        $values,
        static fn(float $value): bool => $value >= $gradepass
    ));
    return $passed > 0 && $passed < count($values);
}

/**
 * Render the number of assessed students and the overall pass figures.
 *
 * @param array $values grades from assessed students
 * @param float|null $gradepass passing grade, or null when it is not configured
 * @return string
 */
function listgrades_render_global_percentage(array $values, ?float $gradepass): string {
    if (!$values) {
        return '';
    }
    if ($gradepass === null) {
        $text = get_string('globalgradesnopass', 'listgrades', count($values));
    } else {
        $passed = count(array_filter(
            $values,
            static fn(float $value): bool => $value >= $gradepass
        ));
        $text = get_string('globalgradessummary', 'listgrades', (object) [
            'presented' => count($values),
            'passed' => $passed,
            'percentage' => format_float(($passed / count($values)) * 100, 1),
        ]);
    }
    return html_writer::div($text, 'listgrades-global-summary');
}

/**
 * Return whether the site has explicitly enabled the privacy conditions.
 *
 * @return bool
 */
function listgrades_privacy_conditions_enabled(): bool {
    return (bool) get_config('listgrades', 'privacyconditions');
}

/**
 * Render statistics for all selected grade items.
 *
 * @param array $selecteditems selected ids
 * @param array $gradeitems grade_item objects keyed by id
 * @param array $itemnames item names keyed by id
 * @param array $grades grades keyed by user and item ids
 * @param array $statuserids user ids included in the statistics
 * @param stdClass $user current user
 * @param int $courseid course id
 * @param bool $teacherpreview show teacher previews and privacy explanations
 * @return string
 */
function listgrades_render_statistics(
    array $selecteditems,
    array $gradeitems,
    array $itemnames,
    array $grades,
    array $statuserids,
    stdClass $user,
    int $courseid,
    bool $teacherpreview = false
): string {
    global $OUTPUT;

    $privacyconditions = listgrades_privacy_conditions_enabled();
    $config = get_config('listgrades');
    $showglobalpercentage = (bool) ($config->showglobalpercentage ?? false);
    $showboxplot = (bool) ($config->showboxplot ?? false);
    $showhistogram = (bool) ($config->showhistogram ?? false);
    $numericgradesasletters = (bool) ($config->numericgradesasletters ?? false);
    $charts = '';
    $privacyrestricted = false;
    foreach (listgrades_get_selected_grade_ids($selecteditems) as $itemid) {
        if (!isset($gradeitems[$itemid])) {
            continue;
        }
        $item = $gradeitems[$itemid];
        $values = [];
        foreach ($statuserids as $userid) {
            $value = $grades[$userid][$itemid]->finalgrade ?? null;
            if ($value !== null) {
                $values[] = (float) $value;
            }
        }
        $usergrade = $grades[$user->id][$itemid]->finalgrade ?? null;
        $usergrade = $usergrade === null ? null : (float) $usergrade;

        $illustrative = $teacherpreview && $usergrade === null && !empty($values);
        $itemcharts = '';
        $privacyreason = null;
        $gradepass = empty($item->gradepass) ? null : (float) $item->gradepass;
        if (!$values) {
            if (!$teacherpreview) {
                $privacyrestricted = true;
                continue;
            }
            $heading = html_writer::tag('h4', format_string($itemnames[$itemid]), ['class' => 'h5']);
            $emptygrades = $OUTPUT->notification(get_string('itemnogrades', 'listgrades'), 'info');
            $charts .= html_writer::div($heading . $emptygrades, 'listgrades-stat-item');
            continue;
        }
        if (!listgrades_privacy_allows_aggregates(count($values), $privacyconditions)) {
            $privacyreason = 'privacyaggregateshidden';
        }

        if ($item->gradetype == GRADE_TYPE_SCALE) {
            $scalecounts = [];
            foreach ($values as $value) {
                $scaleindex = (int) round($item->bounded_grade($value));
                $scalecounts[$scaleindex] = ($scalecounts[$scaleindex] ?? 0) + 1;
            }
            if (
                $privacyreason === null &&
                !listgrades_privacy_allows_distribution($scalecounts, $privacyconditions)
            ) {
                $privacyreason = 'privacydistributionhidden';
            }
            if ($privacyreason !== null) {
                $privacyrestricted = true;
                if (!$teacherpreview) {
                    continue;
                }
                $itemcharts .= $OUTPUT->notification(
                    get_string($privacyreason, 'listgrades') . ' ' .
                        get_string('privacyteachernotice', 'listgrades'),
                    \core\output\notification::NOTIFY_ERROR
                );
            }
            if ($illustrative) {
                $usergrade = (float) $values[array_rand($values)];
            }
            if ($showglobalpercentage) {
                $showglobalsummary = listgrades_privacy_allows_global_percentage(
                    $values,
                    $gradepass,
                    $privacyconditions
                );
                if ($showglobalsummary || $teacherpreview) {
                    if (!$showglobalsummary && $privacyreason === null) {
                        $itemcharts .= $OUTPUT->notification(
                            get_string('privacyglobalhidden', 'listgrades') . ' ' .
                                get_string('privacyteachernotice', 'listgrades'),
                            \core\output\notification::NOTIFY_ERROR
                        );
                    }
                    $itemcharts .= listgrades_render_global_percentage($values, $gradepass);
                }
            }
            $itemcharts .= listgrades_render_scale_chart(
                $item,
                $values,
                $usergrade,
                $user,
                $courseid,
                $illustrative
            );
        } else if ($item->gradetype == GRADE_TYPE_VALUE) {
            if (
                $privacyreason === null &&
                !listgrades_privacy_allows_numeric_statistics($values, $gradepass, $privacyconditions)
            ) {
                $privacyreason = 'privacyallfailedhidden';
            }
            $histogrambins = listgrades_build_histogram(
                $values,
                $gradepass,
                (float) $item->grademin,
                (float) $item->grademax
            );
            if (
                $privacyreason === null &&
                !listgrades_privacy_allows_distribution(
                    array_column($histogrambins, 'count'),
                    $privacyconditions
                )
            ) {
                $privacyreason = 'privacydistributionhidden';
            }
            if ($privacyreason === null && $numericgradesasletters) {
                $letterdistribution = listgrades_build_letter_distribution($item, $values);
                if (!listgrades_privacy_allows_distribution($letterdistribution['counts'], $privacyconditions)) {
                    $privacyreason = 'privacydistributionhidden';
                }
            }
            if ($privacyreason !== null) {
                $privacyrestricted = true;
                if (!$teacherpreview) {
                    continue;
                }
                $itemcharts .= $OUTPUT->notification(
                    get_string($privacyreason, 'listgrades') . ' ' .
                        get_string('privacyteachernotice', 'listgrades'),
                    \core\output\notification::NOTIFY_ERROR
                );
            }
            if ($illustrative) {
                $usergrade = listgrades_random_preview_grade($values, $item->get_decimals());
            }
            if ($showglobalpercentage) {
                $showglobalsummary = listgrades_privacy_allows_global_percentage(
                    $values,
                    $gradepass,
                    $privacyconditions
                );
                if ($showglobalsummary || $teacherpreview) {
                    if (!$showglobalsummary && $privacyreason === null) {
                        $itemcharts .= $OUTPUT->notification(
                            get_string('privacyglobalhidden', 'listgrades') . ' ' .
                                get_string('privacyteachernotice', 'listgrades'),
                            \core\output\notification::NOTIFY_ERROR
                        );
                    }
                    $itemcharts .= listgrades_render_global_percentage($values, $gradepass);
                }
            }
            if ($showboxplot) {
                $itemcharts .= listgrades_render_box_plot(
                    $item,
                    $values,
                    $usergrade,
                    $user,
                    $courseid,
                    $illustrative
                );
            }
            if ($showhistogram) {
                $itemcharts .= listgrades_render_histogram(
                    $item,
                    $values,
                    $usergrade,
                    $user,
                    $courseid,
                    $illustrative
                );
            }
            if ($numericgradesasletters) {
                $itemcharts .= listgrades_render_letter_chart(
                    $item,
                    $values,
                    $usergrade,
                    $user,
                    $courseid,
                    $illustrative
                );
            }
        }
        if ($itemcharts !== '') {
            $heading = html_writer::tag('h4', format_string($itemnames[$itemid]), ['class' => 'h5']);
            $charts .= html_writer::div($heading . $itemcharts, 'listgrades-stat-item');
        }
    }

    if ($charts === '') {
        if ($privacyrestricted && !$teacherpreview) {
            return '';
        }
        return $OUTPUT->notification(get_string('nostatistics', 'listgrades'), 'info');
    }
    return html_writer::tag('h3', get_string('statistics', 'listgrades')) . $charts;
}
/**
 * Masks a user field using the configured mask.
 *
 * @param string $userfield value to mask
 * @param string $mask mask definition
 * @return string
 */
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
        // DNI.
        // Remove first character if it is an E.
        if ($userfield[0] == 'E') {
            $userfield = substr($userfield, 1);
        }
        $maskeduserfield = '***' . substr($userfield, 3, 4) . '**';
    } else if (preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $userfield)) {
        // NIE.
        $maskeduserfield = '****' . substr($userfield, 4, 4) . '*';
    } else if (preg_match('/^[A-Z]{3}[0-9]{6}$/', $userfield)) {
        // Passport.
        $maskeduserfield = '*****' . substr($userfield, 5, 4);
    } else if (preg_match('/[0-9]{7,}/', $userfield, $matches, PREG_OFFSET_CAPTURE)) {
        // Otro tipo de identificación.
        $offset = $matches[0][1];
        $numbers = $matches[0][0];
        $length = strlen($numbers);
        $maskeduserfield = listgrades_asterisks($offset + 3)
                            . substr($numbers, 3, 4)
                            . listgrades_asterisks(strlen($userfield) - $offset - 3 - 4);
    } else {
        // Otro tipo de identificación.
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
    if ($n <= 0) {
        return '';
    } else {
        return str_repeat('*', $n);
    }
}



/**
 * Find item names matching by id. Negative ids are feedback items.
 * @param array $itemids Item ids. Negative ids are feedback items.
 * @param array|null $items Existing grade items, or null to load them.
 * @return array of grade_items with id -> name
 */
function listgrades_get_gradeitems_for_backup($itemids, $items = null) {
    global $DB;
    $itemsidsquery = array_map(fn($itemid) => $itemid < 0 ? -$itemid : $itemid, $itemids);
    if ($items == null) {
        // Query db for grade items.
        $items = $DB->get_records_list('grade_items', 'id', $itemsidsquery);
    }
    $gradeitems = [];
    foreach ($itemids as $itemid) {
        if ($itemid < 0) {
            $item = $items[-$itemid];
            $feedbackprefix = '__FEEDBACK__';
        } else {
            $item = $items[$itemid];
            $feedbackprefix = '';
        }
        if ($item->itemtype == 'course') {
            $gradeitems[$itemid] = $feedbackprefix . '__COURSE__';
        } else {
            $gradeitems[$itemid] = $feedbackprefix . $item->itemname;
        }
    }
    return $gradeitems;
}
// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
/**
 * File browsing support class.
 */
class listgrades_content_file_info extends file_info_stored {
    /**
     * Returns information about the parent file.
     *
     * @return file_info|null
     */
    public function get_parent() {
        if ($this->lf->get_filepath() === '/' && $this->lf->get_filename() === '.') {
            return $this->browser->get_file_info($this->context);
        }
        return parent::get_parent();
    }

    /**
     * Returns the visible file name.
     *
     * @return string
     */
    public function get_visible_name() {
        if ($this->lf->get_filepath() === '/' && $this->lf->get_filename() === '.') {
            return $this->topvisiblename;
        }
        return parent::get_visible_name();
    }
}
// phpcs:enable PSR1.Classes.ClassDeclaration.MultipleClasses

/**
 * Returns editor options used by the activity form.
 *
 * @param context $context module context
 * @return array
 */
function listgrades_get_editor_options($context) {
    global $CFG;
    return ['subdirs' => 1, 'maxbytes' => $CFG->maxbytes, 'maxfiles' => -1,
        'changeformat' => 1, 'context' => $context, 'noclean' => 1, 'trusttext' => 0];
}
/**
 * Update the calendar entries for this listgradesment.
 *
 * @param stdClass $instance Activity instance.
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
        $event->id = $DB->get_field('event', 'id', ['modulename' => 'listgrades',
            'instance' => $instance->id, 'eventtype' => $event->eventtype]);

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
