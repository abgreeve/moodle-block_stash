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
 * Item removal form.
 *
 * @package    block_stash
 * @copyright  2023 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_stash\form;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use stdClass;
use MoodleQuickForm;

MoodleQuickForm::registerElementType('block_stash_integer', __DIR__ . '/integer.php', 'block_stash_form_integer');

/**
 * Item removal form.
 *
 * @package    block_stash
 * @copyright  2023 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class removal extends \moodleform {


    protected static $fieldstoremove = ['save', 'submitbutton'];
    protected static $foreignfields = ['saveandnext'];

    public function definition() {

        $mform = $this->_form;
        $manager = $this->_customdata['manager'];
        $item = $this->_customdata['item'];
        $context = $manager->get_context();
        $itemname = $item ? format_string($item->get_name(), null, ['context' => $context]) : null;

        $mform->addElement('header', 'generalhdr', get_string('general'));

        // Item ID.
        if ($item) {
            $mform->addElement('hidden', 'itemid');
            $mform->setType('itemid', PARAM_INT);
            $mform->setConstant('itemid', $item->get_id());
            $mform->addElement('static', '', get_string('item', 'block_stash'), $itemname);
        } else {
            $items = $manager->get_items();
            $options = [];
            foreach ($items as $stashitem) {
                $options[$stashitem->get_id()] = format_string($stashitem->get_name(), null, ['context' => $context]);
            }
            $mform->addElement('select', 'itemid', get_string('item', 'block_stash'), $options);
        }

        // Quiz
        $removalhelper = new \block_stash\local\stash_elements\removal_helper($manager);
        $mform->addElement('select', 'quizcmid', 'Quiz', $removalhelper->get_quizzes_for_course());
        $mform->setType('quizcmid', PARAM_INT);

        // Quantity
        $mform->addElement('block_stash_integer', 'quantity', get_string('quantity', 'block_stash'), ['style' => 'width: 4em;']);
        $mform->setType('quantity', PARAM_INT);
        $mform->addHelpButton('quantity', 'quantity', 'block_stash');

        // Detail.
        $mform->addElement('editor', 'detail_editor', get_string('itemdetail', 'block_stash'), ['rows' => 10],
            $this->_customdata['editoroptions']);
        $mform->setType('detail_editor', PARAM_RAW);
        $mform->addHelpButton('detail_editor', 'itemdetail', 'block_stash');

        // This form is being displayed in a modal and has it's own submit buttons and save system.
        // if (isset($this->_customdata['modal']) && $this->_customdata['modal']) {
        //     return;
        // }

        // Buttons.
        $buttonarray = [];
        // if (!$this->get_persistent()->get_id()) {
            // Only for new items.
            // $buttonarray[] = &$mform->createElement('submit', 'saveandnext', get_string('saveandnext', 'block_stash'),
            //     ['class' => 'form-submit']);
            // $buttonarray[] = &$mform->createElement('submit', 'save', get_string('savechanges', 'block_stash'));
        // } else {
            $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('savechanges', 'block_stash'));
        // }

        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

}
