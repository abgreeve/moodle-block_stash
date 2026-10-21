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
 * Tests that trade items and removals can only be deleted in the caller's course.
 *
 * @package    block_stash
 * @copyright  2026 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_stash\manager::get_trade_item
 * @covers     \block_stash\external\delete_removal
 */
final class delete_removal_test extends \advanced_testcase {
    /**
     * Create a course with a stash block, a trade item and a removal configuration.
     *
     * @return array [course, tradeitem id, removal id]
     */
    private function create_course_with_records(): array {
        global $DB;
        $dg = $this->getDataGenerator();
        $sg = $dg->get_plugin_generator('block_stash');
        $course = $dg->create_course();
        $sg->create_instance(['parentcontextid' => \context_course::instance($course->id)->id]);
        $stash = \block_stash\manager::get($course->id, true)->get_stash();
        $item = $sg->create_item(['stash' => $stash]);
        $trade = new \block_stash\trade(null, (object) ['stashid' => $stash->get_id(), 'name' => 'Trade']);
        $trade->create();
        $tradeitem = new \block_stash\tradeitems(null, (object) ['tradeid' => $trade->get_id(), 'itemid' => $item->get_id()]);
        $tradeitem->create();
        $removalid = $DB->insert_record('block_stash_removal', [
            'stashid' => $stash->get_id(), 'modulename' => 'quiz', 'cmid' => 0, 'detail' => '', 'detailformat' => FORMAT_HTML,
        ]);
        return [$course, $tradeitem->get_id(), $removalid];
    }

    /**
     * A teacher must not reach trade items or removals of another course.
     */
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    public function test_foreign_records_are_rejected(): void {
        global $DB;
        $this->resetAfterTest();
        [$course] = $this->create_course_with_records();
        [, $foreigntradeitemid, $foreignremovalid] = $this->create_course_with_records();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        try {
            \block_stash\manager::get($course->id)->get_trade_item($foreigntradeitemid);
            $this->fail('Expected coding_exception');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('Unexpected trade item ID', $e->getMessage());
        }
        try {
            delete_removal::execute($course->id, $foreignremovalid);
            $this->fail('Expected moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidaccess', $e->errorcode);
        }
        $this->assertTrue($DB->record_exists('block_stash_trade_items', ['id' => $foreigntradeitemid]));
        $this->assertTrue($DB->record_exists('block_stash_removal', ['id' => $foreignremovalid]));
    }

    /**
     * A teacher can still reach trade items and delete removals of their own course.
     */
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    public function test_own_records_are_accepted(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $tradeitemid, $removalid] = $this->create_course_with_records();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $this->assertSame($tradeitemid, \block_stash\manager::get($course->id)->get_trade_item($tradeitemid)->get_id());
        $this->assertTrue(delete_removal::execute($course->id, $removalid));
        $this->assertFalse($DB->record_exists('block_stash_removal', ['id' => $removalid]));
    }
}
