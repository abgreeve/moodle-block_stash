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
 * Random drop pool entry tests.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_stash\drop;
use block_stash\drop_pool_item;

/**
 * Random drop pool entry testcase class.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_stash_drop_pool_item_testcase extends advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    public function test_drop_pool_item_can_be_created_and_reloaded(): void {
        [$drop, $item] = $this->create_drop_and_item_pair();

        $dropitem = new drop_pool_item(null, (object) [
            'dropid' => $drop->get_id(),
            'itemid' => $item->get_id(),
            'weight' => 7,
        ]);
        $dropitem->create();

        $this->assertGreaterThan(0, $dropitem->get_id());
        $this->assertSame(1, drop_pool_item::count_records(['dropid' => $drop->get_id()]));

        $reloaded = new drop_pool_item($dropitem->get_id());
        $this->assertSame($drop->get_id(), $reloaded->get_dropid());
        $this->assertSame($item->get_id(), $reloaded->get_itemid());
        $this->assertSame(7, $reloaded->get_weight());
    }

    public function test_drop_pool_item_can_be_updated(): void {
        [$drop, $item] = $this->create_drop_and_item_pair();

        $dropitem = new drop_pool_item(null, (object) [
            'dropid' => $drop->get_id(),
            'itemid' => $item->get_id(),
            'weight' => 3,
        ]);
        $dropitem->create();
        $createdrecord = $dropitem->to_record();

        $dropitem->set_weight(9);
        $dropitem->update();

        $reloaded = new drop_pool_item($dropitem->get_id());
        $this->assertSame(9, $reloaded->get_weight());
        $this->assertSame($createdrecord->timecreated, $reloaded->to_record()->timecreated);
        $this->assertGreaterThanOrEqual($createdrecord->timecreated, $reloaded->to_record()->timemodified);
    }

    public function test_drop_pool_item_uniqueness_is_enforced_by_database(): void {
        [$drop, $item] = $this->create_drop_and_item_pair();

        $first = new drop_pool_item(null, (object) [
            'dropid' => $drop->get_id(),
            'itemid' => $item->get_id(),
            'weight' => 1,
        ]);
        $first->create();

        $this->expectException(dml_write_exception::class);

        $duplicate = new drop_pool_item(null, (object) [
            'dropid' => $drop->get_id(),
            'itemid' => $item->get_id(),
            'weight' => 5,
        ]);
        $duplicate->create();
    }

    /**
     * Create one drop and one separate item in the same stash.
     *
     * @return array{0: drop, 1: \block_stash\item}
     */
    private function create_drop_and_item_pair(): array {
        $generator = $this->getDataGenerator()->get_plugin_generator('block_stash');
        $course = $this->getDataGenerator()->create_course();
        $stash = $generator->create_stash(['courseid' => $course->id]);
        $dropitem = $generator->create_item(['stash' => $stash]);
        $poolitem = $generator->create_item(['stash' => $stash]);
        $drop = $generator->create_drop([
            'item' => $dropitem,
            'droptype' => drop::TYPE_RANDOM,
        ]);

        return [$drop, $poolitem];
    }
}
