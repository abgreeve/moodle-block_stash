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
 * Random drop pool validator tests.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_stash\drop;
use block_stash\drop_pool_item;
use block_stash\random_drop_pool_validator;

/**
 * Random drop pool validator testcase class.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_stash_random_drop_pool_validator_testcase extends advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    public function test_random_pool_with_exactly_two_items_is_valid(): void {
        [$drop, $items] = $this->create_random_drop_with_pool_items(2);
        foreach ($items as $item) {
            $this->create_stored_pool_item($drop, $item->get_id());
        }

        $this->assertTrue($drop->is_valid_random_pool());
        $this->assertSame([], $drop->get_random_pool_validation_errors());
    }

    public function test_random_pool_with_twenty_items_is_valid(): void {
        [$drop, $items] = $this->create_random_drop_with_pool_items(20);
        $poolitems = array_map(fn($item) => $this->make_pool_item($drop, $item->get_id()), $items);

        $validator = new random_drop_pool_validator();
        $this->assertTrue($validator->is_valid($drop, $poolitems));
        $this->assertSame([], $validator->get_errors($drop, $poolitems));
    }

    public function test_random_pool_with_same_stash_non_scarce_items_is_valid(): void {
        [$drop, $items] = $this->create_random_drop_with_pool_items(3);
        $poolitems = array_map(fn($item) => $this->make_pool_item($drop, $item->get_id()), $items);

        $this->assertTrue((new random_drop_pool_validator())->is_valid($drop, $poolitems));
    }

    public function test_random_pool_with_zero_items_is_invalid(): void {
        [$drop] = $this->create_random_drop_with_pool_items(0);

        $this->assertValidationErrors(['minitems'], $drop, []);
    }

    public function test_random_pool_with_one_item_is_invalid(): void {
        [$drop, $items] = $this->create_random_drop_with_pool_items(1);

        $this->assertValidationErrors(['minitems'], $drop, [
            $this->make_pool_item($drop, $items[0]->get_id()),
        ]);
    }

    public function test_random_pool_with_more_than_twenty_items_is_invalid(): void {
        [$drop, $items] = $this->create_random_drop_with_pool_items(21);
        $poolitems = array_map(fn($item) => $this->make_pool_item($drop, $item->get_id()), $items);

        $this->assertValidationErrors(['maxitems'], $drop, $poolitems);
    }

    public function test_random_pool_with_duplicate_items_is_invalid(): void {
        [$drop, $items] = $this->create_random_drop_with_pool_items(2);
        $poolitems = [
            $this->make_pool_item($drop, $items[0]->get_id()),
            $this->make_pool_item($drop, $items[0]->get_id()),
        ];

        $this->assertValidationErrors(['duplicateitems'], $drop, $poolitems);
    }

    public function test_random_pool_with_missing_item_is_invalid(): void {
        [$drop, $items] = $this->create_random_drop_with_pool_items(2);
        $poolitems = [
            $this->make_pool_item($drop, $items[0]->get_id()),
            $this->make_pool_item($drop, 999999),
        ];

        $this->assertValidationErrors(['missingitems'], $drop, $poolitems);
    }

    public function test_random_pool_with_item_from_another_stash_is_invalid(): void {
        [$drop, $items] = $this->create_random_drop_with_pool_items(1);
        $otheritem = $this->create_item_in_new_stash();
        $poolitems = [
            $this->make_pool_item($drop, $items[0]->get_id()),
            $this->make_pool_item($drop, $otheritem->get_id()),
        ];

        $this->assertValidationErrors(['stashmismatch'], $drop, $poolitems);
    }

    public function test_random_pool_with_scarce_item_is_invalid(): void {
        [$drop, $items] = $this->create_random_drop_with_pool_items(1);
        $scarceitem = $this->create_item_for_drop_stash($drop, ['amountlimit' => 5, 'currentamount' => 5]);
        $poolitems = [
            $this->make_pool_item($drop, $items[0]->get_id()),
            $this->make_pool_item($drop, $scarceitem->get_id()),
        ];

        $this->assertValidationErrors(['scarceitems'], $drop, $poolitems);
    }

    public function test_random_pool_validation_does_not_apply_to_fixed_drops(): void {
        $drop = $this->create_drop(drop::TYPE_FIXED);
        $validator = new random_drop_pool_validator();

        $this->assertFalse($validator->applies_to($drop));
        $this->assertFalse($validator->is_valid($drop));
        $this->assertSame([], $validator->get_errors($drop));
        $this->assertFalse($drop->is_valid_random_pool());
        $this->assertSame([], $drop->get_random_pool_validation_errors());
    }

    private function assertValidationErrors(array $expectedkeys, drop $drop, array $poolitems): void {
        $validator = new random_drop_pool_validator();

        $this->assertFalse($validator->is_valid($drop, $poolitems));
        $errors = $validator->get_errors($drop, $poolitems);
        foreach ($expectedkeys as $key) {
            $this->assertArrayHasKey($key, $errors);
        }
    }

    /**
     * Create a random drop and additional candidate pool items in the same stash.
     *
     * @param int $poolitemcount Number of candidate pool items to create.
     * @return array{0: drop, 1: array}
     */
    private function create_random_drop_with_pool_items(int $poolitemcount): array {
        $drop = $this->create_drop(drop::TYPE_RANDOM);
        $items = [];
        for ($i = 0; $i < $poolitemcount; $i++) {
            $items[] = $this->create_item_for_drop_stash($drop);
        }

        return [$drop, $items];
    }

    private function create_drop(int $droptype): drop {
        $generator = $this->getDataGenerator()->get_plugin_generator('block_stash');
        $course = $this->getDataGenerator()->create_course();
        $stash = $generator->create_stash(['courseid' => $course->id]);
        $dropitem = $generator->create_item(['stash' => $stash]);

        return $generator->create_drop([
            'item' => $dropitem,
            'droptype' => $droptype,
        ]);
    }

    private function create_item_for_drop_stash(drop $drop, array $record = []): \block_stash\item {
        $generator = $this->getDataGenerator()->get_plugin_generator('block_stash');
        $dropitem = new \block_stash\item($drop->get_itemid());

        return $generator->create_item($record + ['stashid' => $dropitem->get_stashid()]);
    }

    private function create_item_in_new_stash(): \block_stash\item {
        $generator = $this->getDataGenerator()->get_plugin_generator('block_stash');
        $course = $this->getDataGenerator()->create_course();
        $stash = $generator->create_stash(['courseid' => $course->id]);

        return $generator->create_item(['stash' => $stash]);
    }

    private function create_stored_pool_item(drop $drop, int $itemid): drop_pool_item {
        $poolitem = $this->make_pool_item($drop, $itemid);
        $poolitem->create();
        return $poolitem;
    }

    private function make_pool_item(drop $drop, int $itemid, int $weight = 1): drop_pool_item {
        return new drop_pool_item(null, (object) [
            'dropid' => $drop->get_id(),
            'itemid' => $itemid,
            'weight' => $weight,
        ]);
    }
}
