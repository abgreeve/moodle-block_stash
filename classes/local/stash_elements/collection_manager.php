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
 * Collection manager
 *
 * @package    block_stash\local\stash_elements
 * @copyright  2024 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\local\stash_elements;

use block_stash\local\models\collection;
use block_stash\local\models\collection_item;
use block_stash\local\models\collection_prize;
use block_stash\local\repositories\collection as collection_repository;
use block_stash\external\items_exporter;

class collection_manager {

    private $manager;
    private $collectionrepository;

    public function __construct($manager, collection_repository $collectionrepository) {
        $this->manager = $manager;
        $this->collectionrepository = $collectionrepository;
    }

    public function create_collection($collectiondata) {
        $removal = isset($collectiondata->removeoncompletion) ? 1 : 0;
        $collection = new collection(
            $this->manager->get_stash()->get_id(),
            $collectiondata->name,
            $collectiondata->showtostudent,
            $removal
        );

        $collection->set_id($this->collectionrepository->save($collection));

        foreach ($collectiondata->items as $item) {
            $collectionitem = new collection_item(
                $collection->get_id(),
                $item,
            );
            $this->collectionrepository->save_item($collectionitem);
        }
        if (count($collectiondata->prizes) == 1 && $collectiondata->prizes[0] == 0) {
            return;
        }
        foreach ($collectiondata->prizes as $prize) {
            if ($prize != 0) { // 0 Is none, and we are not saving that.
                // Create a drop for this prize.
                $dropdata = (object) [
                    'id' => 0,
                    'itemid' => $prize,
                    'name' => 'Prize for completing ' . $collection->get_name(),
                    'pickupinterval' => 3600,
                ];
                $drop = $this->manager->create_or_update_drop($dropdata);
                $collectionprize = new collection_prize(
                    $collection->get_id(),
                    $prize,
                    $drop->get_id()
                );
                $this->collectionrepository->save_prize($collectionprize);
            }
        }
    }

    public function get_collection($collectionid) {
        return $this->collectionrepository->load($collectionid);
    }

    public function get_all_collections() {
        return $this->collectionrepository->load_all($this->manager->get_stash()->get_id());
    }

    public function get_collection_items($collectionid) {
        return $this->collectionrepository->get_collection_items_array($collectionid);
    }

    public function get_collection_prizes($collectionid) {
        return $this->collectionrepository->get_collection_prizes_array($collectionid);
    }

    public function organise_items_into_collections($items) {
        $collections = $this->get_all_collections();
        $organised = [];
        $unused = [];
        foreach ($collections as $collection) {
            if ($collection->show_to_student()) {
                // get all collection items.
                $collectionitems = $this->get_collection_items($collection->get_id());
                $useditems = array_filter($items, function($item) use ($collectionitems) {
                    return array_key_exists($item->item->id, $collectionitems) && $item->useritem->quantity != 0;
                });

                if (count($useditems) >= 1) {
                    $a = (object) [
                        'name' => $collection->get_name(),
                        'collected' => count($useditems),
                        'total' => count($collectionitems)
                    ];

                    $thing = (count($useditems) == count($collectionitems));

                    $organised[] = [
                        'collectionid' => $collection->get_id(),
                        'collection' => get_string('collected', 'block_stash', $a),
                        'completed' => $thing,
                        'items' => array_values($useditems)
                    ];
                }
            }
        }
        return $organised;
    }

    public function get_collections_with_items() {

        $richcollectiondata = [];
        $collections = $this->get_all_collections();
        $allitems = $this->get_all_item_details_for_display();

        foreach ($collections as $collection) {
            $cdata = $collection->to_array();
            $cdata['id'] = $collection->get_id();
            $collectionitems = $this->get_collection_items($collection->get_id());
            $itemsdata = $this->find_item_data($collectionitems, $allitems);
            $richcollectiondata[] = [
                'collection' => $cdata,
                'items' => $itemsdata
            ];
        }

        return $richcollectiondata;
    }

    public function get_items_for_collection_display($collectionid) {
        $richcollectiondata = [];
        $allitems = $this->get_all_item_details_for_display();

        $collectionitems = $this->get_collection_items($collectionid);
        return $this->find_item_data($collectionitems, $allitems);
    }

    private function find_item_data($items, $allitems) {
        $data = [];
        foreach ($allitems->items as $item) {
            if (isset($items[$item->id])) {
                $data[] = $item;
            }
        }
        return $data;
    }

    private function get_all_item_details_for_display() {
        global $PAGE;

        $output = $PAGE->get_renderer('block_stash');
        $allitems = $this->manager->get_items();
        $exporter = new items_exporter($allitems, ['context' => $this->manager->get_context()]);
        return $exporter->export($output);

    }

    public function delete_collection($collection) {
        $prizes = $this->get_collection_prizes($collection->get_id());
        foreach ($prizes as $prize) {
            $this->manager->delete_drop($prize->dropid); // Deletes user drops and drops.
        }
        $this->collectionrepository->delete_prizes($collection->get_id());
        $this->collectionrepository->delete_items($collection->get_id());
        $this->collectionrepository->delete($collection->get_id());
    }

    // This function needs refactoring. Too many loops.
    public function get_collection_completion_with_item($userid, $itemid) {
        $result = [];
        $collections = $this->collectionrepository->get_collections_with_prizes($this->manager->get_stash()->get_id());
        foreach ($collections as $collection) {
            $collection['items'] = $this->get_collection_items($collection['collection']->get_id());

            if (array_key_exists($itemid, $collection['items'])) {
                $result[$collection['collection']->get_id()] = $collection;
            }
        }
        if (empty($result)) {
            return [];
        }
        // We have a list of potential collections that may have been completed. Now to query if the user has all of the items in these collections.
        $useritems = $this->manager->get_all_user_items_in_stash($userid);

        $verdict = [];

        foreach ($result as $collection) {
            $collectionid = $collection['collection']->get_id();
            foreach ($collection['items'] as $item) {
                if (array_key_exists($item->itemid, $useritems)) {
                    if ($useritems[$item->itemid]->useritem->get_quantity() > 0) {
                        $verdict[$collectionid]['items'][$item->itemid] = $item;
                    }
                }
            }
            if (isset($verdict[$collectionid])) {
                if (count($collection['items']) == count($verdict[$collectionid]['items'])) {
                    $verdict[$collectionid]['completed'] = true;
                }
            }
        }

        $yetanotherarray = [];
        foreach ($verdict as $key => $data) {
            if (isset($data['completed'])) {
                $yetanotherarray[] = $result[$key];
            }
        }
        return $yetanotherarray;
    }

    public static function init($manager) {
        $repository = new collection_repository();
        return new self($manager, $repository);
    }
}
