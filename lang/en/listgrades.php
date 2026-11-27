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
$string['alloweddisplaymodes'] = 'Allowed grade list formats';
$string['alloweddisplaymodes_help'] = 'Choose whether activities may use Listing, Individual, or both page formats.';
$string['boxplotsummary'] = 'Minimum: {$a->min}; first quartile: {$a->q1}; median: {$a->median}; third quartile: {$a->q3}; maximum: {$a->max}.';
$string['closedate'] = 'Unpublication of grades';
$string['closedate_help'] = 'The date and time when the listing will be closed.';
$string['collapsegradecategory'] = 'Collapse grade category {$a}';

$string['configdisplayoptions'] = 'Select all options that should be available, existing settings are not modified. Hold CTRL key to select multiple fields.';
$string['createlisting'] = 'Create listing';
$string['defaultdisplaymode'] = 'Default grade list format';
$string['defaultdisplaymode_help'] = 'Page format selected by default when both formats are allowed.';
$string['defaultfooter'] = 'Default text footer';
$string['defaultfooter_help'] = 'Default text footer that will be displayed in the listing. Available placeholders: <code>{logourl}</code>, <code>{logocompacturl}</code>, <code>{coursefullname}</code>, <code>{courseshortname}</code>, <code>{courseidnumber}</code>, <code>{courseurl}</code>, <code>{courseimage}</code> and <code>{coursesummary}</code>.';
$string['defaultfootertext'] = '<p><img class="img-fluid align-bottom" style="margin: 10px;" role="presentation" src="{logocompacturl}" alt="" width="64" height="64" align="left">This publication is made for informational purposes in exercise of missions of public interest provided for in the Organic Law of the University System (LOSU). Its use by the student for other purposes, and in particular its alteration, manipulation or improper distribution in social networks or other public media may generate legal liability.</p>';
$string['defaultintro'] = 'Default text introduction';
$string['defaultintro_help'] = 'Default text introduction that will be displayed in the listing. Available placeholders: <code>{logourl}</code>, <code>{logocompacturl}</code>, <code>{coursefullname}</code>, <code>{courseshortname}</code>, <code>{courseidnumber}</code>, <code>{courseurl}</code>, <code>{courseimage}</code> and <code>{coursesummary}</code>.';
$string['defaultintrotext'] = '<table border="0" width="100%"><tbody><tr><td><h3><img role="presentation" src="{logourl}" alt="" width="121" height="78"></h3></td>
<td><h3>GRADE LISTING</h3><p>Call: 1st</p>
</td></tr></tbody></table>';
$string['displaymode'] = 'Page format';
$string['displaymode_help'] = 'Choose whether to show the grade listing or only the current user’s grades.';
$string['displaymodeboth'] = 'Both';
$string['displaymodeindividual'] = 'Individual';
$string['displaymodelist'] = 'Listing';
$string['dynamicicon'] = 'Dynamic activity icon';
$string['dynamicicon_help'] = 'When enabled, the activity icon indicates whether the current user has a grade or feedback to view.';
$string['eventreviewaction'] = 'Grade review action recorded';
$string['expandgradecategory'] = 'Expand grade category {$a}';
$string['feedback'] = 'Feedback';
$string['footer'] = 'Footer section';
$string['footerheader'] = 'Footer';
$string['globalgradesnopass'] = 'Students assessed: {$a}. Passing figures are unavailable because no passing grade is configured.';
$string['globalgradessummary'] = 'Students assessed: {$a->presented}. Students passing: {$a->passed} ({$a->percentage}%).';
$string['grade'] = 'Grade';
$string['gradeitem'] = 'Grade item';
$string['gradeitems'] = 'Grade items to publish';
$string['gradeitems_help'] = 'For each grade item, select whether to publish its grade, its feedback, or both. The controls at the start of the section select or deselect an entire column.';
$string['histogrambin'] = '{$a->range}: {$a->count} grades ({$a->percentage}%)';
$string['illustrativegrade'] = 'Illustrative grade';
$string['individualgradedate'] = 'Date: {$a}';
$string['individualgradeintro'] = 'The grade for {$a} is:';
$string['individualgradesintro'] = 'The grades for {$a} are:';
$string['itemnogrades'] = 'There are no grades.';
$string['listgrades:addinstance'] = 'Add a new grade listing';
$string['listgrades:managereviews'] = 'View grade review requests';
$string['listgrades:view'] = 'View listings';
$string['listingssettings'] = 'Listings';
$string['listingssettings_help'] = 'Configure the information displayed in grade listings.';
$string['modulename'] = 'Grades publication';
$string['modulename_help'] = 'The "list grades" module enables a teacher to publish the grades of all the students on a page in the course to fullfill transparency requirements. It protects the privacy of the students by masking the user field selected by the teacher.';
$string['modulename_link'] = 'mod/listgrades/view';
$string['modulenameplural'] = 'List Grades';
$string['nogradeitemsselected'] = 'No grade items selected';
$string['nostatistics'] = 'There are no grades available to calculate statistics.';
$string['notopen'] = 'This listing is not open.';
$string['numericgradesasletters'] = 'Show grade statistics as text';
$string['numericgradesasletters_help'] = 'Add horizontal percentage bars using the course grade letter equivalences. Numeric histograms and boxplots remain independently available.';
$string['numericgradewithmaximum'] = '{$a->grade}/{$a->maximum}';
$string['opendate'] = 'Publication of grades';
$string['opendate_help'] = 'The date and time when the listing will be published.';
$string['page-mod-listgrades-x'] = 'Any listgrades module page';
$string['pluginadministration'] = 'List grades module administration';
$string['pluginname'] = 'Listgrades';
$string['printintro'] = 'Display header text description';
$string['privacy:metadata'] = 'Listgrades stores grade review requests, student and teacher comments, the author and timestamp of each action, and a snapshot of the published grade table.';
$string['privacy:metadata:listgrades_review'] = 'Records of student and teacher actions in a grade review process.';
$string['privacy:metadata:listgrades_review:action'] = 'The review action recorded: request, cancellation, confirmation, comment, or reopening.';
$string['privacy:metadata:listgrades_review:actorid'] = 'The user who performed this review action.';
$string['privacy:metadata:listgrades_review:comment'] = 'A review message, including the statements selected by the student.';
$string['privacy:metadata:listgrades_review:listgradesid'] = 'The grade publication activity associated with the review.';
$string['privacy:metadata:listgrades_review:snapshot'] = 'The published grade table captured when the student confirmed or commented on the review.';
$string['privacy:metadata:listgrades_review:timecreated'] = 'When the review action was recorded.';
$string['privacy:metadata:listgrades_review:userid'] = 'The student whose review process this action belongs to.';
$string['privacyaggregateshidden'] = 'Aggregated results are hidden because five or fewer students have grades for this item.';
$string['privacyallfailedhidden'] = 'Statistics are hidden because all grades are below the passing grade.';
$string['privacyconditions'] = 'Privacy conditions';
$string['privacyconditions_help'] = 'As established in Spanish Royal Decree 855/2026 of 21 October, approving the Statute of University Students in Spain, and summarised as follows: “Aggregated results will not be published when the number of students assessed is five or fewer. The number and percentage of students who passed will not be published when all assessed students passed or failed, nor will the distribution by bands be published when all students are in the same band.”';
$string['privacydistributionhidden'] = 'The distribution is hidden because all students are in the same grade band.';
$string['privacyglobalhidden'] = 'Pass totals and percentages are hidden because all assessed students passed or failed.';
$string['privacyteachernotice'] = '(Students will not see this block at all.)';
$string['resetreviewprocess'] = 'Delete grade review process history';
$string['reviewaddcomment'] = 'Add comment';
$string['reviewbreadcrumb'] = 'Review';
$string['reviewcancel'] = 'Cancel review request';
$string['reviewcancelled'] = 'Your review request was cancelled. The action remains recorded.';
$string['reviewcommenthistory'] = 'Comment history';
$string['reviewcommentrequired'] = 'Enter a comment about the review process.';
$string['reviewconfirm'] = 'Confirm that the review took place';
$string['reviewcopyavailable'] = 'I have online access to or a copy of my assessment work.';
$string['reviewcopyrequested'] = 'I have requested a copy of my assessment work.';
$string['reviewcurrentgrades'] = 'Current grades (no response snapshot has been saved)';
$string['reviewdate'] = 'Review date';
$string['reviewdate_help'] = 'The scheduled date and time of the review. Students may submit their confirmation or comment from this time onward.';
$string['reviewdaterequired'] = 'Set a review date when the review process is enabled.';
$string['reviewenabled'] = 'Enable the review process for this activity';
$string['reviewenabled_help'] = 'Students can request or cancel a review before the scheduled date. After that date, they can record whether the review took place or leave a comment.';
$string['reviewinvalidaction'] = 'That review action is no longer available.';
$string['reviewlist'] = 'Review request history';
$string['reviewmanage'] = 'Manage reviews';
$string['reviewnone'] = 'There are no review requests.';
$string['reviewnotrequested'] = 'You have not requested a review.';
$string['reviewobservations'] = 'I have observations about the review process.';
$string['reviewoutcomecancelled'] = 'Request cancelled';
$string['reviewoutcomecommented'] = 'Comment submitted';
$string['reviewoutcomevalidated'] = 'Confirmed';
$string['reviewprocess'] = 'Enable the grade review process';
$string['reviewprocess_help'] = 'Allow teachers to enable a review process for individual grade publication activities.';
$string['reviewpublishedgrade'] = 'Published grade:';
$string['reviewrecordcompletion'] = 'Record completion of the review';
$string['reviewreopen'] = 'Allow another student response';
$string['reviewreopened'] = 'A new review opportunity was opened.';
$string['reviewreopenedmessage'] = 'A new review opportunity has been opened for the student.';
$string['reviewrequest'] = 'Request a review';
$string['reviewrequestcancelled'] = 'Review request cancelled';
$string['reviewrequestcount'] = '{$a} review requests';
$string['reviewrequestcountone'] = '1 review request';
$string['reviewrequested'] = 'Review requested';
$string['reviewrequestedmessage'] = 'Your review request has been recorded. ({$a})';
$string['reviewresponseoptions'] = 'Review response options';
$string['reviewresponseoptions_help'] = 'Enter one option per line. Filter tags such as {mlang es}...{mlang} are supported when the corresponding filter is enabled. Selected text is retained in each review record.';
$string['reviewresult'] = 'Record the review outcome';
$string['reviewresult_help'] = 'Select the statements that apply and optionally add comments about the review.';
$string['reviewsection'] = 'Grade review process';
$string['reviewseen'] = 'I have seen my assessment work and received explanations.';
$string['reviewselectionrequired'] = 'Select at least one option.';
$string['reviewstatus'] = 'Review status';
$string['reviewstudentcomment'] = 'Student comment';
$string['reviewsubmitted'] = 'Your review outcome has been recorded.';
$string['reviewteachercomment'] = 'Teacher comment';
$string['reviewviewsnapshot'] = 'Published grades when the student responded';
$string['reviewwaiting'] = 'The review date has not yet arrived.';
$string['scalegradewithreference'] = '{$a->grade} ({$a->scale})';
$string['search:activity'] = 'listgrades';
$string['selectatleastonegradeitem'] = 'Select at least one grade or feedback item.';
$string['selectedgradeitems'] = 'Selected items';
$string['showboxplot'] = 'Show boxplot';
$string['showboxplot_help'] = 'Display a boxplot with the minimum, quartiles, median and maximum for numeric grade items.';
$string['showglobalpercentage'] = 'Show overall percentage';
$string['showglobalpercentage_help'] = 'Display the number of students assessed and the number and percentage who achieved the passing grade.';
$string['showhistogram'] = 'Show numeric histogram';
$string['showhistogram_help'] = 'Display or hide the histogram for numeric grades.';
$string['showstatistics'] = 'Show grade statistics';
$string['showstatistics_help'] = 'Display distribution charts for each grade item in both Listing and Individual modes.';
$string['showuserfield'] = 'Show user ids';
$string['showuserfield_help'] = 'Select if the userid will be displayed in the list of grades.';
$string['showusername'] = 'Show usernames';
$string['showusername_help'] = 'Select if the username will be displayed in the list of grades.';
$string['statistics'] = 'Grade statistics';
$string['statisticssettings'] = 'Statistics';
$string['statisticssettings_help'] = 'Configure the statistical information displayed by grade publications.';
$string['toggleallfeedback'] = 'Select/deselect all feedback';
$string['toggleallgrades'] = 'Select/deselect all grades';


$string['userfield'] = 'User field';
$string['userfield_help'] = 'Select the user field that will be used to display the list of users.';
$string['userfieldmask'] = 'User field mask';
$string['userfieldmask_help'] = 'Define the mask that will be used to hide the user field. An * will substitute the character, a - will omit the character and a + will show the character.';
$string['useridalways'] = 'Always';
$string['useridnever'] = 'Never';
$string['useridonlyifnamecollide'] = 'Only if name collide';
