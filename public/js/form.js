/**
 * Front-end submit handler for Enkompass forms.
 *
 * Runs for every `form.form-card[data-enk-form]` on the page. For each form it
 * builds a client-side "definition" by reading the data-* attributes the PHP
 * renderer (Form_Renderer) stamps onto each field wrapper, reuses the shared
 * window.EnkValidation validator to mirror the server, and POSTs JSON to the
 * REST endpoint described by window.ENK_FORM.
 *
 * Vanilla JS only. No build step, no frameworks. Everything is scoped per-form
 * so multiple independent forms can coexist on one page. Injected message text
 * is always set via textContent to avoid HTML injection.
 *
 * @package Enkompass\Contact
 */
(function () {
    'use strict';

    /**
     * Safely parse a JSON attribute, returning a fallback on any failure.
     *
     * @param {string|null} raw
     * @param {*} fallback
     * @return {*}
     */
    function parseJson(raw, fallback) {
        if (!raw) {
            return fallback;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return fallback;
        }
    }

    /**
     * Build the client definition by scanning every field wrapper.
     *
     * @param {HTMLFormElement} form
     * @return {{fields: Array<Object>}}
     */
    function buildDefinition(form) {
        var fields = [];
        var wrappers = form.querySelectorAll('[data-enk-name]');

        for (var i = 0; i < wrappers.length; i++) {
            var wrapper = wrappers[i];
            var labelEl = wrapper.querySelector('.label');

            fields.push({
                name: wrapper.getAttribute('data-enk-name') || '',
                type: wrapper.getAttribute('data-enk-type') || '',
                required: !!wrapper.getAttribute('data-enk-required'),
                validation: parseJson(wrapper.getAttribute('data-enk-validate'), []),
                validation_params: parseJson(wrapper.getAttribute('data-enk-params'), {}),
                fail_message: wrapper.getAttribute('data-enk-fail') || '',
                label: labelEl ? (labelEl.textContent || '').trim() : ''
            });
        }

        return { fields: fields };
    }

    /**
     * Read the current value of one field from the DOM.
     *
     * - radios -> the checked value or ''
     * - checkbox group (name="NAME[]") -> array of checked values
     * - single input / select / textarea -> string
     *
     * @param {HTMLFormElement} form
     * @param {string} name
     * @return {string|Array<string>}
     */
    function readValue(form, name) {
        // Checkbox group: controls use name="NAME[]".
        var checkboxes = form.querySelectorAll('input[type="checkbox"][name="' + cssEscape(name) + '[]"]');
        if (checkboxes.length) {
            var checked = [];
            for (var c = 0; c < checkboxes.length; c++) {
                if (checkboxes[c].checked) {
                    checked.push(checkboxes[c].value);
                }
            }
            return checked;
        }

        // Radio group: shares the field name across inputs.
        var radios = form.querySelectorAll('input[type="radio"][name="' + cssEscape(name) + '"]');
        if (radios.length) {
            for (var r = 0; r < radios.length; r++) {
                if (radios[r].checked) {
                    return radios[r].value;
                }
            }
            return '';
        }

        // Single control: input / select / textarea.
        var control = form.querySelector('[name="' + cssEscape(name) + '"]');
        if (control) {
            return control.value != null ? String(control.value) : '';
        }

        return '';
    }

    /**
     * Collect a {name: value} map for every data field in the definition.
     *
     * @param {HTMLFormElement} form
     * @param {{fields: Array<Object>}} definition
     * @return {Object}
     */
    function collectValues(form, definition) {
        var values = {};
        for (var i = 0; i < definition.fields.length; i++) {
            var name = definition.fields[i].name;
            if (name) {
                values[name] = readValue(form, name);
            }
        }
        return values;
    }

    /**
     * Escape a string for safe use inside an attribute-value CSS selector.
     * Prefer the native CSS.escape; fall back to escaping quotes/backslashes.
     *
     * @param {string} value
     * @return {string}
     */
    function cssEscape(value) {
        if (typeof window !== 'undefined' && window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }
        return String(value).replace(/(["\\])/g, '\\$1');
    }

    /**
     * Remove all error states (classes + injected hints) from a form.
     *
     * @param {HTMLFormElement} form
     */
    function clearErrors(form) {
        var errored = form.querySelectorAll('.enk-has-error');
        for (var i = 0; i < errored.length; i++) {
            errored[i].classList.remove('enk-has-error');
        }
        var hints = form.querySelectorAll('.enk-error');
        for (var h = 0; h < hints.length; h++) {
            if (hints[h].parentNode) {
                hints[h].parentNode.removeChild(hints[h]);
            }
        }
    }

    /**
     * Render an error message into a single field wrapper.
     *
     * @param {HTMLFormElement} form
     * @param {string} name
     * @param {string} message
     * @return {HTMLElement|null} the wrapper that received the error
     */
    function renderFieldError(form, name, message) {
        var wrapper = form.querySelector('[data-enk-name="' + cssEscape(name) + '"]');
        if (!wrapper) {
            return null;
        }

        wrapper.classList.add('enk-has-error');

        var hint = wrapper.querySelector('.enk-error');
        if (!hint) {
            hint = document.createElement('span');
            hint.className = 'hint enk-error';
            hint.setAttribute('role', 'alert');
            wrapper.appendChild(hint);
        }
        hint.textContent = message;

        return wrapper;
    }

    /**
     * Render a map of {name: message} errors and focus the first invalid field.
     *
     * @param {HTMLFormElement} form
     * @param {Object} errors
     */
    function renderErrors(form, errors) {
        var first = null;
        for (var name in errors) {
            if (Object.prototype.hasOwnProperty.call(errors, name)) {
                var wrapper = renderFieldError(form, name, errors[name]);
                if (!first && wrapper) {
                    first = wrapper;
                }
            }
        }
        if (first) {
            var focusable = first.querySelector('input, select, textarea');
            if (focusable && typeof focusable.focus === 'function') {
                focusable.focus();
            }
        }
    }

    /**
     * Replace the form with a success notice.
     *
     * @param {HTMLFormElement} form
     * @param {string} message
     */
    function showSuccess(form, message) {
        var note = document.createElement('div');
        note.className = 'form-note enk-success';
        note.setAttribute('role', 'status');
        note.textContent = message || 'Thank you. Your submission has been received.';

        // Empty the form, then mount the notice in its place.
        while (form.firstChild) {
            form.removeChild(form.firstChild);
        }
        form.appendChild(note);
    }

    /**
     * Show a general (form-level) error notice without destroying the form.
     *
     * @param {HTMLFormElement} form
     * @param {string} message
     */
    function showGeneralError(form, message) {
        var existing = form.querySelector('.enk-form-error');
        if (!existing) {
            existing = document.createElement('div');
            existing.className = 'form-note enk-error enk-form-error';
            existing.setAttribute('role', 'alert');
            var foot = form.querySelector('.form-foot');
            if (foot) {
                foot.parentNode.insertBefore(existing, foot);
            } else {
                form.appendChild(existing);
            }
        }
        existing.textContent = message || 'Something went wrong. Please try again.';
    }

    /**
     * Remove any general (form-level) error notice.
     *
     * @param {HTMLFormElement} form
     */
    function clearGeneralError(form) {
        var existing = form.querySelector('.enk-form-error');
        if (existing && existing.parentNode) {
            existing.parentNode.removeChild(existing);
        }
    }

    /**
     * Find the submit button for a form.
     *
     * @param {HTMLFormElement} form
     * @return {HTMLButtonElement|null}
     */
    function submitButton(form) {
        return form.querySelector('.form-foot button[type="submit"], button[type="submit"]');
    }

    /**
     * POST the collected values to the REST endpoint and handle the response.
     *
     * @param {HTMLFormElement} form
     * @param {Object} values
     */
    function send(form, values) {
        var globals = (typeof window !== 'undefined' && window.ENK_FORM) ? window.ENK_FORM : {};
        var button = submitButton(form);
        if (button) {
            button.disabled = true;
        }

        var nonceInput = form.querySelector('input[name="_enk_nonce"]');
        var honeypot = form.querySelector('.enk-hp input[name="enk_hp"]');

        var payload = {};
        for (var key in values) {
            if (Object.prototype.hasOwnProperty.call(values, key)) {
                payload[key] = values[key];
            }
        }
        payload.form_id = form.getAttribute('data-enk-form') || '';
        payload._enk_nonce = nonceInput ? nonceInput.value : '';
        payload.enk_hp = honeypot ? honeypot.value : '';

        fetch(globals.restUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': globals.nonce || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().then(function (data) {
                return { status: response.status, ok: response.ok, data: data || {} };
            }).catch(function () {
                return { status: response.status, ok: response.ok, data: {} };
            });
        }).then(function (result) {
            var data = result.data;

            if (result.status === 200 && data && data.success) {
                showSuccess(form, data.message);
                return;
            }

            if (result.status === 400 && data && data.errors) {
                if (button) {
                    button.disabled = false;
                }
                renderErrors(form, data.errors);
                return;
            }

            // Any other status (403/404/500/...) or a success:false body.
            if (button) {
                button.disabled = false;
            }
            showGeneralError(form, (data && data.message) || 'Something went wrong. Please try again.');
        }).catch(function () {
            // Network / fetch failure.
            if (button) {
                button.disabled = false;
            }
            showGeneralError(form, 'A network error occurred. Please try again.');
        });
    }

    /**
     * Wire a single form: validation, submit, cancel and on-input recovery.
     *
     * @param {HTMLFormElement} form
     */
    function initForm(form) {
        if (form.getAttribute('data-enk-init') === '1') {
            return;
        }
        form.setAttribute('data-enk-init', '1');

        var definition = buildDefinition(form);

        // Clear a field's error as soon as the user edits it.
        form.addEventListener('input', function (event) {
            var wrapper = event.target.closest ? event.target.closest('.enk-has-error') : null;
            if (wrapper) {
                wrapper.classList.remove('enk-has-error');
                var hint = wrapper.querySelector('.enk-error');
                if (hint && hint.parentNode) {
                    hint.parentNode.removeChild(hint);
                }
            }
        });
        form.addEventListener('change', function (event) {
            var wrapper = event.target.closest ? event.target.closest('.enk-has-error') : null;
            if (wrapper) {
                wrapper.classList.remove('enk-has-error');
                var hint = wrapper.querySelector('.enk-error');
                if (hint && hint.parentNode) {
                    hint.parentNode.removeChild(hint);
                }
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            clearErrors(form);
            clearGeneralError(form);

            var values = collectValues(form, definition);

            var validator = (typeof window !== 'undefined') ? window.EnkValidation : null;
            var errors = validator ? validator.validateForm(values, definition) : {};

            var hasErrors = false;
            for (var k in errors) {
                if (Object.prototype.hasOwnProperty.call(errors, k)) {
                    hasErrors = true;
                    break;
                }
            }

            if (hasErrors) {
                renderErrors(form, errors);
                return;
            }

            send(form, values);
        });

        // Cancel: reset the form and clear all error states.
        var cancel = form.querySelector('[data-enk-cancel]');
        if (cancel) {
            cancel.addEventListener('click', function (event) {
                event.preventDefault();
                form.reset();
                clearErrors(form);
                clearGeneralError(form);
            });
        }
    }

    /**
     * Initialise every Enkompass form on the page.
     */
    function init() {
        var forms = document.querySelectorAll('form.form-card[data-enk-form]');
        for (var i = 0; i < forms.length; i++) {
            initForm(forms[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
