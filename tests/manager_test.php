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
 * Manager tests.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_stash\drop;
use block_stash\drop_pickup;
use block_stash\drop_pool_item;
use block_stash\external;
use block_stash\manager;

/**
 * Manager testcase class.
 *
 * @package    block_stash
 * @category   test
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class block_stash_manager_testcase extends advanced_testcase {

    public function setUp(): void {
        $this->resetAfterTest();
        parent::setUp();
    }

    public function test_fixed_drop_pickup_returns_awarded_item_and_preserves_behaviour(): void {
        [$manager, $user, $stash, $dropitem] = $this->create_manager_fixture();
        $drop = $this->create_drop($stash, $dropitem, drop::TYPE_FIXED);

        $awardeditem = $manager->pickup_drop($drop, $user->id);

        $this->assertSame($dropitem->get_id(), $awardeditem->get_id());
        $this->assertSame(1, (int) $manager->get_user_item($user->id, $dropitem->get_id())->get_quantity());

        $pickup = drop_pickup::get_relation($drop->get_id(), $user->id);
        $this->assertSame(1, $pickup->get_pickupcount());
        $this->assertGreaterThan(0, $pickup->get_lastpickup());
    }

    public function test_random_drop_pickup_awards_and_returns_pool_item(): void {
        [$manager, $user, $stash, $dropitem] = $this->create_manager_fixture();
        $poolitemone = $this->create_item($stash, 'Pool 1');
        $poolitemtwo = $this->create_item($stash, 'Pool 2');
        $drop = $this->create_drop($stash, $dropitem, drop::TYPE_RANDOM);
        $this->create_pool_item($drop, $poolitemone->get_id(), 1);
        $this->create_pool_item($drop, $poolitemtwo->get_id(), 10);

        $awardeditem = $manager->pickup_drop($drop, $user->id);

        $this->assertContains($awardeditem->get_id(), [$poolitemone->get_id(), $poolitemtwo->get_id()]);
        $this->assertSame(1, (int) $manager->get_user_item($user->id, $awardeditem->get_id())->get_quantity());
        $this->assertSame(0, (int) $manager->get_user_item($user->id, $dropitem->get_id())->get_quantity());

        $pickup = drop_pickup::get_relation($drop->get_id(), $user->id);
        $this->assertSame(1, $pickup->get_pickupcount());
        $this->assertGreaterThan(0, $pickup->get_lastpickup());
    }

    public function test_random_drop_external_pickup_returns_awarded_item_summary(): void {
        global $PAGE;

        [$manager, $user, $stash, $dropitem] = $this->create_manager_fixture();
        $poolitemone = $this->create_item($stash, 'Pool 1');
        $poolitemtwo = $this->create_item($stash, 'Pool 2');
        $drop = $this->create_drop($stash, $dropitem, drop::TYPE_RANDOM);
        $this->create_pool_item($drop, $poolitemone->get_id(), 1);
        $this->create_pool_item($drop, $poolitemtwo->get_id(), 10);

        $PAGE->set_context($manager->get_context());
        $PAGE->set_url(new moodle_url('/course/view.php', ['id' => $manager->get_courseid()]));

        $summary = external::pickup_drop($drop->get_id(), $drop->get_hashcode());

        $this->assertContains($summary->item->id, [$poolitemone->get_id(), $poolitemtwo->get_id()]);
        $this->assertSame($summary->item->id, $summary->useritem->itemid);
        $this->assertSame(1, (int) $summary->useritem->quantity);
        $this->assertSame(0, (int) $manager->get_user_item($user->id, $dropitem->get_id())->get_quantity());
    }

    public function test_create_or_update_drop_populates_stashid_for_fixed_drop(): void {
        [$manager, , $stash, $dropitem] = $this->create_manager_fixture();

        $drop = $manager->create_or_update_drop((object) [
            'id' => 0,
            'itemid' => $dropitem->get_id(),
            'name' => 'Managed fixed drop',
            'maxpickup' => 1,
            'pickupinterval' => HOURSECS,
            'hashcode' => random_string(6),
            'droptype' => drop::TYPE_FIXED,
        ]);

        $this->assertSame($stash->get_id(), $drop->get_stashid());

        $updated = $manager->create_or_update_drop((object) [
            'id' => $drop->get_id(),
            'name' => 'Managed fixed drop updated',
            'maxpickup' => 2,
            'pickupinterval' => DAYSECS,
            'hashcode' => $drop->get_hashcode(),
            'droptype' => drop::TYPE_FIXED,
        ]);

        $this->assertSame($stash->get_id(), $updated->get_stashid());
    }

    public function test_invalid_random_drop_pool_cannot_be_picked_up_or_award_item(): void {
        [$manager, $user, $stash, $dropitem] = $this->create_manager_fixture();
        $poolitem = $this->create_item($stash, 'Pool only');
        $drop = $this->create_drop($stash, $dropitem, drop::TYPE_RANDOM);
        $this->create_pool_item($drop, $poolitem->get_id(), 1);

        $this->expectException(coding_exception::class);
        try {
            $manager->pickup_drop($drop, $user->id);
        } finally {
            $this->assertSame(0, (int) $manager->get_user_item($user->id, $dropitem->get_id())->get_quantity());
            $this->assertSame(0, (int) $manager->get_user_item($user->id, $poolitem->get_id())->get_quantity());
            $this->assertFalse(drop_pickup::record_exists(['dropid' => $drop->get_id(), 'userid' => $user->id]));
        }
    }

    private function create_manager_fixture(): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $plugingenerator = $generator->get_plugin_generator('block_stash');
        $course = $generator->create_course();
        $user = $generator->create_user();
        $context = context_course::instance($course->id);
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $generator->enrol_user($user->id, $course->id, $studentroleid);
        role_change_permission($studentroleid, $context, manager::CAN_ACQUIRE_ITEMS, CAP_ALLOW);
        $this->setUser($user);

        $stash = $plugingenerator->create_stash(['courseid' => $course->id]);
        $dropitem = $plugingenerator->create_item(['stash' => $stash, 'name' => 'Fixed']);
        $manager = manager::get($course->id);
        $manager->set_enabled();

        return [$manager, $user, $stash, $dropitem];
    }

    private function create_drop(\block_stash\stash $stash, \block_stash\item $item, int $droptype): drop {
        $generator = $this->getDataGenerator()->get_plugin_generator('block_stash');

        return $generator->create_drop([
            'item' => $item,
            'droptype' => $droptype,
        ]);
    }

    private function create_item(\block_stash\stash $stash, string $name): \block_stash\item {
        $generator = $this->getDataGenerator()->get_plugin_generator('block_stash');
        return $generator->create_item(['stash' => $stash, 'name' => $name]);
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
