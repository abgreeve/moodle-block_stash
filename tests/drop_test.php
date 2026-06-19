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
 * Drop tests.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_stash\drop;

/**
 * Drop testcase class.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_stash_drop_testcase extends advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    public function test_drop_defaults_to_fixed_type(): void {
        $drop = $this->create_drop();

        $this->assertSame(drop::TYPE_FIXED, $drop->get_droptype());
        $this->assertTrue($drop->is_fixed());
        $this->assertFalse($drop->is_random());

        $reloaded = new drop($drop->get_id());
        $this->assertSame(drop::TYPE_FIXED, $reloaded->get_droptype());
        $this->assertTrue($reloaded->is_fixed());
        $this->assertFalse($reloaded->is_random());
    }

    public function test_drop_type_round_trips_when_set(): void {
        $drop = $this->create_drop(['droptype' => drop::TYPE_RANDOM]);

        $this->assertSame(drop::TYPE_RANDOM, $drop->get_droptype());
        $this->assertFalse($drop->is_fixed());
        $this->assertTrue($drop->is_random());

        $reloaded = new drop($drop->get_id());
        $this->assertSame(drop::TYPE_RANDOM, $reloaded->get_droptype());
        $this->assertFalse($reloaded->is_fixed());
        $this->assertTrue($reloaded->is_random());
    }

    public function test_invalid_drop_type_is_rejected(): void {
        $drop = $this->create_drop_instance();
        $drop->set_droptype(99);

        $this->assertFalse($drop->is_valid());
        $this->assertArrayHasKey('droptype', $drop->get_errors());
    }

    private function create_drop(array $record = []): drop {
        $generator = $this->getDataGenerator()->get_plugin_generator('block_stash');
        $item = $generator->create_item([
            'stash' => $generator->create_stash([
                'courseid' => $this->getDataGenerator()->create_course()->id,
            ]),
        ]);

        return $generator->create_drop($record + ['item' => $item]);
    }

    private function create_drop_instance(): drop {
        $generator = $this->getDataGenerator()->get_plugin_generator('block_stash');
        $item = $generator->create_item([
            'stash' => $generator->create_stash([
                'courseid' => $this->getDataGenerator()->create_course()->id,
            ]),
        ]);

        return new drop(null, (object) ['itemid' => $item->get_id(), 'name' => 'Drop 1']);
    }
}
