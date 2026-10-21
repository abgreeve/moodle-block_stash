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
 * Swap details renderable
 *
 * @package    block_stash
 * @copyright  2023 Adrian Greeve - adriangreeve.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\output;
defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use stdClass;
use moodle_url;

use block_stash\manager;
use block_stash\swap_handler;

/**
 * Trade renderable class.
 *
 * @package    block_stash
 * @copyright  2017 Adrian Greeve - adriangreeve.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class swap_details implements renderable, templatable {

    /** @var manager The manager. */
    protected $manager;

    /** @var tradeitems items related to this trade */
    protected $swaphandler;

    protected $swapid;

    protected $userid;

    protected $declineurl;

    /**
     * Constructor.
     *
     * @param manager $manager The manager.
     * @param swap_handler $swaphander A class for handling swaps.
     */
    public function __construct(manager $manager, swap_handler $swaphandler, int $swapid, int $userid) {
        $this->manager = $manager;
        $this->swaphandler = $swaphandler;
        $this->swapid = $swapid;
        $this->userid = $userid;

        $params = ['id' => $this->swapid,'courseid' => $this->manager->get_courseid()];
        $this->declineurl = new moodle_url('/blocks/stash/swapdetail.php', $params);
        $this->declineurl->params(['decision' => \block_stash\swap::BLOCK_STASH_SWAP_DECLINE, 'sesskey' => sesskey()]);
    }

    private function get_user_details(array $swapdata) {
        global $OUTPUT;

        if (!isset($swapdata['otheritems'][0])) {
            return '';
        }
        $other = $swapdata['otheritems'][0];
        $user = new stdClass();
        foreach(\core_user\fields::get_picture_fields() as $field) {
            $user->$field = $other->$field;
        }
        $user->id = $other->userid;
        $picture = $OUTPUT->user_picture((object) $user, ['size' => 100]);
        $user->picture = $picture;
        $user->fullname = \fullname($user);

        return $user;
    }

    private function get_my_items(array $swapdata) {
        return $this->get_items($swapdata['myitems']);
    }

    private function get_their_items(array $swapdata) {
        return $this->get_items($swapdata['otheritems']);
    }

    private function get_items(array $items) {
        $fulldata = [];
        foreach ($items as $item) {
            // print_object($item);
            $datatemp = [
                'id' => $item->itemid,
                'name' => $item->name,
                'quantity' => $item->quantity,
            ];
            $picture = moodle_url::make_pluginfile_url($this->manager->get_context()->id, 'block_stash', 'item', $item->itemid,
                '/', 'image');
            $datatemp['imageurl'] = $picture->out(false);
            $fulldata[] = $datatemp;
        }
        return $fulldata;
    }

    public function use_offer_decline_url() {
        $params = ['id' => $this->swapid,'courseid' => $this->manager->get_courseid()];
        $this->declineurl = new moodle_url('/blocks/stash/swapofferdetail.php', $params);
        $this->declineurl->params(['decision' => \block_stash\swap::BLOCK_STASH_SWAP_DECLINE, 'sesskey' => sesskey()]);
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output Renderer.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = [];

        $swapbasics = $this->swaphandler->get_swap_details($this->swapid, $this->userid);
        // TODO: check that there is something to trade.
        $data['user'] = $this->get_user_details($swapbasics);
        $data['mine'] = $this->get_my_items($swapbasics);
        $data['theirs'] = $this->get_their_items($swapbasics);
        $data['requestpossible'] = $swapbasics['requestpossible'];
        $params = ['id' => $this->swapid,'courseid' => $this->manager->get_courseid()];
        $accepturl = new moodle_url('/blocks/stash/swapdetail.php', $params);
        $accepturl->params(['decision' => \block_stash\swap::BLOCK_STASH_SWAP_APPROVE, 'sesskey' => sesskey()]);
        $data['accepturl'] = $accepturl->out(false);
        $data['declineurl'] = $this->declineurl->out(false);

        return $data;
    }

}
