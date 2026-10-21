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
 * Tests for the leaderboard_settings external functions.
 *
 * @package    block_stash
 * @copyright  2026 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_stash\external\leaderboard_settings
 */
final class leaderboard_settings_test extends \advanced_testcase {
    /**
     * Create a course with a stash block.
     *
     * @return \stdClass The course.
     */
    private function create_fixture(): \stdClass {
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $dg->get_plugin_generator('block_stash')->create_instance([
            'parentcontextid' => \context_course::instance($course->id)->id,
        ]);
        \block_stash\manager::get($course->id, true);
        return $course;
    }

    /**
     * A student must not be able to change the block or leaderboard settings.
     */
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    public function test_settings_require_manage_capability(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->create_fixture();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $configbefore = $DB->get_field('block_instances', 'configdata', ['blockname' => 'stash']);

        try {
            leaderboard_settings::update_block_setting($course->id, 'leaderboard', true);
            $this->fail('Expected required_capability_exception');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        try {
            leaderboard_settings::update_leaderboard_setting($course->id, 'most_unique_items', '', 'DESC', 5, true);
            $this->fail('Expected required_capability_exception');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        $this->assertSame($configbefore, $DB->get_field('block_instances', 'configdata', ['blockname' => 'stash']));
        $this->assertSame(0, $DB->count_records('block_stash_lb_settings'));
    }

    /**
     * A teacher can still change the block and leaderboard settings.
     */
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    public function test_settings_as_teacher(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->create_fixture();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $this->assertTrue(leaderboard_settings::update_block_setting($course->id, 'leaderboard', true));
        $this->assertTrue(
            leaderboard_settings::update_leaderboard_setting($course->id, 'most_unique_items', '', 'DESC', 5, true)
        );
        $config = unserialize(base64_decode($DB->get_field('block_instances', 'configdata', ['blockname' => 'stash'])));
        $this->assertTrue($config->leaderboard);
        $this->assertSame(1, $DB->count_records('block_stash_lb_settings'));
    }
}
