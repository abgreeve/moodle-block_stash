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

namespace block_stash\local\repositories;

use block_stash\local\models\collection;
use block_stash\local\models\collection_item;
use block_stash\local\models\collection_prize;


class collection {



    public function save(collection $collection) {
        global $DB;

        return $DB->insert_record('block_stash_collections', $collection->to_array());
    }

    public function save_item(collection_item $collectionitem) {
        global $DB;

        return $DB->insert_record('block_stash_collection_items', $collectionitem->to_array());
    }

    public function save_prize(collection_prize $collectionprize) {
        global $DB;

        return $DB->insert_record('block_stash_collection_prizes', $collectionprize->to_array());
    }

    // public function save_collection_item()

    public function load(int $id): collection {

    }

    public function load_all(): array {
        global $DB;



    }



}
