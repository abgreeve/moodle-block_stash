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

use context_course;
use moodle_url;

/**
 * Shortcode tests.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class shortcodes_test extends \advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    public function test_fixed_drop_shortcode_renders_with_fixed_item_behaviour(): void {
        [$manager, $stash] = $this->create_shortcode_fixture();
        $fixeditem = $this->create_item($stash, 'Fixed item');
        $drop = $this->create_drop($fixeditem, drop::TYPE_FIXED, 'Fixed drop');

        $output = $this->render_drop_shortcode($manager, $drop, []);

        $this->assertStringContainsString('Fixed item', $output);
        $this->assertStringContainsString($drop->get_hashcode(), $output);
        $this->assertNotSame('', $output);
    }

    public function test_valid_random_drop_shortcode_renders_without_fixed_itemid(): void {
        [$manager, $stash] = $this->create_shortcode_fixture();
        $drop = new drop(null, (object) [
            'stashid' => $stash->get_id(),
            'itemid' => 0,
            'droptype' => drop::TYPE_RANDOM,
            'name' => 'Mystery drop',
        ]);
        $drop->create();

        $poolitemone = $this->create_item($stash, 'Pool item 1');
        $poolitemtwo = $this->create_item($stash, 'Pool item 2');
        $this->create_pool_item($drop, $poolitemone->get_id(), 1);
        $this->create_pool_item($drop, $poolitemtwo->get_id(), 5);

        $output = $this->render_drop_shortcode($manager, $drop, []);

        $this->assertStringContainsString('Mystery drop', $output);
        $this->assertStringContainsString($drop->get_hashcode(), $output);
        $this->assertStringNotContainsString('Pool item 1', $output);
        $this->assertStringNotContainsString('Pool item 2', $output);
    }

    public function test_invalid_random_drop_shortcode_is_hidden_for_students(): void {
        [$manager, $stash] = $this->create_shortcode_fixture();
        $drop = new drop(null, (object) [
            'stashid' => $stash->get_id(),
            'itemid' => 0,
            'droptype' => drop::TYPE_RANDOM,
            'name' => 'Broken mystery drop',
        ]);
        $drop->create();

        $poolitem = $this->create_item($stash, 'Only pool item');
        $this->create_pool_item($drop, $poolitem->get_id(), 1);

        $this->assertFalse($manager->is_drop_visible($drop));
        $this->assertSame('', $this->render_drop_shortcode($manager, $drop, []));
    }

    public function test_random_drop_shortcode_uses_placeholder_image_when_no_custom_image_set(): void {
        global $PAGE;

        [$manager, $stash] = $this->create_shortcode_fixture();
        $drop = $this->create_random_drop_with_pool($manager, $stash, 'Mystery drop');

        $output = $this->render_drop_shortcode($manager, $drop, ['image' => 1]);

        $renderer = $PAGE->get_renderer('block_stash');
        $placeholderurl = $renderer->image_url('random-item-md', 'block_stash')->out(false);

        $this->assertStringContainsString($placeholderurl, $output);
    }

    public function test_random_drop_shortcode_uses_custom_image_when_configured(): void {
        global $PAGE;

        [$manager, $stash] = $this->create_shortcode_fixture();
        $drop = $this->create_random_drop_with_pool($manager, $stash, 'Mystery drop');

        // The image is attached directly via the file API, as it would already be by the time
        // a student (the current user in this fixture) views the rendered drop.
        get_file_storage()->create_file_from_string([
            'contextid' => $manager->get_context()->id,
            'component' => 'block_stash',
            'filearea' => drop::FILEAREA_IMAGE,
            'itemid' => $drop->get_id(),
            'filepath' => '/',
            'filename' => 'image.png',
        ], 'fake image content');

        $output = $this->render_drop_shortcode($manager, $drop, ['image' => 1]);

        $expectedurl = moodle_url::make_pluginfile_url($manager->get_context()->id, 'block_stash',
            drop::FILEAREA_IMAGE, $drop->get_id(), '/', 'image.png')->out(false);
        $renderer = $PAGE->get_renderer('block_stash');
        $placeholderurl = $renderer->image_url('random-item-md', 'block_stash')->out(false);

        $this->assertStringContainsString($expectedurl, $output);
        $this->assertStringNotContainsString($placeholderurl, $output);
    }

    public function test_random_drop_shortcode_image_only_renders_image_without_button(): void {
        [$manager, $stash] = $this->create_shortcode_fixture();
        $drop = $this->create_random_drop_with_pool($manager, $stash, 'Mystery drop');

        $output = $this->render_drop_shortcode($manager, $drop, ['image' => 1]);

        $this->assertStringContainsString('item-image', $output);
        $this->assertStringNotContainsString('btn btn-secondary', $output);
    }

    public function test_random_drop_shortcode_button_only_renders_without_image(): void {
        [$manager, $stash] = $this->create_shortcode_fixture();
        $drop = $this->create_random_drop_with_pool($manager, $stash, 'Mystery drop');

        $output = $this->render_drop_shortcode($manager, $drop, ['text' => 'Open the mystery box']);

        $this->assertStringContainsString('Open the mystery box', $output);
        $this->assertStringNotContainsString('item-image', $output);
    }

    public function test_random_drop_shortcode_image_and_button_renders_both(): void {
        [$manager, $stash] = $this->create_shortcode_fixture();
        $drop = $this->create_random_drop_with_pool($manager, $stash, 'Mystery drop');

        $output = $this->render_drop_shortcode($manager, $drop, ['image' => 1, 'text' => 'Claim your prize']);

        $this->assertStringContainsString('item-image', $output);
        $this->assertStringContainsString('btn btn-secondary', $output);
        $this->assertStringContainsString('Claim your prize', $output);
    }

    public function test_random_drop_shortcode_supports_custom_button_text(): void {
        [$manager, $stash] = $this->create_shortcode_fixture();
        $drop = $this->create_random_drop_with_pool($manager, $stash, 'Mystery drop');

        $defaultoutput = $this->render_drop_shortcode($manager, $drop, ['image' => 1]);
        $customoutput = $this->render_drop_shortcode($manager, $drop, ['image' => 1, 'text' => 'Unlock this!']);

        $this->assertStringContainsString('Unlock this!', $customoutput);
        $this->assertStringNotContainsString('Unlock this!', $defaultoutput);
    }

    private function render_drop_shortcode(manager $manager, drop $drop, array $args): string {
        global $PAGE;

        $context = $manager->get_context();
        $PAGE->set_context($context);
        $PAGE->set_url(new moodle_url('/course/view.php', ['id' => $manager->get_courseid()]));

        $args = $args + ['secret' => substr($drop->get_hashcode(), 0, 6)];
        $env = (object) ['context' => $context];

        return shortcodes::drop('stashdrop', $args, null, $env, function($content = null) {
            return $content;
        });
    }

    private function create_shortcode_fixture(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $plugingenerator = $generator->get_plugin_generator('block_stash');
        $course = $generator->create_course();
        $user = $generator->create_user();
        $context = context_course::instance($course->id);
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $generator->enrol_user($user->id, $course->id, $studentroleid);
        role_change_permission($studentroleid, $context, manager::CAN_VIEW, CAP_ALLOW);
        role_change_permission($studentroleid, $context, manager::CAN_ACQUIRE_ITEMS, CAP_ALLOW);
        $this->setUser($user);

        $stash = $plugingenerator->create_stash(['courseid' => $course->id]);
        $manager = manager::get($course->id);
        $manager->set_enabled();

        return [$manager, $stash, $user];
    }

    private function create_item(\block_stash\stash $stash, string $name): \block_stash\item {
        return $this->getDataGenerator()->get_plugin_generator('block_stash')->create_item([
            'stash' => $stash,
            'name' => $name,
        ]);
    }

    private function create_drop(\block_stash\item $item, int $droptype, string $name): drop {
        return $this->getDataGenerator()->get_plugin_generator('block_stash')->create_drop([
            'item' => $item,
            'droptype' => $droptype,
            'name' => $name,
        ]);
    }

    private function create_pool_item(drop $drop, int $itemid, int $weight): drop_pool_item {
        $poolitem = new drop_pool_item(null, (object) [
            'dropid' => $drop->get_id(),
            'itemid' => $itemid,
            'weight' => $weight,
        ]);
        $poolitem->create();
        return $poolitem;
    }

    private function create_random_drop_with_pool(manager $manager, \block_stash\stash $stash, string $name): drop {
        $drop = new drop(null, (object) [
            'stashid' => $stash->get_id(),
            'itemid' => 0,
            'droptype' => drop::TYPE_RANDOM,
            'name' => $name,
        ]);
        $drop->create();

        $poolitemone = $this->create_item($stash, 'Pool item 1');
        $poolitemtwo = $this->create_item($stash, 'Pool item 2');
        $this->create_pool_item($drop, $poolitemone->get_id(), 1);
        $this->create_pool_item($drop, $poolitemtwo->get_id(), 5);

        return $drop;
    }
}
