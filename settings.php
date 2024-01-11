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
 * Page module admin settings and defaults
 *
 * @package mod_listgrades
 * @copyright  2023 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once("$CFG->libdir/resourcelib.php");

    // Modedit defaults.
    // @var admin_settingpage $settings
    $settings->add(new admin_setting_heading('listgradesmodeditdefaults', get_string('modeditdefaults', 'admin'), get_string('condifmodeditdefaults', 'admin')));
    // Show the username in listing.
    $settings->add(new admin_setting_configcheckbox('listgrades/showusername',
                    get_string('showusername', 'listgrades'), get_string('showusername_help', 'listgrades'), 1));
    // Checkbox to show the userfield.
    // 'always', 'onlyifnamecollide', 'never'.
    $options = [
        'always' => get_string('useridalways', 'listgrades'),
        'onlyifnamecollide' => get_string('useridonlyifnamecollide', 'listgrades'),
        'never' => get_string('useridnever', 'listgrades'),
    ];
    $settings->add(new admin_setting_configselect('listgrades/showuserfield',
                    get_string('showuserfield', 'listgrades'), get_string('showuserfield_help', 'listgrades'), 'onlyifnamecollide', $options));
    // User field section.
    // Get userfields.
    $fields = get_user_fieldnames();
    require_once($CFG->dirroot . '/user/profile/lib.php');
    $customfields = profile_get_custom_fields();
    $userfields = [];
    // Make the keys string values and not indexes.
    foreach ($fields as $field) {
        $userfields[$field] = $field;
    }
    foreach ($customfields as $field) {
        $userfields["profile_field_{$field->shortname}"] = $field->name;
    }


    $settings->add(new admin_setting_configselect('listgrades/userfield',
        get_string('userfield', 'listgrades'), get_string('userfield_help', 'listgrades'), 'userid', $userfields));
    // Checkbox to use  AEPD method.
    $settings->add(new admin_setting_configcheckbox('listgrades/aepdmethod',
        get_string('aepdmethod', 'listgrades'), get_string('aepdmethod_help', 'listgrades'), 1));

    // Define mask string to hide digits of the userfield.
    $mask = '-XXX****XX-';
    $settings->add(new admin_setting_configtext('listgrades/userfieldmask',
        get_string('userfieldmask', 'listgrades'), get_string('userfieldmask_help', 'listgrades'), $mask, PARAM_TEXT));
    // Hide custom mask if aepdmethod is selected.
    $settings->hide_if('listgrades/userfieldmask', 'listgrades/aepdmethod', 'eq', 1);

    // Get site logos.
    $a = new stdClass();
    $logo = $OUTPUT->get_logo_url();
    $a->logourl = $logo == false ? '' : $logo->out();
    $logo = $OUTPUT->get_compact_logo_url();
    $a->logocompacturl = $logo->out();

    // Default texts.
    // TODO: Intro does not accept default value! Disabled until a workaround is found.
    $settings->add(new admin_setting_confightmleditor('listgrades/defaultintro',
       get_string('defaultintro', 'listgrades'), get_string('defaultintro_help', 'listgrades', $a),
       get_string('defaultintrotext', 'listgrades', $a), PARAM_RAW));
    $settings->add(new admin_setting_confightmleditor('listgrades/defaultfooter',
        get_string('defaultfooter', 'listgrades'), get_string('defaultfooter_help', 'listgrades'),
        get_string('defaultfootertext', 'listgrades', $a), PARAM_RAW));
}
