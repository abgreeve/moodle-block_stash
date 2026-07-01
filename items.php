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
 * Items page.
 *
 * @package    block_stash
 * @copyright  2016 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$itemid = optional_param('itemid', 0, PARAM_INT);

require_login($courseid);

$manager = \block_stash\manager::get($courseid);
$manager->require_enabled();
$manager->require_manage();

$url = new moodle_url('/blocks/stash/items.php', array('courseid' => $courseid));
list($title, $subtitle, $returnurl) = \block_stash\page_helper::setup_for_item($url, $manager);

switch ($action) {
    case 'delete':
        require_sesskey();
        $item = $manager->get_item($itemid);
        $manager->delete_item($item);
        redirect($url, get_string('theitemhasbeendeleted', 'block_stash', $item->get_name()));
        break;
}

// Do some checks for plugins and provide a notification about new products.
$filterplugins = core_component::get_plugin_list('filter');
$nofilter = (!isset($filterplugins['shortcodes']));
$tinyplugins = core_component::get_plugin_list('tiny');
$notiny = (!isset($tinyplugins['stash']));

$notification = '';
if ($nofilter && $notiny) {
    $notification = $OUTPUT->notification(get_string('nofilterandnotiny', 'block_stash'), 'info');
} else if ($notiny) {
    $notification = $OUTPUT->notification(get_string('notiny', 'block_stash'), 'info');
}

$renderer = $PAGE->get_renderer('block_stash');
echo $OUTPUT->header();

echo $notification;
echo $OUTPUT->heading($title);
echo $renderer->navigation($manager, 'items');

$additemurl = new moodle_url('/blocks/stash/item_edit.php', ['courseid' => $courseid]);
$additembtn = $OUTPUT->single_button($additemurl, get_string('additem', 'block_stash'), 'get',
    ['class' => 'singlebutton heading-button']);
$randomdropurl = new moodle_url('/blocks/stash/random_drop_edit.php', ['courseid' => $courseid]);
$addrandomdropbtn = $OUTPUT->single_button($randomdropurl, get_string('addrandomdrop', 'block_stash'), 'get',
    ['class' => 'singlebutton heading-button']);

echo $OUTPUT->heading(get_string('itemslist', 'block_stash') . $additembtn, 3);

$itemstable = new \block_stash\output\items_table('itemstable', $manager, $renderer);
$itemstable->define_baseurl($url);
echo $itemstable->out(50, false);

echo $OUTPUT->heading(get_string('randomdrops', 'block_stash') . $addrandomdropbtn, 3);

$randomdropstable = new \block_stash\output\random_drops_table('randomdropstable', $manager, $renderer);
$randomdropstable->define_baseurl($url);
echo $randomdropstable->out(50, false);

list($altsnippetmaker, $warning) = \block_stash\helper::get_alternate_amd_snippet_maker($manager->get_context());
$altsnippetmaker = $altsnippetmaker->drop;
$warnings = $warning ? [$warning] : null;

$PAGE->requires->js_call_amd('block_stash/items', 'init', [$altsnippetmaker, $warnings]);

echo $OUTPUT->footer();
