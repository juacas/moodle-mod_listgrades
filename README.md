# Moodle Privacy-Friendly Grade Publishing Plugin

## Overview

The **Moodle Privacy-Friendly Grade Publishing Plugin** allows teachers to publish selected grade items either as a privacy-aware class listing or as a personalised report for the current user. Institutions can control which publication formats are available, while each activity can optionally include visual statistics about the published grades.

## Features

### 1. Selective Grade Publishing

- **Granular Control:** Teachers can choose which grades to publish, offering fine-grained control over the information shared with students.

- **Feedback-Only Rows:** Selecting feedback without its corresponding grade displays only the feedback in the individual report and does not include that grade in the statistics.

- **Group Support:** Grade distributions and listings respect the activity group selection.

### 2. Publication Modes

- **Listing:** Displays the traditional table containing the selected grade items for the course or group. User names and identifiers can be hidden, conditionally displayed, or masked according to the site privacy settings.

- **Individual:** Displays only the current user's grades in a centred table. Each row contains the grade item name, grade and, when selected, feedback. A personalised sentence showing the user's full name appears before the table and the publication date appears below it.

- **Site Policy:** Administrators can allow Listing only, Individual only, or both formats. When both are enabled, administrators can select the default format for new activities. Listing-specific privacy settings are hidden when only Individual publishing is allowed.

### 3. Optional Grade Statistics

Statistics can be enabled independently of the publication mode and therefore displayed in both Listing and Individual views.

- **Overall Results:** Administrators can optionally show the number of assessed students and the number and percentage who achieved the passing grade.

- **Numeric Grades:** Each item can independently include a histogram and a boxplot, grouped in a shaded panel. The boxplot represents the observed distribution. The histogram uses the configured grade-item range, contains no more than 10 bins, favours round interval widths and attempts to place a bin boundary close to the configured passing grade.

- **Text Grade Statistics:** Administrators can add horizontal percentage bars based on Moodle's course grade-letter equivalences. This textual distribution can be displayed together with the numeric histogram and boxplot.

- **Text Scales:** Scale items use a horizontal stacked distribution with totals and percentages for each scale value.

- **Current User Marker:** The user's position is highlighted in the boxplot, histogram and scale distribution with a circular marker containing their profile picture. When a teacher without a grade previews the activity, an illustrative random marker is generated without storing or changing any grade.

- **Privacy Conditions:** Administrators can enforce the safeguards summarised in Spanish Royal Decree 855/2026. When enabled, aggregated statistics are hidden for grade items with five or fewer assessed students and whenever every grade is below the configured passing grade. Pass totals and percentages are not exposed for unanimous results, and distributions are hidden when every assessed student belongs to the same grade band.

- **Silent Student View:** Privacy-restricted statistical blocks are omitted without explanation for students. Teachers always retain the charts and see a prominent red warning explaining why the complete block is hidden from students.
- **Empty Grade Items:** Items without grades are identified for teachers, but their statistical blocks are completely omitted for students.

### 4. Privacy Masking

- **Automatic ID Masking:** The plugin automatically masks personal IDs, hiding some digits to safeguard student privacy.

- **Configurable Masking Options:** Customize the masking process based on institutional policies, ensuring compliance with privacy regulations.

### 5. Transparency and Collaboration

- **Viewing Options:** Listing mode supports transparent publication of peer grades, while Individual mode keeps each student's report private.

- **User-Friendly Interface:** A simple and intuitive interface allows both teachers and students to easily navigate and access the published grades.

### 6. Grade Reviews

Administrators can enable the review process and configure its response options, one option per line. The defaults contain Spanish and English text using the Multilanguage Content (v2) filter syntax, for example `{mlang es}Texto{mlang}{mlang other}Text{mlang}`. Enable the corresponding Moodle filter to display the appropriate language. Each review record retains the original selected option text and filter tags even if the administrator later changes the options.

## Installation

1. Download the plugin ZIP file from the [Moodle Plugins Directory](https://moodle.org/plugins/) or GitHub repository.
2. Upload the ZIP file to the Moodle plugins directory.
3. Install the plugin via the Moodle administrator interface.
4. Configure the plugin settings to align with your institution's privacy and transparency requirements.

## Usage

1. Create an instance of the activity.
2. Select the grade items and optional feedback fields to publish.
3. Choose Listing or Individual format, subject to the formats enabled by the administrator.
4. Enable or disable grade statistics.
5. Select the publication time window.
6. Set the header, footer and group mode.
7. The grades are published according to the privacy policy configured by the institution.

## Configuration

- Access the plugin settings in the Moodle administration panel.

- Choose whether Listing, Individual, or both publication formats are allowed.

- When both formats are allowed, choose the default format for new activities.

- Customize privacy masking options, such as ID replacement patterns and masking algorithms.

- Enable or disable the privacy conditions applied to aggregated grade statistics.

- Choose independently whether to show overall pass figures, boxplots, numeric histograms and textual grade distributions.

- Define default settings for new instances.

## Compatibility

This plugin is compatible with Moodle version 4.0 and above.

## Support and Feedback

For any issues, questions, or feedback, please [open an issue on GitHub](https://github.com/juacas/moodle-mod_listgrades).

## License

This Moodle plugin is released under the [GNU General Public License](https://www.gnu.org/licenses/gpl-3.0.en.html).

See also
=========

 - [Moodle plugins entry page](http://moodle.org/plugins/view.php?plugin=mod_listgrades)
 - [Moodle.org forum discussion thread](Future)
 - [Tutorial and manuals in English and Spanish](https://juacas.github.io/moodle-mod_listgrades/)

Change log
==========
- v1.0.0 Initial release.
- v1.0.3 AEPD ofuscation method.
- v1.0.4 Show collapsed categories.
- v1.0.5 Show grade items feedback messages.
- v1.0.6 Custom fields suport fixed.
- v1.0.7 Fix error in settings when compact logo is not defined.
- v1.0.8 Fix bug #8. Error when deleting course.
- v1.0.9 Fix bug #9. Error when restoring a course with listgrades activities.
- v1.0.10 Fix bug #10. Error when restoring a course with listgrades activities in Moodle 4.5.
- v1.1.0 Add Individual publication, optional grade statistics, student selection, user markers, publication format controls and customisable course templates.
