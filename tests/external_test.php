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

namespace block_stash;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
/**
 * Tests for the external functions.
 *
 * @package    block_stash
 * @copyright  2026 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_stash\external
 */
final class external_test extends \advanced_testcase {
    /**
     * Create a course with a stash block, two students and an item owned by the second student.
     *
     * @param bool $swapping Whether trading between students is enabled.
     * @param bool $grouponly Whether trading is restricted to group members.
     * @return array The course, the calling student and the target student.
     */
    private function create_fixture(bool $swapping, bool $grouponly): array {
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $dg->get_plugin_generator('block_stash')->create_instance([
            'parentcontextid' => \context_course::instance($course->id)->id,
        ]);
        $manager = manager::get($course->id, true);
        $manager->set_config_entry('useritemswap', $swapping);
        manager::get($course->id, true)->set_config_entry('grouponly', $grouponly);
        $manager = manager::get($course->id, true);

        $caller = $dg->create_and_enrol($course, 'student');
        $target = $dg->create_and_enrol($course, 'student');
        $sg = $dg->get_plugin_generator('block_stash');
        $item = $sg->create_item(['stashid' => $manager->get_stash()->get_id(), 'detail' => 'Detail']);
        $sg->create_user_item(['itemid' => $item->get_id(), 'userid' => $target->id, 'quantity' => 3]);
        return [$course, $caller, $target];
    }

    /**
     * A student must not read another student's stash when trading or a shared group does not allow it.
     */
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    public function test_get_stash_for_user_other_user_not_allowed(): void {
        $this->resetAfterTest();

        [$course, $caller, $target] = $this->create_fixture(false, false);
        $this->setUser($caller);
        try {
            external::get_stash_for_user($course->id, $target->id);
            $this->fail('Expected moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('tradesnotenabled', $e->errorcode);
        }

        [$course, $caller, $target] = $this->create_fixture(true, true);
        $this->getDataGenerator()->create_group_member([
            'userid' => $caller->id,
            'groupid' => $this->getDataGenerator()->create_group(['courseid' => $course->id])->id,
        ]);
        $this->setUser($caller);
        try {
            external::get_stash_for_user($course->id, $target->id);
            $this->fail('Expected moodle_exception');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
    }

    /**
     * A student can read the stash of a group member when trading is enabled.
     */
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    public function test_get_stash_for_user_trade_partner(): void {
        $this->resetAfterTest();

        [$course, $caller, $target] = $this->create_fixture(true, true);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member(['userid' => $caller->id, 'groupid' => $group->id]);
        $this->getDataGenerator()->create_group_member(['userid' => $target->id, 'groupid' => $group->id]);
        $this->setUser($caller);

        $result = external::get_stash_for_user($course->id, $target->id);
        $this->assertCount(1, $result->useritems);
        $this->assertEquals(3, $result->useritems[0]->useritem->quantity);
    }
}
