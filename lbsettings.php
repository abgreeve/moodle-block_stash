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
 * Leader board settings page.
 *
 * @package    block_stash
 * @copyright  2016 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);

$manager = \block_stash\manager::get($courseid);
$manager->require_enabled();
$manager->require_manage();

$url = new moodle_url('/blocks/stash/lbsettings.php', ['courseid' => $courseid]);
list($title, $subtitle, $returnurl) = \block_stash\page_helper::setup_for_lbsettings($url, $manager);


$renderer = $PAGE->get_renderer('block_stash');
$PAGE->requires->js_call_amd('block_stash/local/leaderboard/settings', 'init');

echo $OUTPUT->header();

echo $OUTPUT->heading($title);
echo $renderer->navigation($manager, 'leaderboards');

if (!empty($subtitle)) {
    echo $OUTPUT->heading($subtitle, 3);
}

// $manager->delete_leaderboard_settings('block_stash\local\leaderboards\most_items');

$settingsenabled = $manager->leaderboard_enabled();
$lbgroups = $manager->leaderboard_groups_enabled();

$boards = $manager->get_leaderboards();
$boardsettings = $manager->get_leaderboard_settings();
// print_object($boards);
// print_object($boardsettings);

$data = (object) ['courseid' => $courseid, 'lbenabled' => $settingsenabled, 'lbgroups' => $lbgroups, 'boards' => []];
foreach($boards as $key => $value) {
    $active = false;
    $rowlimit = 5;
    foreach($boardsettings as $boardvalues) {
        if ($boardvalues->boardname == $key) {
            $active = true;
            $rowlimit = $boardvalues->rowlimit;
        }
    }
    $data->boards[] = [
        'id' => html_writer::random_id(),
        'location' => $key,
        'title' => $value,
        'active' => $active,
        'rowlimit' => $rowlimit
    ];
}
// print_object($data);

echo $OUTPUT->render_from_template('block_stash/local/leaderboard_settings/mainsettings', $data);

echo $OUTPUT->footer();
