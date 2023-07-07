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


namespace block_stash\form;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use stdClass;
use MoodleQuickForm;

MoodleQuickForm::registerElementType('block_stash_integer', __DIR__ . '/integer.php', 'block_stash_form_integer');

class item extends persistent {

    protected static $persistentclass = 'block_stash\\item';

    protected static $fieldstoremove = array('save', 'submitbutton');

    protected static $foreignfields = array('image', 'saveandnext', 'detail_editor', 'scarceitem');

    /**
     * Define the form - called by parent constructor
     */
    public function definition() {
        global $PAGE, $OUTPUT, $CFG;

        $mform = $this->_form;
        $stash = $this->_customdata['stash'];
        $item = $this->get_persistent();

        $mform->addElement('header', 'generalhdr', get_string('general'));

        // Stash ID.
        $mform->addElement('hidden', 'stashid');
        $mform->setType('stashid', PARAM_INT);
        $mform->setConstant('stashid', $stash->get_id());

        // Name.
        $mform->addElement('text', 'name', get_string('itemname', 'block_stash'), 'maxlength="255"');
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'itemname', 'block_stash');

        // Check button to enable this as a scarce item.
        $scarceitem = $mform->addElement('advcheckbox', 'scarceitem', get_string('scarceitem', 'block_stash'), get_string('enabled', 'block_stash'));
        $mform->addHelpButton('scarceitem', 'scarceitem', 'block_stash');
        if (!empty($item->get_amountlimit())) {
            $scarceitem->setChecked(true);
        } else {
            $scarceitem->setChecked(false);
        }

        // Amount limit.
        $mform->addElement('text', 'amountlimit', get_string('itemamountlimit', 'block_stash'), 'maxlength="100"');
        $mform->setType('amountlimit', PARAM_INT);
        $mform->addHelpButton('amountlimit', 'itemamountlimit', 'block_stash');

        $mform->disabledIf('amountlimit', 'scarceitem', 'unchecked');

        // Image.
        $mform->addElement('filemanager', 'image', get_string('itemimage', 'block_stash'), array(),
            $this->_customdata['fileareaoptions']);
        $mform->addRule('image', null, 'required', null, 'client');
        $mform->addHelpButton('image', 'itemimage', 'block_stash');

        // Detail.
        if (isset($this->_customdata['modal']) && $this->_customdata['modal']) {
            $mform->addElement('textarea', 'detail_text', get_string('itemdetail', 'block_stash'));
            $mform->setType('detail_text', PARAM_TEXT);
            $mform->addHelpButton('detail_text', 'itemdetail', 'block_stash');
        } else {
            $mform->addElement('editor', 'detail_editor', get_string('itemdetail', 'block_stash'), array('rows' => 10),
                $this->_customdata['editoroptions']);
            $mform->setType('detail_editor', PARAM_RAW);
            $mform->addHelpButton('detail_editor', 'itemdetail', 'block_stash');
        }

        // This form is being displayed in a modal and has it's own submit buttons and save system.
        if (isset($this->_customdata['modal']) && $this->_customdata['modal']) {
            return;
        }
        // Buttons.
        $buttonarray = [];
        if (!$this->get_persistent()->get_id()) {
            // Only for new items.
            $buttonarray[] = &$mform->createElement('submit', 'saveandnext', get_string('saveandnext', 'block_stash'),
                ['class' => 'form-submit']);
            $buttonarray[] = &$mform->createElement('submit', 'save', get_string('savechanges', 'block_stash'));
        } else {
            $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges', 'block_stash'));
        }

        $buttonarray[] = &$mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

}
