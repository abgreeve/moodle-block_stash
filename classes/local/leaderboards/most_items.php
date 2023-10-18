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

class most_items implements renderable, templatable {
    private manager $manager;

    public function __construct($manager) {
        $this->manager = $manager;
    }

    public function get_title(): string {
        return get_string('mostitems', 'block_stash');
    }

    private function get_leaderboard_data($limit) {
        global $DB;

        $userids = $this->manager->get_userids_for_leaderboard();

        $fields = ['id', ...\core_user\fields::for_name()->get_required_fields()];
        $fields = implode(',', array_map(fn($f) => "u.$f", $fields));

        [$idsql, $idparams] = $DB->get_in_or_equal($userids);
        $idparams[] = $this->manager->get_stash()->get_id();

        $sql = "SELECT $fields, ui.userid, SUM(ui.quantity) as num_items
                  FROM {block_stash_user_items} ui
                  JOIN {block_stash_items} i ON i.id = ui.itemid
                  JOIN {user} u ON u.id = ui.userid
                 WHERE u.id $idsql
                   AND i.stashid = ?
              GROUP BY ui.userid, $fields
              ORDER BY num_items DESC";
        return $DB->get_records_sql($sql, $idparams, 0, $limit);

    }

    private function get_settings() {
        $allsettings = $this->manager->get_leaderboard_settings();
        foreach ($allsettings as $value) {
            if ($value->boardname == 'block_stash\local\leaderboards\most_items') {
                return (array) $value;
            }
        }
        return [];
    }

    public function export_for_template(renderer_base $output) {
        $settings = $this->get_settings();
        if (empty($settings)) {
            return [];
        }

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