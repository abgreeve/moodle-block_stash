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
 * Random drop edit page tests.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_stash\drop;
use block_stash\drop_pool_item;
use block_stash\form\random_drop as random_drop_form;
use block_stash\manager;

/**
 * Random drop edit page testcase class.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_stash_random_drop_edit_testcase extends advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    public function test_page_can_be_accessed_by_teacher(): void {
        [$course, $teacher] = $this->create_course_users();
        $this->create_enabled_stash($course->id);
        $this->setUser($teacher);

        $output = $this->execute_page(['courseid' => $course->id]);

        $this->assertStringContainsString(get_string('addrandomdrop', 'block_stash'), $output);
        $this->assertStringContainsString(get_string('randomdroppool', 'block_stash'), $output);
    }

    public function test_page_requires_manage_capability(): void {
        [$course, , $student] = $this->create_course_users();
        $this->create_enabled_stash($course->id);
        $this->setUser($student);

        $this->expectException(required_capability_exception::class);
        $this->execute_page(['courseid' => $course->id]);
    }

    public function test_form_get_data_includes_dynamic_pool_fields(): void {
        [$course, $teacher] = $this->create_course_users();
        $this->setUser($teacher);
        $stash = $this->create_enabled_stash($course->id);
        $manager = manager::get($course->id);
        $itemone = $this->create_item($stash, 'Pool item 1');
        $itemtwo = $this->create_item($stash, 'Pool item 2');

        $form = $this->submit_form($manager, null, [
            'poolitemids' => [$itemone->get_id(), $itemtwo->get_id()],
            'poolitemweights' => [random_drop_form::WEIGHT_LOW, random_drop_form::WEIGHT_HIGH],
        ]);
        $data = $form->get_data();

        $this->assertNotNull($data);
        $this->assertSame([$itemone->get_id(), $itemtwo->get_id()], $data->poolitemids);
        $this->assertSame([random_drop_form::WEIGHT_LOW, random_drop_form::WEIGHT_HIGH], $data->poolitemweights);
    }

    public function test_teacher_can_create_random_drop_with_pool_items(): void {
        [$manager, $drop, $items] = $this->create_random_drop_with_submission([7, 1]);

        $this->assertSame(drop::TYPE_RANDOM, $drop->get_droptype());
        $this->assertSame(0, $drop->get_itemid());
        $this->assertSame($manager->get_stash()->get_id(), $drop->get_stashid());
        $this->assertSame(2, drop_pool_item::count_records(['dropid' => $drop->get_id()]));

        $stored = drop_pool_item::get_records(['dropid' => $drop->get_id()], 'itemid');
        $this->assertCount(2, $stored);
        $this->assertSame([1, 7], array_values(array_map(fn($poolitem) => $poolitem->get_weight(), $stored)));
    }

    public function test_teacher_can_edit_existing_pool_items(): void {
        [$manager, $drop, $items] = $this->create_random_drop_with_submission([1, 5]);

        $updated = $this->submit_form_and_save($manager, $drop, [
            'poolitemids' => [$items[0]->get_id(), $items[1]->get_id()],
            'poolitemweights' => [10, 1],
        ]);

        $stored = drop_pool_item::get_records(['dropid' => $updated->get_id()], 'itemid');
        $this->assertSame($manager->get_stash()->get_id(), $updated->get_stashid());
        $this->assertSame([10, 1], array_values(array_map(fn($poolitem) => $poolitem->get_weight(), $stored)));
    }

    public function test_teacher_can_remove_pool_items(): void {
        [$manager, $drop, $items] = $this->create_random_drop_with_submission([1, 5, 10]);

        $updated = $this->submit_form_and_save($manager, $drop, [
            'poolitemids' => [$items[0]->get_id(), $items[2]->get_id()],
            'poolitemweights' => [1, 10],
        ]);

        $stored = drop_pool_item::get_records(['dropid' => $updated->get_id()], 'itemid');
        $this->assertCount(2, $stored);
        $this->assertSame([$items[0]->get_id(), $items[2]->get_id()], array_values(array_map(fn($poolitem) => $poolitem->get_itemid(), $stored)));
    }

    public function test_duplicate_items_are_rejected(): void {
        [$course, $teacher] = $this->create_course_users();
        $this->setUser($teacher);
        $stash = $this->create_enabled_stash($course->id);
        $item = $this->create_item($stash, 'Item 1');
        $manager = manager::get($course->id);

        $form = $this->submit_form($manager, null, [
            'poolitemids' => [$item->get_id(), $item->get_id()],
            'poolitemweights' => [1, 5],
        ]);

        $this->assertNull($form->get_data());
        $this->assertSame(0, drop_pool_item::count_records());
    }

    public function test_scarce_items_are_rejected(): void {
        [$course, $teacher] = $this->create_course_users();
        $this->setUser($teacher);
        $stash = $this->create_enabled_stash($course->id);
        $normal = $this->create_item($stash, 'Normal');
        $scarce = $this->create_item($stash, 'Scarce', ['amountlimit' => 5, 'currentamount' => 5]);
        $manager = manager::get($course->id);

        $form = $this->submit_form($manager, null, [
            'poolitemids' => [$normal->get_id(), $scarce->get_id()],
            'poolitemweights' => [1, 5],
        ]);

        $this->assertNull($form->get_data());
        $this->assertSame(0, drop_pool_item::count_records());
    }

    public function test_more_than_twenty_items_are_rejected(): void {
        [$course, $teacher] = $this->create_course_users();
        $this->setUser($teacher);
        $stash = $this->create_enabled_stash($course->id);
        $manager = manager::get($course->id);

        $ids = [];
        $weights = [];
        for ($i = 0; $i < 21; $i++) {
            $ids[] = $this->create_item($stash, 'Item ' . $i)->get_id();
            $weights[] = random_drop_form::WEIGHT_MEDIUM;
        }

        $form = $this->submit_form($manager, null, [
            'poolitemids' => $ids,
            'poolitemweights' => $weights,
        ]);

        $this->assertNull($form->get_data());
        $this->assertSame(0, drop_pool_item::count_records());
    }

    public function test_saving_with_fewer_than_two_items_is_allowed_but_pool_remains_invalid(): void {
        [$course, $teacher] = $this->create_course_users();
        $this->setUser($teacher);
        $stash = $this->create_enabled_stash($course->id);
        $manager = manager::get($course->id);
        $item = $this->create_item($stash, 'Only one');

        $drop = $this->submit_form_and_save($manager, null, [
            'poolitemids' => [$item->get_id()],
            'poolitemweights' => [random_drop_form::WEIGHT_MEDIUM],
        ]);

        $this->assertSame(1, drop_pool_item::count_records(['dropid' => $drop->get_id()]));
        $this->assertFalse($drop->is_valid_random_pool());
    }

    public function test_no_pool_entries_are_created_for_invalid_item_ids(): void {
        [$course, $teacher] = $this->create_course_users();
        $this->setUser($teacher);
        $stash = $this->create_enabled_stash($course->id);
        $manager = manager::get($course->id);
        $item = $this->create_item($stash, 'Valid');

        $form = $this->submit_form($manager, null, [
            'poolitemids' => [$item->get_id(), 999999],
            'poolitemweights' => [1, 5],
        ]);

        $this->assertNull($form->get_data());
        $this->assertSame(0, drop_pool_item::count_records());
    }

    private function create_random_drop_with_submission(array $weights): array {
        [$course, $teacher] = $this->create_course_users();
        $this->setUser($teacher);
        $stash = $this->create_enabled_stash($course->id);
        $manager = manager::get($course->id);
        $items = [];
        foreach ($weights as $index => $weight) {
            $items[] = $this->create_item($stash, 'Pool item ' . $index);
        }

        $drop = $this->submit_form_and_save($manager, null, [
            'poolitemids' => array_map(fn($item) => $item->get_id(), $items),
            'poolitemweights' => $weights,
        ]);

        return [$manager, $drop, $items];
    }

    private function submit_form_and_save(manager $manager, ?drop $drop, array $pooldata): drop {
        $form = $this->submit_form($manager, $drop, $pooldata);
        $data = $form->get_data();

        $this->assertNotNull($data);
        $saved = $manager->create_or_update_drop($data);
        $manager->save_random_drop_pool($saved, random_drop_form::parse_pool_entries_from_data($data));

        return new drop($saved->get_id());
    }

    private function submit_form(manager $manager, ?drop $drop, array $pooldata): random_drop_form {
        random_drop_form::mock_submit([
            'name' => $drop ? $drop->get_name() : 'Random location',
            'maxpickup' => '7',
            'pickupinterval' => HOURSECS * 3,
            'poolitemids' => $pooldata['poolitemids'] ?? [],
            'poolitemweights' => $pooldata['poolitemweights'] ?? [],
            'submitbutton' => 1,
        ]);

        return new random_drop_form(null, ['persistent' => $drop, 'item' => null, 'manager' => $manager]);
    }

    private function execute_page(array $get): string {
        global $CFG, $PAGE, $SITE;

        $_GET = $get;
        $_POST = [];
        $_REQUEST = $get;
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $PAGE = null;
        $SITE = null;

        ob_start();
        require($CFG->dirroot . '/blocks/stash/random_drop_edit.php');
        return ob_get_clean();
    }

    private function create_course_users(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $student = $generator->create_user();
        $context = context_course::instance($course->id);
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $generator->enrol_user($teacher->id, $course->id, $teacherroleid);
        $generator->enrol_user($student->id, $course->id, $studentroleid);
        role_change_permission($teacherroleid, $context, manager::CAN_MANAGE, CAP_ALLOW);

        return [$course, $teacher, $student];
    }

    private function create_enabled_stash(int $courseid): \block_stash\stash {
        $generator = $this->getDataGenerator()->get_plugin_generator('block_stash');
        $stash = $generator->create_stash(['courseid' => $courseid]);
        $manager = manager::get($courseid, true);
        $manager->set_enabled();
        return $stash;
    }

    private function create_item(\block_stash\stash $stash, string $name, array $record = []): \block_stash\item {
        return $this->getDataGenerator()->get_plugin_generator('block_stash')->create_item($record + [
            'stash' => $stash,
            'name' => $name,
        ]);
    }
}
