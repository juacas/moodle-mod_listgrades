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
 * Page configuration form
 *
 * @package     mod_listgrades
 * @copyright   2023 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once($CFG->dirroot . '/mod/listgrades/locallib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->libdir . '/filelib.php');

/**
 * Activity configuration form.
 */
class mod_listgrades_mod_form extends moodleform_mod {
    /** @var int[] Grade item ids in gradebook order. */
    private array $gradeitemids = [];

    /**
     * Whether a grade tree node contains at least one selectable item.
     *
     * @param array $node grade tree node
     * @param array $alloweditems selectable item ids
     * @return bool
     */
    private function grade_tree_node_has_items(array $node, array $alloweditems): bool {
        if (($node['type'] ?? '') !== 'category') {
            return isset($node['object']->id) && isset($alloweditems[(int) $node['object']->id]);
        }
        foreach ($node['children'] ?? [] as $child) {
            if ($this->grade_tree_node_has_items($child, $alloweditems)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add a grade category and its descendants to the item selector.
     *
     * @param MoodleQuickForm $mform Moodle form
     * @param array $node category node from grade_tree
     * @param array $alloweditems selectable item ids
     * @param int[] $ancestors parent category ids
     * @param int $depth tree depth
     * @return void
     */
    private function add_grade_tree_category(
        MoodleQuickForm $mform,
        array $node,
        array $alloweditems,
        array $ancestors = [],
        int $depth = 0
    ): void {
        if (!$this->grade_tree_node_has_items($node, $alloweditems)) {
            return;
        }

        $category = $node['object'];
        $categoryid = (int) $category->id;
        $categoryname = format_string($category->get_name(), true, ['context' => $this->context]);
        $categorypath = implode(',', $ancestors);
        $totalitem = null;
        foreach ($node['children'] ?? [] as $child) {
            if (($child['type'] ?? '') === 'category' || !isset($child['object']->id)) {
                continue;
            }
            $candidate = $child['object'];
            if (isset($alloweditems[(int) $candidate->id]) &&
                    ($candidate->itemtype === 'category' || $candidate->itemtype === 'course')) {
                $totalitem = $candidate;
                break;
            }
        }

        $categorylabel = $categoryname;
        if ($totalitem) {
            $categorylabel .= ' ' . html_writer::span('(' . get_string('total') . ')', 'listgrades-grade-tree-total');
        }
        $toggle = html_writer::span('', 'listgrades-grade-tree-chevron', ['aria-hidden' => 'true']);
        $toggle .= html_writer::span($categorylabel, 'listgrades-grade-tree-category-name');
        $toggle = html_writer::tag('button', $toggle, [
            'type' => 'button',
            'class' => 'listgrades-grade-tree-toggle',
            'data-category-id' => $categoryid,
            'data-category-path' => $categorypath,
            'data-expand-label' => get_string('expandgradecategory', 'listgrades', strip_tags($categoryname)),
            'data-collapse-label' => get_string('collapsegradecategory', 'listgrades', strip_tags($categoryname)),
            'aria-expanded' => 'false',
            'aria-label' => get_string('expandgradecategory', 'listgrades', strip_tags($categoryname)),
            'style' => '--listgrades-tree-depth:' . $depth,
        ]);
        if ($totalitem) {
            $totalid = (int) $totalitem->id;
            $this->gradeitemids[] = $totalid;
            $itemname = strip_tags($categoryname) . ' (' . get_string('total') . ')';
            $metadata = html_writer::span('', 'listgrades-grade-tree-item-metadata', [
                'data-item-id' => $totalid,
                'data-item-name' => $itemname,
                'data-category-path' => $categorypath,
            ]);
            $checkboxes = [
                $mform->createElement(
                    'advcheckbox',
                    "gradeitems[$totalid]",
                    '',
                    html_writer::span(get_string('grade', 'listgrades'), 'visually-hidden'),
                    [],
                    [0, 1]
                ),
                $mform->createElement(
                    'advcheckbox',
                    "feedbackitems[$totalid]",
                    '',
                    html_writer::span(get_string('feedback', 'listgrades'), 'visually-hidden'),
                    [],
                    [0, 1]
                ),
            ];
            $mform->addGroup($checkboxes, "itemselection_$totalid", $toggle . $metadata, '', false);
            $mform->setType("gradeitems[$totalid]", PARAM_BOOL);
            $mform->setType("feedbackitems[$totalid]", PARAM_BOOL);
        } else {
            $mform->addElement('static', 'gradecategory_' . $categoryid, $toggle, '');
        }

        $itemancestors = [...$ancestors, $categoryid];
        foreach ($node['children'] ?? [] as $child) {
            if (($child['type'] ?? '') === 'category') {
                $this->add_grade_tree_category($mform, $child, $alloweditems, $itemancestors, $depth + 1);
                continue;
            }
            if (!isset($child['object']->id)) {
                continue;
            }
            $item = $child['object'];
            $itemid = (int) $item->id;
            if (!isset($alloweditems[$itemid]) || ($totalitem && $itemid === (int) $totalitem->id)) {
                continue;
            }
            $this->gradeitemids[] = $itemid;
            $itemname = format_string($item->get_name(), true, ['context' => $this->context]);
            $metadata = html_writer::span('', 'listgrades-grade-tree-item-metadata', [
                'data-item-id' => $itemid,
                'data-item-name' => strip_tags($itemname),
                'data-category-path' => implode(',', $itemancestors),
            ]);
            $itemlabel = html_writer::span($itemname, 'listgrades-grade-tree-item-name', [
                'style' => '--listgrades-tree-depth:' . ($depth + 1),
            ]) . $metadata;
            $checkboxes = [
                $mform->createElement(
                    'advcheckbox',
                    "gradeitems[$itemid]",
                    '',
                    html_writer::span(get_string('grade', 'listgrades'), 'visually-hidden'),
                    [],
                    [0, 1]
                ),
                $mform->createElement(
                    'advcheckbox',
                    "feedbackitems[$itemid]",
                    '',
                    html_writer::span(get_string('feedback', 'listgrades'), 'visually-hidden'),
                    [],
                    [0, 1]
                ),
            ];
            $mform->addGroup($checkboxes, "itemselection_$itemid", $itemlabel, '', false);
            $mform->setType("gradeitems[$itemid]", PARAM_BOOL);
            $mform->setType("feedbackitems[$itemid]", PARAM_BOOL);
        }
    }

    /**
     * Defines the activity form.
     */
    public function definition() {
        global $CFG, $DB, $PAGE;

        $mform = $this->_form;

        $config = get_config('listgrades');
        $course = $DB->get_record('course', ['id' => $this->current->course], '*', MUST_EXIST);

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => '48']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();
        $defaultintro = listgrades_expand_course_template($config->defaultintro ?? '', $course);
        $mform->setDefault('introeditor', ['text' => $defaultintro, 'format' => FORMAT_HTML]);

        $mform->addElement('header', 'signaturesection', get_string('footerheader', 'listgrades'));
        $mform->addElement(
            'editor',
            'signature',
            get_string('footer', 'listgrades'),
            null,
            listgrades_get_editor_options($this->context)
        );
        $defaultfooter = listgrades_expand_course_template($config->defaultfooter ?? '', $course);
        $mform->setDefault('signature', ['text' => $defaultfooter, 'format' => FORMAT_HTML]);
        $mform->addRule('signature', get_string('required'), 'required', null, 'client');

        // Get grade items from the course and create one grade/feedback checkbox pair per item.
        // Use grader report as base to get the grades of the students.
        $grader = new grade_report_listing($this->current->course, "0", $this->context);
        $items = $grader->get_item_names();
        $alloweditems = array_fill_keys(array_map('intval', array_keys($items)), true);

        $mform->addElement('header', 'gradeitemssection', get_string('gradeitems', 'listgrades'));
        $mform->addHelpButton('gradeitemssection', 'gradeitems', 'listgrades');
        $mform->setExpanded('gradeitemssection', true);
        $badgeheader = html_writer::tag('strong', get_string('selectedgradeitems', 'listgrades'));
        $badgeheader .= html_writer::div('', 'listgrades-grade-tree-badges', [
            'role' => 'status',
            'aria-live' => 'polite',
        ]);
        $columnheaders = '';
        foreach ([
            'gradeitems' => [get_string('grade', 'listgrades'), get_string('toggleallgrades', 'listgrades')],
            'feedbackitems' => [get_string('feedback', 'listgrades'), get_string('toggleallfeedback', 'listgrades')],
        ] as $column => [$label, $togglelabel]) {
            $heading = html_writer::span($label, 'listgrades-grade-tree-column-label');
            $heading .= html_writer::empty_tag('input', [
                'type' => 'checkbox',
                'class' => 'form-check-input listgrades-grade-tree-column-toggle',
                'data-column' => $column,
                'aria-label' => $togglelabel,
            ]);
            $columnheaders .= html_writer::tag(
                'label',
                $heading,
                ['class' => 'listgrades-grade-tree-column-heading']
            );
        }
        $mform->addElement(
            'static',
            'gradeitemcolumnheaders',
            html_writer::div($badgeheader, 'listgrades-grade-tree-badge-header'),
            html_writer::div($columnheaders, 'listgrades-grade-tree-column-headings')
        );
        $formgradetree = new grade_tree($this->current->course, false, false);
        $this->add_grade_tree_category($mform, $formgradetree->top_element, $alloweditems);
        $PAGE->requires->js_call_amd('mod_listgrades/grade_item_tree', 'init', [[
            'sectionId' => 'id_gradeitemssection',
            'gradeLabel' => get_string('grade', 'listgrades'),
            'feedbackLabel' => get_string('feedback', 'listgrades'),
            'noneSelectedLabel' => get_string('nogradeitemsselected', 'listgrades'),
        ]]);

        $allowedmodes = $config->alloweddisplaymodes ?? LISTGRADES_ALLOWED_BOTH;
        if ($allowedmodes === LISTGRADES_DISPLAY_LIST) {
            $displaymodes = [LISTGRADES_DISPLAY_LIST => get_string('displaymodelist', 'listgrades')];
        } else if ($allowedmodes === LISTGRADES_DISPLAY_INDIVIDUAL) {
            $displaymodes = [LISTGRADES_DISPLAY_INDIVIDUAL => get_string('displaymodeindividual', 'listgrades')];
        } else {
            $displaymodes = [
                LISTGRADES_DISPLAY_LIST => get_string('displaymodelist', 'listgrades'),
                LISTGRADES_DISPLAY_INDIVIDUAL => get_string('displaymodeindividual', 'listgrades'),
            ];
        }
        $mform->addElement('select', 'displaymode', get_string('displaymode', 'listgrades'), $displaymodes);
        $mform->addHelpButton('displaymode', 'displaymode', 'listgrades');
        $defaultmode = $allowedmodes === LISTGRADES_ALLOWED_BOTH
            ? ($config->defaultdisplaymode ?? LISTGRADES_DISPLAY_INDIVIDUAL)
            : $allowedmodes;
        $mform->setDefault('displaymode', listgrades_resolve_display_mode(null, $allowedmodes, $defaultmode));

        $mform->addElement('advcheckbox', 'showstatistics', get_string('showstatistics', 'listgrades'));
        $mform->addHelpButton('showstatistics', 'showstatistics', 'listgrades');
        $mform->setDefault('showstatistics', 1);

        // Date window for grade items.
        $mform->addElement('date_time_selector', 'opendate', get_string('opendate', 'listgrades'), ['optional' => true]);
        $mform->addHelpButton('opendate', 'opendate', 'listgrades');

        $mform->addElement('date_time_selector', 'closedate', get_string('closedate', 'listgrades'), ['optional' => true]);
        $mform->addHelpButton('closedate', 'closedate', 'listgrades');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();

        $mform->addElement('hidden', 'revision');
        $mform->setType('revision', PARAM_INT);
        $mform->setDefault('revision', 1);
    }

    /**
     * Enforce defaults here.
     *
     * @param array $defaultvalues Form defaults
     * @return void
     **/
    public function data_preprocessing(&$defaultvalues) {
        global $DB;

        if ($this->current->instance) {
            $draftitemid = file_get_submitted_draft_itemid('signature');
            $defaultvalues['signature']['format'] = $defaultvalues['footerformat'];

            $defaultvalues['signature']['text']   = file_prepare_draft_area(
                $draftitemid,
                $this->context->id,
                'mod_listgrades',
                'footer',
                0,
                listgrades_get_editor_options($this->context),
                $defaultvalues['footer']
            );
            $defaultvalues['signature']['itemid'] = $draftitemid;
        }
        if (!empty($defaultvalues['items'])) {
            $selecteditems = array_keys((array) unserialize($defaultvalues['items']));
            foreach ($selecteditems as $itemid) {
                $itemid = (int) $itemid;
                if ($itemid > 0) {
                    $defaultvalues['gradeitems'][$itemid] = 1;
                } else if ($itemid < 0) {
                    $defaultvalues['feedbackitems'][abs($itemid)] = 1;
                }
            }
        }
        // Populate both editors for a new activity, expanding the course placeholders
        // while leaving the globally configured templates untouched.
        if (!$this->current->instance) {
            $config = get_config('listgrades');
            $course = $DB->get_record('course', ['id' => $this->current->course], '*', MUST_EXIST);
            $defaultvalues['introeditor']['text'] = listgrades_expand_course_template(
                $config->defaultintro ?? '',
                $course
            );
            $defaultvalues['introeditor']['format'] = FORMAT_HTML;
            $defaultvalues['signature']['text'] = listgrades_expand_course_template(
                $config->defaultfooter ?? '',
                $course
            );
            $defaultvalues['signature']['format'] = FORMAT_HTML;
        }
    }

    /**
     * Convert the checkbox matrix to the existing ordered list of positive and negative ids.
     *
     * Positive ids select grades and negative ids select feedback. Keeping this representation
     * avoids a database or backup format change.
     *
     * @param stdClass $data submitted form data
     * @return void
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);

        $grades = (array) ($data->gradeitems ?? []);
        $feedback = (array) ($data->feedbackitems ?? []);
        $data->items = listgrades_build_item_selection($this->gradeitemids, $grades, $feedback);
        unset($data->gradeitems, $data->feedbackitems);
    }

    /**
     * Validate that at least one grade or feedback item is selected.
     *
     * @param array $data submitted form data
     * @param array $files submitted files
     * @return array validation errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $grades = array_filter((array) ($data['gradeitems'] ?? []));
        $feedback = array_filter((array) ($data['feedbackitems'] ?? []));
        if (!$grades && !$feedback && $this->gradeitemids) {
            $errors['itemselection_' . reset($this->gradeitemids)] = get_string(
                'selectatleastonegradeitem',
                'listgrades'
            );
        }
        return $errors;
    }
}
