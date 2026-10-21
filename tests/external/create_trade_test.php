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

namespace block_stash\external;

/**
 * Tests that create_trade and add_drop stay inside the caller's course.
 *
 * @package    block_stash
 * @copyright  2026 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_stash\external\create_trade
 * @covers     \block_stash\external\add_drop
 */
final class create_trade_test extends \advanced_testcase {
    /**
     * Create a course with a stash block and one item.
     *
     * @return array [course, stash, item]
     */
    private function create_course_with_item(): array {
        $dg = $this->getDataGenerator();
        $sg = $dg->get_plugin_generator('block_stash');
        $course = $dg->create_course();
        $sg->create_instance(['parentcontextid' => \context_course::instance($course->id)->id]);
        $stash = \block_stash\manager::get($course->id, true)->get_stash();
        $item = $sg->create_item(['stash' => $stash]);
        return [$course, $stash, $item];
    }

    /**
     * A teacher must not create trades or drops in another course's stash.
     */
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    public function test_foreign_stash_and_item_are_rejected(): void {
        global $DB;
        $this->resetAfterTest();
        [$course] = $this->create_course_with_item();
        [, $foreignstash, $foreignitem] = $this->create_course_with_item();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $items = [['itemid' => $foreignitem->get_id(), 'quantity' => 99]];

        try {
            create_trade::execute($course->id, $foreignstash->get_id(), 'aaaaaa', 'Trade', '', '', $items, []);
            $this->fail('Expected invalid_parameter_exception');
        } catch (\invalid_parameter_exception $e) {
            $this->assertSame('invalidparameter', $e->errorcode);
        }
        try {
            add_drop::execute($course->id, $foreignitem->get_id(), 'Drop', 0, 0);
            $this->fail('Expected invalid_parameter_exception');
        } catch (\invalid_parameter_exception $e) {
            $this->assertSame('invalidparameter', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('block_stash_trade'));
        $this->assertSame(0, $DB->count_records('block_stash_drops'));
    }

    /**
     * A teacher can still create trades and drops in the own course's stash.
     */
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    public function test_own_stash_and_item_are_accepted(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $stash, $item] = $this->create_course_with_item();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));
        $items = [['itemid' => $item->get_id(), 'quantity' => 2]];

        create_trade::execute($course->id, $stash->get_id(), 'aaaaaa', 'Trade', '', '', $items, []);
        add_drop::execute($course->id, $item->get_id(), 'Drop', 0, 0);

        $this->assertSame(1, $DB->count_records('block_stash_trade', ['stashid' => $stash->get_id()]));
        $this->assertSame(1, $DB->count_records('block_stash_trade_items', ['itemid' => $item->get_id()]));
        $this->assertSame(1, $DB->count_records('block_stash_drops', ['itemid' => $item->get_id()]));
    }
}
