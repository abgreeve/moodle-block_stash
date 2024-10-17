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
 * Collection edit page.
 *
 * @package    block_stash
 * @copyright  2024 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$id = optional_param('id', '0', PARAM_INT);

require_login($courseid);

$manager = \block_stash\manager::get($courseid);
$manager->require_enabled();
$manager->require_manage();

$context = context_course::instance($courseid);
$url = new moodle_url('/blocks/stash/collection_edit.php', ['courseid' => $courseid, 'id' => $id]);

list($title, $subtitle, $returnurl) = \block_stash\page_helper::setup_for_collections($url, $manager);


$renderer = $PAGE->get_renderer('block_stash');
$customdata = ['manager' => $manager];
$form = new \block_stash\form\collection($url->out(false), $customdata);

// $data = new stdClass();
// if (!is_null($item)) {
//     $data->id = $item->get_id();
//     $data->detail = $item->get_detail();
//     $data->detailformat = $item->get_detailformat();
// } else {
//     $data->id = $id;
//     $data->detail = '';
//     $data->detailformat = 1;
// }
// $form->set_data((object) array('image' => $draftitemid, 'detail_editor' => $data->detail_editor));

if ($data = $form->get_data()) {
    // print_object($data);
    // die();
    $collectionrepository = new \block_stash\local\repositories\collection();
    $collectionmanager = new \block_stash\local\stash_elements\collection_manager($manager, $collectionrepository);
    $collectionmanager->create_collection($data);

    redirect($returnurl);

} else if ($form->is_cancelled()) {
    redirect($returnurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title, 2);
echo $renderer->navigation($manager, 'collections');
if (!empty($subtitle)) {
    echo $OUTPUT->heading($subtitle, 3);
}
$form->display();
echo $OUTPUT->footer();
