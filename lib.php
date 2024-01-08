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
 * @package mod_listgrades
 * @copyright  2023 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * List of features supported in Page module
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 */
function listgrades_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE:           return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_MOD_PURPOSE:             return MOD_PURPOSE_CONTENT;

        default: return null;
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function listgrades_reset_userdata($data) {

    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.

    return [];
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function listgrades_get_view_actions() {
    return ['view', 'view all'];
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function listgrades_get_post_actions() {
    return ['update', 'add'];
}

/**
 * Add page instance.
 * @param stdClass $data
 * @param mod_listgrades_mod_form $mform
 * @return int new page instance id
 */
function listgrades_add_instance($data, $mform = null) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    $cmid = $data->coursemodule;

    $data->timemodified = time();

    $items = $data->items;
    $items = listgrades_get_gradeitems($items);
    $data->items = serialize($items);

    if ($mform) {
        $data->footerformat = $data->signature['format'];
        $data->footer       = $data->signature['text'];
    }

    $data->id = $DB->insert_record('listgrades', $data);

    // We need to use context now, so we need to make sure all needed info is already in db.
    $DB->set_field('course_modules', 'instance', $data->id, ['id' => $cmid]);
    $context = context_module::instance($cmid);

    if ($mform && !empty($data->signature['itemid'])) {
        $draftitemid = $data->signature['itemid'];
        $data->footer = file_save_draft_area_files($draftitemid, $context->id, 'mod_listgrades',
                                'footer', 0, listgrades_get_editor_options($context), $data->footer);
        $DB->update_record('listgrades', $data);
    }

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'listgrades', $data->id, $completiontimeexpected);
    listgrades_update_calendar($data);
    return $data->id;
}

/**
 * Update listgrade instance.
 * @param object $data
 * @param object $mform
 * @return bool true
 */
function listgrades_update_instance($data, $mform) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    $cmid        = $data->coursemodule;
    $draftitemid = $data->signature['itemid'];

    $data->timemodified = time();
    $data->id           = $data->instance;
    $data->revision++;

    $data->items = listgrades_get_gradeitems($data->items);
    $data->items = serialize($data->items);

    $data->footerformat = $data->signature['format'];
    $data->footer       = $data->signature['text'];

    $DB->update_record('listgrades', $data);

    $context = context_module::instance($cmid);
    if ($draftitemid) {
        $data->footer = file_save_draft_area_files($draftitemid, $context->id, 'mod_listgrades',
                            'footer', 0, listgrades_get_editor_options($context), $data->footer);
        $DB->update_record('listgrades', $data);
    }

    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'listgrades', $data->id, $completiontimeexpected);
    listgrades_update_calendar($data);
    return true;
}

/**
 * Delete page instance.
 * @param int $id
 * @return bool true
 */
function listgrades_delete_instance($id) {
    global $DB;

    if (!$listgrades = $DB->get_record('listgrades', ['id' => $id])) {
        return false;
    }

    $cm = get_coursemodule_from_instance('listgrades', $id);
    \core_completion\api::update_completion_date_event($cm->id, 'listgrades', $id, null);

    // Note: all context files are deleted automatically.
    $DB->delete_records('listgrades', ['id' => $listgrades->id]);
    // Delete calendar events.
    $listgrades->opendate = null;
    $listgrades->closedate = null;
    listgrades_update_calendar($listgrades);

    return true;
}




/**
 * Lists all browsable file areas
 *
 * @package  mod_listgrades
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array
 */
function listgrades_get_file_areas($course, $cm, $context) {
    $areas = [];
    $areas['footer'] = get_string('footer', 'listgrades');
    return $areas;
}

/**
 * File browsing support for listgrades module footer area.
 *
 * @package  mod_listgrades
 * @category files
 * @param stdClass $browser file browser instance
 * @param stdClass $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info instance or null if not found
 */
function listgrades_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG;

    if (!has_capability('moodle/course:managefiles', $context)) {
        // Students can not peak here!
        return null;
    }

    $fs = get_file_storage();

    if ($filearea === 'footer') {
        $filepath = is_null($filepath) ? '/' : $filepath;
        $filename = is_null($filename) ? '.' : $filename;

        $urlbase = $CFG->wwwroot.'/pluginfile.php';
        if (!$storedfile = $fs->get_file($context->id, 'mod_listgrades', 'footer', 0, $filepath, $filename)) {
            if ($filepath === '/' && $filename === '.') {
                $storedfile = new virtual_root_file($context->id, 'mod_listgrades', 'footer', 0);
            } else {
                // Not found.
                return null;
            }
        }
        require_once("$CFG->dirroot/mod/listgrades/locallib.php");
        return new listgrades_content_file_info($browser, $context, $storedfile, $urlbase, $areas[$filearea], true, true, true, false);
    }

    // Note: intro handled in file_browser automatically.

    return null;
}

/**
 * Serves the page files.
 *
 * @package  mod_listgrades
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function listgrades_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);
    if (!has_capability('mod/listgrades:view', $context)) {
        return false;
    }

    if ($filearea !== 'footer') {
        // Intro is handled automatically in pluginfile.php.
        return false;
    }

    // The $arg could be revision number or index.html.
    $arg = array_shift($args);
    if ($arg == 'index.html' || $arg == 'index.htm') {
        // Serve page content.
        $filename = $arg;

        if (!$page = $DB->get_record('listgrades', ['id' => $cm->instance], '*', MUST_EXIST)) {
            return false;
        }

        // We need to rewrite the pluginfile URLs so the media filters can work.
        $footer = file_rewrite_pluginfile_urls($page->footer, 'webservice/pluginfile.php', $context->id,
                                            'mod_listgrades', 'footer', 0);
        $formatoptions = new stdClass;
        $formatoptions->noclean = true;
        $formatoptions->overflowdiv = true;
        $formatoptions->context = $context;
        $footer = format_text($footer, $page->footerformat, $formatoptions);

        // Remove @@PLUGINFILE@@/ from path.
        $options = ['reverse' => true];
        $footer = file_rewrite_pluginfile_urls($footer, 'webservice/pluginfile.php', $context->id,
                                                'mod_listgrades', 'footer', 0, $options);
        $footer = str_replace('@@PLUGINFILE@@/', '', $footer);

        send_file($footer, $filename, 0, 0, true, true);
    } else {
        $fs = get_file_storage();
        $relativepath = implode('/', $args);
        $fullpath = "/$context->id/mod_listgrades/$filearea/0/$relativepath";
        $file = $fs->get_file_by_hash(sha1($fullpath));
        if ( $file == false || $file->is_directory()) {
            return false;
        }

        // Finally send the file.
        send_stored_file($file, null, 0, $forcedownload, $options);
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function listgrades_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $modulepagetype = ['mod-listgrades-*' => get_string('page-mod-listgrades-x', 'listgrades')];
    return $modulepagetype;
}

/**
 * Export page resource contents
 *
 * @return array of file content
 */
function listgrades_export_contents($cm, $baseurl) {
    global $CFG, $DB;
    $contents = array();
    $context = context_module::instance($cm->id);

    $page = $DB->get_record('listgrades', ['id' => $cm->instance], '*', MUST_EXIST);

    // Page contents.
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_listgrades', 'footer', 0, 'sortorder DESC, id ASC', false);
    foreach ($files as $fileinfo) {
        $file = array();
        $file['type']         = 'file';
        $file['filename']     = $fileinfo->get_filename();
        $file['filepath']     = $fileinfo->get_filepath();
        $file['filesize']     = $fileinfo->get_filesize();
        $file['fileurl']      = file_encode_url("$CFG->wwwroot/" . $baseurl, '/'.$context->id.'/mod_listgrades/content/0/'.$fileinfo->get_filepath().$fileinfo->get_filename(), true);
        $file['timecreated']  = $fileinfo->get_timecreated();
        $file['timemodified'] = $fileinfo->get_timemodified();
        $file['sortorder']    = $fileinfo->get_sortorder();
        $file['userid']       = $fileinfo->get_userid();
        $file['author']       = $fileinfo->get_author();
        $file['license']      = $fileinfo->get_license();
        $file['mimetype']     = $fileinfo->get_mimetype();
        $file['isexternalfile'] = $fileinfo->is_external_file();
        if ($file['isexternalfile']) {
            $file['repositorytype'] = $fileinfo->get_repository_type();
        }
        $contents[] = $file;
    }

    // Page html content.
    $filename = 'index.html';
    $pagefile = [];
    $pagefile['type']         = 'file';
    $pagefile['filename']     = $filename;
    $pagefile['filepath']     = '/';
    $pagefile['filesize']     = 0;
    $pagefile['fileurl']      = file_encode_url("$CFG->wwwroot/" . $baseurl, '/'.$context->id.'/mod_listgrades/content/' . $filename, true);
    $pagefile['timecreated']  = null;
    $pagefile['timemodified'] = $page->timemodified;
    // Make this file as main file.
    $pagefile['sortorder']    = 1;
    $pagefile['userid']       = null;
    $pagefile['author']       = null;
    $pagefile['license']      = null;
    $contents[] = $pagefile;

    return $contents;
}

/**
 * Register the ability to handle drag and drop file uploads
 * @return array containing details of the files / types the mod can handle
 */
function listgrades_dndupload_register() {
    return [
            'types' => [
                     ['identifier' => 'text/html', 'message' => get_string('createlisting', 'listgrades')],
                     ['identifier' => 'text', 'message' => get_string('createlisting', 'listgrades')],
                ],
            ];
}

/**
 * Handle a file that has been uploaded
 * @param object $uploadinfo details of the file / content that has been uploaded
 * @return int instance id of the newly created mod
 */
function listgrades_dndupload_handle($uploadinfo) {
    // Gather the required info.
    $data = new stdClass();
    $data->course = $uploadinfo->course->id;
    $data->name = $uploadinfo->displayname;
    $data->intro = '<p>'.$uploadinfo->displayname.'</p>';
    $data->introformat = FORMAT_HTML;
    if ($uploadinfo->type == 'text/html') {
        $data->footerformat = FORMAT_HTML;
        $data->footer = clean_param($uploadinfo->content, PARAM_CLEANHTML);
    } else {
        $data->footerformat = FORMAT_PLAIN;
        $data->footer = clean_param($uploadinfo->content, PARAM_TEXT);
    }
    $data->coursemodule = $uploadinfo->coursemodule;

    // Set the display options to the site defaults.
    $config = get_config('listgrades');
    $data->display = $config->display;
    $data->popupheight = $config->popupheight;
    $data->popupwidth = $config->popupwidth;
    $data->printintro = $config->printintro;
    $data->printlastmodified = $config->printlastmodified;

    return page_add_instance($data, null);
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $page       page object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.0
 */
function listgrades_view($page, $course, $cm, $context) {

    // Trigger course_module_viewed event.
    $params = [
        'context' => $context,
        'objectid' => $page->id,
    ];

    $event = \mod_listgrades\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('listgrades', $page);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function listgrades_check_updates_since(cm_info $cm, $from, $filter = []) {
    $updates = course_check_module_updates_since($cm, $from, ['footer'], $filter);
    return $updates;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_listgrades_core_calendar_provide_event_action(calendar_event $event,
                                                      \core_calendar\action_factory $factory, $userid = 0) {
    global $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['listgrades'][$event->instance];

    $completion = new \completion_info($cm->get_course());

    $completiondata = $completion->get_data($cm, false, $userid);

    if ($completiondata->completionstate != COMPLETION_INCOMPLETE) {
        return null;
    }

    return $factory->create_instance(
        get_string('view'),
        new \moodle_url('/mod/listgrades/view.php', ['id' => $cm->id]),
        1,
        true
    );
}

/**
 * Given an array with a file path, it returns the itemid and the filepath for the defined filearea.
 *
 * @param  string $filearea The filearea.
 * @param  array  $args The path (the part after the filearea and before the filename).
 * @return array The itemid and the filepath inside the $args path, for the defined filearea.
 */
function mod_listgrades_get_path_from_pluginfile(string $filearea, array $args) : array {
    // Page never has an itemid (the number represents the revision but it's not stored in database).
    array_shift($args);

    // Get the filepath.
    if (empty($args)) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    return [
        'itemid' => 0,
        'filepath' => $filepath,
    ];
}
