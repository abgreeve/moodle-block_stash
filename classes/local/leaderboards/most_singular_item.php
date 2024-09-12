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

namespace block_stash\local\leaderboards;

use block_stash\manager;
use block_stash\local\leaderboard_base as base;
use renderable;
use renderer_base;
use templatable;
use html_writer;

class most_singular_item extends base {
    private int $itemid;

    public function set_itemid(int $itemid): void {
        $this->itemid = $itemid;
    }

    public function get_title(): string {
        if (!isset($this->itemid)) {
            return get_string('mostsingularitem', 'block_stash');
        }
        // Get item name
        $item = $this->manager->get_item($this->itemid);
        return get_string('mostsingularitemname', 'block_stash', $item->get_name());
    }

    protected function get_leaderboard_data(int $limit): ?array {
        global $DB;

        [$fields, $idsql, $idparams] = $this->get_base_leaderboard_sql_fields_and_params();
        if (empty($idparams)) {
            return null;
        }
        $idparams['itemid'] = $this->itemid;

        $sql = "SELECT $fields, ui.userid, ui.quantity as num_items
                  FROM {block_stash_user_items} ui
                  JOIN {block_stash_items} i ON i.id = ui.itemid
                  JOIN {user} u ON u.id = ui.userid
                 WHERE u.id $idsql
                   AND i.stashid = :stashid
                   AND i.id = :itemid
                   AND ui.quantity > 0
              ORDER BY num_items DESC";
        return $DB->get_records_sql($sql, $idparams, 0, $limit);
    }

    public function options_html($id) {
        global $PAGE;

        $options = $this->manager->get_items();
        if (empty($options)) {
            return get_string('nosingularitem', 'block_stash');
        }

        $selecteditemid = $this->get_settings()['options'] ?? $options[0]->get_id();

        $params = ['id' => $id, 'itemid' => $selecteditemid];
        $PAGE->requires->js_call_amd('block_stash/local/leaderboard/most_singular_item', 'init', $params);
        $html = html_writer::start_div('row pb-1');
        $html .= html_writer::label(get_string('item', 'block_stash'), 'msi_options', false, ['class' => 'form-label']);
        $html .= html_writer::start_div('col-sm-2');
        $html .= html_writer::start_tag('select', ['name' => 'msi_options', 'class' => 'block_stash_change_element form-control']);

        foreach ($options as $option) {
            $attributes = ['value' => $option->get_id()];
            if ($option->get_id() == $selecteditemid) {
                $attributes['selected'] = true;
            }
            $html .= html_writer::tag('option', $option->get_name(), $attributes);
        }
        $html .= html_writer::end_tag('select');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        return $html;
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output Renderer.
     * @return array context data for template.
     */
    public function export_for_template(renderer_base $output): array {
        $settings = $this->get_settings();
        if (empty($settings) || empty($settings['options'])) {
            return [];
        }
        $this->set_itemid($settings['options']);

        return parent::export_for_template($output);
    }
}
