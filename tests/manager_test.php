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

    /**
     * Test creating a drop with a custom code and updating course content when code changes.
     */
    public function test_custom_drop_code_and_course_content_update(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $stashgenerator = $dg->get_plugin_generator('block_stash');
        $stashgenerator->create_instance([
            'parentcontextid' => \context_course::instance($course->id)->id,
        ]);
        $manager = manager::get($course->id, true);

        $item = $stashgenerator->create_item(['stashid' => $manager->get_stash()->get_id()]);

        // 1. Create drop with custom code.
        $dropdata = (object) [
            'id' => 0,
            'itemid' => $item->get_id(),
            'name' => 'Secret Cave',
            'maxpickup' => 1,
            'pickupinterval' => 3600,
            'hashcode' => 'custom_cave_key_1',
        ];
        $drop = $manager->create_or_update_drop($dropdata);
        $this->assertEquals('custom_cave_key_1', $drop->get_hashcode());

        // 2. Put shortcodes and snippet into course section and page activity.
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
        $section->summary = '<p>Check out [stashdrop secret="custom_cave_key_1"] here.</p>';
        $DB->update_record('course_sections', $section);

        $page = $dg->create_module('page', [
            'course' => $course->id,
            'name' => 'Test Page',
            'content' => 'Find the key: [stashdrop code="custom_cave_key_1" image] or js: hashcode: "custom_cave_key_1"',
        ]);

        // 3. Update the drop code to a new custom code.
        $updatedata = (object) [
            'id' => $drop->get_id(),
            'itemid' => $item->get_id(),
            'name' => 'Secret Cave Updated',
            'maxpickup' => 1,
            'pickupinterval' => 3600,
            'hashcode' => 'new_cave_key_2',
        ];
        $updateddrop = $manager->create_or_update_drop($updatedata);
        $this->assertEquals('new_cave_key_2', $updateddrop->get_hashcode());

        // 4. Verify that course content was automatically updated.
        $updatedsection = $DB->get_record('course_sections', ['id' => $section->id]);
        $this->assertStringContainsString('secret="new_cave_key_2"', $updatedsection->summary);
        $this->assertStringNotContainsString('custom_cave_key_1', $updatedsection->summary);

        $updatedpage = $DB->get_record('page', ['id' => $page->id]);
        $this->assertStringContainsString('code="new_cave_key_2"', $updatedpage->content);
        $this->assertStringContainsString('hashcode: "new_cave_key_2"', $updatedpage->content);
        $this->assertStringNotContainsString('custom_cave_key_1', $updatedpage->content);
    }
}
