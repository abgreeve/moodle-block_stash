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

require_once($CFG->libdir . '/formslib.php');

use stdClass;
// use MoodleQuickForm;

// MoodleQuickForm::registerElementType('block_stash_integer', __DIR__ . '/integer.php', 'block_stash_form_integer');

/**
 * Item removal form.
 *
 * @package    block_stash
 * @copyright  2023 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collection extends \moodleform {


    protected static $fieldstoremove = ['save', 'submitbutton'];
    protected static $foreignfields = ['saveandnext'];

    public function definition() {

        $mform = $this->_form;
        $manager = $this->_customdata['manager'];
        // $item = $this->_customdata['item'];
        $item = null;
        $context = $manager->get_context();
        // $itemname = $item ? format_string($item->get_name(), null, ['context' => $context]) : null;

        $mform->addElement('header', 'generalhdr', get_string('general'));
        $mform->addElement('text', 'name', get_string('collectionname', 'block_stash'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('checkbox', 'showtostudent', get_string('showtostudent', 'block_stash'));
        $mform->setDefault('showtostudent', 1);
        $mform->setType('showtostudent', PARAM_INT);

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
            $itemselect = $mform->addElement('select', 'items', get_string('collectionitems', 'block_stash'), $options);
            $itemselect->setMultiple(true);
            $mform->addRule('items', null, 'required', null, 'client');
            array_unshift($options, 'none');
            $prizeselect = $mform->addElement('select', 'prizes', get_string('prizeitems', 'block_stash'), $options);
            $prizeselect->setMultiple(true);
            $prizeselect->setSelected(0);
        }
        $mform->addElement('checkbox', 'removeoncompletion', get_string('removeoncompletion', 'block_stash'));
        $mform->setDefault('removeoncompletion', 0);
        $mform->setType('removeoncompletion', PARAM_INT);



        // This form is being displayed in a modal and has it's own submit buttons and save system.
        // if (isset($this->_customdata['modal']) && $this->_customdata['modal']) {
        //     return;
        // }

        // Buttons.
        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('savechanges', 'block_stash'));

        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

}
