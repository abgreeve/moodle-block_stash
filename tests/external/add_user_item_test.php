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

use block_stash\user_item;

/**
 * Tests for the add_user_item external function.
 *
 * @package    block_stash
 * @copyright  2026 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_stash\external\add_user_item
 */
final class add_user_item_test extends \advanced_testcase {
    /**
     * Create a course with a stash item held by a student.
     *
     * @return array [course, item, holder]
     */
    private function create_fixture(): array {
        $dg = $this->getDataGenerator();
        $sg = $dg->get_plugin_generator('block_stash');
        $course = $dg->create_course();
        $sg->create_instance(['parentcontextid' => \context_course::instance($course->id)->id]);
        \block_stash\manager::get($course->id, true);
        $stash = $sg->create_stash(['courseid' => $course->id]);
        $item = $sg->create_item(['stash' => $stash]);
        $holder = $dg->create_and_enrol($course, 'student');
        $sg->create_user_item(['item' => $item, 'userid' => $holder->id, 'quantity' => 5]);
        return [$course, $item, $holder];
    }

    /**
     * A student must not be able to delete another user's item with quantity 0.
     *
     * @covers \block_stash\manager::reset_user_item
     */
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    public function test_reset_user_item_requires_manage_capability(): void {
        $this->resetAfterTest();
        [$course, $item, $holder] = $this->create_fixture();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        try {
            add_user_item::add_user_item($course->id, $item->get_id(), $holder->id, 0);
            $this->fail('Expected required_capability_exception');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        $this->assertEquals(5, user_item::get_record(['itemid' => $item->get_id(), 'userid' => $holder->id])->get_quantity());
    }

    /**
     * A teacher can still reset a user's item with quantity 0.
     *
     * @covers \block_stash\manager::reset_user_item
     */
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    public function test_reset_user_item_as_teacher(): void {
        $this->resetAfterTest();
        [$course, $item, $holder] = $this->create_fixture();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $this->assertTrue(add_user_item::add_user_item($course->id, $item->get_id(), $holder->id, 0));
        $this->assertFalse(user_item::get_record(['itemid' => $item->get_id(), 'userid' => $holder->id]));
    }
}
