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
 * Random drops table.
 *
 * @package    block_stash
 * @copyright  2016 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\output;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/tablelib.php');

use block_stash\drop as dropmodel;
use block_stash\external\drop_exporter;
use confirm_action;
use html_writer;
use moodle_url;
use pix_icon;
use stdClass;
use table_sql;

/**
 * Random drops table class.
 *
 * @package    block_stash
 * @copyright  2016 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class random_drops_table extends table_sql {

    /** @var block_stash\manager The manager. */
    protected $manager;

    /** @var block_stash\renderer The renderer. */
    protected $renderer;

    /**
     * Constructor.
     *
     * @param string $uniqueid Unique ID.
     * @param manager $manager The manager.
     */
    public function __construct($uniqueid, $manager, $renderer) {
        parent::__construct($uniqueid);
        $this->set_attribute('class', $uniqueid . ' generaltable generalbox');
        $this->manager = $manager;
        $this->renderer = $renderer;

        $this->define_columns([
            'name',
            'status',
            'shortcode',
            'actions',
        ]);
        $this->define_headers([
            get_string('dropname', 'block_stash'),
            get_string('status'),
            get_string('snippet', 'block_stash'),
            get_string('actions'),
        ]);

        $this->sql = new stdClass();
        $this->sql->fields = dropmodel::get_sql_fields('d', '');
        $this->sql->from = '{' . dropmodel::TABLE . '} d';
        $this->sql->where = 'd.stashid = :stashid AND d.droptype = :droptype';
        $this->sql->params = [
            'stashid' => $this->manager->get_stash()->get_id(),
            'droptype' => dropmodel::TYPE_RANDOM,
        ];

        $this->sortable(true, 'name', SORT_ASC);
        $this->no_sorting('status');
        $this->no_sorting('shortcode');
        $this->no_sorting('actions');
        $this->collapsible(false);
    }

    /**
     * Formats the actions column.
     *
     * @param stdClass $row Table row.
     * @return string Output produced.
     */
    protected function col_actions($row) {
        global $OUTPUT;

        $actions = [];

        $url = new moodle_url('/blocks/stash/random_drop_edit.php', [
            'courseid' => $this->manager->get_courseid(),
            'dropid' => $row->id,
        ]);
        $actions[] = $OUTPUT->action_link($url, '', null, null, new pix_icon('t/edit',
            get_string('editdrop', 'block_stash', $row->name)));

        $action = new confirm_action(get_string('reallydeletedrop', 'block_stash'));
        $url = new moodle_url($this->baseurl);
        $url->params(['action' => 'delete', 'dropid' => $row->id, 'sesskey' => sesskey()]);
        $actions[] = $OUTPUT->action_link($url, '', $action, null, new pix_icon('t/delete',
            get_string('deletedrop', 'block_stash', $row->name)));

        return implode(' ', $actions);
    }

    /**
     * Formats the name column.
     *
     * @param stdClass $row Table row.
     * @return string Output produced.
     */
    protected function col_name($row) {
        $url = new moodle_url('/blocks/stash/random_drop_edit.php', [
            'courseid' => $this->manager->get_courseid(),
            'dropid' => $row->id,
        ]);

        return html_writer::link($url, format_string($row->name, true, ['context' => $this->manager->get_context()]));
    }

    /**
     * Formats the shortcode column.
     *
     * Rather than a static shortcode, this renders a trigger that opens the same
     * configurable snippet dialogue used for fixed item drops, so teachers can pick
     * the appearance (image, button, or both) and the button text.
     *
     * @param stdClass $row Table row.
     * @return string Output produced.
     */
    protected function col_shortcode($row) {
        $drop = new dropmodel(null, $row);

        $exporter = new drop_exporter($drop, ['context' => $this->manager->get_context()]);
        $dropdata = $exporter->export($this->renderer);

        $droprenderable = new drop($drop, null, $this->manager);
        $itemdata = $droprenderable->export_for_template($this->renderer)->item;

        return html_writer::link('#', get_string('getsnippet', 'block_stash'), [
            'rel' => 'block-stash-drop',
            'data-id' => $drop->get_id(),
            'data-json' => json_encode($dropdata),
            'data-item' => json_encode($itemdata),
        ]);
    }

    /**
     * Formats the status column.
     *
     * @param stdClass $row Table row.
     * @return string Output produced.
     */
    protected function col_status($row) {
        $drop = new dropmodel(null, $row);
        if ($drop->is_valid_random_pool()) {
            return get_string('enabled', 'block_stash');
        }

        $messages = [];
        $map = [
            'minitems' => 'randomdroppoolnotenoughitems',
            'maxitems' => 'randomdroppooltoomanyitems',
            'duplicateitems' => 'randomdroppoolduplicateitems',
            'missingitems' => 'randomdroppoolinvaliditems',
            'stashmismatch' => 'randomdroppoolwrongstashitems',
            'scarceitems' => 'randomdroppoolscarceitems',
        ];

        foreach (array_keys($drop->get_random_pool_validation_errors()) as $key) {
            if (!isset($map[$key])) {
                continue;
            }
            $messages[] = get_string($map[$key], 'block_stash');
        }

        if (empty($messages)) {
            return get_string('invaliddata', 'error');
        }

        return implode(' ', $messages);
    }

    /**
     * Override the default implementation to set a decent heading level.
     */
    public function print_nothing_to_display() {
        global $OUTPUT;
        if (method_exists($this, 'render_reset_button')) {
            // Compatibility with 2.9.
            echo $this->render_reset_button();
        }
        $this->print_initials_bar();
        echo $OUTPUT->heading(get_string('nothingtodisplay'), 4);
    }
}
