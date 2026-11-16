# X-Fields

Simple, secure custom fields for WordPress: field groups, placement rules, repeaters, relationships and front-end forms.

![PHP](https://img.shields.io/badge/PHP-7.4%E2%80%938.4-777bb4) ![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b) ![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue)

X-Fields is a fork of [Custom Field Suite](https://github.com/mgibbs189/custom-field-suite) by Matt Gibbs, security-hardened, made PHP 8 compatible and redesigned. Maintained by [Gianluca Esposito](https://github.com/giaesp).

## Requirements

- WordPress 6.0+
- PHP 7.4+ (tested up to 8.4)

## Installation

Download the latest zip from [Releases](https://github.com/giaesp/x-fields/releases) and install it from **Plugins → Add New → Upload**, or clone into `wp-content/plugins/x-fields`.

If Custom Field Suite is installed, deactivate it first: X-Fields imports its field groups and values automatically on activation (the CFS tables are kept as a backup).

## Usage

1. **Settings → X-Fields → Add New**: create a field group and add fields.
2. In **Placement Rules** choose where it appears (post types, templates, terms, roles…).
3. Read values in your theme:

```php
// Single value (always escape output)
echo esc_html( XF()->get( 'subtitle' ) );

// Another post
$cta = XF()->get( 'cta', $post_id );

// All fields of the current post
$fields = XF()->get();

// Repeater (Loop)
foreach ( (array) XF()->get( 'faq' ) as $row ) {
    printf( '<h3>%s</h3>%s', esc_html( $row['question'] ), wp_kses_post( $row['answer'] ) );
}

// Posts that reference this post through a Relationship field
$ids = XF()->get_reverse_related( get_the_ID(), [ 'field_name' => 'related', 'post_type' => 'page' ] );
```

### Saving from code

```php
XF()->save( [ 'subtitle' => 'Hello' ], [ 'ID' => $post_id ] );
```

### Front-end form

```php
echo XF()->form( [
    'post_id'              => false,       // false = create a new post
    'field_groups'         => [ 123 ],     // required for new posts
    'post_title'           => 'Title',     // label, or false to hide
    'post_status'          => 'pending',
    'post_type'            => 'post',
    'confirmation_url'     => home_url( '/thanks/' ),
    'submit_label'         => 'Send',
] );
```

Values submitted by users without the `unfiltered_html` capability are filtered with KSES, and shortcodes from anonymous visitors are neutralized. After submit, visitors are redirected only within the site: to use an external `confirmation_url`, whitelist its host with the `allowed_redirect_hosts` filter.

### Permissions

Managing field groups and tools requires `manage_options`. Change it with:

```php
add_filter( 'xf_manage_capability', fn() => 'edit_theme_options' );
```

### Field types

Text · Textarea · WYSIWYG · Date · Color · True/False · Select · File · Relationship · Term · User · Loop (repeater) · Hyperlink · Tab

### Hooks (selection)

| Hook | Type | Purpose |
|---|---|---|
| `xf_field_types` | filter | Register custom field types |
| `xf_matching_groups` | filter | Override which groups apply to a post |
| `xf_pre_save_input` / `xf_after_save_input` | action | Before/after a form save |
| `xf_pre_render_fields` | filter | Alter fields before rendering |
| `xf_field_relationship_query_args` | filter | Customise the Relationship post query |
| `xf_relationship_display` / `xf_term_display` / `xf_user_display` | filter | Item label in pickers (receives escaped text) |

## Multilingual (Polylang)

- Field groups are configuration and are never translated.
- When you create a translation, X-Fields copies the source values so you only translate them.
- Strings in the admin are translatable (text domain `x-fields`, Italian included).

## Uninstall

Data is kept by default. To remove everything on uninstall add to `wp-config.php`:

```php
define( 'XF_REMOVE_ALL_DATA', true );
```

## Development

```bash
composer install
composer lint        # PHP syntax (all PHP files)
composer phpcs       # WordPress security + PHP compatibility rules
wp eval-file tests/smoke-test.php   # inside a WordPress install
```

Releases follow [Semantic Versioning](https://semver.org/); see [CHANGELOG.md](CHANGELOG.md).
To release: update the version in `x-fields.php` (header + `XF_VERSION`) and `readme.txt`, update the changelog, then tag `vX.Y.Z` — GitHub Actions builds the zip.

## Security

Please report vulnerabilities privately via GitHub Security Advisories, not public issues. See [SECURITY.md](SECURITY.md).

## License

GPL-2.0-or-later. Copyright © 2026 Gianluca Esposito; © 2011–2024 Matt Gibbs (Custom Field Suite).
