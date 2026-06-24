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
 * Random drop form.
 *
 * @package    block_stash
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_stash\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use block_stash\drop as drop_model;
use block_stash\drop_pool_item;
use block_stash\item;
use block_stash\manager;
use block_stash\random_drop_pool_validator;
use MoodleQuickForm;
use moodle_url;
use stdClass;

MoodleQuickForm::registerElementType('block_stash_integer', __DIR__ . '/integer.php', 'block_stash_form_integer');

/**
 * Random drop form class.
 *
 * @package    block_stash
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class random_drop extends persistent {

    /** Low weight label option. */
    public const WEIGHT_LOW = 1;

    /** Medium weight label option. */
    public const WEIGHT_MEDIUM = 5;

    /** High weight label option. */
    public const WEIGHT_HIGH = 10;

    protected static $persistentclass = 'block_stash\\drop';

    protected static $fieldstoremove = ['submitbutton'];

    /**
     * Define the form.
     */
    public function definition() {
        global $PAGE;

        $mform = $this->_form;
        $manager = $this->_customdata['manager'];
        $drop = $this->get_persistent();

        $mform->addElement('header', 'generalhdr', get_string('general'));

        // Hash code.
        $mform->addElement('hidden', 'hashcode');
        $mform->setType('hashcode', PARAM_ALPHANUM);
        $mform->setConstant('hashcode', $drop->get_hashcode());

        // Drop type.
        $mform->addElement('hidden', 'droptype');
        $mform->setType('droptype', PARAM_INT);
        $mform->setConstant('droptype', drop_model::TYPE_RANDOM);

        // Name.
        $mform->addElement('text', 'name', get_string('dropname', 'block_stash'),
            'maxlength="100" placeholder="' . s(get_string('eginthecastle', 'block_stash')) . '"');
        $mform->setType('name', PARAM_NOTAGS);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');
        $mform->addHelpButton('name', 'dropname', 'block_stash');

        // Max pickup.
        $mform->addElement('block_stash_integer', 'maxpickup', get_string('maxpickup', 'block_stash'), ['style' => 'width: 4em;']);
        $mform->setType('maxpickup', PARAM_INT);
        $mform->addHelpButton('maxpickup', 'maxpickup', 'block_stash');

        // Pickup interval.
        $mform->addElement('duration', 'pickupinterval', get_string('pickupinterval', 'block_stash'));
        $mform->setType('pickupinterval', PARAM_INT);
        $mform->addHelpButton('pickupinterval', 'pickupinterval', 'block_stash');

        $mform->addElement('header', 'poolhdr', get_string('randomdroppool', 'block_stash'));
        $mform->addElement('static', 'poolvalidationnotice', '', '');

        $renderer = $PAGE->get_renderer('block_stash');
        $context = (object) [
            'courseid' => $manager->get_courseid(),
            'defaultweight' => self::get_default_weight(),
            'weightoptionsjson' => json_encode(array_values(self::get_weight_options_for_template())),
            'items' => $this->get_pool_rows_for_display($manager),
        ];
        $mform->addElement('html', $renderer->render_from_template('block_stash/random_drop_pool_editor', $context));

        $this->add_action_buttons(true, get_string('savechanges', 'block_stash'));
    }


    /**
     * Get form data, including dynamic pool row inputs rendered as raw HTML.
     *
     * Moodle only exports registered form elements, so the pool editor inputs
     * need to be merged back in explicitly.
     *
     * @return object|null
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            $data = $this->add_pool_data_to_form_data($data);
        }
        return $data;
    }

    /**
     * Get submitted form data, including dynamic pool row inputs.
     *
     * @return object|null
     */
    public function get_submitted_data() {
        $data = parent::get_submitted_data();
        if ($data) {
            $data = $this->add_pool_data_to_form_data($data);
        }
        return $data;
    }

    /**
     * Extra validation for pool entries.
     *
     * @param stdClass $data Form data.
     * @param array $files Files.
     * @param array $errors Existing errors.
     * @return array
     */
    protected function extra_validation($data, $files, array &$errors) {
        $manager = $this->_customdata['manager'];
        $drop = $this->get_persistent();
        $entries = self::parse_pool_entries_from_data($data);
        $poolitems = [];
        $hasinvaliditemid = false;
        $hasinvalidweight = false;

        foreach ($entries as $entry) {
            if ($entry['itemid'] <= 0) {
                $hasinvaliditemid = true;
                continue;
            }
            if (!array_key_exists($entry['weight'], self::get_weight_choices())) {
                $hasinvalidweight = true;
                continue;
            }

            $poolitems[] = new drop_pool_item(null, (object) [
                'dropid' => $drop->get_id(),
                'itemid' => $entry['itemid'],
                'weight' => $entry['weight'],
            ]);
        }

        $messages = [];
        if ($hasinvaliditemid) {
            $messages[] = get_string('randomdroppoolinvaliditems', 'block_stash');
        }
        if ($hasinvalidweight) {
            $messages[] = get_string('randomdroppoolinvalidweights', 'block_stash');
        }

        $validator = new random_drop_pool_validator();
        $validationerrors = $validator->get_errors($drop, $poolitems, $manager->get_stash()->get_id());
        unset($validationerrors['minitems']);

        $messagemap = [
            'maxitems' => 'randomdroppooltoomanyitems',
            'duplicateitems' => 'randomdroppoolduplicateitems',
            'missingitems' => 'randomdroppoolinvaliditems',
            'stashmismatch' => 'randomdroppoolwrongstashitems',
            'scarceitems' => 'randomdroppoolscarceitems',
        ];
        foreach ($messagemap as $key => $stringid) {
            if (isset($validationerrors[$key])) {
                $messages[] = get_string($stringid, 'block_stash');
            }
        }

        if (!empty($messages)) {
            $messages = array_values(array_unique($messages));
            return ['poolvalidationnotice' => implode(' ', $messages)];
        }

        return [];
    }


    /**
     * Merge the raw posted pool data into exported form data.
     *
     * @param stdClass $data Existing form data.
     * @return stdClass
     */
    protected function add_pool_data_to_form_data(stdClass $data): stdClass {
        $data->poolitemids = optional_param_array('poolitemids', [], PARAM_INT);
        $data->poolitemweights = optional_param_array('poolitemweights', [], PARAM_INT);
        return $data;
    }

    /**
     * Parse posted pool entries from form data.
     *
     * @param stdClass $data Form data.
     * @return array<int, array{itemid:int, weight:int}>
     */
    public static function parse_pool_entries_from_data(stdClass $data): array {
        $itemids = isset($data->poolitemids) ? array_values((array) $data->poolitemids) : [];
        $weights = isset($data->poolitemweights) ? array_values((array) $data->poolitemweights) : [];
        $entries = [];

        foreach ($itemids as $index => $itemid) {
            $entries[] = [
                'itemid' => (int) $itemid,
                'weight' => isset($weights[$index]) ? (int) $weights[$index] : self::get_default_weight(),
            ];
        }

        return $entries;
    }

    /**
     * Return the default teacher-facing weight.
     *
     * @return int
     */
    public static function get_default_weight(): int {
        return self::WEIGHT_MEDIUM;
    }

    /**
     * Return the weight choices.
     *
     * @return array<int, string>
     */
    public static function get_weight_choices(): array {
        return [
            self::WEIGHT_LOW => get_string('randomdroppoolweightlow', 'block_stash'),
            self::WEIGHT_MEDIUM => get_string('randomdroppoolweightmedium', 'block_stash'),
            self::WEIGHT_HIGH => get_string('randomdroppoolweighthigh', 'block_stash'),
        ];
    }

    /**
     * Return weight choices for template rendering.
     *
     * @param int|null $selectedweight Selected weight.
     * @return array<int, array<string, mixed>>
     */
    public static function get_weight_options_for_template(?int $selectedweight = null): array {
        $options = [];
        foreach (self::get_weight_choices() as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
                'selected' => $selectedweight !== null && $selectedweight === $value,
            ];
        }
        return $options;
    }

    /**
     * Get rows for display.
     *
     * @param manager $manager Manager.
     * @return array
     */
    protected function get_pool_rows_for_display(manager $manager): array {
        $submitted = $this->get_submitted_data();
        if ($submitted && (isset($submitted->poolitemids) || isset($submitted->poolitemweights))) {
            return $this->build_pool_rows_from_entries(self::parse_pool_entries_from_data($submitted), $manager);
        }

        $drop = $this->get_persistent();
        if (!$drop->get_id()) {
            return [];
        }

        $rows = [];
        foreach ($drop->get_pool_items() as $poolitem) {
            $rows[] = $this->build_pool_row_context($poolitem->get_itemid(), $poolitem->get_weight(), $manager);
        }
        return $rows;
    }

    /**
     * Build template rows from parsed entries.
     *
     * @param array $entries Parsed entries.
     * @param manager $manager Manager.
     * @return array
     */
    protected function build_pool_rows_from_entries(array $entries, manager $manager): array {
        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = $this->build_pool_row_context($entry['itemid'], $entry['weight'], $manager);
        }
        return $rows;
    }

    /**
     * Build one pool row context.
     *
     * @param int $itemid Item ID.
     * @param int $weight Weight value.
     * @param manager $manager Manager.
     * @return array<string, mixed>
     */
    protected function build_pool_row_context(int $itemid, int $weight, manager $manager): array {
        $name = get_string('item', 'block_stash') . ' #' . $itemid;
        $imageurl = '';
        if ($itemid > 0 && item::record_exists($itemid)) {
            $item = new item($itemid);
            $name = format_string($item->get_name(), null, ['context' => $manager->get_context()]);
            $imageurl = moodle_url::make_pluginfile_url($manager->get_context()->id, 'block_stash', 'item', $itemid, '/', 'image')->out(false);
        }

        return [
            'id' => $itemid,
            'itemid' => $itemid,
            'name' => $name,
            'imageurl' => $imageurl,
            'weightoptions' => self::get_weight_options_for_template($weight),
        ];
    }
}
