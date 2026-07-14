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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/tests/backup_restore_base_testcase.php');

/**
 * Backup and restore tests, including random drops.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
final class backup_restore_test extends \core_backup_backup_restore_base_testcase {

    public function test_fixed_drop_backup_and_restore(): void {
        [$course1, $stash1] = $this->create_course_with_stash_block();
        $item1 = $this->create_item($stash1, 'Sword');
        $drop1 = $this->create_fixed_drop($item1, 'Fixed location');

        $course2 = $this->getDataGenerator()->create_course();
        $backupid = $this->perform_backup($course1);
        $this->perform_restore($backupid, $course2);

        $stash2 = stash::get_record(['courseid' => $course2->id]);
        $this->assertNotFalse($stash2);
        $this->assertNotEquals($stash1->get_id(), $stash2->get_id());

        $item2 = item::get_record(['stashid' => $stash2->get_id(), 'name' => 'Sword']);
        $this->assertNotFalse($item2);
        $this->assertNotEquals($item1->get_id(), $item2->get_id());

        $drops2 = drop::get_records(['itemid' => $item2->get_id()]);
        $this->assertCount(1, $drops2);
        $drop2 = reset($drops2);

        $this->assertNotEquals($drop1->get_id(), $drop2->get_id());
        $this->assertSame('Fixed location', $drop2->get_name());
        $this->assertSame(drop::TYPE_FIXED, $drop2->get_droptype());
        $this->assertSame($stash2->get_id(), $drop2->get_stashid());
        $this->assertSame($item2->get_id(), $drop2->get_itemid());
    }

    public function test_backup_without_random_drop_data_restores_successfully(): void {
        // A course whose stash has never had any random drops at all. This is the
        // practical equivalent of restoring a backup produced before random drops
        // existed: the </randomdrops> branch is empty, so those restore paths are
        // simply never matched.
        [$course1, $stash1] = $this->create_course_with_stash_block();
        $item1 = $this->create_item($stash1, 'Shield');
        $this->create_fixed_drop($item1, 'Only location');

        $course2 = $this->getDataGenerator()->create_course();
        $backupid = $this->perform_backup($course1);
        $this->perform_restore($backupid, $course2);

        $stash2 = stash::get_record(['courseid' => $course2->id]);
        $this->assertNotFalse($stash2);
        $this->assertCount(1, item::get_records(['stashid' => $stash2->get_id()]));
        $this->assertCount(0, drop::get_records(['stashid' => $stash2->get_id(), 'droptype' => drop::TYPE_RANDOM]));
    }

    public function test_random_drop_pool_backup_and_restore(): void {
        [$course1, $stash1] = $this->create_course_with_stash_block();
        $poolitemone1 = $this->create_item($stash1, 'Pool item 1');
        $poolitemtwo1 = $this->create_item($stash1, 'Pool item 2');
        $randomdrop1 = $this->create_random_drop($stash1, 'Mystery drop', ['maxpickup' => 3, 'pickupinterval' => 900]);
        $this->create_pool_item($randomdrop1, $poolitemone1->get_id(), 1);
        $this->create_pool_item($randomdrop1, $poolitemtwo1->get_id(), 5);

        $course2 = $this->getDataGenerator()->create_course();
        $backupid = $this->perform_backup($course1);
        $this->perform_restore($backupid, $course2);

        $stash2 = stash::get_record(['courseid' => $course2->id]);
        $randomdrops2 = drop::get_records(['stashid' => $stash2->get_id(), 'droptype' => drop::TYPE_RANDOM]);
        $this->assertCount(1, $randomdrops2);
        $randomdrop2 = reset($randomdrops2);

        // Drop type, configuration, and stashid are preserved.
        $this->assertNotEquals($randomdrop1->get_id(), $randomdrop2->get_id());
        $this->assertSame('Mystery drop', $randomdrop2->get_name());
        $this->assertSame(drop::TYPE_RANDOM, $randomdrop2->get_droptype());
        $this->assertSame(0, $randomdrop2->get_itemid());
        $this->assertSame($stash2->get_id(), $randomdrop2->get_stashid());
        $this->assertSame(3, $randomdrop2->get_maxpickup());
        $this->assertSame(900, $randomdrop2->get_pickupinterval());

        // Pool entries reference the restored items, not the originals, with weights intact.
        $itemone2 = item::get_record(['stashid' => $stash2->get_id(), 'name' => 'Pool item 1']);
        $itemtwo2 = item::get_record(['stashid' => $stash2->get_id(), 'name' => 'Pool item 2']);
        $this->assertNotEquals($poolitemone1->get_id(), $itemone2->get_id());
        $this->assertNotEquals($poolitemtwo1->get_id(), $itemtwo2->get_id());

        $poolitems2 = $randomdrop2->get_pool_items();
        $this->assertCount(2, $poolitems2);

        $weightbyitemid = [];
        foreach ($poolitems2 as $poolitem) {
            $this->assertNotContains($poolitem->get_itemid(), [$poolitemone1->get_id(), $poolitemtwo1->get_id()]);
            $weightbyitemid[$poolitem->get_itemid()] = $poolitem->get_weight();
        }
        $this->assertSame(1, $weightbyitemid[$itemone2->get_id()]);
        $this->assertSame(5, $weightbyitemid[$itemtwo2->get_id()]);
    }

    public function test_random_drop_custom_image_is_restored(): void {
        [$course1, $stash1] = $this->create_course_with_stash_block();
        $poolitemone1 = $this->create_item($stash1, 'Pool item 1');
        $poolitemtwo1 = $this->create_item($stash1, 'Pool item 2');
        $randomdrop1 = $this->create_random_drop($stash1, 'Mystery drop');
        $this->create_pool_item($randomdrop1, $poolitemone1->get_id(), 1);
        $this->create_pool_item($randomdrop1, $poolitemtwo1->get_id(), 1);

        get_file_storage()->create_file_from_string([
            'contextid' => context_course::instance($course1->id)->id,
            'component' => 'block_stash',
            'filearea' => drop::FILEAREA_IMAGE,
            'itemid' => $randomdrop1->get_id(),
            'filepath' => '/',
            'filename' => 'mystery.png',
        ], 'fake image content');

        $course2 = $this->getDataGenerator()->create_course();
        $backupid = $this->perform_backup($course1);
        $this->perform_restore($backupid, $course2);

        $stash2 = stash::get_record(['courseid' => $course2->id]);
        $randomdrops2 = drop::get_records(['stashid' => $stash2->get_id(), 'droptype' => drop::TYPE_RANDOM]);
        $randomdrop2 = reset($randomdrops2);

        $files = get_file_storage()->get_area_files(context_course::instance($course2->id)->id, 'block_stash',
            drop::FILEAREA_IMAGE, $randomdrop2->get_id(), 'filename', false);
        $this->assertCount(1, $files);
        $file = reset($files);
        $this->assertSame('mystery.png', $file->get_filename());
        $this->assertSame('fake image content', $file->get_content());
    }

    public function test_random_drop_without_custom_image_uses_placeholder_after_restore(): void {
        global $PAGE;

        [$course1, $stash1] = $this->create_course_with_stash_block();
        $poolitemone1 = $this->create_item($stash1, 'Pool item 1');
        $poolitemtwo1 = $this->create_item($stash1, 'Pool item 2');
        $randomdrop1 = $this->create_random_drop($stash1, 'Mystery drop');
        $this->create_pool_item($randomdrop1, $poolitemone1->get_id(), 1);
        $this->create_pool_item($randomdrop1, $poolitemtwo1->get_id(), 1);

        $course2 = $this->getDataGenerator()->create_course();
        $backupid = $this->perform_backup($course1);
        $this->perform_restore($backupid, $course2);

        $stash2 = stash::get_record(['courseid' => $course2->id]);
        $randomdrops2 = drop::get_records(['stashid' => $stash2->get_id(), 'droptype' => drop::TYPE_RANDOM]);
        $randomdrop2 = reset($randomdrops2);

        // No custom image was ever backed up for this drop, so nothing should exist
        // under the restored drop's file area either.
        $files = get_file_storage()->get_area_files(context_course::instance($course2->id)->id, 'block_stash',
            drop::FILEAREA_IMAGE, $randomdrop2->get_id(), 'filename', false);
        $this->assertCount(0, $files);

        // Rendering the restored drop's shortcode must fall back to the placeholder image.
        $output = $this->render_random_drop_shortcode($course2, $randomdrop2);
        $renderer = $PAGE->get_renderer('block_stash');
        $placeholderurl = $renderer->image_url('random-item-md', 'block_stash')->out(false);
        $this->assertStringContainsString($placeholderurl, $output);
    }

    public function test_restored_random_drop_can_be_rendered_and_picked_up(): void {
        global $DB;

        [$course1, $stash1] = $this->create_course_with_stash_block();
        $poolitemone1 = $this->create_item($stash1, 'Pool item 1');
        $poolitemtwo1 = $this->create_item($stash1, 'Pool item 2');
        $randomdrop1 = $this->create_random_drop($stash1, 'Mystery drop');
        $this->create_pool_item($randomdrop1, $poolitemone1->get_id(), 1);
        $this->create_pool_item($randomdrop1, $poolitemtwo1->get_id(), 1);

        $course2 = $this->getDataGenerator()->create_course();
        $backupid = $this->perform_backup($course1);
        $this->perform_restore($backupid, $course2);

        $stash2 = stash::get_record(['courseid' => $course2->id]);
        $randomdrops2 = drop::get_records(['stashid' => $stash2->get_id(), 'droptype' => drop::TYPE_RANDOM]);
        $randomdrop2 = reset($randomdrops2);

        // The restored drop renders successfully.
        $output = $this->render_random_drop_shortcode($course2, $randomdrop2);
        $this->assertStringContainsString('Mystery drop', $output);
        $this->assertNotSame('', $output);

        // A student in the restored course can pick it up.
        $student = $this->getDataGenerator()->create_user();
        $context2 = context_course::instance($course2->id);
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $this->getDataGenerator()->enrol_user($student->id, $course2->id, $studentroleid);
        role_change_permission($studentroleid, $context2, manager::CAN_VIEW, CAP_ALLOW);
        role_change_permission($studentroleid, $context2, manager::CAN_ACQUIRE_ITEMS, CAP_ALLOW);

        $manager2 = manager::get($course2->id, true);
        $pickeditem = $manager2->pickup_drop($randomdrop2, $student->id);

        $restoreditemnames = [
            item::get_record(['stashid' => $stash2->get_id(), 'name' => 'Pool item 1'])->get_id(),
            item::get_record(['stashid' => $stash2->get_id(), 'name' => 'Pool item 2'])->get_id(),
        ];
        $this->assertContains($pickeditem->get_id(), $restoreditemnames);
        $this->assertNotFalse(user_item::get_record(['itemid' => $pickeditem->get_id(), 'userid' => $student->id]));
    }

    /**
     * Create a course with an enabled stash block instance.
     *
     * @return array [stdClass $course, stash $stash]
     */
    private function create_course_with_stash_block(): array {
        $course = $this->getDataGenerator()->create_course();

        // A real block instance is required: is_enabled() checks for one in the DB,
        // and the backup/restore machinery only includes a block's structure step
        // for block types that are actually instantiated in the course.
        $this->getDataGenerator()->create_block('stash', [
            'parentcontextid' => context_course::instance($course->id)->id,
        ]);

        $manager = manager::get($course->id, true);
        $stash = $manager->get_stash();

        return [$course, $stash];
    }

    private function create_item(\block_stash\stash $stash, string $name): item {
        return $this->getDataGenerator()->get_plugin_generator('block_stash')->create_item([
            'stash' => $stash,
            'name' => $name,
        ]);
    }

    private function create_fixed_drop(item $item, string $name): drop {
        return $this->getDataGenerator()->get_plugin_generator('block_stash')->create_drop([
            'item' => $item,
            'droptype' => drop::TYPE_FIXED,
            'name' => $name,
        ]);
    }

    private function create_random_drop(\block_stash\stash $stash, string $name, array $overrides = []): drop {
        $record = (object) ($overrides + [
            'stashid' => $stash->get_id(),
            'itemid' => 0,
            'droptype' => drop::TYPE_RANDOM,
            'name' => $name,
        ]);
        $drop = new drop(null, $record);
        $drop->create();
        return $drop;
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

    /**
     * Render a random drop's shortcode as a student would see it.
     *
     * @param \stdClass $course The course the drop belongs to.
     * @param drop $drop The random drop.
     * @return string
     */
    private function render_random_drop_shortcode(\stdClass $course, drop $drop): string {
        global $PAGE;

        $context = context_course::instance($course->id);
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));

        $args = ['secret' => substr($drop->get_hashcode(), 0, 6), 'image' => 1];
        $env = (object) ['context' => $context];

        return shortcodes::drop('stashdrop', $args, null, $env, function($content = null) {
            return $content;
        });
    }
}
