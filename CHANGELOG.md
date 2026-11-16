# Changelog

All notable changes to X-Fields are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [2.0.0] - 2026-11-16

Prepared for the WordPress.org plugin directory.

### Changed — breaking
- **New prefixes** (WordPress.org requires ≥ 4 characters): `XF()` → `XFields()`, `xf_*` → `xfields_*` (hooks, options, tables, post type `xfields_group`, CSS/JS), `XF_*` → `XFIELDS_*`.
- Requires **WordPress 6.2+** (tested up to 7.1).

### Added
- Automatic migration from X-Fields 1.x (tables, groups, meta, options, cron), tested in CI together with the Custom Field Suite migration.
- Date field: optional **time** (native `datetime-local`), strict validation of impossible dates/times.
- WordPress.org assets (banner, icon, screenshots) and deploy workflows (release → SVN, readme/assets updates).

### Changed — libraries
- **Tom Select 2.6.2** replaces Select2 3.x for placement rules, field options and the searchable Select field (with server-side post search).
- **Native date / date-time inputs** replace bootstrap-datepicker.
- **WordPress color picker** (`wp-color-picker`) replaces miniColors.
- **Official `wp.editor` API** replaces the custom TinyMCE setup and bundled "code" plugin (Visual/Code tabs, Add Media, works in Loop rows and after drag & drop).
- PowerTip tooltips replaced by always-visible descriptions under labels.
- Assets are registered/enqueued with the WordPress script API (no hard-coded `<script>` tags).

### Security / quality
- All queries use `$wpdb->prepare()` (with `%i` identifiers) or WordPress APIs (`get_posts`, `get_terms`, `get_users`).
- Official **Plugin Check: no errors, no warnings**; PHPCS (WordPress security + PHP compatibility): 0 warnings.

### Fixed
- Tools → Reset did not deactivate the plugin (wrong file path since 1.0.0).
- Changing a field's type in the editor could lose its options (JS name mismatch introduced by the 1.0 rename).
- The CFS migration was skipped if an empty "Add New" field group draft existed.
- The migration notice could appear with "0 field groups".
- Field editor layout: aligned Label/Name/Type inputs, descriptions under the inputs.
- Relationship field no longer offers the post being edited.

## [1.1.0] - 2026-11-16

### Added
- **Server-side validation** of required fields and min/max limits (Relationship, Term, User, Loop), including fields inside Loop rows. Browser checks can be bypassed; the server now enforces them.
  - Front-end forms: invalid submissions are rejected with a list of errors (nothing is saved).
  - Admin: the post is always saved (no lost work); when publishing, a notice lists the fields that need attention. Drafts are not validated, as in the browser.
  - New filter `xf_validate_input` to add custom rules.

### Security
- All translatable strings are escaped on output (`esc_html_e`, `esc_attr_e`, `esc_html__`).
- WYSIWYG default values are escaped in the editor textarea.
- Input handling uses `wp_unslash()` + sanitization everywhere it is safe; remaining raw reads are documented.

### Changed
- PHPCS (WordPress security + PHP compatibility) now reports **0 warnings** (was 136) and the CI fails on any new warning.
- Test suite: 42 checks; test data is cleaned up even if a test crashes.

## [1.0.1] - 2026-11-16

### Security
- Field groups and X-Fields Tools now require `manage_options` (filter `xf_manage_capability`); previously anyone with page capabilities (Editors) could create field groups.
- Front-end form redirects are restricted to the same site (`wp_safe_redirect`), fixing an open redirect via protocol-relative `REQUEST_URI` (e.g. `//evil.example`). External confirmation URLs must be whitelisted with `allowed_redirect_hosts`.
- Shortcodes submitted by anonymous visitors (field values and post content) are neutralized.
- User field no longer exposes login names to users who can't list users.
- Relationship field never shows titles of posts the current user can't read.
- Select values are restricted to configured choices; True/False, Color and Date values are validated server-side.
- Expired form sessions are purged (daily cron + on write), preventing unbounded table growth from public forms.

### Fixed
- Field option values were HTML-encoded again on every save (`&` → `&amp;amp;…`).

### Tests
- Smoke/security suite extended to 38 checks (roles, redirects, shortcodes, validation, sessions).

## [1.0.0] - 2026-11-16

First release of X-Fields, forked from Custom Field Suite 2.6.7.

### Changed
- Renamed to **X-Fields**: `CFS()` → `XF()`, prefix `cfs_` → `xf_` (classes, hooks, options, tables, CSS/JS), text domain `x-fields`, field group post type `xf_group`.
- Redesigned admin interface: field rows with drag handle, field name chips and type badges, icon actions, empty state, cleaner placement rules, repeater rows and two-column pickers. The accent color follows the user's admin color scheme. Responsive on small screens.
- Redesigned Tools page (export / import / reset).
- Minimum requirements: WordPress 6.0, PHP 7.4.

### Added
- Automatic one-time migration from Custom Field Suite (field groups, values, field ID counter). CFS tables are kept as a backup.
- Polylang integration: field groups are never translated; values are copied when a translation is created.
- `uninstall.php`: data is kept unless `XF_REMOVE_ALL_DATA` is defined.
- Smoke/security test suite (`tests/smoke-test.php`), PHPCS configuration and GitHub Actions CI.

### Security
- Removed `eval()` in the Loop field (remote code execution through crafted row keys).
- Fixed SQL injection in Relationship / Term / User fields and in `get_reverse_related()`.
- Form sessions use `random_bytes()` and are bound to the user; admin saves require `edit_post`.
- Front-end title/content and field values are filtered with KSES for users without `unfiltered_html`.
- Field group saves check post type and capability; names, labels, rules and extras are sanitized; forged field IDs can no longer hijack other groups.
- Hardened import/export; `unserialize()` never instantiates objects; escaped output across admin screens.
- Hyperlink URLs sanitized, `target` whitelisted, `rel="noopener noreferrer"` on `_blank`.

### Fixed
- PHP 8.x fatal errors, `TypeError`s, undefined index/property warnings and dynamic property deprecations.
- Reset now actually deactivates the plugin.
- Relationship field listed the wrong post types when none were selected.
- File field ignored the "File Type" option.
- "Insert row" in Loop fields targeted the first loop on the page.
- Search filters crashed on regex special characters.
- Post Type Switcher integration never matched.
- Double-encoded HTML entities in text/textarea values.
- Tables are created with the site charset/collation (utf8mb4).

[Unreleased]: https://github.com/giaesp/x-fields/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/giaesp/x-fields/compare/v1.1.0...v2.0.0
[1.1.0]: https://github.com/giaesp/x-fields/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/giaesp/x-fields/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/giaesp/x-fields/releases/tag/v1.0.0
