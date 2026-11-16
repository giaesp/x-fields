=== Custom Field Suite ===
Contributors: mgibbs189
Tags: custom fields, fields, postmeta, relationship, repeater, file upload
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: trunk
License: GPLv2

Add custom fields to your post types

== Description ==

Custom Field Suite (CFS) lets you add custom fields to your posts. It's lightweight and battle-tested (there's not much to break).

= Things to know =
* We do not provide support.
* This is a free plugin. We're not selling anything.
* CFS includes 14 [field types](https://mgibbs189.github.io/custom-field-suite/field-types.html). There are no plans to add more.
* If you want all the bells-and-whistles, use ACF.

= Field types =
* Text
* Textarea
* WYSIWYG
* Date
* Color
* True / False
* Select
* File Upload
* Relationship
* Term
* User
* Loop (repeatable fields)
* Hyperlink
* Tab (group fields)

= Usage =
* Browse to the "Field Groups" admin menu
* Create a Field Group containing one or more custom fields
* Choose where the Field Group should appear, using the Placement Rules box
* Use the [get](https://mgibbs189.github.io/custom-field-suite/api/get.html) method in your template files to display custom fields

= Links =
* [Documentation →](https://mgibbs189.github.io/custom-field-suite/)
* [Github →](https://github.com/mgibbs189/custom-field-suite)

== Changelog ==

= 2.7.0 =
* Security: removed eval() in the Loop field (remote code execution via crafted row keys)
* Security: fixed SQL injection in Relationship / Term / User fields and in get_reverse_related()
* Security: form sessions now use random_bytes() and are bound to the user; admin saves require edit_post
* Security: front-end post_title / post_content and field values are filtered with KSES for users without unfiltered_html
* Security: field group save checks post type and capability; names, labels, rules and extras are sanitized
* Security: forged field IDs can no longer hijack fields of other groups
* Security: hardened import/export, object-injection-safe unserialize, escaped output (XSS) across admin screens
* Security: hyperlink URLs sanitized, target whitelisted, rel="noopener" on _blank
* PHP 8: fixed fatal errors on unknown field types, TypeErrors and undefined index/property warnings; no dynamic properties
* Fix: CFS Tools "Reset" now actually deactivates the plugin
* Fix: Relationship field listed the wrong post types when none were selected
* Fix: File field ignored the "File Type" option in the media library
* Fix: "Insert row" in Loop fields used the first loop on the page instead of the current one
* Fix: search filters crashed on regex special characters
* Fix: Post Type Switcher integration never matched (undefined variable)
* Fix: text/textarea values with HTML entities were double-encoded
* Fix: tables are created with the site charset/collation (utf8mb4)

= 2.6.7 =
* Reverted some int casting to prevent errors
* Refactor coming soon (hopefully)

= 2.6.6 =
* Even more sanitization (props wp.org team)

= 2.6.5 =
* Extra sanitization to prevent XSS via admin-imported field groups (props WordFence)

= 2.6.4 =
* Fixed: cleared PHP8 deprecation notices

= 2.6.3 =
* Fixed: possible placement rules XSS (props Patchstack)

= 2.6.2.1 =
* Confirmed 6.0.1 compatibility

= 2.6.2 =
* Removed broken links, confirmed 5.9 compatibility

= 2.6.1 =
* Fixed: PHP8 warnings

= 2.6 =
New: moved CFS into "Settings" menu
Improved: relationship fields now only run 1 query to retrieve related posts
Improved: code modernization
Improved: styling tweaks
Fix: "Posts" field group rule ajax wasn't loading
