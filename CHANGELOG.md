Block Stash
===========

Version 2.0.3 (TBA)
-------------------

* Fixed offers from other instances (different stash in a different course) being displayed. Now only offers for this course are shown.
* Added additional webservices to allow tiny_stash to create items and drops.

Version 2.0.2 (12 June 2023)
----------------------------

* Have added a setting to restrict trading within individual groups. When enabled students can only trade with other students in the groups that they belong to.
* Stopped the ability to add block_stash to a category, which results in an error.
* Have updated the JavaScript to ES6 and away from YUI.

Version 2.0.1 (15 May 2023)
---------------------------

* Fixed a bug selecting items, with updating the database query to not be hardcoded.
* The user profile is now viewable again.
* A scheduled task has been added to remove old completed trade requests.
* Trade headings have been updated to better represent the actual trade.
* Added a webservice to help with future development in other plugins.

Version 2.0 (19 April 2023)
---------------------------

* Added user trades. Students can now send and accept trade requests from each other. This setting is on by default, but can be turned off in the block settings.
  Turn editing on and an edit cog will appear with a menu. Click configure block.
* This new version uses a lot of current code and so the minimum Moodle version is 4.1.2.

Version 1.3.3 (2nd January 2020)
----------------------------------

* Fixed images in the item detail from not being re-written properly and displaying a notice.
* Students viewing another student's profile page now does not result in an error.
* Non-editing teachers by default now can view course pages. They have been given the block/stash:view capability by default.
* Added more control to the teacher to directly add and delete items from a user's stash. This can be accessed through the reports page.

Version 1.3.2 (28th November 2019)
----------------------------------

* User's stash will now be displayed in the user's profile page.
* Scarce items have been added. Now a teacher can set up items that are limited through out the course and not just to the user.
* Fix to some exceptions not being defined properly.

Version 1.3.1 (10th May 2018)
-----------------------------

* Fix typo in language string identifier

Version 1.3.0 (9th May 2018)
----------------------------

* Implement privacy API (GDPR compliance)
* Drop support for Moodle 2.9 and 3.0
* Hash code size was reduced to 6 characters
* Support snippets from [filter_shortcodes](https://github.com/branchup/moodle-filter_shortcodes)
* Drop support and deprecate filter_stash
* Slightly improved styling across themes and versions
* Minor improvements and bug fixing

Version 1.2.3 (30th August 2017)
--------------------------------
* Added the ability to edit the block title.

Version 1.2.2 (10th August 2017)
--------------------------------
* Issues with the persistence in Moodle 3.2 fixed.

Version 1.2.1 (9th August 2017)
-------------------------------
* Fixed the error on the trade creation form. We can't export for template the help icon in earlier versions.

Version 1.2.0 (9th August 2017)
-------------------------------
* Added the trading system. This improvement requires the filter_stash plugin to be installed to work. Teachers can create a trade widget that will allow students to swap or exchange items they currently have for different items.
* Backup and restore should work properly now. If the filter was used and the snippet was small enough then the encoding would not work.
* Basic fixes to deprecated libraries calls.

Version 1.1.0 (26th August 2016)
--------------------------------
* Support for filter_stash - filter_stash allows the copying of code to be simplified to a very small string which can then be copied straight into editors. The need for burrowing to the raw HTML is no longer needed.
* Detail about each item can now be added. When creating an item you have an extra field (editor) to put additional detail about an item. When a student clicks on an item they have acquired a dialogue will pop up with the full information about the item.
* Clicking on an item that is visible on the course page will now update to the block without the need for a page refresh.
* A report page allows teachers to get a peek at their students' stash.
* Various user interface improvements

Version 1.0.0 (21st July 2016)
------------------------------
Various bug fixes.
