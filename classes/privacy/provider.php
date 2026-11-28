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
 * Privacy provider for mod_listgrades.
 *
 * @package    mod_listgrades
 * @copyright  2023 Juan Pablo de Castro <juanpablo.decastro@uva.es>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_listgrades\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\core_userlist_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for review process data.
 */
class provider implements \core_privacy\local\metadata\provider, core_userlist_provider, plugin_provider {
    /**
     * Describe personal data stored by the review process.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('listgrades_review', [
            'listgradesid' => 'privacy:metadata:listgrades_review:listgradesid',
            'userid' => 'privacy:metadata:listgrades_review:userid',
            'actorid' => 'privacy:metadata:listgrades_review:actorid',
            'action' => 'privacy:metadata:listgrades_review:action',
            'comment' => 'privacy:metadata:listgrades_review:comment',
            'snapshot' => 'privacy:metadata:listgrades_review:snapshot',
            'timecreated' => 'privacy:metadata:listgrades_review:timecreated',
        ], 'privacy:metadata:listgrades_review');

        return $collection;
    }

    /**
     * Get module contexts containing review actions by a user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {listgrades} lg ON lg.id = cm.instance
                  JOIN {listgrades_review} lr ON lr.listgradesid = lg.id
                 WHERE lr.userid = :userid1 OR lr.actorid = :userid2";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'listgrades',
            'userid1' => $userid,
            'userid2' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * List users with review actions in a module context.
     *
     * @param userlist $userlist User list.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('listgrades', $context->instanceid);
        if (!$cm) {
            return;
        }
        $userlist->add_from_sql('userid', "SELECT userid FROM {listgrades_review} WHERE listgradesid = :listgradesid1
            UNION SELECT actorid AS userid FROM {listgrades_review}
             WHERE listgradesid = :listgradesid2 AND actorid <> :anonymousid", [
            'listgradesid1' => $cm->instance,
            'listgradesid2' => $cm->instance,
            'anonymousid' => guest_user()->id,
        ]);
    }

    /**
     * Export review history for an approved user/context request.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/listgrades/locallib.php');

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('listgrades', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $actions = $DB->get_records_select(
                'listgrades_review',
                'listgradesid = :listgradesid AND (userid = :userid1 OR actorid = :userid2)',
                ['listgradesid' => $cm->instance, 'userid1' => $userid, 'userid2' => $userid],
                'id ASC'
            );
            if (!$actions) {
                continue;
            }
            $export = [];
            foreach ($actions as $action) {
                $comment = $action->comment;
                $source = in_array($action->action, ['requested', 'cancelled'], true)
                    ? listgrades_get_choice_review_source($comment) : null;
                if ($source) {
                    $comment = listgrades_can_view_choice_review_source($source, $context, (int) $userid)
                        ? get_string('modulename', 'choice') . ': ' . $source['name'] : null;
                }
                $export[] = [
                    'action' => $action->action,
                    'comment' => $comment,
                    'snapshot' => $action->snapshot,
                    'studentid' => $action->userid,
                    'actorid' => $action->actorid,
                    'timecreated' => transform::datetime($action->timecreated),
                    'recordedat' => isset($source['recordedat']) && is_int($source['recordedat'])
                        ? transform::datetime($source['recordedat']) : null,
                ];
            }
            writer::with_context($context)->export_data([get_string('reviewsection', 'listgrades')], (object) [
                'actions' => $export,
            ]);
        }
    }

    /**
     * Delete all review data in a module context.
     *
     * @param \context $context Context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('listgrades', $context->instanceid);
        if ($cm) {
            $DB->delete_records('listgrades_review', ['listgradesid' => $cm->instance]);
        }
    }

    /**
     * Delete review data for a user in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('listgrades', $context->instanceid);
            if ($cm) {
                self::delete_or_anonymise_reviews((int) $cm->instance, [(int) $userid]);
            }
        }
    }

    /**
     * Delete review data for an approved set of users in one context.
     *
     * @param approved_userlist $userlist Approved user list.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('listgrades', $context->instanceid);
        if (!$cm) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        self::delete_or_anonymise_reviews((int) $cm->instance, $userids);
    }

    /**
     * Delete students' histories and anonymise their contributions to other histories.
     *
     * @param int $listgradesid Activity instance id.
     * @param array $userids Users whose data should be erased.
     * @return void
     */
    private static function delete_or_anonymise_reviews(int $listgradesid, array $userids): void {
        global $DB;

        [$usersql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'reviewuser');
        $params['listgradesid'] = $listgradesid;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records_select('listgrades_review', "listgradesid = :listgradesid AND userid $usersql", $params);
        $DB->set_field_select(
            'listgrades_review',
            'actorid',
            guest_user()->id,
            "listgradesid = :listgradesid AND actorid $usersql",
            $params
        );
        $transaction->allow_commit();
    }
}
