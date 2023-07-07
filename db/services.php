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
 * Services definition.
 *
 * @package    block_stash
 * @copyright  2016 Frédéric Massart - FMCorz.net
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_stash_is_drop_visible' => [
        'classname'     => 'block_stash\\external',
        'methodname'    => 'is_drop_visible',
        'description'   => 'Check if a drop is visible to the user.',
        'type'          => 'read',
        // TODO Add capability name here.
        'capabilities'  => '',
        'ajax'          => true
    ],
    'block_stash_pickup_drop' => [
        'classname'     => 'block_stash\\external',
        'methodname'    => 'pickup_drop',
        'description'   => 'An item drop has been found.',
        'type'          => 'write',
        // TODO Add capability name here.
        'capabilities'  => '',
        'ajax'          => true
    ],
    'block_stash_get_item' => [
        'classname'     => 'block_stash\\external',
        'methodname'    => 'get_item',
        'description'   => 'Get an item.',
        'type'          => 'read',
        // TODO Add capability name here.
        'capabilities'  => '',
        'ajax'          => true
    ],
    'block_stash_get_items' => [
        'classname'     => 'block_stash\\external',
        'methodname'    => 'get_items',
        'description'   => 'Get all items.',
        'type'          => 'read',
        // TODO Add capability name here.
        'capabilities'  => '',
        'ajax'          => true
    ],
    'block_stash_get_trade_items' => [
        'classname'    => 'block_stash\\external',
        'methodname'   => 'get_trade_items',
        'description'  => 'Get all trade items for the trade widget',
        'type'         => 'read',
        'capabilities' => '',
        'ajax'         => true
    ],
    'block_stash_complete_trade' => [
        'classname'    => 'block_stash\\external',
        'methodname'   => 'complete_trade',
        'description'  => 'Complete a trade with a user',
        'type'         => 'write',
        'capabilities' => '',
        'ajax'         => true
    ],
    'block_stash_get_user_stash_items' => [
        'classname'    => 'block_stash\\external',
        'methodname'   => 'get_stash_for_user',
        'description'  => 'Get the stash of a user.',
        'type'         => 'read',
        'capabilities' => '',
        'ajax'         => true
    ],
    'block_stash_create_swap_request' => [
        'classname'    => 'block_stash\\external',
        'methodname'   => 'create_swap_request',
        'description'  => 'Create a request to swap items between users',
        'type'         => 'write',
        'capabilities' => '',
        'ajax'         => true
    ],
    'block_stash_get_swap_items_for_search_widget' => [
        'classname' => 'block_stash\\external\\itemlist',
        'methodname' => 'get_swap_items_for_search_widget',
        'description' => 'Gets available items for a swap',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => true
    ],
    'block_stash_get_users_for_search_widget' => [
        'classname' => 'block_stash\\external\\userlist',
        'methodname' => 'get_users_for_search_widget',
        'description' => 'Gets available items for a swap',
        'type' => 'read',
        'capabilities' => '',
        'ajax' => true
    ],
    'block_stash_get_all_drops' => [
        'classname'    => 'block_stash\\external\\dropwidget_select_data',
        'methodname'   => 'get_all_drop_data',
        'description'  => 'Get all drop data for a items and trades',
        'type'         => 'read',
        'capabilities' => '',
        'ajax'         => true
    ],
    'block_stash_add_user_item' => [
        'classname'    => 'block_stash\\external\\add_user_item',
        'methodname'   => 'add_user_item',
        'description'  => 'Add an item to a users stash',
        'type'         => 'write',
        'capabilities' => '',
        'ajax'         => true
    ],
    'block_stash_add_drop' => [
        'classname'    => 'block_stash\\external\\add_drop',
        'methodname'   => 'execute',
        'description'  => 'Create a drop for an item',
        'type'         => 'write',
        'capabilities' => '',
        'ajax'         => true
    ],
    'block_stash_add_item' => [
        'classname'    => 'block_stash\\external\\add_item',
        'methodname'   => 'execute',
        'description'  => 'Create an item',
        'type'         => 'write',
        'capabilities' => '',
        'ajax'         => true
    ],
];
