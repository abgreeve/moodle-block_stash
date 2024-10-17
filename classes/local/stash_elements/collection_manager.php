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

use block_stash\local\models\collection;
use block_stash\local\models\collection_item;
use block_stash\local\models\collection_prize;
use block_stash\local\repositories\collection as collection_repository;


class collection_manager {

    private $manager;

    public function __construct($manager) {
        $this->manager = $manager;
    }

    public function create_collection($collectiondata) {
        $collectionrepository = new collection_repository();
        $removal = isset($collectiondata->removeoncompletion) ? 1 : 0;
        $collection = new collection(
            $this->manager->get_stash()->get_id(),
            $collectiondata->name,
            $collectiondata->showtostudent,
            $removal
        );

        $collection->set_id($collectionrepository->save($collection));

        // foreach collection items
        foreach ($collectiondata->items as $item) {
            $collectionitem = new collection_item(
                $collection->get_id(),
                $item,
            );
            $collectionrepository->save_item($collectionitem);
        }
        // foreach collection prizes
        if (count($collectiondata->prizes) == 1 && $collectiondata->prizes[0] == 0) {
            return;
        }
        foreach ($collectiondata->prizes as $prize) {
            if ($prize != 0) { // 0 Is none, and we are not saving that.
                $collectionprize = new collection_prize(
                    $collection->get_id(),
                    $prize,
                );
                $collectionrepository->save_prize($collectionprize);
            }
        }
    }


    public function get_all_collections() {

    }

    public function get_collection($collectionid) {


    }


}
