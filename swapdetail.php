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
 * Swap requests page.
 *
 * @package    block_stash
 * @copyright  2021 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$swapid = required_param('id', PARAM_INT);
$decision = optional_param('decision', null, PARAM_RAW); // TODO change to something else.


require_login($courseid);

$manager = \block_stash\manager::get($courseid);
$swaphandler = new \block_stash\swap_handler($manager);

$userid = $USER->id;
// TODO: Make sure to check that this was done by the owner and not some other user.
if (!$swaphandler->veryify_my_swap_requests($swapid, $userid)) {
    echo 'You don\'t belong here';
    exit();
}

// Set the swap invite as viewed.
$swaphandler->view_swap_invite($swapid);

$renderer = $PAGE->get_renderer('block_stash');


if (isset($decision)) {
    require_sesskey();
    if ($decision == \block_stash\swap::BLOCK_STASH_SWAP_DECLINE) {
        $swaphandler->decline_swap($swapid);
        // Redirect to requests page.
        redirect(new moodle_url('/blocks/stash/swaprequests.php', ['courseid' => $courseid]));
    }
    if ($decision == \block_stash\swap::BLOCK_STASH_SWAP_APPROVE) {
        $swaphandler->swap_items($swapid);
        // Redirect to requests page.
        redirect(new moodle_url('/blocks/stash/swaprequests.php', ['courseid' => $courseid]));
    }
}

$data = $swaphandler->get_swap_details($swapid, $userid);

$url = new moodle_url('/blocks/stash/swapdetail.php', ['courseid' => $courseid, 'id' => $swapid]);
$PAGE->set_url($url);
$PAGE->set_pagelayout('course');
$context = $manager->get_context();
$PAGE->set_heading($context->get_context_name());

echo $OUTPUT->header();
$tradeurl = new moodle_url('/blocks/stash/tradecenter.php', ['courseid' => $courseid]);
$offerurl = new moodle_url('/blocks/stash/swaprequests.php', ['courseid' => $courseid]);
$navdata = [
    'header' => 'Trade details',
    'tradeurl' => $tradeurl->out(false),
    'offerurl' => $offerurl->out(false)
];

echo $OUTPUT->render_from_template('block_stash/local/tertiary_navigation/swap-nav', $navdata);

$swapdetails = new \block_stash\output\swap_details($manager, $swaphandler, $swapid, $userid);


echo $renderer->render_swapdetail($swapdetails);

echo $OUTPUT->footer();
