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

namespace block_stash\local;

use block_stash\manager;
use renderable;
use renderer_base;
use templatable;

abstract class leaderboard_base implements renderable, templatable {

    protected manager $manager;
    protected bool $active;

    public function __construct($manager, $active) {
        $this->manager = $manager;
        $this->active = $active;
    }

    /**
     * This needs to be extended to return a language string for this leaderboard.
     *
     * @return string The title for this leaderboard
     */
    abstract public function get_title(): string;

    protected function get_settings() {
        $allsettings = $this->manager->get_leaderboard_settings();
        foreach ($allsettings as $value) {
            if ($value->boardname == get_class($this)) {
                return (array) $value;
            }
        }
        return [];
    }

    /**
     * Typically this does a database query to return results for the leaderboard.
     * NOTE: Must return an array of user classes with the following:
     * - All user names (firstname, lastname, middlename etc)
     * - num_items
     *
     * @param int $rowlimit A limit for the number of rows to be returned.
     * @return array of records
     */
    abstract protected function get_leaderboard_data(int $rowlimit): ?array;

    /**
     * A method which helpfully retrieves fields, sql, and base params for a leaderboard sql query.
     *
     * @return array userfields, sql for including relevant user ids, and params for the sql
     */
    public function get_base_leaderboard_sql_fields_and_params() {
        global $DB;

        $userids = $this->manager->get_userids_for_leaderboard();

        if (empty($userids)) {
            return ['','',[]];
        }

        $fields = ['id', ...\core_user\fields::for_name()->get_required_fields()];
        $fields = implode(',', array_map(fn($f) => "u.$f", $fields));

        [$idsql, $idparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $idparams['stashid'] = $this->manager->get_stash()->get_id();
        return [$fields, $idsql, $idparams];
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output Renderer.
     * @return array context data for template.
     */
    public function export_for_template(renderer_base $output): array {
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
        $data['active'] = $this->active;

        return $data;
    }
}
