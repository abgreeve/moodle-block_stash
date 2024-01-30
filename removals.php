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
 * Item removals page.
 *
 * @package    block_stash
 * @copyright  2024 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

require_login($courseid);

$manager = \block_stash\manager::get($courseid);
$manager->require_enabled();
$manager->require_manage();

$url = new moodle_url('/blocks/stash/removals.php', ['courseid' => $courseid]);

$pagetitle = 'Removal thing'; // Todo get_string()
list($title, $subtitle, $returnurl) = \block_stash\page_helper::setup_for_removal($url, $manager, $pagetitle);

$renderer = $PAGE->get_renderer('block_stash');

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $renderer->navigation($manager, 'removals');

$removeurl = new moodle_url('/blocks/stash/remove.php', ['courseid' => $courseid]);
$removebtn = $OUTPUT->single_button($removeurl, get_string('configureremoval', 'block_stash'), 'get', ['class' => 'singlebutton heading-button']);

$subtitle .= $removebtn;

if (!empty($subtitle)) {
    echo $OUTPUT->heading($subtitle, 3);
}

$removalhelper = new \block_stash\local\stash_elements\removal_helper($manager);
$removals = $removalhelper->get_all_removals();
print_object($removals);

echo $OUTPUT->footer();
