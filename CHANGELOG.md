# Changelog

All notable changes to X-Fields are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

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

[Unreleased]: https://github.com/giaesp/x-fields/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/giaesp/x-fields/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/giaesp/x-fields/releases/tag/v1.0.0
