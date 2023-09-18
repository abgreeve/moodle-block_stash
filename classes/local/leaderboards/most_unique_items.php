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
use context_course;
use renderable;
use renderer_base;
use templatable;

class most_unique_items implements renderable, templatable {
    private manager $manager;

    public function __construct($manager) {
        $this->manager = $manager;
    }

    public function get_title(): string {
        return 'Most Unique Items';
    }

    function export_for_template(renderer_base $output) {
        global $USER, $DB;

        $courseid = $this->manager->get_courseid();
        $context = context_course::instance($courseid);
        if ($this->manager->leaderboard_groups_enabled()) {
            $groupids = groups_get_user_groups($courseid, $USER->id)[0];
            $userids = array_keys($DB->get_records_sql(...groups_get_members_ids_sql($groupids, $context)));
        } else {
            $userids = array_keys(get_enrolled_users($context, '', 0, 'u.id'));
        }

        $fields = ['id', ...\core_user\fields::for_name()->get_required_fields()];
        $fields = implode(',', array_map(fn($f) => "u.$f", $fields));

        [$idsql, $idparams] = $DB->get_in_or_equal($userids);

        $sql = "SELECT $fields, ui.userid, COUNT(*) as num_items
                  FROM {block_stash_user_items} ui
                  JOIN {user} u
                    ON u.id=ui.userid
                 WHERE u.id $idsql
              GROUP BY ui.userid, $fields
              ORDER BY num_items DESC";
        $result = $DB->get_records_sql($sql, $idparams);

        foreach($result as $user) {
            $students[] = (object)[
                    'name' => fullname($user),
                    'num_items' => $user->num_items
            ];
        }

        return ['students' => $students];
    }
}
