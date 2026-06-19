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
 * Random drop pool selector tests.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_stash\drop;
use block_stash\drop_pool_item;
use block_stash\random_drop_pool_selector;

/**
 * Random drop pool selector testcase class.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_stash_random_drop_pool_selector_testcase extends advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    public function test_selecting_from_single_entry_pool_returns_the_provided_entry(): void {
        $drop = $this->create_random_drop();
        $poolitem = $this->make_pool_item($drop, 101, 5);

        $selected = (new random_drop_pool_selector())->select([$poolitem]);

        $this->assertSame($poolitem, $selected);
    }

    public function test_zero_weight_is_rejected(): void {
        $drop = $this->create_random_drop();
        $poolitem = $this->make_pool_item($drop, 101, 0);

        $this->expectException(coding_exception::class);
        (new random_drop_pool_selector())->select([$poolitem]);
    }

    public function test_negative_weight_is_rejected(): void {
        $drop = $this->create_random_drop();
        $poolitem = $this->make_pool_item($drop, 101, -1);

        $this->expectException(coding_exception::class);
        (new random_drop_pool_selector())->select([$poolitem]);
    }

    public function test_empty_pool_is_rejected(): void {
        $this->expectException(coding_exception::class);
        (new random_drop_pool_selector())->select([]);
    }

    public function test_weighted_selection_prefers_higher_weight_entry(): void {
        $drop = $this->create_random_drop();
        $low = $this->make_pool_item($drop, 101, 1);
        $high = $this->make_pool_item($drop, 102, 10);
        $selector = new random_drop_pool_selector();
        $counts = [
            $low->get_itemid() => 0,
            $high->get_itemid() => 0,
        ];

        for ($i = 0; $i < 4000; $i++) {
            $selected = $selector->select([$low, $high]);
            $counts[$selected->get_itemid()]++;
        }

        $this->assertGreaterThan($counts[$low->get_itemid()] * 5, $counts[$high->get_itemid()]);
        $this->assertGreaterThan(3000, $counts[$high->get_itemid()]);
    }

    public function test_selector_does_not_mutate_pool_entries(): void {
        $drop = $this->create_random_drop();
        $poolitems = [
            $this->make_pool_item($drop, 101, 2),
            $this->make_pool_item($drop, 102, 7),
        ];
        $before = array_map(fn($poolitem) => clone $poolitem->to_record(), $poolitems);

        (new random_drop_pool_selector())->select($poolitems);

        foreach ($poolitems as $index => $poolitem) {
            $this->assertEquals($before[$index], $poolitem->to_record());
        }
    }

    private function create_random_drop(): drop {
        $generator = $this->getDataGenerator()->get_plugin_generator('block_stash');
        $course = $this->getDataGenerator()->create_course();
        $stash = $generator->create_stash(['courseid' => $course->id]);
        $dropitem = $generator->create_item(['stash' => $stash]);

        return $generator->create_drop([
            'item' => $dropitem,
            'droptype' => drop::TYPE_RANDOM,
        ]);
    }

    private function make_pool_item(drop $drop, int $itemid, int $weight): drop_pool_item {
        return new drop_pool_item(null, (object) [
            'dropid' => $drop->get_id(),
            'itemid' => $itemid,
            'weight' => $weight,
        ]);
    }
}
