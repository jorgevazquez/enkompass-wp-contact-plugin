# Enkompass Contact

A WordPress contact form builder plugin styled for the Enkompass site. It ships
an admin form builder, a public-facing form renderer, and a Gutenberg block
(`enkompass-form`) for placing forms in the block editor.

> Status: scaffold. Directories exist but are mostly empty. Sections below
> describe the **conventions to follow as code is added**, not features that
> already exist. Do not assume implementation that isn't on disk.

## Stack

- WordPress: >= 6.5
- PHP: >= 8.0 (authoritative source: `composer.json` `require.php`)
- License: GPL-2.0-or-later
- PHP namespace: `Enkompass\Contact\` (PSR-4)

## Behavior directives (mandatory)

- Always use superpowers.
- Always use test-driven development (TDD): write the failing test first, then
  the implementation.
- Use multiple agents whenever possible.
- Document everything.
- Save documentation to Notion and keep it in sync.

## Architecture & directory conventions

| Path | Purpose |
| --- | --- |
| `includes/` | PHP source under namespace `Enkompass\Contact\` (PSR-4). Core domain logic, hooks, and services live here. |
| `admin/` | wp-admin assets and views: `admin/css`, `admin/js`, `admin/js/vendor`, `admin/views` (PHP templates). |
| `public/` | Front-end assets: `public/css`, `public/js`. |
| `blocks/enkompass-form/` | The Gutenberg block. `block.json`, editor/save scripts, and block styles go here. |
| `tests/` | PHPUnit tests. `tests/Unit` for unit tests; `tests/bootstrap.php` and `tests/bootstrap/` for harness setup. Test namespace: `Enkompass\Contact\Tests\`. |

Conventions to follow:

- Keep admin-only and public-only code separated; shared logic belongs in
  `includes/`. Do not load admin assets on the front end or vice versa.
- New PHP classes map their namespace to directory per PSR-4
  (`Enkompass\Contact\Foo\Bar` → `includes/Foo/Bar.php`).
- The block lives at `blocks/enkompass-form/` and should be registered from PHP
  in `includes/` via `register_block_type` pointing at its `block.json`.

## Development workflow

- Run tests: `composer test` (runs `phpunit`).
- TDD is required: add or update a test in `tests/Unit` (namespace
  `Enkompass\Contact\Tests\`) before writing production code.
- Test autoloading is dev-only (`autoload-dev` PSR-4 maps
  `Enkompass\Contact\Tests\` → `tests/`).

## Coding standards

- Follow the WordPress Coding Standards (WPCS) for PHP, JS, and CSS.
- Prefix global functions, hooks, options, and post meta to avoid collisions
  (e.g. `enkompass_contact_*`); namespaced classes do not need prefixes.
- Escape on output, sanitize on input, and verify nonces/capabilities for every
  admin action and form submission.
- Wrap user-facing strings in i18n functions using a single text domain
  (e.g. `enkompass-contact`).
