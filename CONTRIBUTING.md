# Contributing to X-Fields

Thanks for your interest! Bug reports, ideas and pull requests are welcome.

## Reporting

- **Bugs / features:** open an [issue](https://github.com/giaesp/x-fields/issues/new/choose) using the templates.
- **Security:** never in public issues — use [private vulnerability reporting](https://github.com/giaesp/x-fields/security/advisories/new). See [SECURITY.md](SECURITY.md).

## Development setup

```bash
git clone https://github.com/giaesp/x-fields.git wp-content/plugins/x-fields
cd wp-content/plugins/x-fields
composer install
```

Any local WordPress works (DDEV, wp-env, Local…). Activate the plugin, then:

```bash
composer lint                          # PHP syntax
composer phpcs                         # security + PHP compatibility rules
wp eval-file tests/smoke-test.php      # functional & security tests
wp eval-file tests/migration-test.php  # migrations
wp plugin check x-fields               # official Plugin Check (install the plugin-check plugin)
```

## Guidelines

- Target **PHP 7.4+** and **WordPress 6.2+**.
- Escape all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`), sanitize all input, check capabilities and nonces, use `$wpdb->prepare()`.
- Prefix everything with `xfields_` / `XFields`; text domain `x-fields`.
- Add a test to `tests/smoke-test.php` for every bug or security fix.
- Update `CHANGELOG.md` under **Unreleased** and `README.md` when behaviour, API or requirements change.
- Keep pull requests focused: one topic per PR.

## Releasing (maintainers)

1. Bump the version in `x-fields.php` (header and `XFIELDS_VERSION`) and `readme.txt` (`Stable tag`).
2. Move the **Unreleased** changelog entries under the new version.
3. `git tag vX.Y.Z && git push --tags` — GitHub Actions verifies the version and publishes the release zip.

By contributing you agree that your work is licensed under GPL-2.0-or-later.
