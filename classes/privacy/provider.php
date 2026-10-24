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
 * Privacy provider.
 *
 * @package    block_stash
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\privacy;
defined('MOODLE_INTERNAL') || die();

use context;
use context_course;
use block_stash\drop;
use block_stash\drop_pickup;
use block_stash\item;
use block_stash\stash;
use block_stash\swap;
use block_stash\user_item;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 *
 * @package    block_stash
 * @copyright  2018 Frédéric Massart
 * @author     Frédéric Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    use \core_privacy\local\legacy_polyfill;

    /**
     * Returns metadata.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function _get_metadata($collection) {

        $collection->add_database_table(user_item::TABLE, [
            'itemid' => 'privacy:metadata:useritem:itemid',
            'userid' => 'privacy:metadata:useritem:userid',
            'quantity' => 'privacy:metadata:useritem:quantity',
            'timecreated' => 'privacy:metadata:useritem:timecreated',
            'timemodified' => 'privacy:metadata:useritem:timemodified',
        ], 'privacy:metadata:useritem');

        $collection->add_database_table(drop_pickup::TABLE, [
            'dropid' => 'privacy:metadata:pickup:dropid',
            'userid' => 'privacy:metadata:pickup:userid',
            'pickupcount' => 'privacy:metadata:pickup:pickupcount',
            'lastpickup' => 'privacy:metadata:pickup:lastpickup',
            'timecreated' => 'privacy:metadata:pickup:timecreated',
            'timemodified' => 'privacy:metadata:pickup:timemodified',
        ], 'privacy:metadata:pickup');

        $collection->add_database_table(swap::TABLE, [
            'stashid' => 'privacy:metadata:swap:stashid',
            'initiator' => 'privacy:metadata:swap:initiator',
            'receiver' => 'privacy:metadata:swap:receiver',
            'message' => 'privacy:metadata:swap:message',
            'messageformat' => 'privacy:metadata:swap:messageformat',
            'status' => 'privacy:metadata:swap:status',
            'timecreated' => 'privacy:metadata:swap:timecreated',
            'timemodified' => 'privacy:metadata:swap:timemodified',
        ], 'privacy:metadata:swap');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function _get_contexts_for_userid($userid) {
        global $DB;
        $contextlist = new \core_privacy\local\request\contextlist();

        $sql = "SELECT ctx.id
                  FROM {" . user_item::TABLE . "} ui
                  JOIN {" . item::TABLE . "} i
                    ON i.id = ui.itemid
                  JOIN {" . stash::TABLE . "} s
                    ON s.id = i.stashid
                  JOIN {context} ctx
                    ON ctx.instanceid = s.courseid
                   AND ctx.contextlevel = :courselevel
                 WHERE ui.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'courselevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        $sql = "SELECT ctx.id
                  FROM {" . drop_pickup::TABLE . "} dp
                  JOIN {" . drop::TABLE . "} d
                    ON d.id = dp.dropid
                  JOIN {" . item::TABLE . "} i
                    ON i.id = d.itemid
                  JOIN {" . stash::TABLE . "} s
                    ON s.id = i.stashid
                  JOIN {context} ctx
                    ON ctx.instanceid = s.courseid
                   AND ctx.contextlevel = :courselevel
                 WHERE dp.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'courselevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        $sql = "SELECT ctx.id
                  FROM {block_stash_swap} ss
                  JOIN {block_stash} s ON ss.stashid = s.id
                  JOIN {context} ctx ON ctx.instanceid = s.courseid AND ctx.contextlevel = :contextlevel
                 WHERE ss.initiator = :initiatorid OR ss.receiver = :receiverid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'initiatorid' => $userid,
            'receiverid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function _export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $courseids = array_map(function($context) {
            return $context->instanceid;
        }, array_filter($contextlist->get_contexts(), function($context) {
            return $context->contextlevel == CONTEXT_COURSE;
        }));

        if (empty($courseids)) {
            return;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $uniqueid = $DB->sql_concat_join("'-'", ['i.id', 'COALESCE(ui.id, 0)', 'COALESCE(d.id, 0)', 'COALESCE(dp.id, 0)']);
        $sql = "
            SELECT $uniqueid AS uniqueid,
                   s.courseid,
                   i.id AS itemid,
                   i.name AS itemname,
                   d.name AS dropname,
                   ui.quantity AS qty,
                   dp.pickupcount AS pickupcount,
                   dp.lastpickup AS lastpickup
              FROM {" . item::TABLE . "} i
              JOIN {" . stash::TABLE . "} s
                ON s.id = i.stashid
         LEFT JOIN {" . user_item::TABLE . "} ui
                ON ui.itemid = i.id
               AND ui.userid = :userid1
         LEFT JOIN {" . drop::TABLE . "} d
                ON d.itemid = i.id
         LEFT JOIN {" . drop_pickup::TABLE . "} dp
                ON dp.dropid = d.id
               AND dp.userid = :userid2
             WHERE s.courseid $insql
               AND (ui.id IS NOT NULL
                OR dp.id IS NOT NULL)
          ORDER BY s.courseid, ui.id, dp.id";
        $params = array_merge($inparams, ['userid1' => $userid, 'userid2' => $userid]);
        $recordset = $DB->get_recordset_sql($sql, $params);
        static::recordset_loop_and_export($recordset, 'courseid', [], function($carry, $record) {
            $id = $record->itemid;

            if (!isset($carry[$id])) {
                $carry[$id] = [
                    'name' => $record->itemname,
                    'owned' => 0,
                    'pickups' => []
                ];
                if (!empty($record->qty)) {
                    $carry[$id]['owned'] = (int) $record->qty;
                }
            }

            if (!empty($record->lastpickup)) {
                $carry[$id]['pickups'][] = [
                    'location' => $record->dropname,
                    'quantity' => $record->pickupcount,
                    'last_pickup' => transform::datetime($record->lastpickup)
                ];
            }

            return $carry;

        }, function($courseid, $data) {
            writer::with_context(context_course::instance($courseid))->export_data(
                [get_string('pluginname', 'block_stash')],
                (object) ['items' => array_values($data)]
            );
        });

        // Export swap requests.
        $swapsql = "SELECT (CASE WHEN (sd.id IS NULL) THEN (" . $DB->sql_concat('ss.id', "'_'", '0') . ") ELSE
                      (" . $DB->sql_concat('ss.id', "'_'", 'sd.id') . ") END)  AS uniqueid, ss.id, ss.initiator, ss.receiver,
                      ss.status, ss.timecreated, ui.userid, sd.quantity, i.name, s.courseid
                      FROM {block_stash_swap} ss
                      JOIN {block_stash} s ON ss.stashid = s.id
                 LEFT JOIN {block_stash_swap_detail} sd ON sd.swapid = ss.id
                 LEFT JOIN {block_stash_user_items} ui ON sd.useritemid = ui.id
                 LEFT JOIN {block_stash_items} i ON ui.itemid = i.id
                     WHERE (ss.initiator = :userid OR ss.receiver = :ruserid)
                       AND s.courseid $insql";
        // print_object($swapsql);
        $params = array_merge($inparams, ['userid' => $userid, 'ruserid' => $userid]);
        $recordset = $DB->get_recordset_sql($swapsql, $params);
        static::recordset_loop_and_export($recordset, 'courseid', [], function($carry, $record) {

            $userid = $record->userid ?? 0;

            $carry[$record->uniqueid][$userid] = [
                'tradeid' => $record->id,
                'item' => $record->name ?? get_string('notrecorded', 'block_stash'),
                'quantity' => $record->quantity,
                'intiatorid' => $record->initiator,
                'recieverid' => $record->receiver,
                'timecreated' => transform::datetime($record->timecreated),
                'status' => static::transform_swap_status($record->status)
            ];

            return $carry;


        }, function($courseid, $data) {
            writer::with_context(context_course::instance($courseid))->export_related_data(
                [get_string('pluginname', 'block_stash')],
                'trades',
                (object) ['trades' => array_values($data)]
            );
        });
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function _delete_data_for_all_users_in_context(context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        // Find the item IDs.
        $itemids = static::get_itemids_from_courseids([$context->instanceid]);
        if (empty($itemids)) {
            return;
        }

        // Delete the items from users..
        list($insql, $inparams) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        $DB->delete_records_select(user_item::TABLE, "itemid $insql", $inparams);

        // Find the relevant drop IDs.
        $dropids = static::get_dropids_from_itemids($itemids);
        if (empty($dropids)) {
            return;
        }

        // Delete the drop pickups.
        list($insql, $inparams) = $DB->get_in_or_equal($dropids, SQL_PARAMS_NAMED);
        $DB->delete_records_select(drop_pickup::TABLE, "dropid $insql", $inparams);

        // Delete all swap details and then swaps.
        $swapdetailids = static::get_swap_detail_ids_from_courseids([$context->instanceid]);
        if (empty($swapdetailids)) {
            return;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($swapdetailids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('block_stash_swap_detail', "id $insql", $inparams);

        $stashids = static::get_stashids_from_courseids([$context->instanceid]);
        if (empty($stashids)) {
            return;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($stashids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('block_stash_swap', "stashid $insql", $inparams);

    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function _delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        $courseids = array_map(function($context) {
            return $context->instanceid;
        }, array_filter($contextlist->get_contexts(), function($context) {
            return $context->contextlevel == CONTEXT_COURSE;
        }));

        $itemids = static::get_itemids_from_courseids($courseids);
        if (!empty($itemids)) {

            // Delete the items a user has.
            list($initemsql, $initemparams) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
            $params = array_merge($initemparams, ['userid' => $userid]);
            $DB->delete_records_select(user_item::TABLE, "userid = :userid AND itemid $initemsql", $params);

            // Find the relevant drop IDs.
            $dropids = static::get_dropids_from_itemids($itemids);
            if (!empty($dropids)) {
                // Delete the drop pickups.
                list($indropsql, $indropparams) = $DB->get_in_or_equal($dropids, SQL_PARAMS_NAMED);
                $params = array_merge($indropparams, ['userid' => $userid]);
                $DB->delete_records_select(drop_pickup::TABLE, "userid = :userid AND dropid $indropsql", $params);
            }
        }

        list($coursesql, $courseparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        // Delete swaps and swap detail.
        $sql = "SELECT sd.id
                  FROM {block_stash_swap_detail} sd
                  JOIN {block_stash_swap} ss ON ss.id = sd.swapid
                  JOIN {block_stash} s ON s.id = ss.stashid
                  JOIN {course} c ON s.courseid = c.id
                 WHERE (ss.initiator = :iuserid OR ss.receiver = :ruserid)
                   AND c.id $coursesql";
        $params = array_merge($courseparams, ['iuserid' => $userid, 'ruserid' => $userid]);
        $swapdetailids = $DB->get_records_sql($sql, $params);

        $DB->delete_records_list('block_stash_swap_detail', 'id', array_keys($swapdetailids));

        $sql = "SELECT ss.id
                  FROM {block_stash_swap} ss
                  JOIN {block_stash} s ON s.id = ss.stashid
                  JOIN {course} c ON c.id = s.courseid
                 WHERE (ss.initiator = :iuserid OR ss.receiver = :ruserid)
                   AND c.id $coursesql";

        $swapids = $DB->get_records_sql($sql, $params);
        $DB->delete_records_list('block_stash_swap', 'id', array_keys($swapids));
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        $params = ['courseid' => $context->instanceid];

        $sql = "SELECT ui.userid
                  FROM {" . user_item::TABLE . "} ui
                  JOIN {" . item::TABLE . "} i
                    ON i.id = ui.itemid
                  JOIN {" . stash::TABLE . "} s
                    ON s.id = i.stashid
                 WHERE s.courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT dp.userid
                  FROM {" . drop_pickup::TABLE . "} dp
                  JOIN {" . drop::TABLE . "} d
                    ON d.id = dp.dropid
                  JOIN {" . item::TABLE . "} i
                    ON i.id = d.itemid
                  JOIN {" . stash::TABLE . "} s
                    ON s.id = i.stashid
                 WHERE s.courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT ss.initiator
                  FROM {block_stash_swap} ss
                  JOIN {block_stash} s ON s.id = ss.stashid
                 WHERE s.courseid = :courseid";
        $userlist->add_from_sql('initiator', $sql, $params);

        $sql = "SELECT ss.receiver
                  FROM {block_stash_swap} ss
                  JOIN {block_stash} s ON s.id = ss.stashid
                 WHERE s.courseid = :courseid";
        $userlist->add_from_sql('receiver', $sql, $params);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if ($context->contextlevel != CONTEXT_COURSE || empty($userids)) {
            return;
        }

        $itemids = static::get_itemids_from_courseids([$context->instanceid]);
        if (!empty($itemids)) {
            // Delete the items the users have.
            [$inusersql, $inuserparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
            [$initemsql, $initemparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'item');
            $params = array_merge($inuserparams, $initemparams);
            $DB->delete_records_select(user_item::TABLE, "userid $inusersql AND itemid $initemsql", $params);

            // Find the relevant drop IDs.
            $dropids = static::get_dropids_from_itemids($itemids);
            if (!empty($dropids)) {
                // Delete the drop pickups.
                [$indropsql, $indropparams] = $DB->get_in_or_equal($dropids, SQL_PARAMS_NAMED, 'drop');
                $params = array_merge($inuserparams, $indropparams);
                $DB->delete_records_select(drop_pickup::TABLE, "userid $inusersql AND dropid $indropsql", $params);
            }
        }

        // Delete swaps and swap detail.
        [$ininitiatorsql, $ininitiatorparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'initiator');
        [$inreceiversql, $inreceiverparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'receiver');
        $params = array_merge($ininitiatorparams, $inreceiverparams, ['courseid' => $context->instanceid]);

        $sql = "SELECT sd.id
                  FROM {block_stash_swap_detail} sd
                  JOIN {block_stash_swap} ss ON ss.id = sd.swapid
                  JOIN {block_stash} s ON s.id = ss.stashid
                 WHERE (ss.initiator $ininitiatorsql OR ss.receiver $inreceiversql)
                   AND s.courseid = :courseid";
        $swapdetailids = $DB->get_records_sql($sql, $params);
        $DB->delete_records_list('block_stash_swap_detail', 'id', array_keys($swapdetailids));

        $sql = "SELECT ss.id
                  FROM {block_stash_swap} ss
                  JOIN {block_stash} s ON s.id = ss.stashid
                 WHERE (ss.initiator $ininitiatorsql OR ss.receiver $inreceiversql)
                   AND s.courseid = :courseid";
        $swapids = $DB->get_records_sql($sql, $params);
        $DB->delete_records_list('block_stash_swap', 'id', array_keys($swapids));
    }

    /**
     * Get drop IDs from item IDs.
     *
     * @param array $itemids The item IDs.
     * @return array
     */
    protected static function get_dropids_from_itemids(array $itemids) {
        global $DB;
        if (empty($itemids)) {
            return [];
        }

        list($insql, $inparams) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        return $DB->get_fieldset_select(drop::TABLE, 'id', "itemid $insql", $inparams);
    }

    /**
     * Get item IDs from course IDs.
     *
     * @param array $courseids The course IDs.
     * @return array
     */
    protected static function get_itemids_from_courseids(array $courseids) {
        global $DB;
        if (empty($courseids)) {
            return [];
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $sql = "SELECT i.id
                  FROM {" . item::TABLE . "} i
                  JOIN {" . stash::TABLE . "} s
                    ON s.id = i.stashid
                 WHERE s.courseid $insql";
        return $DB->get_fieldset_sql($sql, $inparams);
    }

    protected static function get_swap_detail_ids_from_courseids(array $courseids) {
        global $DB;

        if (empty($courseids)) {
            return [];
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $sql = "SELECT sd.id
                  FROM {block_stash_swap_detail} sd
                  JOIN {block_stash_swap} ss ON ss.id = sd.swapid
                  JOIN {block_stash} s ON s.id = ss.stashid
                 WHERE s.courseid $insql";
        return $DB->get_fieldset_sql($sql, $inparams);

    }

    protected static function get_stashids_from_courseids(array $courseids) {
        global $DB;

        if (empty($courseids)) {
            return [];
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $sql = "SELECT s.id
                  FROM {block_stash} s
                 WHERE s.courseid $insql";
        return $DB->get_fieldset_sql($sql, $inparams);
    }

    /**
     * Loop and export from a recordset.
     *
     * @param moodle_recordset $recordset The recordset.
     * @param string $splitkey The record key to determine when to export.
     * @param mixed $initial The initial data to reduce from.
     * @param callable $reducer The function to return the dataset, receives current dataset, and the current record.
     * @param callable $export The function to export the dataset, receives the last value from $splitkey and the dataset.
     * @return void
     */
    protected static function recordset_loop_and_export(\moodle_recordset $recordset, $splitkey, $initial,
            callable $reducer, callable $export) {

        $data = $initial;
        $lastid = null;

        foreach ($recordset as $record) {
            if ($lastid && $record->{$splitkey} != $lastid) {
                $export($lastid, $data);
                $data = $initial;
            }
            $data = $reducer($data, $record);
            $lastid = $record->{$splitkey};
        }
        $recordset->close();

        if (!empty($lastid)) {
            $export($lastid, $data);
        }
    }

    private static function transform_swap_status($statuscode) {
        switch ($statuscode) {
            case swap::BLOCK_STASH_SWAP_DECLINE:
                return get_string('tradestatusdeclined', 'block_stash');
                break;
            case swap::BLOCK_STASH_SWAP_VIEWED:
                return get_string('tradestatusviewed', 'block_stash');
                break;
            case swap::BLOCK_STASH_SWAP_COMPLETED:
                return get_string('tradestatuscompleted', 'block_stash');
                break;
            case swap::BLOCK_STASH_SWAP_APPROVE:
                return get_string('tradestatusapproved', 'block_stash');
                break;
            default:
                return get_string('notrecorded', 'block_stash');
        }
    }

}
