=== Enkompass Contact ===
Contributors: enkompass
Tags: contact form, form builder, drag and drop, csv, leads
Requires at least: 6.4
Tested up to: 6.5
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Drag-and-drop contact form builder styled for the Enkompass site, with email, database and CSV delivery.

== Description ==

Enkompass Contact adds a top-level "Contacts" menu in the admin sidebar (directly
below Comments) where you build forms with a visual drag-and-drop editor and
collect their submissions. A fresh install ships with a ready-to-use Contact form
mirroring the live Enkompass contact form. Forms render on the front end using the
Enkompass theme's own classes, so they match the site.

Features:

* Visual form builder (snap-to-grid) with 11 field types.
* Per-field properties: name, label, required, tab order, CSS class, validation,
  fail message, and an options repeater for dropdowns, checkboxes and radios.
* Client + server validation that always agree.
* Deliver submissions to Email, the WordPress database, and/or a CSV file — any
  combination, per form.
* Download CSV button on each form once data has been collected.
* Embed with the [enkompass_form id="N"] shortcode or the Enkompass Form block.
* Deleting a form never deletes its collected data.

== Installation ==

1. Upload the plugin to wp-content/plugins/enkompass-contact.
2. Activate it through the Plugins screen.
3. Open Contacts in the sidebar (just below Comments). A ready-made Contact form is already there; click it to edit, or use the + card to add another.

== Frequently Asked Questions ==

= Where are CSV files stored? =

In a protected wp-content/uploads/enkompass-forms/ directory with a deny-all
.htaccess and randomized filenames. They are served only through a
capability-and-nonce-checked download endpoint.

= Does deleting a form delete its submissions? =

No. Submissions and the CSV file are preserved. Uninstalling preserves them too.

= How do I embed a form? =

Use [enkompass_form id="N"] (the id is shown while editing the form) or add the
"Enkompass Form" block and choose a form.

== Changelog ==

= 1.0.0 =
* Initial release: form builder, 11 field types, mirrored validation, email/
  database/CSV destinations, shortcode + block, secured CSV download.
