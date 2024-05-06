# Moodle Privacy-Friendly Grade Publishing Plugin

## Overview

The **Moodle Privacy-Friendly Grade Publishing Plugin** is a powerful tool designed to enhance transparency in educational institutions while preserving student privacy. This plugin empowers teachers to publish grades for specific assignments, fostering a collaborative and supportive learning environment. It allows instructors to selectively share grades while masking personal IDs, ensuring compliance with privacy regulations.

## Features

### 1. Selective Grade Publishing

- **Granular Control:** Teachers can choose which grades to publish, offering fine-grained control over the information shared with students.
  
- **Individual or Group Publishing:** Decide whether to give access to the grades to all students or by groups, providing flexibility based on course dynamics.

### 2. Privacy Masking

- **Automatic ID Masking:** The plugin automatically masks personal IDs, hidding some digits to safeguard student privacy.
  
- **Configurable Masking Options:** Customize the masking process based on institutional policies, ensuring compliance with privacy regulations.

### 3. Transparency and Collaboration

- **Viewing Options:** Students can view the grades of their peers, promoting transparency and collaboration within the learning community.
  
- **User-Friendly Interface:** A simple and intuitive interface allows both teachers and students to easily navigate and access the published grades.

## Installation

1. Download the plugin ZIP file from the [Moodle Plugins Directory](https://moodle.org/plugins/) or GitHub repository.
2. Upload the ZIP file to the Moodle plugins directory.
3. Install the plugin via the Moodle administrator interface.
4. Configure the plugin settings to align with your institution's privacy and transparency requirements.

## Usage

1. Create an instance of the activity
2. Select the grade items that you will to publish.
3. Select the time window of the publication.
4. Set the header and footer texts for decorating the listing.
4. Select group mode.
3. The grades will be published according to the privacy policy of your institution (contact your admin for details).

## Configuration

- Access the plugin settings in the Moodle administration panel.

- Customize privacy masking options, such as ID replacement patterns and masking algorithms.

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