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
 * Trades table.
 *
 * @package    block_stash
 * @copyright  2017 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\output;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/tablelib.php');

use confirm_action;
use help_icon;
use html_writer;
use moodle_url;
use pix_icon;
use stdClass;
use table_sql;
use block_stash\trade as trademodel;
use block_stash\external\trade_exporter;

/**
 * Trades table class.
 *
 * @package    block_stash
 * @copyright  2017 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trades_table extends table_sql {

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
        $this->set_attribute('class', $uniqueid . ' tablewithitems generaltable generalbox');
        $this->manager = $manager;
        $this->renderer = $renderer;

        // Define columns.
        $this->define_columns(array(
            'name',
            'actions'
        ));
        $this->define_headers(array(
            get_string('tradename', 'block_stash'),
            get_string('actions')
        ));
        $this->define_help_for_headers([
            null,
            null
        ]);

        $sqlfields = trademodel::get_sql_fields('i', '');
        $sqlfrom = "{" . trademodel::TABLE . "} i";

        $this->sql = new stdClass();
        $this->sql->fields = $sqlfields;
        $this->sql->from = $sqlfrom;
        $this->sql->where = 'i.stashid = :stashid';
        $this->sql->params = ['stashid' => $this->manager->get_stash()->get_id()];

        // Define various table settings.
        $this->sortable(true, 'name', SORT_ASC);
        $this->no_sorting('actions');
        $this->collapsible(false);
    }

    /**
     * Formats the column.
     *
     * @param stdClass $row Table row.
     * @return string Output produced.
     */
    protected function col_actions($row) {
        global $OUTPUT;

        $url = new moodle_url('/blocks/stash/trade_edit_new.php');
        $url->params(['id' => $row->id, 'courseid' => $this->manager->get_courseid()]);
        $actionlink = $OUTPUT->action_link($url, '', null, null, new pix_icon('t/edit',
            get_string('edititem', 'block_stash', $row->name)));
        $actions[] = $actionlink;

        $action = new confirm_action(get_string('reallydeleteitem', 'block_stash'));
        $url = new moodle_url($this->baseurl);
        $url->params(['tradeid' => $row->id, 'action' => 'delete', 'sesskey' => sesskey()]);
        $actionlink = $OUTPUT->action_link($url, '', $action, null, new pix_icon('t/delete',
            get_string('deleteitem', 'block_stash', $row->name)));
        $actions[] = $actionlink;

        return implode(' ', $actions);
    }

    /**
     * Formats the column.
     *
     * @param stdClass $row Table row.
     * @return string Output produced.
     */
    protected function col_name($row) {
        $trade = new trademodel(null, $row);
        $exporter = new trade_exporter($trade, ['context' => $this->manager->get_context()]);
        $tradedata = $exporter->export($this->renderer);
        $link = html_writer::link('#', $trade->get_name(), [
            'rel' => 'block-stash-trade',
            'data-id' => $trade->get_id(),
            'data-trade' => json_encode($tradedata)
        ]);
        $str = $link;

        return $str;
    }

    /**
     * Formats the column.
     *
     * @param stdClass $row Table row.
     * @return string Output produced.
     */
    protected function col_maxnumber($row) {
        $str = $row->maxnumber;
        if ($row->maxnumber === null) {
            $str = get_string('unlimited', 'block_stash');
        }
        return $str;
    }

    /**
     * Override the default implementation to set a decent heading level.
     */
    public function print_nothing_to_display() {
        global $OUTPUT;
        if (method_exists($this, 'render_reset_button')) {
            // Compability with 2.9.
            echo $this->render_reset_button();
        }
        $this->print_initials_bar();
        echo $OUTPUT->heading(get_string('nothingtodisplay'), 4);
    }

    /**
     * Defines a help icon for the header
     *
     * Always use this function if you need to create header with sorting and help icon.
     *
     * @param renderable[] $helpicons An array of renderable objects to be used as help icons
     */
    public function define_help_for_headers($helpicons) {
        // Check if parent method exists.
        if (method_exists('table_sql', 'define_help_for_headers')) {
            parent::define_help_for_headers($helpicons);
        }
        // This method does not exist in the parent yet. Do nothing.
    }

}
