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
 * Removal table templatable.
 *
 * @package    block_stash
 * @copyright  2016 Adrian Greeve <adriangreeve.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\output\local\configuration;

use renderable;
use renderer_base;
use templatable;

class removals implements renderable, templatable {

    protected $manager;

    function __construct($manager) {
        $this->manager = $manager;
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output Renderer.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $data = [];
        $data['removals'] = [];
        $removalhelper = new \block_stash\local\stash_elements\removal_helper($this->manager);
        $tmepper = $removalhelper->get_the_full_whammy();
        $newsuperdata = [];
        foreach ($tmepper as $tmepp) {
            // print_object($tmepp);
            if (!isset($newsuperdata[$tmepp->removalid])) {
                [$course, $cm] = get_course_and_cm_from_cmid($tmepp->cmid, 'quiz');
                $thestuff = [
                    'removalid' => $tmepp->removalid,
                    'cmid' => $tmepp->cmid,
                    'cmname' => $cm->get_formatted_name(),
                    'courseid' => $course->id,
                    'items' => [
                        [
                            'itemid' => $tmepp->itemid,
                            'name' => $tmepp->name,
                            'quantity' => $tmepp->quantity
                        ]
                    ]
                ];
                $newsuperdata[$tmepp->removalid] = $thestuff;
            } else {
                $newsuperdata[$tmepp->removalid]['items'][] = [
                    'itemid' => $tmepp->itemid,
                    'name' => $tmepp->name,
                    'quantity' => $tmepp->quantity
                ];
            }
        }
        $data['removals'] = array_values($newsuperdata);
        // print_object(array_values($newsuperdata));
        // print_object($data);

        return $data;
    }

}
