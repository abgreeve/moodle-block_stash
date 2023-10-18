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
use renderable;
use renderer_base;
use templatable;
use html_writer;

class most_singular_item implements renderable, templatable {
    private manager $manager;
    private int $itemid;

    public function __construct($manager) {
        $this->manager = $manager;
    }

    public function set_itemid(int $itemid): void {
        $this->itemid = $itemid;
    }

    public function get_title(): string {
        if (!isset($this->itemid)) {
            return get_string('mostsingularitem', 'block_stash');
        }
        // Get item name
        $items = $this->get_options_data();
        foreach ($items as $item) {
            if ($item->get_id() == $this->itemid) {
                return get_string('mostsingularitemname', 'block_stash', $item->get_name());
            }
        }
        return get_string('mostsingularitem', 'block_stash');
    }

    private function get_leaderboard_data($limit) {
        global $DB;

        $userids = $this->manager->get_userids_for_leaderboard();

        $fields = ['id', ...\core_user\fields::for_name()->get_required_fields()];
        $fields = implode(',', array_map(fn($f) => "u.$f", $fields));

        [$idsql, $idparams] = $DB->get_in_or_equal($userids);
        $idparams[] = $this->manager->get_stash()->get_id();
        $idparams[] = $this->itemid;

        $sql = "SELECT $fields, ui.userid, ui.quantity as num_items
                  FROM {block_stash_user_items} ui
                  JOIN {block_stash_items} i ON i.id = ui.itemid
                  JOIN {user} u ON u.id = ui.userid
                 WHERE u.id $idsql
                   AND i.stashid = ?
                   AND i.id = ?
                   AND ui.quantity > 0
              ORDER BY num_items DESC";
        return $DB->get_records_sql($sql, $idparams, 0, $limit);

    }

    private function get_settings() {
        $allsettings = $this->manager->get_leaderboard_settings();
        foreach ($allsettings as $value) {
            if ($value->boardname == 'block_stash\local\leaderboards\most_singular_item') {
                return (array) $value;
            }
        }
        return [];
    }

    public function get_options_data() {
        // Return a list of all items for this stash.
        return $this->manager->get_items();
    }

    public function options_html($id) {
        global $PAGE;

        $options = $this->get_options_data();
        $selecteditemid = $this->get_settings()['options'] ?? $options[0]->get_id();

        $params = ['id' => $id, 'itemid' => $selecteditemid];
        $PAGE->requires->js_call_amd('block_stash/local/leaderboard/most_singular_item', 'init', $params);
        $html = html_writer::start_div('row pb-1');
        $html .= html_writer::label(get_string('item', 'block_stash'), 'msi_options', false, ['class' => 'form-label']);
        $html .= html_writer::start_div('col-sm-2');
        $html .= html_writer::start_tag('select', ['name' => 'msi_options', 'class' => 'block_stash_change_element form-control']);

        foreach ($options as $option) {
            if ($option->get_id() == $selecteditemid) {
                $html .= html_writer::tag('option', $option->get_name(), ['value' => $option->get_id(), 'selected' => true]);
            } else {
                $html .= html_writer::tag('option', $option->get_name(), ['value' => $option->get_id()]);
            }
        }
        $html .= html_writer::end_tag('select');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        return $html;
    }

    public function export_for_template(renderer_base $output) {
        $settings = $this->get_settings();
        if (empty($settings)) {
            return [];
        }

        if (empty($settings['options'])) {
            return [];
        }

        $this->set_itemid($settings['options']);

        $result = $this->get_leaderboard_data($settings['rowlimit']);

        if (!$result) {
            return [];
        }

        $data = ['title' => $this->get_title()];
        foreach($result as $user) {
            $students[] = (object)[
                    'name' => fullname($user),
                    'num_items' => $user->num_items
            ];
        }
        $data['students'] = $students;

        return $data;
    }
}
