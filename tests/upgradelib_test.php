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
 * Upgrade library tests.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/stash/db/upgrade.php');

use block_stash\drop;
use block_stash\drop_pool_item;

/**
 * Upgrade library testcase class.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_stash_upgradelib_testcase extends advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    public function test_backfill_drop_stashids_uses_item_relationship(): void {
        global $DB;

        $generator = $this->getDataGenerator()->get_plugin_generator('block_stash');
        $course = $this->getDataGenerator()->create_course();
        $stash = $generator->create_stash(['courseid' => $course->id]);
        $fixeditem = $generator->create_item(['stash' => $stash, 'name' => 'Fixed item']);
        $poolitem = $generator->create_item(['stash' => $stash, 'name' => 'Pool item']);

        $fixeddrop = $generator->create_drop(['item' => $fixeditem, 'name' => 'Fixed drop']);
        $randomdrop = new drop(null, (object) [
            'stashid' => $stash->get_id(),
            'itemid' => 0,
            'name' => 'Random drop',
            'hashcode' => random_string(6),
            'droptype' => drop::TYPE_RANDOM,
        ]);
        $randomdrop->create();
        $poolentry = new drop_pool_item(null, (object) [
            'dropid' => $randomdrop->get_id(),
            'itemid' => $poolitem->get_id(),
            'weight' => 1,
        ]);
        $poolentry->create();

        $DB->set_field(drop::TABLE, 'stashid', 0, ['id' => $fixeddrop->get_id()]);
        $DB->set_field(drop::TABLE, 'stashid', 0, ['id' => $randomdrop->get_id()]);

        block_stash_upgrade_backfill_drop_stashids();

        $this->assertSame($stash->get_id(), (int) $DB->get_field(drop::TABLE, 'stashid', ['id' => $fixeddrop->get_id()]));
        $this->assertSame($stash->get_id(), (int) $DB->get_field(drop::TABLE, 'stashid', ['id' => $randomdrop->get_id()]));
    }
}
