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

class most_unique_items extends base {

    public function get_title(): string {
        return get_string('mostuniqueitems', 'block_stash');
    }

    protected function get_leaderboard_data(int $limit): ?array {
        global $DB;

        [$fields, $idsql, $idparams] = $this->get_base_leaderboard_sql_fields_and_params();
        if (empty($idparams)) {
            return null;
        }

        $sql = "SELECT $fields, ui.userid, COUNT(*) as num_items
                  FROM {block_stash_user_items} ui
                  JOIN {block_stash_items} i ON i.id = ui.itemid
                  JOIN {user} u ON u.id = ui.userid
                 WHERE u.id $idsql
                   AND i.stashid = :stashid
                   AND ui.quantity <> 0
              GROUP BY ui.userid, $fields
              ORDER BY num_items DESC";
        return $DB->get_records_sql($sql, $idparams, 0, $limit);

    }
}
