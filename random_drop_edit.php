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
 * Random drop creation and editing page.
 *
 * @package    block_stash
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$dropid = optional_param('dropid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
if (!$dropid) {
    $courseid = required_param('courseid', PARAM_INT);
} else if (!$courseid) {
    $courseid = \block_stash\manager::get_courseid_by_dropid($dropid);
}

require_login($courseid);

$manager = \block_stash\manager::get($courseid);
$manager->require_enabled();
$manager->require_manage();

$url = new moodle_url('/blocks/stash/random_drop_edit.php', [
    'courseid' => $manager->get_courseid(),
    'dropid' => $dropid,
]);
$drop = null;
if ($dropid) {
    $drop = new \block_stash\drop($dropid);
    if (!$drop->is_random()) {
        throw new coding_exception('Unexpected drop type.');
    }
}
$pagetitle = $drop ? get_string('editdrop', 'block_stash', $drop->get_name()) : get_string('addrandomdrop', 'block_stash');
list($title, $subtitle, $returnurl) = \block_stash\page_helper::setup_for_drop($url, $manager, $drop, $pagetitle);

$context = $manager->get_context();
$fileareaoptions = ['maxfiles' => 1];

$form = new \block_stash\form\random_drop($url->out(false), [
    'persistent' => $drop,
    'item' => null,
    'manager' => $manager,
    'fileareaoptions' => $fileareaoptions,
]);

$draftitemid = file_get_submitted_draft_itemid('image');
file_prepare_draft_area($draftitemid, $context->id, 'block_stash', \block_stash\drop::FILEAREA_IMAGE, $dropid, $fileareaoptions);
$form->set_data((object) ['image' => $draftitemid]);

if ($data = $form->get_data()) {
    $draftitemid = $data->image;
    unset($data->image);

    $drop = $manager->create_or_update_drop($data, $draftitemid);
    $manager->save_random_drop_pool($drop, \block_stash\form\random_drop::parse_pool_entries_from_data($data));
    redirect(new moodle_url('/blocks/stash/random_drop_edit.php', [
        'courseid' => $manager->get_courseid(),
        'dropid' => $drop->get_id(),
    ]));
} else if ($form->is_cancelled()) {
    redirect($returnurl);
}

$renderer = $PAGE->get_renderer('block_stash');
echo $OUTPUT->header();

echo $OUTPUT->heading($title, 2);
echo $renderer->navigation($manager, 'items');
if (!empty($subtitle)) {
    echo $OUTPUT->heading($subtitle . $OUTPUT->help_icon('drops', 'block_stash'), 3);
}

if (empty($drop)) {
    echo $renderer->drop_whats_that();
}

$form->display();

echo $OUTPUT->footer();
