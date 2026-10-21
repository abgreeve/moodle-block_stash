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
$swapid = optional_param('id', null, PARAM_INT);

require_login($courseid);

$manager = \block_stash\manager::get($courseid);
if (!$manager->is_swapping_enabled()) {
    $url = new moodle_url('/course/view.php', ['id' => $courseid]);
    redirect($url, get_string('tradesnotenabled', 'block_stash'), null, \core\output\notification::NOTIFY_WARNING);
}
$swaphandler = new \block_stash\swap_handler($manager);

$userid = $USER->id;

if (!is_null($swapid)) {
    require_sesskey();
    if ($swaphandler->veryify_my_swap_offers($swapid, $userid)) {
        $swaphandler->delete_swap($swapid);
    }
}

$renderer = $PAGE->get_renderer('block_stash');
$swaprequests = new \block_stash\output\swap_requests($manager, $swaphandler, $userid);

$url = new moodle_url('/blocks/stash/swaprequests.php', ['courseid' => $courseid]);
$PAGE->set_url($url);

$PAGE->set_pagelayout('course');
$context = $manager->get_context();
$PAGE->set_heading($context->get_context_name());

echo $OUTPUT->header();
$tradeurl = new moodle_url('/blocks/stash/tradecenter.php', ['courseid' => $courseid]);
$navdata = [
    'header' => get_string('offers','block_stash'),
    'tradeurl' => $tradeurl->out(false),
    'offerurl' => $url->out(false)
];

echo $OUTPUT->render_from_template('block_stash/local/tertiary_navigation/swap-nav', $navdata);
echo $renderer->render_swaprequests($swaprequests);

echo $OUTPUT->footer();
