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
 * Random drop pool validator.
 *
 * @package    block_stash
 * @copyright  2026 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_stash;
defined('MOODLE_INTERNAL') || die();

/**
 * Validates the pool for a random drop.
 *
 * This validator can work against persisted pool entries or an explicit list
 * of candidate entries, so future forms and runtime checks can reuse the same
 * business rules.
 *
 * @package    block_stash
 * @copyright  2026 Adrian Greeve
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class random_drop_pool_validator {

    /** @var int Minimum allowed pool size. */
    const MIN_POOL_ITEMS = 2;

    /** @var int Maximum allowed pool size. */
    const MAX_POOL_ITEMS = 20;

    /**
     * Whether random pool validation applies to the drop.
     *
     * @param drop $drop The drop to inspect.
     * @return bool
     */
    public function applies_to(drop $drop): bool {
        return $drop->is_random();
    }

    /**
     * Whether the drop has a valid random pool.
     *
     * @param drop $drop The drop to validate.
     * @param drop_pool_item[]|null $poolitems Candidate pool entries, or null to use stored entries.
     * @return bool
     */
    public function is_valid(drop $drop, ?array $poolitems = null, ?int $stashid = null): bool {
        return $this->applies_to($drop) && empty($this->get_errors($drop, $poolitems, $stashid));
    }

    /**
     * Return the pool validation errors keyed by business rule.
     *
     * Fixed drops are not evaluated and therefore return an empty array.
     *
     * @param drop $drop The drop to validate.
     * @param drop_pool_item[]|null $poolitems Candidate pool entries, or null to use stored entries.
     * @return array
     */
    public function get_errors(drop $drop, ?array $poolitems = null, ?int $stashid = null): array {
        if (!$this->applies_to($drop)) {
            return [];
        }

        $poolitems = $poolitems ?? $drop->get_pool_items();
        $errors = [];
        $count = count($poolitems);

        if ($count < self::MIN_POOL_ITEMS) {
            $errors['minitems'] = $count;
        }
        if ($count > self::MAX_POOL_ITEMS) {
            $errors['maxitems'] = $count;
        }

        $stashid = $this->resolve_stashid($drop, $poolitems, $stashid);
        $seenitemids = [];
        $duplicateitemids = [];
        $missingitemids = [];
        $wrongstashitemids = [];
        $scarceitemids = [];

        foreach ($poolitems as $poolitem) {
            $itemid = $poolitem->get_itemid();

            if (isset($seenitemids[$itemid])) {
                $duplicateitemids[$itemid] = $itemid;
                continue;
            }
            $seenitemids[$itemid] = true;

            if (!item::record_exists($itemid)) {
                $missingitemids[$itemid] = $itemid;
                continue;
            }

            $item = new item($itemid);
            if ($stashid !== null && $item->get_stashid() !== $stashid) {
                $wrongstashitemids[$itemid] = $itemid;
            }
            if ($item->is_scarce_item()) {
                $scarceitemids[$itemid] = $itemid;
            }
        }

        if (!empty($duplicateitemids)) {
            $errors['duplicateitems'] = array_values($duplicateitemids);
        }
        if (!empty($missingitemids)) {
            $errors['missingitems'] = array_values($missingitemids);
        }
        if (!empty($wrongstashitemids)) {
            $errors['stashmismatch'] = array_values($wrongstashitemids);
        }
        if (!empty($scarceitemids)) {
            $errors['scarceitems'] = array_values($scarceitemids);
        }

        return $errors;
    }

    /**
     * Resolve the stash ID to validate against.
     *
     * @param drop $drop The drop being validated.
     * @param drop_pool_item[] $poolitems Candidate pool items.
     * @param int|null $stashid Explicit stash ID override.
     * @return int|null
     */
    protected function resolve_stashid(drop $drop, array $poolitems, ?int $stashid): ?int {
        if ($stashid !== null) {
            return $stashid;
        }

        if ($drop->get_itemid() > 0 && item::record_exists($drop->get_itemid())) {
            return (new item($drop->get_itemid()))->get_stashid();
        }

        foreach ($poolitems as $poolitem) {
            if (item::record_exists($poolitem->get_itemid())) {
                return (new item($poolitem->get_itemid()))->get_stashid();
            }
        }

        return null;
    }
}

