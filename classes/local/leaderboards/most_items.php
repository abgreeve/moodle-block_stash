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

class most_items implements renderable, templatable {
    private manager $manager;

    public function __construct($manager) {
        $this->manager = $manager;
    }

    public function get_title(): string {
        return 'Most Items';
    }

    function export_for_template(renderer_base $output) {
        global $USER, $DB;

        $courseid = $this->manager->get_courseid();
        $context = context_course::instance($courseid);
        if ($this->manager->leaderboard_groups_enabled()) {
            $groupids = groups_get_user_groups($courseid, $USER->id)[0];
            $userids = $DB->get_records_sql(...groups_get_members_ids_sql($groupids, $context));
            $fields = ['id', ...\core_user\fields::for_name()->get_required_fields()];
            $users = $DB->get_records_list('user', 'id', array_keys($userids), '', implode(',', $fields));
        } else {
            $users = get_enrolled_users($context);
        }

        foreach($users as $user) {
            $students[] = (object)[
                    'name' => fullname($user),
                    'num_items' => count($this->manager->get_all_user_items_in_stash($user->id))
            ];
        }
        usort($students, fn($a, $b) => $b->num_items <=> $a->num_items);
        return ['students' => $students];
    }
}
