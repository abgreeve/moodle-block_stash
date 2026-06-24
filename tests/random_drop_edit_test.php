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
        $this->assertStringContainsString(get_string('dropname', 'block_stash'), $output);
    }

    public function test_page_requires_manage_capability(): void {
        [$course, , $student] = $this->create_course_users();
        $this->create_enabled_stash($course->id);
        $this->setUser($student);

        $this->expectException(required_capability_exception::class);
        $this->execute_page(['courseid' => $course->id]);
    }

    public function test_form_submission_creates_random_drop_without_pool_entries(): void {
        global $DB;

        [$course, $teacher] = $this->create_course_users();
        $this->setUser($teacher);

        $this->create_enabled_stash($course->id);
        $manager = manager::get($course->id);
        $beforecount = $DB->count_records(drop::TABLE);

        random_drop_form::mock_submit([
            'name' => 'Random location',
            'maxpickup' => '7',
            'pickupinterval' => HOURSECS * 3,
            'submitbutton' => 1,
        ]);
        $form = new random_drop_form(null, ['persistent' => null, 'item' => null, 'manager' => $manager]);
        $data = $form->get_data();

        $this->assertNotNull($data);
        $drop = $manager->create_or_update_drop($data);

        $reloaded = new drop($drop->get_id());
        $this->assertSame($beforecount + 1, $DB->count_records(drop::TABLE));
        $this->assertSame(drop::TYPE_RANDOM, $reloaded->get_droptype());
        $this->assertSame(0, $reloaded->get_itemid());
        $this->assertSame('Random location', $reloaded->get_name());
        $this->assertSame(7, $reloaded->get_maxpickup());
        $this->assertSame(HOURSECS * 3, $reloaded->get_pickupinterval());
        $this->assertSame(0, $DB->count_records('block_stash_drop_pool', ['dropid' => $drop->get_id()]));
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
}
