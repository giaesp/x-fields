=== X-Fields ===
Contributors: giaesp
Tags: custom fields, repeater, relationship, meta box, fields
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Simple, secure custom fields: field groups, placement rules, repeaters, relationships and front-end forms.

== Description ==

X-Fields adds custom fields to any post type with a clean editor that follows the WordPress admin design.

**Field types**

Text, Textarea, WYSIWYG (WordPress editor), Date and date-time (native browser pickers), Color (WordPress color picker), True / False, Select (optionally searchable), File / image, Relationship, Term, User, Loop (repeater, nestable), Hyperlink and Tab.

**Features**

* Placement rules by post type, post format, user role, specific posts, taxonomy terms and page template
* Simple PHP API: `XFields()->get( 'subtitle' )`
* Front-end forms: `echo XFields()->form( [ ... ] );`
* Required fields and min/max limits validated in the browser **and** on the server
* Import / export field groups between sites
* Polylang support: field groups are never translated and values are copied to new translations
* Automatic migration from Custom Field Suite
* Translation ready (Italian included)

**Security first**

Every output is escaped, every input sanitized and every query prepared. Field groups can only be managed by administrators, values sent by users without the `unfiltered_html` capability are filtered, and front-end forms are protected against open redirects and shortcode injection. The code is checked on every change with the WordPress Coding Standards security rules on PHP 7.4 to 8.4.

**Developer friendly**

`XFields()->get()`, `XFields()->save()`, `XFields()->form()`, `XFields()->get_reverse_related()` and filters such as `xfields_field_types`, `xfields_matching_groups`, `xfields_validate_input` and `xfields_manage_capability`. Full documentation on [GitHub](https://github.com/giaesp/x-fields#readme).

X-Fields is a modernized fork of Custom Field Suite by Matt Gibbs.

== Installation ==

1. Install X-Fields from Plugins → Add New, or upload the `x-fields` folder to `/wp-content/plugins/`.
2. Activate it. If Custom Field Suite is installed, deactivate it first: its data is imported automatically.
3. Go to Settings → X-Fields → Add New to create your first field group.
4. Display values in your theme, always escaping output:

`<?php echo esc_html( XFields()->get( 'subtitle' ) ); ?>`

== Frequently Asked Questions ==

= How do I display a repeater (Loop)? =

`foreach ( (array) XFields()->get( 'faq' ) as $row ) { echo esc_html( $row['question'] ); }`

= Who can create field groups? =

Administrators (`manage_options`). Change it with the `xfields_manage_capability` filter. Editors and authors can fill in the fields on the posts they are allowed to edit.

= Does it work with Polylang? =

Yes. Field groups are shared by all languages; when you create a translation, X-Fields copies the values of the original so you only have to translate them.

= Is my data deleted when I uninstall? =

No. Data is kept unless you add `define( 'XFIELDS_REMOVE_ALL_DATA', true );` to wp-config.php before uninstalling, or use Tools → X-Fields Tools → Reset.

= I'm upgrading from X-Fields 1.x =

Version 2.0 renames the PHP API from `XF()` to `XFields()` (and hooks from `xf_*` to `xfields_*`). Your data is migrated automatically; update the calls in your theme.

== Screenshots ==

1. Field group editor: drag & drop fields, clear names and types.
2. Placement rules with searchable selects.
3. Fields on the edit screen: repeater rows, relationship picker, WordPress editor.
4. Tools: export, import and reset.

== Changelog ==

= 2.0.0 =
* Breaking: PHP API renamed to `XFields()`, prefix `xfields_` (WordPress.org requirement). Data from 1.x and from Custom Field Suite is migrated automatically.
* Modern libraries: Tom Select for searchable selects, native date/time inputs, WordPress color picker, official `wp.editor` API for WYSIWYG. Removed Select2 3, bootstrap-datepicker, miniColors, PowerTip and a custom TinyMCE plugin.
* All database queries use `$wpdb->prepare()` or WordPress APIs.
* Requires WordPress 6.2+, tested up to 7.1.

= 1.1.0 =
* Server-side validation of required fields and limits.

= 1.0.1 =
* Security hardening release.

= 1.0.0 =
* First release (fork of Custom Field Suite 2.6.7).

== Upgrade Notice ==

= 2.0.0 =
Major release: the PHP API is now `XFields()` instead of `XF()`. Update your theme before upgrading; data is migrated automatically.

== Third-party libraries ==

* [Tom Select](https://github.com/orchidjs/tom-select) 2.6.2 — Apache License 2.0 — searchable selects. Readable sources are included in `assets/vendor/tom-select/`.
