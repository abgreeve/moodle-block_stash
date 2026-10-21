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
 * Trade model.
 *
 * @package    block_stash
 * @copyright  2017 Adrian Greeve - adriangreeve.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash;
defined('MOODLE_INTERNAL') || die();

use coding_exception;
use core\invalid_persistent_exception;
use invalid_parameter_exception;
use lang_string;
use stdClass;

/**
 * Trade model class.
 *
 * The hashcode was initially 40 characters long, and we were not checking that the
 * code was unique per stash. We changed the length of the hash to be of 6
 * characters, but then it must be unique within its stash. This allows for the
 * snippets to contain the full hash, and no longer require the ID. If we
 * don't require the ID, we do not have to worry about backup and restore
 * and can pretty much always assume that the hash is unique.
 *
 * @package    block_stash
 * @copyright  2017 Adrian Greeve - adriangreeve.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class trade {

    const TABLE = 'block_stash_trade';

    /** @var int Primary key. */
    private int $id = 0;

    /** @var int The stash this trade belongs to. */
    private int $stashid = 0;

    /** @var string Display name for this trade. */
    private string $name = '';

    /** @var string Title shown for items the user loses. */
    private string $losstitle = '';

    /** @var string Title shown for items the user gains. */
    private string $gaintitle = '';

    /** @var string Short unique code used in shortcodes/snippets. */
    private string $hashcode = '';

    /** @var int Unix timestamp of record creation. */
    private int $timecreated = 0;

    /** @var int Unix timestamp of last modification. */
    private int $timemodified = 0;

    /** @var array Validation errors from the last validate() call. */
    private array $errors = [];

    /** @var bool Whether the current data has already been validated. */
    private bool $validated = false;

    /**
     * Constructor.
     *
     * The hashcode callable default is eagerly evaluated here so that new instances
     * always have a valid hashcode before any DB interaction.
     *
     * @param int|null $id If > 0, load the record with this ID from the DB.
     * @param stdClass|null $record If set, hydrate from this record.
     */
    public function __construct(int|null $id = 0, ?stdClass $record = null) {
        // Evaluate the callable default for hashcode upfront; from_record() will
        // override this with the stored value if a record is provided.
        $this->hashcode = random_string(6);

        if ($id > 0) {
            $this->id = $id;
            $this->read();
        }
        if (!empty($record)) {
            $this->from_record($record);
        }
    }

    // -----------------------------------------------------------------------
    // Schema definition
    // -----------------------------------------------------------------------

    /**
     * Returns the property definitions specific to this model (excluding the
     * standard id / timecreated / timemodified fields).
     *
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'stashid' => [
                'type' => PARAM_INT,
            ],
            'name' => [
                'type' => PARAM_TEXT,
            ],
            'losstitle' => [
                'type' => PARAM_TEXT,
            ],
            'gaintitle' => [
                'type' => PARAM_TEXT,
            ],
            'hashcode' => [
                'type' => PARAM_ALPHANUMEXT,
                'default' => function() {
                    return random_string(6);
                },
            ],
        ];
    }

    /**
     * Returns the full property definitions including id, timecreated, timemodified.
     *
     * The result is cached per class for the lifetime of the request.
     *
     * @return array
     */
    public static function properties_definition(): array {
        global $CFG;

        static $cachedef = [];
        if (isset($cachedef[static::class])) {
            return $cachedef[static::class];
        }

        $cachedef[static::class] = static::define_properties();
        $def = &$cachedef[static::class];

        $def['id']           = ['default' => 0, 'type' => PARAM_INT];
        $def['timecreated']  = ['default' => 0, 'type' => PARAM_INT];
        $def['timemodified'] = ['default' => 0, 'type' => PARAM_INT];

        foreach ($def as $property => $definition) {
            if (!array_key_exists('null', $definition)) {
                $def[$property]['null'] = NULL_NOT_ALLOWED;
            }
            if ($CFG->debugdeveloper) {
                if (!array_key_exists('type', $definition)) {
                    throw new coding_exception('Missing type for: ' . $property);
                } else if (isset($definition['message']) && !($definition['message'] instanceof lang_string)) {
                    throw new coding_exception('Invalid error message for: ' . $property);
                }
            }
        }

        return $def;
    }

    /**
     * Returns properties that have a matching *format field (PARAM_RAW + PARAM_INT pair).
     *
     * losstitle and gaintitle are PARAM_TEXT (not PARAM_RAW), so this returns [].
     *
     * @return array Keys are property names, values are their format property names.
     */
    public static function get_formatted_properties(): array {
        $properties = static::properties_definition();

        $formatted = [];
        foreach ($properties as $property => $definition) {
            $propertyformat = $property . 'format';
            if ($definition['type'] == PARAM_RAW
                    && array_key_exists($propertyformat, $properties)
                    && $properties[$propertyformat]['type'] == PARAM_INT) {
                $formatted[$property] = $propertyformat;
            }
        }

        return $formatted;
    }

    // -----------------------------------------------------------------------
    // Serialisation
    // -----------------------------------------------------------------------

    /**
     * Hydrate this instance from a DB record.
     *
     * Silently ignores unknown fields so partial records (e.g. from JOINs) work.
     *
     * @param stdClass $record
     * @return static
     */
    public function from_record(stdClass $record): static {
        $this->validated = false;
        foreach ((array) $record as $property => $value) {
            switch ($property) {
                case 'id':           $this->id           = (int) $value; break;
                case 'stashid':      $this->stashid      = (int) $value; break;
                case 'name':         $this->name         = (string) $value; break;
                case 'losstitle':    $this->losstitle    = (string) $value; break;
                case 'gaintitle':    $this->gaintitle    = (string) $value; break;
                case 'hashcode':     $this->hashcode     = (string) $value; break;
                case 'timecreated':  $this->timecreated  = (int) $value; break;
                case 'timemodified': $this->timemodified = (int) $value; break;
            }
        }
        return $this;
    }

    /**
     * Produce a DB record from this instance.
     *
     * @return stdClass
     */
    public function to_record(): stdClass {
        $record = new stdClass();
        $record->id           = $this->id;
        $record->stashid      = $this->stashid;
        $record->name         = $this->name;
        $record->losstitle    = $this->losstitle;
        $record->gaintitle    = $this->gaintitle;
        $record->hashcode     = $this->hashcode;
        $record->timecreated  = $this->timecreated;
        $record->timemodified = $this->timemodified;
        return $record;
    }

    // -----------------------------------------------------------------------
    // Validation
    // -----------------------------------------------------------------------

    /**
     * Validates the data against the property definitions, including custom
     * validate_*() hook methods.
     *
     * @return array|true True when valid; an array of property => lang_string errors otherwise.
     */
    public function validate(): array|true {
        global $CFG;

        if ($this->validated) {
            return empty($this->errors) ? true : $this->errors;
        }

        $errors = [];
        $properties = static::properties_definition();
        $record = (array) $this->to_record();

        foreach ($properties as $property => $definition) {
            $value = $record[$property] ?? null;

            // Required check (no 'default' key means the field is required).
            if ($value === null && !array_key_exists('default', $definition)) {
                $errors[$property] = new lang_string('requiredelement', 'form');
                continue;
            }

            // Type check — skip callable defaults when value is empty string for
            // properties that have a callable default (they always produce a valid value).
            try {
                $checkvalue = ($definition['type'] === PARAM_BOOL && $value === false) ? 0 : $value;
                validate_param($checkvalue, $definition['type'], $definition['null']);
            } catch (invalid_parameter_exception $e) {
                $errors[$property] = isset($definition['message'])
                    ? $definition['message']
                    : new lang_string('invaliddata', 'error');
                continue;
            }

            // Choices check.
            if (isset($definition['choices']) && !in_array($value, $definition['choices'])) {
                $errors[$property] = isset($definition['message'])
                    ? $definition['message']
                    : new lang_string('invaliddata', 'error');
                continue;
            }

            // Custom validate_*() hook.
            $method = 'validate_' . $property;
            if (method_exists($this, $method)) {
                if ($CFG->debugdeveloper) {
                    $reflection = new \ReflectionMethod($this, $method);
                    if (!$reflection->isProtected()) {
                        throw new coding_exception('The method ' . get_class($this) . '::' . $method . ' should be protected.');
                    }
                }
                $valid = $this->{$method}($value);
                if ($valid !== true) {
                    if (!($valid instanceof lang_string)) {
                        throw new coding_exception('Unexpected error message.');
                    }
                    $errors[$property] = $valid;
                    continue;
                }
            }
        }

        $this->validated = true;
        $this->errors    = $errors;

        return empty($errors) ? true : $errors;
    }

    /**
     * Returns true when the current data passes validation.
     *
     * @return bool
     */
    public function is_valid(): bool {
        return $this->validate() === true;
    }

    /**
     * Returns the validation errors from the last validate() call.
     *
     * @return array
     */
    public function get_errors(): array {
        $this->validate();
        return $this->errors;
    }

    // -----------------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------------

    /**
     * Load the record from the DB by the current ID.
     *
     * @return static
     */
    public function read(): static {
        global $DB;

        if ($this->id <= 0) {
            throw new coding_exception('id is required to load');
        }
        $record = $DB->get_record(static::TABLE, ['id' => $this->id], '*', MUST_EXIST);
        $this->from_record($record);
        $this->validated = true;

        return $this;
    }

    /**
     * Insert a new record into the DB.
     *
     * @return static
     */
    public function create(): static {
        global $DB;

        if ($this->id) {
            throw new coding_exception('Cannot create an object that has an ID defined.');
        }
        if (!$this->is_valid()) {
            throw new invalid_persistent_exception($this->get_errors());
        }

        $now = time();
        $this->timecreated  = $now;
        $this->timemodified = $now;

        $record = $this->to_record();
        unset($record->id);

        $this->id        = $DB->insert_record(static::TABLE, $record);
        $this->validated = true;

        return $this;
    }

    /**
     * Update the existing DB record.
     *
     * @return bool True on success.
     */
    public function update(): bool {
        global $DB;

        if ($this->id <= 0) {
            throw new coding_exception('id is required to update');
        }
        if (!$this->is_valid()) {
            throw new invalid_persistent_exception($this->get_errors());
        }

        $this->timemodified = time();

        $record = $this->to_record();
        unset($record->timecreated);

        $result          = $DB->update_record(static::TABLE, $record);
        $this->validated = true;

        return $result;
    }

    /**
     * Delete the record from the DB.
     *
     * @return bool True on success.
     */
    public function delete(): bool {
        global $DB;

        if ($this->id <= 0) {
            throw new coding_exception('id is required to delete');
        }

        $result = $DB->delete_records(static::TABLE, ['id' => $this->id]);
        if ($result) {
            $this->id = 0;
        }

        return $result;
    }

    // -----------------------------------------------------------------------
    // Getters / setters
    // -----------------------------------------------------------------------

    /**
     * Get the record ID.
     *
     * @return int
     */
    public function get_id(): int {
        return $this->id;
    }

    /**
     * Get the stash ID.
     *
     * @return int
     */
    public function get_stashid(): int {
        return $this->stashid;
    }

    /**
     * Set the stash ID.
     *
     * @param int $stashid
     * @return static
     */
    public function set_stashid(int $stashid): static {
        $this->stashid   = $stashid;
        $this->validated = false;
        return $this;
    }

    /**
     * Get the trade name.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Set the trade name.
     *
     * @param string $name
     * @return static
     */
    public function set_name(string $name): static {
        $this->name      = $name;
        $this->validated = false;
        return $this;
    }

    /**
     * Get the loss title.
     *
     * @return string
     */
    public function get_losstitle(): string {
        return $this->losstitle;
    }

    /**
     * Set the loss title.
     *
     * @param string $losstitle
     * @return static
     */
    public function set_losstitle(string $losstitle): static {
        $this->losstitle = $losstitle;
        $this->validated = false;
        return $this;
    }

    /**
     * Get the gain title.
     *
     * @return string
     */
    public function get_gaintitle(): string {
        return $this->gaintitle;
    }

    /**
     * Set the gain title.
     *
     * @param string $gaintitle
     * @return static
     */
    public function set_gaintitle(string $gaintitle): static {
        $this->gaintitle = $gaintitle;
        $this->validated = false;
        return $this;
    }

    /**
     * Get the hashcode.
     *
     * @return string
     */
    public function get_hashcode(): string {
        return $this->hashcode;
    }

    /**
     * Set the hashcode.
     *
     * @param string $hashcode
     * @return static
     */
    public function set_hashcode(string $hashcode): static {
        $this->hashcode  = $hashcode;
        $this->validated = false;
        return $this;
    }

    // -----------------------------------------------------------------------
    // Custom business methods
    // -----------------------------------------------------------------------

    /**
     * Regenerate the hash code.
     *
     * @return void
     */
    public function regenerate_hashcode() {
        $this->hashcode  = random_string(6);
        $this->validated = false;
    }

    /**
     * Get a trade by hashcode portion.
     *
     * This will throw an exception when there are multiple matches.
     *
     * @param int $stashid The stash in which the item should be.
     * @param string $hash The hash portion.
     * @return self
     */
    public static function get_by_hashcode_portion($stashid, $hash) {
        global $DB;
        $hashlike = $DB->sql_like('hashcode', ':hashcode');
        $params = [
            'stashid'  => $stashid,
            'hashcode' => $DB->sql_like_escape($hash) . '%',
        ];
        $record = $DB->get_record_select(self::TABLE, "stashid = :stashid AND $hashlike", $params, '*', MUST_EXIST);
        return new self(null, $record);
    }

    /**
     * Is the hashcode unique in the stash?
     *
     * @param string $hashcode The hash code.
     * @param int $stashid The stash ID.
     * @param int $ignoreid The ID to ignore when checking.
     * @return bool
     */
    public static function hashcode_exists($hashcode, $stashid, $ignoreid = 0) {
        $sql = 'stashid = :stashid AND hashcode = :hashcode AND id <> :id';
        $params = [
            'stashid'  => $stashid,
            'hashcode' => $hashcode,
            'id'       => $ignoreid,
        ];
        return static::record_exists_select($sql, $params);
    }

    /**
     * Is a trade widget in a specific stash?
     *
     * @param int $tradeid The trade ID.
     * @param int $stashid The stash ID.
     * @return boolean
     */
    public static function is_trade_in_stash($tradeid, $stashid) {
        global $DB;
        $sql = "SELECT i.id
                  FROM {" . self::TABLE . "} i
                 WHERE i.id = ?
                   AND i.stashid = ?";
        return $DB->record_exists_sql($sql, [$tradeid, $stashid]);
    }

    /**
     * Get the full list of trade items with their item details.
     *
     * @param int $tradeid The trade ID.
     * @return array
     */
    public function get_full_trade_items_list($tradeid) {
        global $DB;

        $tradeitemfields = tradeitems::get_sql_fields('ti', 'tradeitem');
        $itemfields = item::get_sql_fields('i', 'item');
        $sql = "SELECT $itemfields, $tradeitemfields
                  FROM {" . tradeitems::TABLE . "} ti
                  JOIN {" . item::TABLE . "} i ON ti.itemid = i.id
                 WHERE ti.tradeid = ?";
        return $DB->get_records_sql($sql, [$tradeid]);
    }

    // -----------------------------------------------------------------------
    // Custom validators
    // -----------------------------------------------------------------------

    /**
     * Validate the stash ID.
     *
     * @param string $value The stash ID.
     * @return true|lang_string
     */
    protected function validate_stashid($value) {
        if (!stash::record_exists($value)) {
            return new lang_string('invaliddata', 'error');
        }
        return true;
    }

    /**
     * Validate the hash code.
     *
     * @param string $value The hash code.
     * @return true|lang_string
     */
    protected function validate_hashcode($value) {
        if (strlen($value) < 2 || strlen($value) > 40) {
            return new lang_string('invalidtradelength', 'block_stash');
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $value)) {
            return new lang_string('invalidtradeformat', 'block_stash');
        }
        if ($this->get_stashid() && static::hashcode_exists($value, $this->get_stashid(), $this->get_id())) {
            return new lang_string('errorcodeinuse', 'block_stash', $value);
        }

        return true;
    }

    // -----------------------------------------------------------------------
    // Static DB helpers
    // -----------------------------------------------------------------------

    /**
     * Load a single record matching the given filters.
     *
     * @param array $filters
     * @return static|false False when no record found.
     */
    public static function get_record(array $filters = []): static|false {
        global $DB;

        $record = $DB->get_record(static::TABLE, $filters);
        return $record ? new static(0, $record) : false;
    }

    /**
     * Load a list of records matching the given filters.
     *
     * @param array  $filters
     * @param string $sort    Field to sort by.
     * @param string $order   Sort direction.
     * @param int    $skip    Offset.
     * @param int    $limit   Maximum rows to return (0 = all).
     * @return static[]
     */
    public static function get_records(array $filters = [], string $sort = '', string $order = 'ASC',
                                       int $skip = 0, int $limit = 0): array {
        global $DB;

        $orderby = '';
        if (!empty($sort)) {
            $orderby = $sort . ' ' . $order;
        }

        $records   = $DB->get_records(static::TABLE, $filters, $orderby, '*', $skip, $limit);
        $instances = [];
        foreach ($records as $record) {
            $instances[] = new static(0, $record);
        }
        return $instances;
    }

    /**
     * Load a list of records using a raw WHERE clause.
     *
     * @param string     $select
     * @param array|null $params
     * @param string     $sort
     * @param string     $fields
     * @param int        $limitfrom
     * @param int        $limitnum
     * @return static[]
     */
    public static function get_records_select(string $select, ?array $params = null, string $sort = '',
                                              string $fields = '*', int $limitfrom = 0, int $limitnum = 0): array {
        global $DB;

        $records   = $DB->get_records_select(static::TABLE, $select, $params, $sort, $fields, $limitfrom, $limitnum);
        $instances = [];
        foreach ($records as $key => $record) {
            $instances[$key] = new static(0, $record);
        }
        return $instances;
    }

    /**
     * Count records matching the given conditions.
     *
     * @param array $conditions
     * @return int
     */
    public static function count_records(array $conditions = []): int {
        global $DB;
        return $DB->count_records(static::TABLE, $conditions);
    }

    /**
     * Check whether a record with the given ID exists.
     *
     * @param int $id
     * @return bool
     */
    public static function record_exists(int $id): bool {
        global $DB;
        return $DB->record_exists(static::TABLE, ['id' => $id]);
    }

    /**
     * Check whether any record matches the given WHERE clause.
     *
     * @param string     $select
     * @param array|null $params
     * @return bool
     */
    public static function record_exists_select(string $select, ?array $params = null): bool {
        global $DB;
        return $DB->record_exists_select(static::TABLE, $select, $params);
    }

    /**
     * Return a SQL fragment listing all columns prefixed for use in a SELECT.
     *
     * Use extract_record() to unpack the result row afterwards.
     *
     * @param string      $alias  Table alias used in the query.
     * @param string|null $prefix Column prefix; defaults to table name with underscores removed + '_'.
     * @return string
     */
    public static function get_sql_fields(string $alias, ?string $prefix = null): string {
        global $CFG;

        if ($prefix === null) {
            $prefix = str_replace('_', '', static::TABLE) . '_';
        }

        // Move 'id' to the front.
        $properties = static::properties_definition();
        $id = $properties['id'];
        unset($properties['id']);
        $properties = ['id' => $id] + $properties;

        $fields = [];
        foreach ($properties as $property => $definition) {
            $as = $prefix . $property;
            $fields[] = $alias . '.' . $property . ' AS ' . $as;

            if ($CFG->debugdeveloper && strlen($as) > 30) {
                throw new coding_exception("The alias '$as' for column '$alias.$property' exceeds 30 characters" .
                    ' and will therefore not work across all supported databases.');
            }
        }

        return implode(', ', $fields);
    }

    /**
     * Extract a record from a prefixed result row (inverse of get_sql_fields()).
     *
     * @param stdClass    $row    The full query result row.
     * @param string|null $prefix Column prefix; defaults to table name with underscores removed + '_'.
     * @return stdClass
     */
    public static function extract_record(stdClass $row, ?string $prefix = null): stdClass {
        if ($prefix === null) {
            $prefix = str_replace('_', '', static::TABLE) . '_';
        }
        $prefixlength = strlen($prefix);

        $data = new stdClass();
        foreach ($row as $property => $value) {
            if (strpos($property, $prefix) === 0) {
                $propertyname        = substr($property, $prefixlength);
                $data->$propertyname = $value;
            }
        }
        return $data;
    }
}
