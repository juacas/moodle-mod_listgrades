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
 * Strings for component 'listgrades', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   mod_listgrades
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['aepdmethod'] = 'AEPD identifiers obfuscation';
$string['aepdmethod_help'] = 'Select the method of obfuscation of identifiers that will be used to comply with the regulations of the Spanish Data Protection Agency: <a href="https://www.aepd.es/documento/orientaciones-da7.pdf">https://www.aepd.es/documento/orientaciones-da7.pdf</a>';
$string['closedate'] = 'Unpublication of grades';
$string['closedate_help'] = 'The date and time when the listing will be closed.';

$string['configdisplayoptions'] = 'Select all options that should be available, existing settings are not modified. Hold CTRL key to select multiple fields.';
$string['defaultfooter'] = 'Default text footer';
$string['defaultfooter_help'] = 'Default text footer that will be displayed in the listing.';
$string['defaultfootertext'] = '<p><img class="img-fluid align-bottom" style="margin: 10px;" role="presentation" src="{$a->logocompacturl}" alt="" width="64" height="64" align="left">This publication is made for informational purposes in exercise of missions of public interest provided for in the Organic Law of the University System (LOSU). Its use by the student for other purposes, and in particular its alteration, manipulation or improper distribution in social networks or other public media may generate legal liability.</p>';
$string['defaultintrotext'] = '<table border="0" width="100%"><tbody><tr><td><h3><img role="presentation" src="{$a->logourl}" alt="" width="121" height="78"></h3></td>
<td><h3>GRADE LISTING</h3><p>Call: 1st</p>
</td></tr></tbody></table>';
$string['defaultintro'] = 'Default text introduction';
$string['defaultintro_help'] = 'Default text introduction that will be displayed in the listing.';
$string['footer'] = 'Footer section';
$string['footerheader'] = 'Footer';
$string['createlisting'] = 'Create listing';
$string['gradeitems'] = 'Grade items to publish';
$string['gradeitems_help'] = 'Select the grade items that will be published. Hold CTRL key to select multiple fields.';

$string['modulename'] = 'Grades publication';
$string['modulename_help'] = 'The "list grades" module enables a teacher to publish the grades of all the students on a page in the course to fullfill transparency requirements. It protects the privacy of the students by masking the user field selected by the teacher.';
$string['modulename_link'] = 'mod/listgrades/view';
$string['modulenameplural'] = 'List Grades';
$string['opendate'] = 'Publication of grades';
$string['opendate_help'] = 'The date and time when the listing will be published.';
$string['listgrades:addinstance'] = 'Add a new grade listing';
$string['listgrades:view'] = 'View listings';
$string['page-mod-listgrades-x'] = 'Any listgrades module page';
$string['pluginadministration'] = 'List grades module administration';
$string['pluginname'] = 'Listgrades';

$string['printintro'] = 'Display header text description';
$string['privacy:metadata'] = 'The Listgrades resource plugin does not store any personal data.';
$string['search:activity'] = 'listgrades';
$string['showusername'] = 'Show usernames';
$string['showusername_help'] = 'Select if the username will be displayed in the list of grades.';
$string['showuserfield'] = 'Show user ids';
$string['showuserfield_help'] = 'Select if the userid will be displayed in the list of grades.';
$string['userfield'] = 'User field';
$string['userfield_help'] = 'Select the user field that will be used to display the list of users.';
$string['userfieldmask'] = 'User field mask';
$string['userfieldmask_help'] = 'Define the mask that will be used to hide the user field. An * will substitute the character, a - will omit the character and a + will show the character.';
$string['notopen'] = 'This listing is not open.';
$string['useridalways'] = 'Always';
$string['useridonlyifnamecollide'] = 'Only if name collide';
$string['useridnever'] = 'Never';