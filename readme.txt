=== X-Fields ===
Contributors: giaesp
Tags: custom fields, repeater, relationship, meta box, fields
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Simple, secure custom fields for WordPress: field groups, placement rules, repeaters, relationships and front-end forms.

== Description ==

X-Fields lets you add custom fields to any post type with a clean, intuitive editor.

* 14 field types: Text, Textarea, WYSIWYG, Date, Color, True / False, Select, File, Relationship, Term, User, Loop (repeater), Hyperlink, Tab
* Placement rules by post type, post format, user role, post, taxonomy term and page template
* Simple PHP API: `XF()->get( 'field_name' )`
* Front-end forms: `echo XF()->form( [ ... ] );`
* Import / export of field groups
* Polylang support (field groups are not translated, values are copied to new translations)
* Automatic migration from Custom Field Suite

X-Fields is a fork of [Custom Field Suite](https://github.com/mgibbs189/custom-field-suite) by Matt Gibbs, hardened and maintained by Gianluca Esposito.

== Installation ==

1. Upload the `x-fields` folder to `/wp-content/plugins/` (or install the release zip).
2. Activate X-Fields. If Custom Field Suite is installed, deactivate it first: its data is imported automatically.
3. Go to Settings → X-Fields to create a field group.

== Frequently Asked Questions ==

= How do I display a value? =

`<?php echo esc_html( XF()->get( 'subtitle' ) ); ?>`

Always escape output in your theme (`esc_html`, `esc_url`, `wp_kses_post`…).

= Is my data deleted when I uninstall? =

No. Data is kept unless you add `define( 'XF_REMOVE_ALL_DATA', true );` to wp-config.php before uninstalling, or use Tools → X-Fields Tools → Reset.

== Changelog ==

= 1.0.0 =
* First release of X-Fields (fork of Custom Field Suite 2.6.7).
* See CHANGELOG.md for the full list of security fixes, PHP 8 compatibility work and the redesigned interface.
