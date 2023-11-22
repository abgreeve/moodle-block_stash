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
 * Swap class
 *
 * @package    block_stash
 * @copyright  2020 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash;

/**
 * Swap class
 *
 * @package    block_stash
 * @copyright  2020 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class swap {

    public const TABLE = 'block_stash_swap';
    public const TABLE_DETAIL = 'block_stash_swap_detail';

    public const BLOCK_STASH_SWAP_APPROVE = 1;
    public const BLOCK_STASH_SWAP_DECLINE = 2;
    public const BLOCK_STASH_SWAP_COMPLETED = 3;
    public const BLOCK_STASH_SWAP_VIEWED = 4;

    private $id;
    private $stashid;
    private $initiator;
    private $receiver;
    /** @var array $initatoritems an array of items */
    private $initiatoritems;
    /** @var array $receiveritems an array of items */
    private $receiveritems;
    private $message;
    private $messageformat;
    private $status;

    public function __construct(int $stashid, int $initiator, int $receiver, array $initiatoritems = [], array $receiveritems = [],
            string $message = '', int $messageformat = 1, int $status = null) {
        $this->stashid = $stashid;
        $this->initiator = $initiator;
        $this->receiver = $receiver;
        $this->initiatoritems = $initiatoritems;
        $this->receiveritems = $receiveritems;
        $this->message = $message;
        $this->messageformat = $messageformat;
        $this->status = $status;
    }

    /**
     * Set the swap id.
     *
     * @param int $id The swap id.
     */
    public function set_id($id) : void {
        $this->id = $id;
    }

    /**
     * Set the status of the swap.
     *
     * @param int $status The status of the swap.
     */
    public function set_status($status) : void {
        $this->status = $status;
    }

    public function get_initiator_items() : array {
        return $this->initiatoritems;
    }

    public function get_receiver_items() : array {
        return $this->receiveritems;
    }

    public function get_receiver_id() : int {
        return $this->receiver;
    }

    public function get_initiator_id() : int {
        return $this->initiator;
    }

    /**
     * Save the swap.
     *
     * @return int The swap id.
     */
    public function save() : int {
        global $DB;
        // Save swap.
        $rawdata = (object) [
            'stashid' => $this->stashid,
            'initiator' => $this->initiator,
            'receiver' => $this->receiver,
            'message' => $this->message,
            'messageformat' => $this->messageformat,
            'timecreated' => time(),
            'status' => $this->status
        ];

        if (isset($this->id)) {
            $rawdata->id = $this->id;
            unset($rawdata->timecreated);
            $DB->update_record(self::TABLE, $rawdata);
        } else {
            $swapid = $DB->insert_record(self::TABLE, $rawdata);
            // Save swap detail.
            // Quick and dirty.
            $this->save_detail($this->initiatoritems, $swapid);
            $this->save_detail($this->receiveritems, $swapid);
        }
        return isset($swapid) ? $swapid : $this->id;

    }

    /**
     * Save the swap details.
     *
     * @param array $swapitems The items to save.
     * @param int $swapid The swap id.
     */
    private function save_detail(array $swapitems, int $swapid) : void {
        global $DB;
        foreach ($swapitems as $items) {
            $data = (object) [
                'swapid' => $swapid,
                'useritemid' => $items['useritem']->get_id(),
                'quantity' => $items['quantity']
            ];
            $DB->insert_record(self::TABLE_DETAIL, $data);
        }
    }

    /**
     * Delete the whole swap (including details).
     */
    public function delete() : void {
        global $DB;

        $DB->delete_records(self::TABLE_DETAIL, ['swapid' => $this->id]);
        $DB->delete_records(self::TABLE, ['id' => $this->id]);
    }

    /**
     * Load the swap from an id.
     *
     * @param int $swapid The swap id.
     * @param bool $detailsaswell Whether to load the details as well.
     * @return \block_stash\swap
     */
    public static function load(int $swapid, bool $detailsaswell = false) : swap {
        global $DB;
        $record = $DB->get_record(self::TABLE, ['id' => $swapid]);
        $initiatoritems = [];
        $receiveritems = [];
        if ($detailsaswell) {
            $sql = "SELECT sd.id, sd.quantity, ui.id as useritemid, ui.itemid, ui.userid, ui.quantity as uiquantity, ui.timecreated,
                    ui.timemodified, ui.version
                    FROM {" . self::TABLE_DETAIL. "} sd
                    LEFT JOIN {block_stash_user_items} ui ON sd.useritemid = ui.id
                    WHERE sd.swapid = :swapid";

            $params = ['swapid' => $swapid];
            $records = $DB->get_records_sql($sql, $params);
            foreach ($records as $detail) {
                $data = (object) [
                    'id' => $detail->useritemid,
                    'itemid' => $detail->itemid,
                    'userid' => $detail->userid,
                    'quantity' => $detail->uiquantity,
                    'timecreated' => $detail->timecreated,
                    'timemodified' => $detail->timemodified,
                    'version' => $detail->version
                ];
                $useritem = new user_item($detail->useritemid, $data);
                if ($record->initiator == $detail->userid) {
                    $initiatoritems[] = ['useritem' => $useritem, 'quantity' => $detail->quantity];
                }
                if ($record->receiver == $detail->userid) {
                    $receiveritems[] = ['useritem' => $useritem, 'quantity' => $detail->quantity];
                }
            }
        }

        $swap = new swap($record->stashid, $record->initiator, $record->receiver, $initiatoritems, $receiveritems, $record->message,
            $record->messageformat);
        $swap->set_id($swapid);
        return $swap;
    }

}
