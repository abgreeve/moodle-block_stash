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
 * Collections page.
 *
 * @package    block_stash
 * @copyright  2024 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use \block_stash\local\stash_elements\collection_manager;

$courseid = required_param('courseid', PARAM_INT);
// $action = optional_param('action', '', PARAM_ALPHA);
$itemid = optional_param('collectionid', 0, PARAM_INT);

require_login($courseid);

$manager = \block_stash\manager::get($courseid);
$manager->require_enabled();
$manager->require_manage();

$url = new moodle_url('/blocks/stash/collections.php', ['courseid' => $courseid]);
list($title, $subtitle, $returnurl) = \block_stash\page_helper::setup_for_collections($url, $manager);

$collectionmanager = collection_manager::init($manager);
$data = $collectionmanager->get_collections_with_items();


// switch ($action) {
//     case 'delete':
//         require_sesskey();
//         $item = $manager->get_item($itemid);
//         $manager->delete_item($item);
//         redirect($url, get_string('theitemhasbeendeleted', 'block_stash', $item->get_name()));
//         break;
// }


$renderer = $PAGE->get_renderer('block_stash');
echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo $renderer->navigation($manager, 'collections');

$addurl = new moodle_url('/blocks/stash/collection_edit.php', ['courseid' => $courseid]);
$addbtn = $OUTPUT->single_button($addurl, get_string('addcollection', 'block_stash'), 'get', ['class' => 'singlebutton heading-button']);
$heading = get_string('collectionslist', 'block_stash') . $addbtn;

// TODO handle this more like removals
echo $OUTPUT->heading($heading, 3);

$table = new \block_stash\output\local\main_pages\collection_table('collectionsstable', $manager, $renderer);
$table->define_baseurl($url);
echo $table->out(50, false);

echo $OUTPUT->footer();
