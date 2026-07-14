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
 * Block backup steplib.
 *
 * @package    block_stash
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_stash\item;
use block_stash\drop;
use block_stash\drop_pickup;
use block_stash\drop_pool_item;
use block_stash\stash;
use block_stash\user_item;
use block_stash\trade;
use block_stash\tradeitems;

/**
 * Block backup structure step class.
 *
 * @package    block_stash
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_stash_block_structure_step extends backup_block_structure_step {

    /**
     * Define structure.
     */
    protected function define_structure() {
        global $DB;

        $userinfo = $this->get_setting_value('users');

        // Define each element separated.
        $stash = new backup_nested_element('stash', ['id'], ['name']);
        $items = new backup_nested_element('items');
        $item = new backup_nested_element('item', ['id'], ['name', 'maxnumber', 'detail', 'detailformat', 'amountlimit', 'currentamount']);
        $drops = new backup_nested_element('drops');
        $drop = new backup_nested_element('drop', ['id'], ['stashid', 'name', 'maxpickup', 'pickupinterval', 'hashcode', 'droptype']);
        $pickups = new backup_nested_element('pickups');
        $pickup = new backup_nested_element('pickup', ['id'], ['userid', 'pickupcount', 'lastpickup']);
        $useritems = new backup_nested_element('useritems');
        $useritem = new backup_nested_element('useritem', ['id'], ['userid', 'quantity']);

        // Random drops. Unlike fixed drops, these are not attached to any item, so they
        // are backed up directly under the stash rather than nested inside an item.
        $randomdrops = new backup_nested_element('randomdrops');
        $randomdrop = new backup_nested_element('randomdrop', ['id'],
            ['stashid', 'name', 'maxpickup', 'pickupinterval', 'hashcode', 'droptype']);
        // Nested element names must be unique across the whole backup structure tree,
        // so these can't reuse the 'pickups'/'pickup' tags from the fixed drop branch.
        $randompickups = new backup_nested_element('randompickups');
        $randompickup = new backup_nested_element('randompickup', ['id'], ['userid', 'pickupcount', 'lastpickup']);
        $poolitems = new backup_nested_element('poolitems');
        $poolitem = new backup_nested_element('poolitem', ['id'], ['itemid', 'weight']);

        // Here we go.
        $trades = new backup_nested_element('trades');
        $trade = new backup_nested_element('trade', ['id'], ['name', 'losstitle', 'gaintitle', 'hashcode']);
        $tradeitems = new backup_nested_element('tradeitems');
        $tradeitem = new backup_nested_element('tradeitem', ['id'], ['itemid', 'quantity', 'gainloss']);

        // Removals.
        $removals = new backup_nested_element('removals');
        $removal = new backup_nested_element('removal', ['id'], ['shashid', 'modulename', 'cmid', 'detail', 'detailformat']);
        $removaldetails = new backup_nested_element('removaldetails');
        $removaldetail = new backup_nested_element('removaldetail', ['id'], ['removalid', 'itemid', 'quantity']);

        // Prepare the structure.
        $wrapper = $this->prepare_block_structure($stash);

        $stash->add_child($items);
        $items->add_child($item);
        $item->add_child($drops);
        $drops->add_child($drop);
        $pickups->add_child($pickup);
        $useritems->add_child($useritem);

        $stash->add_child($randomdrops);
        $randomdrops->add_child($randomdrop);
        $randomdrop->add_child($poolitems);
        $poolitems->add_child($poolitem);
        $randompickups->add_child($randompickup);

        $stash->add_child($trades);
        $trades->add_child($trade);
        $trade->add_child($tradeitems);
        $tradeitems->add_child($tradeitem);

        $stash->add_child($removals);
        $removals->add_child($removal);
        $removal->add_child($removaldetails);
        $removaldetails->add_child($removaldetail);

        // Define sources.
        $stash->set_source_table(stash::TABLE, array('courseid' => backup::VAR_COURSEID));
        $item->set_source_table(item::TABLE, array('stashid' => backup::VAR_PARENTID));
        $drop->set_source_table(drop::TABLE, array('itemid' => backup::VAR_PARENTID));
        $trade->set_source_table(trade::TABLE, array('stashid' => backup::VAR_PARENTID));
        $tradeitem->set_source_table(tradeitems::TABLE, array('tradeid' => backup::VAR_PARENTID));
        $removal->set_source_table('block_stash_removal', ['stashid' => backup::VAR_PARENTID]);
        $removaldetail->set_source_table('block_stash_remove_items', ['removalid' => backup::VAR_PARENTID]);

        // Random drops are stashid-owned rather than item-owned, so they need their own
        // filtered query rather than a plain set_source_table() keyed on itemid.
        $randomdrop->set_source_sql('
                SELECT *
                  FROM {block_stash_drops}
                 WHERE stashid = ?
                   AND droptype = ?',
            [backup::VAR_PARENTID, backup_helper::is_sqlparam(drop::TYPE_RANDOM)]);
        $poolitem->set_source_table(drop_pool_item::TABLE, array('dropid' => backup::VAR_PARENTID));

        // Define user data.
        if ($userinfo) {
            $item->add_child($useritems);
            $drop->add_child($pickups);
            $randomdrop->add_child($randompickups);

            $useritem->set_source_table(user_item::TABLE, array('itemid' => backup::VAR_PARENTID));
            $pickup->set_source_table(drop_pickup::TABLE, array('dropid' => backup::VAR_PARENTID));
            $randompickup->set_source_table(drop_pickup::TABLE, array('dropid' => backup::VAR_PARENTID));
        }

        // Annotations.
        $pickup->annotate_ids('user', 'userid');
        $randompickup->annotate_ids('user', 'userid');
        $useritem->annotate_ids('user', 'userid');
        $item->annotate_files('block_stash', 'item', 'id', context_course::instance($this->get_courseid())->id);
        $item->annotate_files('block_stash', 'detail', 'id', context_course::instance($this->get_courseid())->id);
        $randomdrop->annotate_files('block_stash', drop::FILEAREA_IMAGE, 'id', context_course::instance($this->get_courseid())->id);

        // Return the root element.
        return $wrapper;
    }
}
