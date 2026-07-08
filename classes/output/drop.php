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
 * Drop renderable.
 *
 * @package    block_stash
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\output;
defined('MOODLE_INTERNAL') || die();

use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

use block_stash\drop as dropmodel;
use block_stash\item;
use block_stash\manager;
use block_stash\external\drop_exporter;
use block_stash\external\item_exporter;

/**
 * Drop renderable class.
 *
 * @package    block_stash
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class drop implements renderable, templatable {

    /** @var drop The drop. */
    protected $drop;

    /** @var item|null The fixed item, when applicable. */
    protected $item;

    /** @var manager The manager. */
    protected $manager;

    /**
     * Constructor.
     *
     * @param dropmodel $drop The drop.
     * @param item|null $item The fixed item, when applicable.
     * @param manager $manager The manager.
     */
    public function __construct(dropmodel $drop, ?item $item, manager $manager) {
        $this->drop = $drop;
        $this->item = $item;
        $this->manager = $manager;
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output Renderer.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $exporter = new drop_exporter($this->drop, ['context' => $this->manager->get_context()]);
        $data = $exporter->export($output);
        $data->item = $this->export_item_for_template($output);
        return $data;
    }

    /**
     * Export the item data used by drop templates.
     *
     * Random drops do not have a fixed item, so we provide a generic preview
     * object using the drop name.
     *
     * @param renderer_base $output Renderer.
     * @return stdClass
     */
    protected function export_item_for_template(renderer_base $output): stdClass {
        if ($this->item !== null) {
            $exporter = new item_exporter($this->item, ['context' => $this->manager->get_context()]);
            return $exporter->export($output);
        }

        return (object) [
            'id' => 0,
            'name' => $this->drop->get_name(),
            'imageurl' => $this->get_random_drop_image_url($output),
        ];
    }

    /**
     * Get the image URL representing the unopened random drop.
     *
     * Uses the teacher-configured image for this drop when one has been uploaded,
     * falling back to the generic mystery drop placeholder otherwise.
     *
     * @param renderer_base $output Renderer.
     * @return string
     */
    protected function get_random_drop_image_url(renderer_base $output): string {
        $context = $this->manager->get_context();
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'block_stash', dropmodel::FILEAREA_IMAGE, $this->drop->get_id(), '', false);
        $file = array_pop($files);

        if ($file) {
            return moodle_url::make_pluginfile_url($context->id, 'block_stash', dropmodel::FILEAREA_IMAGE,
                $this->drop->get_id(), '/', $file->get_filename())->out(false);
        }

        return $output->image_url('random-item-md', 'block_stash')->out(false);
    }
}
