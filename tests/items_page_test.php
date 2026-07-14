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
 * Items page tests.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_stash\drop;
use block_stash\drop_pool_item;
use block_stash\manager;

/**
 * Items page testcase class.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_stash_items_page_testcase extends advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    public function test_items_page_renders_items_and_random_drops_sections(): void {
        [$course, $teacher] = $this->create_course_users();
        $this->setUser($teacher);
        $stash = $this->create_enabled_stash($course->id);

        $item = $this->create_item($stash, 'Teacher item');
        $randomdrop = $this->create_random_drop($stash, 'Teacher random drop');
        $poolitemone = $this->create_item($stash, 'Pool item 1');
        $poolitemtwo = $this->create_item($stash, 'Pool item 2');
        $this->create_pool_item($randomdrop, $poolitemone->get_id(), 1);
        $this->create_pool_item($randomdrop, $poolitemtwo->get_id(), 5);

        $othercourse = $this->getDataGenerator()->create_course();
        $otherstash = $this->create_enabled_stash($othercourse->id);
        $this->create_item($otherstash, 'Other stash item');
        $otherrandomdrop = $this->create_random_drop($otherstash, 'Other random drop');
        $otherpoolitemone = $this->create_item($otherstash, 'Other pool item 1');
        $otherpoolitemtwo = $this->create_item($otherstash, 'Other pool item 2');
        $this->create_pool_item($otherrandomdrop, $otherpoolitemone->get_id(), 1);
        $this->create_pool_item($otherrandomdrop, $otherpoolitemtwo->get_id(), 1);

        $output = $this->execute_page(['courseid' => $course->id]);

        $this->assertStringContainsString(get_string('itemslist', 'block_stash'), $output);
        $this->assertStringContainsString(get_string('randomdrops', 'block_stash'), $output);
        $this->assertStringContainsString(get_string('additem', 'block_stash'), $output);
        $this->assertStringContainsString(get_string('addrandomdrop', 'block_stash'), $output);

        $this->assertStringContainsString('Teacher item', $output);
        $this->assertStringContainsString('Teacher random drop', $output);
        $this->assertStringNotContainsString('Other stash item', $output);
        $this->assertStringNotContainsString('Other random drop', $output);

        $this->assertStringContainsString('item_edit.php?courseid=' . $course->id, $output);
        $this->assertStringContainsString('random_drop_edit.php?courseid=' . $course->id, $output);

        $decodedoutput = html_entity_decode($output);
        $this->assertStringContainsString(get_string('getsnippet', 'block_stash'), $decodedoutput);
        $this->assertStringContainsString('rel="block-stash-drop"', $decodedoutput);
        $this->assertStringContainsString($randomdrop->get_hashcode(), $decodedoutput);
    }

    public function test_items_page_requires_manage_capability(): void {
        [$course, , $student] = $this->create_course_users();
        $this->create_enabled_stash($course->id);
        $this->setUser($student);

        $this->expectException(required_capability_exception::class);
        $this->execute_page(['courseid' => $course->id]);
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
        require($CFG->dirroot . '/blocks/stash/items.php');
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

    private function create_item(\block_stash\stash $stash, string $name): \block_stash\item {
        return $this->getDataGenerator()->get_plugin_generator('block_stash')->create_item([
            'stash' => $stash,
            'name' => $name,
        ]);
    }

    private function create_random_drop(\block_stash\stash $stash, string $name): drop {
        $drop = new drop(null, (object) [
            'stashid' => $stash->get_id(),
            'itemid' => 0,
            'droptype' => drop::TYPE_RANDOM,
            'name' => $name,
        ]);
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
}
