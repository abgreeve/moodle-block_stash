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

/**
 * Tests for the manager class.
 *
 * @package    block_stash
 * @copyright  2026 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_stash\manager
 */
final class manager_test extends \advanced_testcase {
    /**
     * Create a course with a stash block and return its manager.
     *
     * @return manager
     */
    private function create_manager(): manager {
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $dg->get_plugin_generator('block_stash')->create_instance([
            'parentcontextid' => \context_course::instance($course->id)->id,
        ]);
        return manager::get($course->id, true);
    }

    /**
     * Deleting board settings must only affect the stash of the own course.
     */
    public function test_delete_leaderboard_settings_only_affects_own_stash(): void {
        $this->resetAfterTest();
        $manager1 = $this->create_manager();
        $manager2 = $this->create_manager();
        $boardname = 'block_stash\local\leaderboards\most_unique_items';
        foreach ([$manager1, $manager2] as $manager) {
            $manager->set_leaderboard_settings((object) [
                'stashid' => $manager->get_stash()->get_id(),
                'boardname' => $boardname,
                'rowlimit' => 5,
            ]);
        }

        $manager1->delete_leaderboard_settings($boardname);

        $this->assertEmpty($manager1->get_leaderboard_settings());
        $this->assertCount(1, $manager2->get_leaderboard_settings());
    }
}
