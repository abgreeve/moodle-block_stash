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
 * Item removals
 *
 * @package    block_stash
 * @copyright  2023 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$removalid = optional_param('removalid', 0, PARAM_INT);
$itemid = optional_param('itemid', 0, PARAM_INT);

require_login($courseid);

$manager = \block_stash\manager::get($courseid);
$manager->require_enabled();
$manager->require_manage();

$url = new moodle_url('/blocks/stash/remove.php', ['courseid' => $manager->get_courseid(), 'removalid' => $removalid]);

$pagetitle = 'Removal thing'; // Todo get_string()

list($title, $subtitle, $returnurl) = \block_stash\page_helper::setup_for_removal($url, $manager, $pagetitle);

$renderer = $PAGE->get_renderer('block_stash');
echo $OUTPUT->header();

echo $OUTPUT->heading($title, 2);
echo $renderer->navigation($manager, 'items');
echo $OUTPUT->heading($subtitle . $OUTPUT->help_icon('drops', 'block_stash'), 3); //  TODO Help is good. Update this to help with item removals.


echo $OUTPUT->footer();
