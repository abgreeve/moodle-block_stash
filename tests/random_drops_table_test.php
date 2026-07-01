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
 * Random drops table tests.
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
use block_stash\output\random_drops_table;

/**
 * Testable random drops table.
 */
class block_stash_testable_random_drops_table extends random_drops_table {

    public function get_sql_definition(): stdClass {
        return $this->sql;
    }

    public function render_name(stdClass $row): string {
        return $this->col_name($row);
    }

    public function render_status(stdClass $row): string {
        return $this->col_status($row);
    }

    public function render_shortcode(stdClass $row): string {
        return $this->col_shortcode($row);
    }

    public function render_actions(stdClass $row): string {
        return $this->col_actions($row);
    }
}

/**
 * Random drops table testcase class.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_stash_random_drops_table_testcase extends advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    public function test_sql_filters_random_drops_for_the_current_stash(): void {
        [$course, $teacher] = $this->create_course_users();
        $this->setUser($teacher);
        $stash = $this->create_enabled_stash($course->id);

        $table = $this->create_table($course->id);
        $sql = $table->get_sql_definition();

        $this->assertSame('d.stashid = :stashid AND d.droptype = :droptype', $sql->where);
        $this->assertSame($stash->get_id(), $sql->params['stashid']);
        $this->assertSame(drop::TYPE_RANDOM, $sql->params['droptype']);
    }

    public function test_table_is_drop_centric_and_uses_random_pool_validation_status(): void {
        [$course, $teacher] = $this->create_course_users();
        $this->setUser($teacher);
        $stash = $this->create_enabled_stash($course->id);
        $table = $this->create_table($course->id);

        $validdrop = new drop(null, (object) [
            'stashid' => $stash->get_id(),
            'itemid' => 0,
            'droptype' => drop::TYPE_RANDOM,
            'name' => 'Valid random drop',
        ]);
        $validdrop->create();

        $firstpoolitem = $this->create_item($stash, 'Pool item 1');
        $secondpoolitem = $this->create_item($stash, 'Pool item 2');
        (new drop_pool_item(null, (object) [
            'dropid' => $validdrop->get_id(),
            'itemid' => $firstpoolitem->get_id(),
            'weight' => 1,
        ]))->create();
        (new drop_pool_item(null, (object) [
            'dropid' => $validdrop->get_id(),
            'itemid' => $secondpoolitem->get_id(),
            'weight' => 5,
        ]))->create();

        $invaliddrop = new drop(null, (object) [
            'stashid' => $stash->get_id(),
            'itemid' => 0,
            'droptype' => drop::TYPE_RANDOM,
            'name' => 'Invalid random drop',
        ]);
        $invaliddrop->create();

        $validrow = $validdrop->to_record();
        $invalidrow = $invaliddrop->to_record();

        $namehtml = $table->render_name($validrow);
        $this->assertStringContainsString('random_drop_edit.php', $namehtml);
        $this->assertStringContainsString('dropid=' . $validdrop->get_id(), $namehtml);

        $this->assertSame(get_string('enabled', 'block_stash'), $table->render_status($validrow));
        $this->assertStringContainsString(
            get_string('randomdroppoolnotenoughitems', 'block_stash'),
            $table->render_status($invalidrow)
        );

        $shortcodehtml = html_entity_decode($table->render_shortcode($validrow));
        $this->assertStringContainsString('[stashdrop secret="' . $validdrop->get_hashcode() . '" image]', $shortcodehtml);

        $actionshtml = html_entity_decode($table->render_actions($validrow));
        $this->assertStringContainsString('random_drop_edit.php?courseid=' . $course->id . '&dropid=' . $validdrop->get_id(), $actionshtml);
        $this->assertStringContainsString('action=delete', $actionshtml);
        $this->assertStringContainsString('dropid=' . $validdrop->get_id(), $actionshtml);
        $this->assertStringNotContainsString('item_edit.php', $actionshtml);
        $this->assertStringNotContainsString('itemid=', $actionshtml);
    }

    private function create_table(int $courseid): block_stash_testable_random_drops_table {
        global $PAGE;

        $PAGE->set_url(new moodle_url('/blocks/stash/items.php', ['courseid' => $courseid]));
        $PAGE->set_context(context_course::instance($courseid));

        $manager = manager::get($courseid, true);
        $renderer = $PAGE->get_renderer('block_stash');
        $table = new block_stash_testable_random_drops_table('randomdropstable', $manager, $renderer);
        $table->define_baseurl(new moodle_url('/blocks/stash/items.php', ['courseid' => $courseid]));

        return $table;
    }

    private function create_course_users(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_user();
        $context = context_course::instance($course->id);
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $generator->enrol_user($teacher->id, $course->id, $teacherroleid);
        role_change_permission($teacherroleid, $context, manager::CAN_MANAGE, CAP_ALLOW);

        return [$course, $teacher];
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
}
