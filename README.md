# Enkompass Contact

A drop-in WordPress plugin that lets an administrator build contact forms with a
drag-and-drop visual editor, style them to match the [Enkompass](https://enkompass.net/contact)
site, and collect submissions via **email**, the **WordPress database**, and/or a
**CSV file** — any combination at once.

- **No build step.** Plain enqueued JavaScript and CSS. Unzip into
  `wp-content/plugins/`, activate, and it works.
- **Requires:** WordPress 6.4+, PHP 8.0+.
- **Capability:** the admin screen and all builder/REST actions require
  `manage_options`.

---

## Features

- **Contacts admin screen** — a top-level **Contacts** menu in the sidebar placed
  directly **below** Comments (a sibling of Comments, not a child of it), with two
  tabs: **Forms** and **Settings** (Settings reserved for a future release).
- **Bundled example form.** A fresh install ships with a ready-to-use **Contact**
  form that mirrors the live Enkompass contact form (name, email, company, the two
  AWS dropdowns, message and privacy checkbox), with the Database destination
  enabled so it captures submissions immediately.
- **Form cards.** Each form is a card showing its name above it. Use the **+** card
  to add more forms.
- **Auto-numbering.** New forms are named `FORM<n>`, one higher than the highest
  existing `FORM<n>`. Custom-named forms (e.g. `CONTACT`) are ignored for
  numbering, so the first auto form is always `FORM1`.
- **Inline rename.** A new form's title opens in edit mode immediately; click any
  saved title to rename it.
- **Drag-and-drop builder** (a modal with a 20% properties sidebar, a 50%
  snap-to-grid canvas, and a 20% field palette + Save/Cancel). Powered by a
  vendored copy of [GridStack](https://gridstackjs.com/); the canvas previews
  fields with the real Enkompass classes.
- **11 field types:** First Name, Last Name, Email, Company, Dropdown, Comments,
  Date, Checkbox, Radio Button, Submit, Submit/Cancel. Width/height resizing is
  enabled per the type (see *Field types* below).
- **Per-field properties:** name, label, required, tab order, CSS class
  (prefilled with the most likely Enkompass class), a validation picker, a
  "Validation Fail Message", an options repeater (dropdown/checkbox/radio), and
  type-specific props (placeholder, textarea rows, cancel button label, …).
- **Validation** mirrored between the browser and the server (identical rule
  keys), so the client gives instant feedback and the server is authoritative.
- **Destinations** chosen per form (one or more): Email (to a validated
  recipient list), Database, and CSV Text File.
- **CSV file** is created on first save, its header row is kept in sync with the
  field names on every save, and a **Download CSV** button appears on the card
  once the file has data. The file lives in a protected uploads subdirectory.
- **Front-end embedding** via the `[enkompass_form id="N"]` shortcode and a
  matching Gutenberg block.
- **Data durability:** deleting a form never deletes its submissions or CSV file,
  and uninstalling preserves them too.

---

## Installation

1. Copy this directory into `wp-content/plugins/enkompass-contact/`.
2. Activate **Enkompass Contact** in **Plugins**.
3. Open **Contacts** in the admin sidebar (directly below **Comments**). A
   ready-made **Contact** form is already there — click it to edit, or use the
   **+** card to add another.

On activation the plugin creates two tables (`{prefix}enk_forms`,
`{prefix}enk_submissions`), a protected `uploads/enkompass-forms/` directory, and
seeds the bundled Enkompass **Contact** form.

## Usage

1. **Start** from the bundled **Contact** form, or **create** a new one with the
   **+** card and name it.
2. **Click the card** to open the builder. Drag field types from the right
   palette onto the grid, click a field to edit its properties on the left, and
   click empty canvas to edit form settings (title, CSS class, destinations).
3. For **Email**, enable it and add one or more recipient addresses (validated).
   For **CSV**, enable Text File. For **Database**, enable it.
4. **Save.**
5. **Embed** the form on a page with `[enkompass_form id="3"]` (the id is shown
   while editing) or add the **Enkompass Form** block and pick the form.

---

## Field types

| Type | Control | Width resize | Height resize | Options repeater | Default CSS class |
|---|---|:---:|:---:|:---:|---|
| First Name | text input | ✔ | | | `input` |
| Last Name | text input | ✔ | | | `input` |
| Email | email input | ✔ | | | `input` |
| Company | text input | ✔ | | | `input` |
| Dropdown | select | ✔ | | ✔ | `select` |
| Comments | textarea | ✔ | ✔ | | `textarea` |
| Date | date input | ✔ | | | `input` |
| Checkbox | checkbox(es) | | | ✔ | `check` |
| Radio Button | radio group | | | ✔ | `check` |
| Submit | button | ✔ | ✔ | | `btn btn--primary` |
| Submit / Cancel | two buttons | ✔ | ✔ | | `btn btn--primary` / `btn btn--ghost` |

> GridStack resizes from a single handle (both axes); resizing is fully locked
> only for types that allow neither (Checkbox, Radio).

## Validation rules

`NotBlank`, `IsEmail`, `IsDate` (`YYYY-MM-DD`), `IsZIPCode` (US), `IsNumber`,
`IsInteger`, `IsDecimal`, `IsPhone`, `IsURL`, `IsAlpha`, `IsAlphaNumeric`,
`MinLength`, `MaxLength`, `IsInRange`, `RegEx`, `MatchesField`.

Format rules pass on an empty value (so optional blank fields don't fail);
presence is enforced only by marking a field **required**. Each field has a
"Validation Fail Message" shown when validation fails; parameterized rules
(`MinLength`, `IsInRange`, `RegEx`, …) store their params with the field.

---

## Architecture

```
enkompass-contact.php        Plugin header, constants, autoloader, hooks
includes/                    Core (WordPress-independent where possible)
  class-autoloader.php       Namespace → WP-convention file autoloader
  class-field-registry.php   The 11 field types (Field_Type value objects)
  class-validation-rules.php Rule catalog (pure)
  class-validator.php        Server-side validation engine
  class-csv-writer.php       CSV header sync + row append + injection guard
  class-form-renderer.php    Form definition → .form-card HTML + validation metadata
  class-form-repository.php  CRUD + FORM<n> auto-numbering ({prefix}enk_forms)
  class-submission-repository.php  Inserts/queries ({prefix}enk_submissions)
  class-activator/installer/deactivator.php  Schema + uploads dir
  class-plugin.php, helpers.php
admin/                       Contacts screen, builder, REST-admin controller, assets
public/                      Shortcode, block registration, submit controller, assets
blocks/enkompass-form/       Gutenberg block (no-build, server-rendered)
tests/                       PHPUnit unit suite + Node JS validation parity test
```

### Data model

- **`{prefix}enk_forms`** — one row per form: `id, name, definition (JSON),
  csv_filename, status, created_at, updated_at`.
- **`{prefix}enk_submissions`** — one row per submission, self-describing so it
  survives form deletion: `id, form_id, form_name, payload (JSON),
  fields_snapshot (JSON), submitted_at, ip_address, user_agent`.

### REST API

- Public: `POST /wp-json/enkompass/v1/submit` — nonce + honeypot protected.
- Admin (require `manage_options` + `wp_rest` nonce):
  `GET|POST /enkompass/v1/forms`, `GET|POST|DELETE /enkompass/v1/forms/{id}`,
  `POST /enkompass/v1/forms/{id}/rename`.

### Security

Capability checks on every admin action; per-form nonce on public submit; nonce
on CSV download; `$wpdb->prepare` throughout; deep sanitization of saved form
definitions; full output escaping; CSV-injection neutralization; CSV files behind
`index.php` + deny-all `.htaccess` with randomized filenames; IP/User-Agent
storage off by default.

---

## Development & testing

This plugin is developed test-first.

```bash
composer install        # PHPUnit, Brain Monkey, Mockery
composer test           # PHP unit suite (96 tests)
npm test                # JS validation parity test (mirrors the PHP rules)
```

The PHP unit suite covers the autoloader, field registry, validation rules and
engine, CSV writer, form renderer, and the `FORM<n>` numbering rule, using
lightweight WordPress function stand-ins (`tests/wp-stubs.php`). The
WordPress-integration layer (`$wpdb` repositories, REST controllers, hooks) is
intended to be exercised with `@wordpress/env` (see `.wp-env.json`).

```bash
npx @wordpress/env start    # requires Docker running
# WordPress at http://localhost:8888  (admin / password)
```

## License

GPL-2.0-or-later.
