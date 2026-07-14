# Block Stash

Engage your students by gamifying your courses with collectible items, random rewards, and item-based progression.

## Features

- Create your own collectible items.
- Hide items throughout your course using shortcodes.
- Create **random drops** that award a weighted random item from a configurable reward pool.
- Upload custom mystery images for random drops.
- Configure how item and random-drop shortcodes are rendered (image, button, or both).
- Automatically respawn items after a configurable delay.
- Exchange items through configurable trades.
- Allow students to trade items with one another in the Trade Centre.
- Remove items when students attempt selected quizzes.
- Restrict access to activities based on collected items (requires the Availability Stash plugin).

## Requirements

Requires Moodle 5.0 or later.

## Installation

Simply install the plugin and add the Stash block to a course page.

_See the [Recommended plugins](#recommended-plugins) section for optional integrations._

## Getting started

### Creating an item

1. Create a new item.
2. Create a new location for the item.
3. Click **Get snippet**.
4. Configure how the item should appear.
5. Copy the generated shortcode.
6. Paste the shortcode into your course content.

When viewing the content, the item will appear. Teachers always see the items, but only students can collect them.

### Creating a random drop

1. Create the items that can be awarded.
2. Create a new random drop.
3. Add two or more items to the reward pool.
4. Optionally adjust the weighting of each item.
5. Optionally upload a custom mystery image.
6. Click **Get snippet**.
7. Copy the generated shortcode.
8. Paste the shortcode into your course content.

When a student collects the random drop, one item from the configured reward pool is awarded.

### Creating a trade

1. Create at least two items.
2. Click **Create trade**.
3. Add the items to receive on the left and the items to exchange on the right.
4. Save the trade.
5. Click **Get snippet**.
6. Copy the generated shortcode.
7. Paste the shortcode into your course content.

### Configuring item removal

1. Create a quiz.
2. Ensure you have at least one item in Stash.
3. Open the Stash block and select the **Removals** tab.
4. Click **Configure removal**.
5. Select the items to remove.
6. Select the quiz.
7. Save the configuration.

Students will lose the configured items when attempting the selected quiz. It is recommended that you explain this behaviour in the quiz description.

### Important

If you are not using the Shortcodes filter (see below), you must use the ATTO editor to insert the shortcode directly into the HTML source.

## Recommended plugins

### Shortcodes filter

The [Shortcodes filter](https://github.com/branchup/moodle-filter_shortcodes) makes it easier and more reliable to embed Stash items, random drops, and trades into course content. It is strongly recommended and is required for the trading feature.

### Stash availability

The [Availability Stash](https://moodle.org/plugins/availability_stash) plugin allows activities and resources to be restricted based on the items collected by a student.

### Tiny Stash

The [Tiny Stash](https://moodle.org/plugins/tiny_stash) editor plugin allows teachers to insert Stash items, random drops, and trades directly from the TinyMCE editor.

## License

Licensed under the [GNU GPL License](http://www.gnu.org/copyleft/gpl.html).
