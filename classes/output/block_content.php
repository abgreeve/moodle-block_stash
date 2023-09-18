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
 * Block stash renderable.
 *
 * @package    block_stash
 * @copyright  2016 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use moodle_url;
use block_stash\external\user_item_summary_exporter;

class block_content implements renderable, templatable {

    protected $manager;

    protected $userid;

    public function __construct($manager, $userid = null) {
        $this->manager = $manager;
        $this->userid = $userid;
    }

    public function export_for_template(renderer_base $output) {
        global $USER;
        $data = array();

        $userid = isset($this->userid) ? $this->userid : $USER->id;


        $useritems = $this->manager->get_all_user_items_in_stash($userid);
        foreach ($useritems as $item) {
            $exporter = new user_item_summary_exporter([], [
                'context' => $this->manager->get_context(),
                'item' => $item->item,
                'useritem' => $item->useritem,
            ]);

            $exported = $exporter->export($output);

            $data['items'][] = $exported;
        }

        $swaphandler = new \block_stash\swap_handler($this->manager);
        $swapcount = $swaphandler->get_unread_requests($userid);

        $data['id'] = $this->manager->get_stash()->get_id();
        $data['canacquireitems'] = $this->manager->can_acquire_items();
        $data['canmanage'] = $this->manager->can_manage();
        $data['hasitems'] = !empty($useritems);
        $data['inventoryurl'] = new moodle_url('/blocks/stash/items.php', array('courseid' => $this->manager->get_courseid()));
        $data['reporturl'] = new moodle_url('/blocks/stash/report.php', array('courseid' => $this->manager->get_courseid()));
        $data['canswap'] = $this->manager->is_swapping_enabled();
        $data['tradecenterurl'] = new moodle_url('/blocks/stash/tradecenter.php', array('courseid' => $this->manager->get_courseid()));
        $data['offersurl'] = new moodle_url('/blocks/stash/swaprequests.php', array('courseid' => $this->manager->get_courseid()));
        $data['swapcount'] = $swapcount ?: false;
        if ($this->manager->leaderboard_enabled()) {
            $data['leaderboards_enabled'] = true;
            $boards = \core_component::get_component_classes_in_namespace('block_stash', 'local\leaderboards');
            $d = [];
            foreach (array_keys($boards) as $board) {
                $b = new $board($this->manager);
                $d[] = [
                        'html' => $output->render($b),
                        'title' => $b->get_title()
                ];
            }
            $data['leaderboards'] = $d;
        }
        return $data;
    }

}
