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
 * Trade center page
 *
 * @package    block_stash
 * @copyright  2023 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$itemid = optional_param('item', null, PARAM_INT);
$userid = optional_param('user', null, PARAM_INT);

require_login($courseid);

$manager = \block_stash\manager::get($courseid);
if (!$manager->is_swapping_enabled()) {
    $url = new moodle_url('/course/view.php', ['id' => $courseid]);
    redirect($url, get_string('tradesnotenabled', 'block_stash'), null, \core\output\notification::NOTIFY_WARNING);
}
$swaphandler = new \block_stash\swap_handler($manager);

$renderer = $PAGE->get_renderer('block_stash');
$PAGE->requires->js_call_amd('block_stash/new-item-select', 'init');
$PAGE->requires->js_call_amd('block_stash/user-select', 'init');

$data = ['zerostate' => true];
$context = $manager->get_context();

if (!is_null($itemid)) {
    $item = $manager->get_item($itemid);
    $exporter = new \block_stash\external\item_exporter($item, ['context' => $context]);
    $data['item'] = $exporter->export($renderer);
    $data['users'] = $swaphandler->get_users_with_item($itemid, $USER->id);
    array_map(function($user) use ($OUTPUT) {
        $user->pic = $OUTPUT->user_picture($user);
        $user->fullname = \fullname($user);
        return $user;
    }, $data['users']);
    $data['zerostate'] = false;
    $data['other']['myuserid'] = $USER->id;
}

if (!is_null($userid)) {
    if (!is_enrolled($context, $userid, '', true)) {
        throw new moodle_exception('invaliduserid');
    }
    $user = \core_user::get_user($userid, '*', MUST_EXIST);
    $data['id'] = $userid;
    $data['userview'] = true;
    $data['fullname'] = \fullname($user);
    $data['pic'] = $OUTPUT->user_picture($user, ['size' => 100]);
    $data['zerostate'] = false;
    $data['other']['myuserid'] = $USER->id;
}
$data['other']['courseid'] = $courseid;

$userid = $USER->id;
// $data = $swaphandler->get_users_swap_requests($userid);

$url = new moodle_url('/blocks/stash/tradecenter.php', ['courseid' => $courseid]);
$PAGE->set_url($url);

$PAGE->set_pagelayout('course');
$PAGE->set_heading($context->get_context_name());

echo $OUTPUT->header();
// Go for tertiary nav here.
$offerurl = new moodle_url('/blocks/stash/swaprequests.php', ['courseid' => $courseid]);
$navdata = [
    'header' => 'Trades',
    'tradeurl' => $url->out(false),
    'offerurl' => $offerurl->out(false),
    'other' => $data['other']
];

echo $OUTPUT->render_from_template('block_stash/local/tertiary_navigation/swap-nav', $navdata);
// echo $OUTPUT->heading('Trades');

// TODO put this into a renderer.
// print_object($data);
echo $OUTPUT->render_from_template('block_stash/local/swap/trade_center', $data);

echo $OUTPUT->footer();
