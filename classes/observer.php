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
 * Event observers used in block stash.
 *
 * @package    block_stash
 * @copyright  2024 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash;

use core\notification;

/**
 * Event observer for block_stash.
 */
class observer {

    /**
     * Triggered via user_enrolment_deleted event.
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function quiz_attempt_started(\mod_quiz\event\attempt_started $event) {

        // Is stash enabled in this course?
        $courseid = $event->courseid;
        $manager = \block_stash\manager::get($courseid);
        if (!$manager->is_enabled()) {
            return;
        }
        // Do I have an entry for this quiz?
        $removalhelper = new local\stash_elements\removal_helper($manager);
        $details = $removalhelper->get_removal_details($event->contextinstanceid);

        if (!$details) {
            return;
        }

        $tmep = array_map(function($detail) {
            return \html_writer::tag('li', $detail->name . ' (' . $detail->quantity . ')');
        }, $details);
        // Horrible string that breaks rules:
        $badstring = get_string('quiznotenoughitems', 'block_stash');
        $badsubstring = \html_writer::start_tag('ul');
        foreach ($tmep as $item) {
            $badsubstring .= $item;
        }
        $badsubstring .= \html_writer::end_tag('ul');
        $badstring .= $badsubstring;

        // Check if removal is possible. If not then clean up attempt and redirect back to view.
        if (!$removalhelper->can_user_lose_removal_items($details, $event->userid)) {
            redirect(new \moodle_url('/mod/quiz/view.php', ['id' => $event->contextinstanceid]), $badstring);
        }

        foreach ($details as $detail) {
            $removalhelper->remove_user_item($detail, $event->userid);
        }

        $anotherbadstring = get_string('quizitemsremoved', 'block_stash');
        $anotherbadstring .= $badsubstring;

        notification::warning($anotherbadstring);
    }

    public static function quiz_module_viewed(\core\event\course_module_viewed $event) {
        $courseid = $event->courseid;
        $manager = \block_stash\manager::get($courseid);
        if (!$manager->is_enabled()) {
            return;
        }
        // Do I have an entry for this quiz?
        $removalhelper = new local\stash_elements\removal_helper($manager);
        $details = $removalhelper->get_removal_details($event->contextinstanceid);

        if (!$details) {
            return;
        }

        if (!$manager->can_manage()) {
            return;
        }

        $url = new \moodle_url('/blocks/stash/removals.php', ['courseid' => $courseid]);
        notification::info(get_string('quizremovalconfigured', 'block_stash', $url->out(false)));
    }
}
