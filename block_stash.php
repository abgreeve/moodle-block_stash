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
 * Classes for block_stash.
 *
 * @package    block_stash
 * @copyright  2016 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Classes for block_stash.
 *
 * @package    block_stash
 * @copyright  2016 Adrian Greeve <adrian@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_stash extends block_base {

    /**
     * Core function used to initialize the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_stash');
    }

    /**
     * Applicable formats.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['course' => true, 'course-index-category' => false];
    }

    /**
     * Get content.
     *
     * @return stdClass
     */
    public function get_content() {
        if (isset($this->content)) {
            return $this->content;
        }
        $this->content = new stdClass();

        $manager = \block_stash\manager::get($this->page->course->id);
        if (!$manager->can_view()) {
            return $this->content;
        }

        $renderer = $this->page->get_renderer('block_stash');
        $page = new \block_stash\output\block_content($manager);

        $this->content->text = $renderer->render($page);
        $this->content->footer = '';

        return $this->content;

    }

    public function specialization() {
        $title = format_string(get_string('pluginname', 'block_stash'));
        $this->title = isset($this->config->title) ? format_string($this->config->title) : $title;
    }

    /**
     * Callback when a block is created.
     *
     * @return bool
     */
    public function instance_create() {
        // Reset the static cache.
        $manager = \block_stash\manager::get($this->page->course->id, true);
        return true;
    }

    public function instance_delete() {
        $context = context::instance_by_id($this->instance->parentcontextid);
        $manager = \block_stash\manager::get($context->instanceid, true);
        // block_stash_removal
        // block_stash_remove_items
        $removalhelper = new \block_stash\local\stash_elements\removal_helper($manager);
        $removalhelper->delete_all_instance_data();
        // block_stash_swap
        // block_stash_swap_detail
        $swaphandler = new \block_stash\swap_handler($manager);
        $swaphandler->delete_all_instance_data();
        // block_stash_trade
        // block_stash_trade_items
        // block_stash_lb_settings
        // block_stash_drop_pickups
        // block_stash_drops
        // block_stash_user_items
        // block_stash_items
        // block_stash
        $manager->delete_all_instance_data();

        return true;
    }

}
