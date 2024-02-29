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
use confirm_action;
use moodle_url;
use action_link;
use pix_icon;

class removals implements renderable, templatable {

    protected $manager;

    function __construct($manager) {
        $this->manager = $manager;
    }

    private function add_to_jsondata($item, $quantity, $jsondata) {
        $data = json_decode($jsondata);
        $data[] = ['itemid' => $item, 'quantity' => $quantity];
        return json_encode($data);
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
        $data['courseid'] = $this->manager->get_courseid();
        $removalhelper = new \block_stash\local\stash_elements\removal_helper($this->manager);
        $tmepper = $removalhelper->get_full_removal_details();
        $newsuperdata = [];
        $jsondata = [];
        foreach ($tmepper as $tmepp) {
            if (!isset($newsuperdata[$tmepp->removalid])) {
                if ($tmepp->cmid > 0) {
                    [$course, $cm] = get_course_and_cm_from_cmid($tmepp->cmid, 'quiz');
                    $action = new confirm_action(get_string('reallydeleteitem', 'block_stash'));
                    $url = new moodle_url('removals.php', ['courseid' => $course->id, 'removalid' => $tmepp->removalid]);
                    $actionlink = new action_link($url, '',$action, [], new pix_icon('t/delete', 'delete thing'));
                    if (isset($jsondata[$tmepp->removalid])) {
                        $jsondata[$tmepp->removalid] = $this->add_to_jsondata($tmepp->itemid, $tmepp->quantity, $jsondata[$tmepp->removalid]);
                    } else {
                        $jsondata[$tmepp->removalid] = $this->add_to_jsondata($tmepp->itemid, $tmepp->quantity, json_encode([]));
                    }
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
                        ],
                        'editinfo' => $jsondata[$tmepp->removalid],
                        'deleteaction' => $actionlink->export_for_template($output)
                    ];
                    $newsuperdata[$tmepp->removalid] = $thestuff;
                }
            } else {
                if (isset($jsondata[$tmepp->removalid])) {
                    $jsondata[$tmepp->removalid] = $this->add_to_jsondata($tmepp->itemid, $tmepp->quantity, $jsondata[$tmepp->removalid]);
                } else {
                    $jsondata[$tmepp->removalid] = $this->add_to_jsondata($tmepp->itemid, $tmepp->quantity, []);
                }
                $newsuperdata[$tmepp->removalid]['items'][] = [
                    'itemid' => $tmepp->itemid,
                    'name' => $tmepp->name,
                    'quantity' => $tmepp->quantity
                ];
                $newsuperdata[$tmepp->removalid]['editinfo'] = $jsondata[$tmepp->removalid];
            }

        }
        $data['removals'] = array_values($newsuperdata);
        // print_object(array_values($newsuperdata));
        // print_object($data);

        return $data;
    }

}
