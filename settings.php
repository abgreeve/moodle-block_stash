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
 * @copyright  2025 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$deleteallusers = optional_param('delete', 0, PARAM_INT);

require_login($courseid);

$manager = \block_stash\manager::get($courseid);
$manager->require_enabled();
$manager->require_manage();

$notification = false;
if ($deleteallusers === 1 && confirm_sesskey()) {
    $manager->delete_all_users_items();
    $swaps = new \block_stash\swap_handler($manager);
    // This only removes swap information, which we want, for this course.
    $swaps->delete_all_instance_data();
    $notification = true;
}

$url = new moodle_url('/blocks/stash/settings.php', ['courseid' => $courseid]);
list($title, $subtitle, $returnurl) = \block_stash\page_helper::setup_for_settings($url, $manager);

$renderer = $PAGE->get_renderer('block_stash');

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $renderer->navigation($manager, 'settings');

if (!empty($subtitle)) {
    echo $OUTPUT->heading($subtitle, 3);
}

if ($notification) {
    echo $OUTPUT->notification(get_string('alluseritemsreset', 'block_stash'), 'notifysuccess');
}

$settingspage = new \block_stash\output\local\main_pages\settings_page($courseid);
echo $renderer->render_reset_user_button($settingspage);

echo $OUTPUT->footer();
