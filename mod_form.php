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

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/listgrades/locallib.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once($CFG->libdir.'/filelib.php');

class mod_listgrades_mod_form extends moodleform_mod {
    public function definition() {
        global $CFG, $DB;

        $mform = $this->_form;

        $config = get_config('listgrades');

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
        $mform->setDefault('introeditor', ['text' => $config->defaultintro]); // Doesn't work!

        $mform->addElement('header', 'signaturesection', get_string('footerheader', 'listgrades'));
        $mform->addElement('editor', 'signature', get_string('footer', 'listgrades'), null,
                            listgrades_get_editor_options($this->context));
        $mform->setDefault('signature', ['text' => $config->defaultfooter]); // Need an array, really Moodle?
        $mform->addRule('signature', get_string('required'), 'required', null, 'client');

        // Get Grade items from the course and create a multi-select box.
        // Use grader report to get the grades of the students.
        $grader = new grade_report_listing($this->current->course, "0", $this->context);
        $gradeitems = $grader->get_gradeitems();

        $gradeitems = array_map(function($item) {
            return $item->get_name();
        }, $gradeitems);
        $mform->addElement('select', 'items', get_string('gradeitems', 'listgrades'), $gradeitems,
                            ['multiple' => 'multiple', 'size' => 10]);
        $mform->addRule('items', get_string('required'), 'required', null, 'client');
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
        if ($this->current->instance) {
            $draftitemid = file_get_submitted_draft_itemid('signature');
            $defaultvalues['signature']['format'] = $defaultvalues['footerformat'];

            $defaultvalues['signature']['text']   = file_prepare_draft_area($draftitemid, $this->context->id, 'mod_listgrades',
                    'footer', 0, listgrades_get_editor_options($this->context), $defaultvalues['footer']);
            $defaultvalues['signature']['itemid'] = $draftitemid;
        }
        if (!empty($defaultvalues['items'])) {
            $gradeitems = (array) unserialize($defaultvalues['items']);
            $defaultvalues['items'] = array_keys($gradeitems);
        }
    }
}

