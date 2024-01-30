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

        // Check if removal is possible. If not then clean up attempt and redirect back to view.
        if (!$removalhelper->can_user_lose_removal_items($details, $event->userid)) {
            redirect(new \moodle_url('/mod/quiz/view.php', ['id' => $event->contextinstanceid]), 'You do not have enough stash items to take this quiz.');
        }

        foreach ($details as $detail) {
            $removalhelper->remove_user_item($detail, $event->userid);
        }

        notification::warning('Stash items removed');
    }
}
