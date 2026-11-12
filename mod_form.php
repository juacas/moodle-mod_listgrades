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
    /**
     * Defines the activity form.
     */
    public function definition() {
        global $CFG, $DB;

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

        // Get Grade items from the course and create a multi-select box.
        // Use grader report as base to get the grades of the students.
        $grader = new grade_report_listing($this->current->course, "0", $this->context);
        $items = $grader->get_item_names();
        // Add feedback items.
        foreach ($items as $itemid => $itemname) {
            $items[-$itemid] = get_string('feedbackforgradeitems', 'grades', $itemname);
        }
        $mform->addElement(
            'select',
            'items',
            get_string('gradeitems', 'listgrades'),
            $items,
            ['multiple' => 'multiple', 'size' => 10]
        );
        $mform->addRule('items', get_string('required'), 'required', null, 'client');

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
            $gradeitems = (array) unserialize($defaultvalues['items']);
            $defaultvalues['items'] = array_keys($gradeitems);
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
}
