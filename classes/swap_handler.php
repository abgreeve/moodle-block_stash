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
 * Stash manager.
 *
 * @package    block_stash
 * @copyright  2023 Adrian Greeve <abgreeve@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash;

use moodle_exception;

class swap_handler {

    private $manager;

    private $courseid;

    public function __construct($manager) {
        $this->manager = $manager;
        $this->courseid = $this->manager->get_courseid();
    }

    public function view_swap_invite($swapid) {
        $swap = swap::load($swapid);
        $swap->set_status(swap::BLOCK_STASH_SWAP_VIEWED);
        $swap->save();
    }

    public function decline_swap($swapid) {
        $swap = swap::load($swapid);
        $swap->set_status(swap::BLOCK_STASH_SWAP_DECLINE);
        $swap->save();
    }

    public function delete_swap($swapid) {
        $swap = swap::load($swapid);
        $swap->delete();
    }

    /**
     * Retrieve the swap requests for a given user.
     *
     * @param int $userid The user ID for which to retrieve the swap requests.
     * @return array An associative array with the following keys:
     *         - requests: An array of objects representing swap requests initiated by other users.
     *         - offers: An array of objects representing swap requests initiated by the current user.
     *         - courseid: The ID of the current course.
     */
    public function get_users_swap_requests(int $userid): array {
        global $DB;

        $sql = "SELECT s.id, u.firstname, u.lastname, ru.firstname AS first, ru.lastname AS last, s.timecreated, s.status,
                       s.initiator
                  FROM {block_stash_swap} s
             LEFT JOIN {user} u ON s.initiator = u.id
             LEFT JOIN {user} ru ON s.receiver = ru.id
                 WHERE s.id = :stashid
                   AND (s.receiver = :ruserid OR s.initiator = :iuserid)
                   AND (s.status IS NULL OR s.status = :viewed OR s.status = :completed)";

        $params = [
            'stashid' => $this->manager->get_stash()->get_id(),
            'ruserid' => $userid,
            'iuserid' => $userid,
            'viewed' => swap::BLOCK_STASH_SWAP_VIEWED,
            'completed' => swap::BLOCK_STASH_SWAP_COMPLETED
        ];

        $records = $DB->get_records_sql($sql, $params);

        $requests = [];
        $offers = [];
        foreach ($records as $record) {
            if ($record->initiator != $userid) {
                if ($record->status != swap::BLOCK_STASH_SWAP_COMPLETED) {
                    $requests[] = $record;
                }
            } else {
                $offers[] = $record;
            }
        }

        return [
            'requests' => $requests,
            'offers' => $offers,
            'courseid' => $this->courseid
        ];
    }

    /**
     * Get the number of unread swap requests for a given user.
     *
     * @param int $userid The ID of the user to get unread swap requests for.
     * @return int The number of unread swap requests.
     */
    public function get_unread_requests($userid): int {
        global $DB;

        // Maybe cache this?
        $result = $DB->count_records_select('block_stash_swap', 'status IS NULL AND receiver = :userid', ['userid' => $userid]);
        return $result;
    }

    /**
     * Retrieves the details of a swap, including the items being swapped and whether the request can be fulfilled.
     *
     * @param int $swapid The ID of the swap to retrieve details for.
     * @param int $userid The ID of the user receiving the swap.
     * @return array An array containing the details of the swap, including:
     *  * myitems: An array of items that the receiving user is offering to swap.
     *  * otheritems: An array of items that requesting users are offering to swap.
     *  * requestpossible: A boolean indicating whether there are enough of the requested items to fulfill the swap.
     */
    public function get_swap_details(int $swapid, int $userid): array {
        global $DB;

        $fielddata = array_map(function($field) {
            if ($field == 'id') {
                return 'u.' . $field . ' AS userid';
            }
            return 'u.' . $field;
        }, \core_user\fields::get_picture_fields());

        $userfields = implode(',', $fielddata);

        $sql = "SELECT sd.id, i.name, i.id as itemid, sd.quantity, $userfields, ui.quantity as actualquantity
                  FROM {block_stash_swap_detail} sd
             LEFT JOIN {block_stash_user_items} ui ON sd.useritemid = ui.id
             LEFT JOIN {block_stash_items} i ON ui.itemid = i.id
                  JOIN {user} u ON ui.userid = u.id
                 WHERE sd.swapid = :swapid";

        $params = ['swapid' => $swapid];

        $records = $DB->get_records_sql($sql, $params);

        $myitems = [];
        $otheritems = [];
        $requestpossible = true;
        foreach ($records as $record) {
            if ($record->quantity > $record->actualquantity) {
                $requestpossible = false;
            }
            if ($record->userid == $userid) {
                $myitems[] = $record;
            } else {
                if (empty($record->userid)) {
                    // This request can no longer be fulfilled.
                    $requestpossible = false;

                    // TODO - could try to see if the user has aquired this item later with a different entry in the user item table.
                    // The teacher may have reset / deleted / returned the items which would result in a new entry with these items.
                }
                $otheritems[] = $record;
            }
        }
        return ['myitems' => $myitems, 'otheritems' => $otheritems, 'requestpossible' => $requestpossible];
    }

    /**
     * Swaps items between users by updating their respective inventories in the database.
     *
     * @param int $swapid ID of the swap transaction
     */
    public function swap_items(int $swapid): void {
        global $DB;

        $transactionhash = $this->generate_transaction_hash();
        $endresult = $this->get_swap_end_result($swapid, $transactionhash);

        $recordtransaction = $DB->start_delegated_transaction();
        $swap = swap::load($swapid, true);

        // A lot of work to do here.
        $initiatoritems = $swap->get_initiator_items();
        $receiveritems = $swap->get_receiver_items();
        foreach ($initiatoritems as $item) {
            // Check that the quantity is still fine.
            if ($item['useritem']->get_quantity() < $item['quantity']) {
                // Stop the transaction.
                // Throw exception.
                throw new \Exception("quantity is wrong", 1);

            }
            // Add first then remove.
            // Step one check if we are going to be updating or inserting.
            $itemdetails = ['itemid' => $item['useritem']->get_itemid(), 'userid' => $swap->get_receiver_id()];
            $useritem = $DB->get_record('block_stash_user_items', $itemdetails);
            if ($useritem) {
                // Update.
                $sql = "UPDATE {block_stash_user_items}
                           SET quantity = :quantity, version = :newversion
                         WHERE id = :id AND version = :version";
                $params = [
                    'quantity' => $useritem->quantity + $item['quantity'],
                    'id' => $useritem->id,
                    'version' => $useritem->version,
                    'newversion' => $transactionhash
                ];
                $DB->execute($sql, $params);
            } else {
                // Insert.
                $data = (object) [
                    'itemid' => $item['useritem']->get_itemid(),
                    'userid' => $swap->get_receiver_id(),
                    'quantity' => $item['quantity'],
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'version' => $transactionhash
                ];
                $DB->insert_record('block_stash_user_items', $data);
            }
            // Now remove.
            $sql = "UPDATE {block_stash_user_items}
                       SET quantity = :quantity, version = :newversion
                     WHERE id = :id AND version = :version";
            $params = [
                'quantity' => $item['useritem']->get_quantity() - $item['quantity'],
                'id' => $item['useritem']->get_id(),
                'version' => $item['useritem']->get_version(),
                'newversion' => $transactionhash
            ];
            $DB->execute($sql, $params);
        }
        foreach ($receiveritems as $item) {
            // Check that the quantity is still fine.
            if ($item['useritem']->get_quantity() < $item['quantity']) {
                // Stop the transaction.
                // Throw exception.
                throw new \Exception("quantity is wrong", 1);
            }
            // Add first then remove.
            // Step one check if we are going to be updating or inserting.
            $useritemdetails = ['itemid' => $item['useritem']->get_itemid(), 'userid' => $swap->get_initiator_id()];
            $useritem = $DB->get_record('block_stash_user_items', $useritemdetails);
            if ($useritem) {
                // Update.
                $sql = "UPDATE {block_stash_user_items}
                           SET quantity = :quantity, version = :newversion
                         WHERE id = :id AND version = :version";
                $params = [
                    'quantity' => $useritem->quantity + $item['quantity'],
                    'id' => $useritem->id,
                    'version' => $useritem->version,
                    'newversion' => $transactionhash
                ];
                $DB->execute($sql, $params);
            } else {
                // Insert.
                $data = (object) [
                    'itemid' => $item['useritem']->get_itemid(),
                    'userid' => $swap->get_initiator_id(),
                    'quantity' => $item['quantity'],
                    'timecreated' => time(),
                    'timemodified' => time(),
                    'version' => $transactionhash
                ];
                $DB->insert_record('block_stash_user_items', $data);
            }
            // Now remove.
            $sql = "UPDATE {block_stash_user_items}
                       SET quantity = :quantity, version = :newversion
                     WHERE id = :id AND version = :version";
            $params = [
                'quantity' => $item['useritem']->get_quantity() - $item['quantity'],
                'id' => $item['useritem']->get_id(),
                'version' => $item['useritem']->get_version(),
                'newversion' => $transactionhash
            ];
            $DB->execute($sql, $params);
        }

        // Do final query and then allow the commit.
        $useritems = $DB->get_records('block_stash_user_items', ['version' => $transactionhash]);
        $initiatorid = $swap->get_initiator_id();
        $receiverid = $swap->get_receiver_id();
        $endinitiator = array_filter($useritems, function($value) use ($initiatorid) {
            return $value->userid == $initiatorid;
        });
        $endreceiver = array_filter($useritems, function($value) use ($receiverid) {
            return $value->userid == $receiverid;
        });

        $allokay = $this->validate_items($endresult['initiator'], $endinitiator) &&
                $this->validate_items($endresult['receiver'], $endreceiver);

        if (!$allokay) {
            throw new \Exception("Trade could not be completed", 1);
        }

        // Last thing. Update the swap request to completed.
        $swap->set_status(swap::BLOCK_STASH_SWAP_COMPLETED);
        $swap->save();

        $recordtransaction->allow_commit();
        $event = \block_stash\event\swap_accepted::create([
                'context' => $this->manager->get_context(),
                'userid' => $receiverid,
                'courseid' => $this->courseid,
                'objectid' => $swapid,
                'relateduserid' => $initiatorid
            ]
        );
        $event->trigger();
    }

    /**
     * Validates that the items in the swap match their expected values.
     *
     * @param array $items An array of the expected end result items.
     * @param array $enditems An array with the end result of our swap queries
     * @return bool Returns `true` if all items match their expected values, `false` otherwise.
     */
    private function validate_items(array $items, array $enditems): bool {
        foreach ($items as $key => $item) {
            if (!isset($item['newitem'])) {
                // Check for that user item id.
                if (!isset($enditems[$item['id']])) {
                    return false;
                }
                // Check the quantity matches.
                if ($enditems[$item['id']]->quantity != $item['quantity']) {
                    return false;
                }
            } else {
                // New item
                $found = false;
                foreach ($enditems as $value) {
                    if ($value->itemid == $item['itemid']) {
                        if ($value->quantity != $item['quantity']) {
                            return false;
                        }
                        $found = true;
                    }
                }
                if (!$found) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Generates a unique transaction hash for large DB swap queries.
     *
     * @return string A unique transaction hash.
     */
    protected function generate_transaction_hash(): string {
        return uniqid("", true);
    }

    /**
     * Retrieves the details of a swap transaction, including the initiator and receiver items involved in the swap,
     * and updates the quantities of these items to reflect the completion of the swap transaction.
     *
     * @param int $swapid The ID of the swap transaction.
     * @param string $transactionhash The unique transaction hash associated with the swap transaction.
     * @return array An array of arrays containing the updated item details for the initiator and receiver,
     *               including the item ID, quantity, version, and whether the item is new or not.
     */
    protected function get_swap_end_result(int $swapid, string $transactionhash): array {
        global $DB;

        $swap = swap::load($swapid, true);
        $itemids = [];
        $initiatoruserid = null;
        $receiveruserid = null;
        foreach ($swap->get_receiver_items() as $ritem) {
            $itemids[$ritem['useritem']->get_itemid()] = $ritem['useritem']->get_itemid();
            $receiveruserid = $ritem['useritem']->get_userid();
        }
        foreach ($swap->get_initiator_items() as $iitem) {
            $itemids[$iitem['useritem']->get_itemid()] = $iitem['useritem']->get_itemid();
            $initiatoruserid = $iitem['useritem']->get_userid();
        }

        list($insql, $inparams) = $DB->get_in_or_equal($itemids);

        $sql = "SELECT id, itemid, userid, quantity, version
                  FROM {block_stash_user_items} ui
                 WHERE itemid $insql
                   AND userid = ? OR userid = ?";

        $inparams = array_merge($inparams, [$receiveruserid, $initiatoruserid]);
        $records = $DB->get_records_sql($sql, $inparams);

        $details = [];
        foreach ($records as $record) {
            $details[$record->userid][$record->itemid] = $record;
        }

        $initiator = [];
        $receiver = [];
        $initiatoritems = $swap->get_initiator_items();
        $receiveritems = $swap->get_receiver_items();
        foreach ($initiatoritems as $item) {
            $itemid = $item['useritem']->get_itemid();
            $initiator[$item['useritem']->get_id()] = [
                'id' => $item['useritem']->get_id(),
                'quantity' => $item['useritem']->get_quantity() - $item['quantity'],
                'itemid' => $itemid,
                'version' => $transactionhash
            ];
            // echo $itemid;
            // print_object($details[$receiveruserid][$itemid]);
            if (!isset($details[$receiveruserid][$itemid])) {
                $id = 'none-' . $itemid;
                $receiver[$id] = [
                    'id' => $item['useritem']->get_id(),
                    'quantity' => $item['quantity'],
                    'itemid' => $itemid,
                    'version' => $transactionhash,
                    'newitem' => true
                ];
                // echo 'no item';
            } else {
                $stuff = $details[$receiveruserid][$itemid]->id;
                $receiver[$stuff] = [
                    'id' => $stuff,
                    'quantity' => $details[$receiveruserid][$itemid]->quantity + $item['quantity'],
                    'itemid' => $itemid,
                    'version' => $transactionhash
                ];
            }
        }

        // print_object($receiveritems);

        foreach ($receiveritems as $item) {
            // print_object($item);
            $itemid = $item['useritem']->get_itemid();
            $receiver[$item['useritem']->get_id()] = [
                'id' => $item['useritem']->get_id(),
                'quantity' => $item['useritem']->get_quantity() - $item['quantity'],
                'itemid' => $itemid,
                'version' => $transactionhash
            ];
            // echo $itemid;
            // print_object($details[$initiatoruserid][$itemid]);
            // print_object($details);
            if (!isset($details[$initiatoruserid][$itemid])) {
                $id = 'none-' . $itemid;
                $initiator[$id] = [
                    'id' => $item['useritem']->get_id(),
                    'quantity' => $item['quantity'],
                    'itemid' => $itemid,
                    'version' => $transactionhash,
                    'newitem' => true
                ];
                // echo 'no item';
            } else {
                $stuff = $details[$initiatoruserid][$itemid]->id;
                $initiator[$stuff] = [
                    'id' => $stuff,
                    'quantity' => $details[$initiatoruserid][$itemid]->quantity + $item['quantity'],
                    'itemid' => $itemid,
                    'version' => $transactionhash
                ];
            }
        }

        // print_object($initiator);
        // print_object($receiver);
        return ['initiator' => $initiator, 'receiver' => $receiver];
    }

    /**
     * Creates a swap request between two users.
     *
     * @param  int $userid   The user the request is for
     * @param  int $myuserid The user who is creating the request
     * @param  array $items  The first user's items requested.
     * @param  array $myitems  The second user's items put forward for swap.
     */
    public function create_swap_request($userid, $myuserid, $items, $myitems) {
        global $USER;

        if (!$this->manager->is_swapping_enabled()) {
            throw new \moodle_exception('User trading has not been enabled');
        }

        // @TODO change the moodle exceptions to lang strings.
        if ($myuserid != $USER->id) {
            throw new \moodle_exception('Swapping is only possible with your own items');
        }

        // Merge together items of the same type.
        $items = $this->merge_items($items);
        $myitems = $this->merge_items($myitems);

        // Go through the items and see if they are available in their stash.
        $yourstash = $this->manager->get_all_user_items_in_stash($userid);
        $mystash = $this->manager->get_all_user_items_in_stash($myuserid);
        $this->check_stash_for_item_and_quantity($items, $yourstash);
        $this->check_stash_for_item_and_quantity($myitems, $mystash);

        // Make these proper objects!
        $mydata = [];
        // Loop through the items, not the whole stash!!!
        foreach ($myitems as $myitem) {
            $mydata[] = [
                'useritem' => $mystash[$myitem['id']]->useritem,
                'quantity' => $myitem['quantity']
            ];
        }

        $yourdata = [];
        foreach ($items as $item) {
            $yourdata[] = [
                'useritem' => $yourstash[$item['id']]->useritem,
                'quantity' => $item['quantity']
            ];
        }

        // Everything seems in order. Create a swap request db entry.
        $swap = new swap($this->manager->get_stash()->get_id(), $myuserid, $userid, $mydata, $yourdata, '', 1);
        $swapid = $swap->save();

        $event = \block_stash\event\swap_created::create([
                'context' => $this->manager->get_context(),
                'userid' => $myuserid,
                'courseid' => $this->courseid,
                'objectid' => $swapid,
                'relateduserid' => $userid
            ]
        );
        $event->trigger();
    }

    private function check_stash_for_item_and_quantity($items, $stash) {
        foreach ($items as $key => $value) {
            if (!isset($stash[$key])) {
                throw new moodle_exception('The user does not have this item in their stash');
            }
            if ($stash[$key]->useritem->get_quantity() < $value['quantity']) {
                throw new moodle_exception('User does not have enough of the requested item');
            }
         }
        return true;
    }

    private function merge_items($items) {
        $processed = [];
        foreach ($items as $value) {
            if (isset($processed[$value['id']])) {
                $processed[$value['id']]['quantity'] += $value['quantity'];
            } else {
                $processed[$value['id']]['id'] = $value['id'];
                $processed[$value['id']]['quantity'] = $value['quantity'];
            }
        }
        return $processed;
    }

    public function veryify_my_swap_requests($swapid, $userid) {
        $swap = swap::load($swapid);
        return ($swap->get_receiver_id() == $userid);
    }

    public function veryify_my_swap_offers($swapid, $userid) {
        $swap = swap::load($swapid);
        return ($swap->get_initiator_id() == $userid);
    }

    /**
     * Get users who have the specified item.
     *
     * @param  int $itemid The item id.
     * @param  int $userid The user id.
     * @return array Array of users who have the item.
     */
    public function get_users_with_item(int $itemid, int $userid): array {
        global $DB;

        $fielddata = array_map(function($field) {
            return 'u.' . $field;
        }, \core_user\fields::get_picture_fields());

        $userfields = implode(',', $fielddata);

        $joins = '';
        $where = '';
        $groupparams = [];
        if ($this->manager->group_trading_enabled()) {
            $groupinfo = groups_get_user_groups($this->courseid, $userid);
            $coursecontext = $this->manager->get_context();
            // Groupinfo[0] contains all groups the user is a member of regardless of grouping.
            $groupjoindata = groups_get_members_join($groupinfo[0], 'ui.userid', $coursecontext);
            if (!empty($groupjoindata->wheres)) {
                $where = 'AND ' . $groupjoindata->wheres;
                $joins = $groupjoindata->joins;
                $groupparams = $groupjoindata->params;
            } else {
                return [];
            }
        }

        $sql = "SELECT $userfields
                  FROM {block_stash_user_items} ui
                  JOIN {user} u ON ui.userid = u.id
                  $joins
                 WHERE ui.itemid = :itemid
                   AND ui.quantity <> 0
                   AND ui.userid <> :userid $where";

        $params = array_merge(['itemid' => $itemid, 'userid' => $userid], $groupparams);

        return array_values($DB->get_records_sql($sql, $params));
    }

    public function get_swappable_items() {
        global $DB;

        $sql = "SELECT DISTINCT i.id, i.name
                  FROM {block_stash_items} i
                  JOIN {block_stash_user_items} ui ON ui.itemid = i.id
                  JOIN {block_stash} s ON s.id = i.stashid
                 WHERE s.courseid = :courseid
                   AND (ui.quantity > 0 AND ui.quantity IS NOT NULL)";

        return $DB->get_records_sql($sql, ['courseid' => $this->courseid]);
    }
}
