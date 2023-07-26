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
 * Full trade renderable.
 *
 * @package    block_stash
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash\output;
defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use help_icon;

use block_stash\trade;

/**
 * Full trade renderable class.
 *
 * This can be used to render a full trade for such things as the trade form
 *
 * @package    block_stash
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fulltrade implements renderable, templatable {

    /** @var trade The trade object. */
    protected $trade;

    protected $tradeitems;

    protected $courseid;

    protected $stashid;

    protected $gainhelp;

    protected $losshelp;

    protected $titlehelp;

    /** @var Removes the save and cancel buttons from the form. Used for dialogue forms. */
    protected $removeformbuttons = false;

    /**
     * Full trade constructor.
     *
     * @param int        $stashid    Stash ID
     * @param trade      $trade      Trade details
     * @param array      $tradeitems Items for the trade
     * @param int        $courseid   Course ID
     */
    public function __construct(int $stashid, trade $trade = null, array $tradeitems = null, int $courseid = null) {
        $this->stashid = $stashid;
        $this->trade = $trade;
        $this->tradeitems = $tradeitems;
        $this->courseid = $courseid;
        $this->titlehelp = new help_icon('tradename', 'block_stash');
        $this->gainhelp = new help_icon('gaintitle', 'block_stash');
        $this->losshelp = new help_icon('losstitle', 'block_stash');
    }

    /**
     * Remove the form cancel and save buttons.
     * This is used for js dialogue forms.
     */
    public function remove_form_buttons(): void {
        $this->removeformbuttons = true;
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output Renderer.
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        $hashcode = 0;
        $tradeid = null;
        $tradetitle = null;
        $gain = null;
        $loss = null;
        if (isset($this->trade)) {
            $tradeid = $this->trade->get_id();
            $tradetitle = $this->trade->get_name();
            $gain = $this->trade->get_gaintitle();
            $loss = $this->trade->get_losstitle();
            $hashcode = $this->trade->get_hashcode();
        } else {
            $peristent = new trade();
            $hashcode = $peristent->get_hashcode();
        }

        $titleicon = '';
        $gainicon = '';
        $lossicon = '';

        // Need to accommodate for stuff that I didn't realise was new.
        if (method_exists($this->titlehelp, 'export_for_template')) {
            $titleicon = $this->titlehelp->export_for_template($output);
            $gainicon = $this->gainhelp->export_for_template($output);
            $lossicon = $this->losshelp->export_for_template($output);
        }

        return (object) [
            'stashid' => $this->stashid,
            'courseid' => $this->courseid,
            'tradeid' => $tradeid,
            'title' => $tradetitle,
            'titleicon' => $titleicon,
            'gain' => $gain,
            'gainicon' => $gainicon,
            'loss' => $loss,
            'lossicon' => $lossicon,
            'hashcode' => $hashcode,
            'sesskey' => sesskey(),
            'additems' => (isset($this->tradeitems['add'])) ? $this->tradeitems['add'] : [],
            'lossitems' => (isset($this->tradeitems['loss'])) ? $this->tradeitems['loss'] : [],
            'removeformbuttons' => $this->removeformbuttons,
        ];
    }

}
