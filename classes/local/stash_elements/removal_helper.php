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
 * Removal of items element helper
 *
 * @package    block_stash\local\stash_elements
 * @copyright  2023 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\local\stash_elements;

class removal_helper {

    private $manager;

    public function __construct($manager) {
        $this->manager = $manager;
    }

    private function format_url($url): string {
        // TODO check that it is a local url.
        $murl = new \moodle_url($url);
        return $murl->out_as_local_url(false);
    }

    public function handle_form_data($data) {
        global $DB;

        $url = $this->format_url($data->url);
        $dbdata = [
            'stashid' => $this->manager->get_stash()->get_id(),
            'itemid' => $data->itemid,
            'quantity' => $data->quantity,
            'url' => $url,
            'detail' => $data->detail_editor['text'],
            'detailformat' => $data->detail_editor['format']
        ];
        $DB->insert_record('block_stash_remove_items', $dbdata);
    }

    public function get_all_removals() {
        global $DB;

        return $DB->get_records('block_stash_remove_items', ['stashid' => $this->manager->get_stash()->get_id()]);
    }

    public function remove_user_item($removal, $userid) {
        global $DB;

        // Is the item a scarce resource? If so it needs to be made available to everyone again.
        $item = $this->manager->get_item($removal->itemid);
        $itemlimit = $item->get_amountlimit();
        if ($itemlimit) {
            $currentamount = $item->get_currentamount();
            $maxamount = $itemlimit - $currentamount;
            if ($removal->quantity > $maxamount) {
                $item->set_currentamount($maxamount);
            } else {
                $item->set_currentamount($currentamount + $removal->quantity);
            }
            $item->update();
            // The user needs the ability to pick the scarce item back up again.
            // For this the drop pickups need to have their pickup count updated, even though the item could have been acquired in
            // a different way (such as a trade, or the teacher manually giving them one).
            // Not that the drop pickup entry updates the pickup count and lastpickup (not a new entry)
            $sql = "SELECT p.*
                      FROM {block_stash_drop_pickups} p
                      JOIN {block_stash_drops} d ON p.dropid = d.id
                      JOIN {block_stash_items} i ON d.itemid = i.id
                     WHERE i.id = :itemid AND p.userid = :userid";
            $params = ['itemid' => $removal->itemid, 'userid' => $userid];
            $records = $DB->get_records_sql($sql, $params);
            $workingquantity = $removal->quantity;
            foreach ($records as $record) {
                $dp = drop_pickup::get_relation($record->dropid, $userid); // This is dumb. It's another DB query for the same info.
                $pickupcount = $dp->get_pickupcount();

            }
        }

        $useritem = $this->manager->get_user_item($userid, $removal->itemid);
        if ($useritem->get_quantity() <= $removal->quantity) {
            // Remove this entry.
            $useritem->delete();
        } else {
            $useritem->set_quantity($useritem->get_quantity() - $removal->quantity);
            $useritem->update();
        }
    }
}
