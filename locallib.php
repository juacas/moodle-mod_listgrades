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

define('LISTGRADES_DISPLAY_LIST', 'list');
define('LISTGRADES_DISPLAY_INDIVIDUAL', 'individual');
define('LISTGRADES_ALLOWED_BOTH', 'both');

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
function listgrades_resolve_display_mode(?string $activitymode, ?string $allowedmodes,
        ?string $defaultmode = null): string {
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
 * Render the current user's selected grades.
 *
 * @param array $selecteditems selected ids, with negative ids representing feedback
 * @param array $gradeitems grade_item objects keyed by id
 * @param array $itemnames item names keyed by id
 * @param array|null $usergrades current user's grades keyed by item id
 * @param context $context module context
 * @param stdClass $user current user
 * @return string
 */
function listgrades_render_individual_table(array $selecteditems, array $gradeitems, array $itemnames,
        ?array $usergrades, context $context, stdClass $user): string {
    $showfeedback = [];
    foreach ($selecteditems as $itemid) {
        if ($itemid < 0) {
            $showfeedback[abs($itemid)] = true;
        }
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable listgrades-individual-table';
    $table->head = [get_string('gradeitem', 'listgrades'), get_string('grade', 'listgrades')];
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
        $row = [format_string($itemnames[$itemid]), grade_format_gradevalue(
            $finalgrade === null ? null : (float) $finalgrade, $item, true, GRADE_DISPLAY_TYPE_REAL
        )];

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
    $date = html_writer::tag('p', get_string('individualgradedate', 'listgrades', userdate(time(), '%d/%m/%Y')), [
        'class' => 'listgrades-individual-date',
    ]);

    return $intro . html_writer::table($table) . $date;
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
 * @return string
 */
function listgrades_render_user_marker(grade_item $item, float $usergrade, stdClass $user, int $courseid,
        float $position, bool $illustrative): string {
    global $OUTPUT;

    $picture = $OUTPUT->user_picture($user, [
        'courseid' => $courseid,
        'size' => 35,
        'link' => false,
        'alttext' => true,
    ]);
    $gradelabel = grade_format_gradevalue($usergrade, $item, true, GRADE_DISPLAY_TYPE_REAL);
    $markerlabel = $illustrative ? get_string('illustrativegrade', 'listgrades') : get_string('grade', 'listgrades');
    return html_writer::span($picture, 'listgrades-user-marker', [
        'style' => 'left:' . max(0, min(100, $position)) . '%',
        'title' => $markerlabel . ': ' . $gradelabel,
    ]);
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
function listgrades_render_box_plot(grade_item $item, array $values, ?float $usergrade, stdClass $user,
        int $courseid, bool $illustrative = false): string {
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
    // Use the observed range. Configured course totals can retain a very large historical
    // maximum which makes the actual distribution unreadable (for example, 0-2083 when
    // the highest published grade is 182).
    $rangemin = $stats->min;
    $rangemax = $stats->max;
    if ($usergrade !== null) {
        $rangemin = min($rangemin, $usergrade);
        $rangemax = max($rangemax, $usergrade);
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

    $decimals = $item->get_decimals();
    $summary = (object) [];
    foreach (['min', 'q1', 'median', 'q3', 'max'] as $stat) {
        $summary->$stat = format_float($stats->$stat, $decimals);
    }
    $axis = html_writer::span(format_float($rangemin, $decimals), 'listgrades-axis-min');
    $axis .= html_writer::span(format_float($rangemax, $decimals), 'listgrades-axis-max');

    $output = html_writer::div($plot . $axis, 'listgrades-boxplot', [
        'role' => 'img',
        'aria-label' => get_string('boxplotsummary', 'listgrades', $summary),
    ]);
    $output .= html_writer::div(get_string('boxplotsummary', 'listgrades', $summary),
        'small text-muted listgrades-stat-summary');
    return html_writer::div($output, 'listgrades-stat-chart');
}

/**
 * Build histogram bins for numeric grades.
 *
 * Small integer ranges get one bin per value. Other distributions use at most 10
 * equal-width bins. When a passing grade is configured inside the observed range,
 * the bin count is chosen so that a boundary falls as close to it as possible.
 *
 * @param array $values numeric values
 * @param float|null $gradepass passing grade
 * @param float|null $domainminimum configured domain minimum
 * @param float|null $domainmaximum configured domain maximum
 * @return array list of bins containing lower, upper, count and percentage
 */
function listgrades_build_histogram(array $values, ?float $gradepass = null, ?float $domainminimum = null,
        ?float $domainmaximum = null): array {
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
        $bincount = $suggestedbincount;
        if ($gradepass !== null && $gradepass > $minimum && $gradepass < $maximum) {
            $passposition = ($gradepass - $minimum) / ($maximum - $minimum);
            $bestdistance = INF;
            for ($candidate = 5; $candidate <= 10; $candidate++) {
                $nearestboundary = round($passposition * $candidate) / $candidate;
                $distance = abs($passposition - $nearestboundary);
                if ($distance < $bestdistance ||
                        ($distance === $bestdistance &&
                            abs($candidate - $suggestedbincount) < abs($bincount - $suggestedbincount))) {
                    $bestdistance = $distance;
                    $bincount = $candidate;
                }
            }
        }
        $binwidth = ($maximum - $minimum) / $bincount;
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
function listgrades_render_histogram(grade_item $item, array $values, ?float $usergrade, stdClass $user,
        int $courseid, bool $illustrative = false): string {
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
    $decimals = $item->get_decimals();
    $bars = '';
    $summary = [];
    foreach ($bins as $bin) {
        if ($bin['lower'] === $bin['upper']) {
            $label = format_float($bin['lower'], $decimals);
        } else {
            $label = format_float($bin['lower'], $decimals) . '–' . format_float($bin['upper'], $decimals);
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
function listgrades_render_scale_chart(grade_item $item, array $values, ?float $usergrade, stdClass $user,
        int $courseid, bool $illustrative = false): string {
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
    $output .= html_writer::tag('ul', $legend, ['class' => 'list-unstyled listgrades-scale-legend']);
    return html_writer::div($output, 'listgrades-stat-chart');
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
 * @param bool $previewmissinggrades show an illustrative marker for an ungraded teacher
 * @return string
 */
function listgrades_render_statistics(array $selecteditems, array $gradeitems, array $itemnames, array $grades,
        array $statuserids, stdClass $user, int $courseid, bool $previewmissinggrades = false): string {
    global $OUTPUT;

    $charts = '';
    foreach (listgrades_get_selected_gradeitem_ids($selecteditems) as $itemid) {
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

        $illustrative = $previewmissinggrades && $usergrade === null && !empty($values);
        $itemcharts = '';
        if ($item->gradetype == GRADE_TYPE_SCALE) {
            if ($illustrative) {
                $usergrade = (float) $values[array_rand($values)];
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
            if ($illustrative) {
                $usergrade = listgrades_random_preview_grade($values, $item->get_decimals());
            }
            $itemcharts .= listgrades_render_box_plot(
                $item,
                $values,
                $usergrade,
                $user,
                $courseid,
                $illustrative
            );
            $itemcharts .= listgrades_render_histogram(
                $item,
                $values,
                $usergrade,
                $user,
                $courseid,
                $illustrative
            );
        }
        if ($itemcharts !== '') {
            $heading = html_writer::tag('h4', format_string($itemnames[$itemid]), ['class' => 'h5']);
            $charts .= html_writer::div($heading . $itemcharts, 'listgrades-stat-item');
        }
    }

    if ($charts === '') {
        return $OUTPUT->notification(get_string('nostatistics', 'listgrades'), 'info');
    }
    return html_writer::tag('h3', get_string('statistics', 'listgrades')) . $charts;
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
    if ($n <= 0) {
        return '';
    } else {
        return str_repeat('*', $n);
    }
}



/**
 * Find item names matching by id. Negative ids are feedback items.
 * @param array $gradeitems ids. Negative ids are feedback items.
 * @return array of grade_items with id -> name
 */
function listgrades_get_gradeitems_for_backup($itemids, $items = null) {
    global $DB;
    $itemsidsquery = array_map( fn($itemid) => $itemid < 0 ? -$itemid : $itemid, $itemids);
    if ($items == null) {
        // Query db for grade items.
        $items = $DB->get_records_list('grade_items', 'id', $itemsidsquery);
    }
    $gradeitems = [];
    foreach ($itemids as $itemid) {
        if($itemid < 0) {
            $item = $items[ -$itemid];
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
