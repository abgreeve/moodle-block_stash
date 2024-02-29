Block Stash
===========

Engage your students! Gamify your courses by hiding objects for your students to find.

Features
--------

- Create any object you like
- Hide objects throughout your course in any possible location
- Set objects to automatically re-appear after a delay to boost engagement
- Unlock access to activities based on the objects found (requires plugin [availability_stash](https://moodle.org/plugins/availability_stash))
- Trade by exchanging items for different items (requires plugin [filter plugin](https://github.com/branchup/moodle-filter_shortcodes))
- Allow students to trade items with each other in the trade center.
- **New** Configure stash to remove items to allow quiz attempts.

Requirements
------------

Moodle 4.1.2 or greater.

Installation
------------

Simply install the plugin and add the block to a course page.

_Please read the [Recommended plugins](#recommended-plugins) section._

Getting started
---------------

### Creating an item

1. Create a new item
2. Create a new location for that item
3. Copy the code snippet for that location
4. Directly paste the code in the HTML view of your editor

When viewing the content the object will now appear.
Note that teachers cannot pick up the objects, for them they will always re-appear.

### Creating a trade (item exchange)

1. Create at least two items (see creating an item above)
2. Click the create trade widget button.
3. Add items to gain on the left side and items to lose on the right.
4. Once saved click on the trade name and copy the code snippet.
5. Directly paste the code snippet into any location that has an editor.

### Configuring an item removal

1. Create a quiz in your course
2. Make sure you have at least one item created in stash
3. As a teacher, go to the stash settings and select the 'Removals' tab
4. Click the 'Configure removal' button
5. Select at least one item to remove (+ symbol)
6. Select a quiz and click save
7. Your quiz will now remove the configured items from students attempting that quiz. It is recommended that you inform your students about the item removal in the quiz description

### Important!

If you are not using the shortcodes filter (mentioned below) then you must use the ATTO editor to insert the code into the HTML source.

Recommended plugins
-------------------

### Shortcodes filter

This [filter plugin](https://github.com/branchup/moodle-filter_shortcodes) makes it easier and more reliable to add the items to your course content. We very highly recommend you to use it. This is a requirement to use the trading feature.

### Stash availability

This [availability plugin](https://moodle.org/plugins/availability_stash) allows to restrict the access to activity modules and resources based on the objects collected by a student.

### Tiny stash

This [tinyMCE editor plugin](https://moodle.org/plugins/tiny_stash) allows the user to add the items and trades to your course content using the TinyMCE editor.


License
-------

Licensed under the [GNU GPL License](http://www.gnu.org/copyleft/gpl.html).
