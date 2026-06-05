/**
 * Field-type helpers for the Enkompass form builder.
 *
 * Pure-ish helpers: field factory (makeDefault), preview HTML generation
 * (previewHtml — mirrors includes/class-form-renderer.php), and the property
 * editors (renderProps for a field, renderFormParams for form settings).
 *
 * Exposes window.EnkFieldTypes. No build step, vanilla JS only.
 *
 * @package Enkompass\Contact
 */
(function (root) {
    'use strict';

    var ENK = root.ENK || {};
    var EnkValidation = root.EnkValidation;

    /* ------------------------------------------------------------------ */
    /* Small utilities                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * HTML-escape a value for safe insertion as text / attribute content.
     *
     * @param {*} value
     * @returns {string}
     */
    function esc(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * Sanitise a string into a submission key: lowercase, only a-z 0-9 _ -.
     *
     * @param {string} value
     * @returns {string}
     */
    function sanitizeName(value) {
        return String(value === null || value === undefined ? '' : value)
            .toLowerCase()
            .replace(/[^a-z0-9_-]+/g, '_')
            .replace(/^_+|_+$/g, '');
    }

    /**
     * Generate a stable-ish unique field id, e.g. "f_ab12cd".
     *
     * @returns {string}
     */
    function makeId() {
        return 'f_' + Math.random().toString(36).slice(2, 8);
    }

    /**
     * Look up a field-type descriptor from the ENK.fieldTypes catalog.
     *
     * @param {string} typeKey
     * @returns {object|null}
     */
    function typeMeta(typeKey) {
        var types = ENK.fieldTypes || [];
        for (var i = 0; i < types.length; i++) {
            if (types[i].key === typeKey) {
                return types[i];
            }
        }
        return null;
    }

    /**
     * Create a DOM element with optional attributes / text.
     *
     * @param {string} tag
     * @param {object} [attrs]
     * @param {string} [text]
     * @returns {HTMLElement}
     */
    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'class') {
                    node.className = attrs[k];
                } else if (k === 'checked' || k === 'disabled' || k === 'selected') {
                    if (attrs[k]) { node.setAttribute(k, k); }
                } else {
                    node.setAttribute(k, attrs[k]);
                }
            });
        }
        if (text !== undefined && text !== null) {
            node.textContent = String(text);
        }
        return node;
    }

    /* ------------------------------------------------------------------ */
    /* makeDefault                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Build a brand-new field object for a given type key.
     *
     * @param {string} typeKey
     * @returns {object}
     */
    function makeDefault(typeKey) {
        var meta = typeMeta(typeKey) || {};
        var isAction = !!meta.isAction;
        var hasOptions = !!meta.hasOptions;
        var label = meta.label || typeKey;

        var w = (typeKey === 'comments' || isAction) ? 12 : 6;
        var h = (typeKey === 'comments') ? 3 : 1;

        var field = {
            id: makeId(),
            type: typeKey,
            name: sanitizeName(typeKey),
            label: label,
            required: false,
            taborder: 0,
            css_class: meta.defaultCssClass || '',
            grid: { x: 0, y: 0, w: w, h: h },
            validation: [],
            validation_params: {},
            fail_message: '',
            options: [],
            props: {}
        };

        if (hasOptions) {
            field.options = [{ name: 'Option 1', value: '1' }];
        }

        if (typeKey === 'submit_cancel') {
            field.props.cancel_label = 'Cancel';
            field.props.cancel_css_class = 'btn btn--ghost';
        }

        return field;
    }

    /* ------------------------------------------------------------------ */
    /* previewHtml — mirrors includes/class-form-renderer.php             */
    /* ------------------------------------------------------------------ */

    /**
     * Render the rough preview HTML for a field inside the builder canvas.
     * Mirrors the live Form_Renderer output using the same Enkompass classes
     * so builder-preview.css can style it like the real form.
     *
     * @param {object} field
     * @returns {string}
     */
    function previewHtml(field) {
        field = field || {};
        var type = field.type || '';
        var meta = typeMeta(type) || {};
        var label = field.label || '';
        var cssClass = field.css_class || '';
        var required = !!field.required;
        var name = field.name || '';
        var reqMark = required ? ' <span class="req">*</span>' : '';

        // Action buttons (submit / submit_cancel).
        if (meta.isAction) {
            var btnClass = cssClass || 'btn btn--primary';
            var out = '<div class="form-foot">';
            out += '<button type="button" class="' + esc(btnClass) + '">' + esc(label || 'Submit') + '</button>';
            if (type === 'submit_cancel') {
                var cancelLabel = (field.props && field.props.cancel_label) || 'Cancel';
                var cancelClass = (field.props && field.props.cancel_css_class) || 'btn btn--ghost';
                out += '<button type="button" class="' + esc(cancelClass) + '">' + esc(cancelLabel) + '</button>';
            }
            out += '</div>';
            return out;
        }

        // Choice groups (checkbox / radio).
        if (type === 'checkbox' || type === 'radio') {
            var checkClass = cssClass || 'check';
            var inputTy = type === 'checkbox' ? 'checkbox' : 'radio';
            var group = '<div class="field">';
            group += '<span class="label">' + esc(label) + reqMark + '</span>';
            var opts = Array.isArray(field.options) ? field.options : [];
            if (opts.length === 0) {
                opts = [{ name: 'Option 1', value: '1' }];
            }
            opts.forEach(function (opt) {
                group += '<label class="' + esc(checkClass) + '">'
                    + '<input type="' + inputTy + '" disabled /> '
                    + esc(opt && opt.name !== undefined ? opt.name : '') + '</label>';
            });
            group += '</div>';
            return group;
        }

        // Single labelled control (text / email / date / comments / dropdown).
        var control;
        if (type === 'comments') {
            control = '<textarea class="' + esc(cssClass || 'textarea') + '" disabled></textarea>';
        } else if (type === 'dropdown') {
            var sel = '<select class="' + esc(cssClass || 'select') + '" disabled>';
            var dopts = Array.isArray(field.options) ? field.options : [];
            if (dopts.length === 0) {
                dopts = [{ name: 'Option 1', value: '1' }];
            }
            dopts.forEach(function (opt) {
                sel += '<option>' + esc(opt && opt.name !== undefined ? opt.name : '') + '</option>';
            });
            sel += '</select>';
            control = sel;
        } else {
            var inputType = type === 'email' ? 'email' : (type === 'date' ? 'date' : 'text');
            var placeholder = (field.props && field.props.placeholder) ? field.props.placeholder : '';
            control = '<input type="' + inputType + '" class="' + esc(cssClass || 'input') + '"'
                + (placeholder ? ' placeholder="' + esc(placeholder) + '"' : '') + ' disabled />';
        }

        var labelHtml = '<label class="label">' + esc(label) + reqMark + '</label>';
        // Show the submission key faintly so the builder is legible.
        var nameHint = name ? '<span class="enk-field-name">' + esc(name) + '</span>' : '';

        return '<div class="field">' + labelHtml + control + nameHint + '</div>';
    }

    /* ------------------------------------------------------------------ */
    /* Property-editor building blocks                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Build a labelled control row and append it to the parent.
     *
     * @param {HTMLElement} parent
     * @param {string} labelText
     * @param {HTMLElement} control
     * @returns {HTMLElement} the row
     */
    function fieldRow(parent, labelText, control) {
        var row = el('div', { class: 'enk-prop-row' });
        var lbl = el('label', { class: 'enk-prop-label' }, labelText);
        if (control.id) {
            lbl.setAttribute('for', control.id);
        }
        row.appendChild(lbl);
        row.appendChild(control);
        parent.appendChild(row);
        return row;
    }

    /**
     * Build a text/number input bound to obj[key], calling onChange on input.
     *
     * @param {object} obj
     * @param {string} key
     * @param {string} type
     * @param {function} onChange
     * @param {function} [transform]
     * @returns {HTMLInputElement}
     */
    function textInput(obj, key, type, onChange, transform) {
        var input = el('input', {
            class: 'enk-prop-input',
            type: type || 'text'
        });
        input.value = obj[key] === null || obj[key] === undefined ? '' : obj[key];
        input.addEventListener('input', function () {
            var v = input.value;
            if (transform) {
                v = transform(v);
                if (v !== input.value) {
                    input.value = v;
                }
            }
            if (type === 'number') {
                obj[key] = v === '' ? 0 : parseInt(v, 10) || 0;
            } else {
                obj[key] = v;
            }
            onChange();
        });
        return input;
    }

    /**
     * Build a checkbox bound to obj[key].
     *
     * @param {object} obj
     * @param {string} key
     * @param {string} labelText
     * @param {function} onChange
     * @returns {HTMLElement}
     */
    function checkboxRow(obj, key, labelText, onChange) {
        var wrap = el('label', { class: 'enk-prop-check' });
        var input = el('input', { type: 'checkbox' });
        input.checked = !!obj[key];
        input.addEventListener('change', function () {
            obj[key] = input.checked;
            onChange();
        });
        wrap.appendChild(input);
        wrap.appendChild(document.createTextNode(' ' + labelText));
        return wrap;
    }

    /**
     * Build the validation rule multi-select (checkbox list).
     *
     * @param {object} field
     * @param {function} onChange
     * @returns {HTMLElement}
     */
    function validationControl(field, onChange) {
        var wrap = el('div', { class: 'enk-prop-validation' });
        var rules = ENK.validationRules || [];

        if (!Array.isArray(field.validation)) {
            field.validation = [];
        }

        rules.forEach(function (rule) {
            var row = el('label', { class: 'enk-prop-check enk-prop-check--rule' });
            var input = el('input', { type: 'checkbox', value: rule.key });
            input.checked = field.validation.indexOf(rule.key) !== -1;
            input.addEventListener('change', function () {
                var idx = field.validation.indexOf(rule.key);
                if (input.checked && idx === -1) {
                    field.validation.push(rule.key);
                } else if (!input.checked && idx !== -1) {
                    field.validation.splice(idx, 1);
                }
                onChange();
            });
            row.appendChild(input);
            row.appendChild(document.createTextNode(' ' + (rule.label || rule.key)));
            wrap.appendChild(row);
        });

        return wrap;
    }

    /**
     * Build the options repeater (name + value rows with add / remove).
     *
     * @param {object} field
     * @param {function} onChange
     * @returns {HTMLElement}
     */
    function optionsRepeater(field, onChange) {
        if (!Array.isArray(field.options)) {
            field.options = [];
        }
        var wrap = el('div', { class: 'enk-options' });
        var list = el('div', { class: 'enk-options-list' });
        wrap.appendChild(list);

        function renderRows() {
            list.innerHTML = '';
            field.options.forEach(function (opt, idx) {
                var row = el('div', { class: 'enk-option-row' });

                var nameIn = el('input', { class: 'enk-prop-input', type: 'text', placeholder: 'Name' });
                nameIn.value = opt.name || '';
                nameIn.addEventListener('input', function () {
                    opt.name = nameIn.value;
                    onChange();
                });

                var valIn = el('input', { class: 'enk-prop-input', type: 'text', placeholder: 'Value' });
                valIn.value = opt.value || '';
                valIn.addEventListener('input', function () {
                    opt.value = valIn.value;
                    onChange();
                });

                var remove = el('button', { type: 'button', class: 'enk-btn-icon', title: 'Remove' }, '×');
                remove.addEventListener('click', function () {
                    field.options.splice(idx, 1);
                    renderRows();
                    onChange();
                });

                row.appendChild(nameIn);
                row.appendChild(valIn);
                row.appendChild(remove);
                list.appendChild(row);
            });
        }

        renderRows();

        var add = el('button', { type: 'button', class: 'enk-btn-small' }, '+ Add option');
        add.addEventListener('click', function () {
            var n = field.options.length + 1;
            field.options.push({ name: 'Option ' + n, value: String(n) });
            renderRows();
            onChange();
        });
        wrap.appendChild(add);

        return wrap;
    }

    /* ------------------------------------------------------------------ */
    /* renderProps                                                         */
    /* ------------------------------------------------------------------ */

    /** Type keys that get a placeholder property. */
    var PLACEHOLDER_TYPES = {
        text: true,
        email: true,
        company: true,
        first_name: true,
        last_name: true,
        date: true
    };

    /**
     * Build the property editor for a single field into containerEl.
     *
     * @param {object} field
     * @param {HTMLElement} containerEl
     * @param {function} onChange
     */
    function renderProps(field, containerEl, onChange) {
        containerEl.innerHTML = '';
        onChange = onChange || function () {};

        var meta = typeMeta(field.type) || {};

        var head = el('div', { class: 'enk-props-head' }, (meta.label || field.type) + ' field');
        containerEl.appendChild(head);

        var form = el('div', { class: 'enk-props-form' });
        containerEl.appendChild(form);

        // Name (sanitised submission key).
        fieldRow(form, 'Name (submission key)',
            textInput(field, 'name', 'text', onChange, sanitizeName));

        // Label.
        fieldRow(form, 'Label', textInput(field, 'label', 'text', onChange));

        // Required.
        var reqRow = el('div', { class: 'enk-prop-row' });
        reqRow.appendChild(checkboxRow(field, 'required', 'Required', onChange));
        form.appendChild(reqRow);

        // Tab order.
        fieldRow(form, 'Tab order', textInput(field, 'taborder', 'number', onChange));

        // CSS class.
        fieldRow(form, 'CSS class', textInput(field, 'css_class', 'text', onChange));

        // Type-specific: placeholder.
        if (PLACEHOLDER_TYPES[field.type]) {
            field.props = field.props || {};
            fieldRow(form, 'Placeholder', textInput(field.props, 'placeholder', 'text', onChange));
        }

        // Type-specific: rows (comments).
        if (field.type === 'comments') {
            field.props = field.props || {};
            fieldRow(form, 'Rows', textInput(field.props, 'rows', 'number', onChange));
        }

        // Type-specific: submit_cancel cancel button.
        if (field.type === 'submit_cancel') {
            field.props = field.props || {};
            fieldRow(form, 'Cancel label', textInput(field.props, 'cancel_label', 'text', onChange));
            fieldRow(form, 'Cancel CSS class', textInput(field.props, 'cancel_css_class', 'text', onChange));
        }

        // Options repeater for choice types.
        if (meta.hasOptions) {
            var optHead = el('div', { class: 'enk-prop-sub' }, 'Options');
            form.appendChild(optHead);
            form.appendChild(optionsRepeater(field, onChange));
        }

        // Validation rules.
        var valHead = el('div', { class: 'enk-prop-sub' }, 'Validation');
        form.appendChild(valHead);
        form.appendChild(validationControl(field, onChange));

        // Fail message.
        fieldRow(form, 'Failure message', textInput(field, 'fail_message', 'text', onChange));
    }

    /* ------------------------------------------------------------------ */
    /* renderFormParams                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Ensure the destinations structure exists with sane defaults.
     *
     * @param {object} definition
     */
    function ensureDestinations(definition) {
        if (!definition.destinations) {
            definition.destinations = {};
        }
        var d = definition.destinations;
        if (!d.email) { d.email = { enabled: false, recipients: [] }; }
        if (!Array.isArray(d.email.recipients)) { d.email.recipients = []; }
        if (!d.database) { d.database = { enabled: false }; }
        if (!d.textfile) { d.textfile = { enabled: false, filename: null }; }
    }

    /**
     * Build the email recipients repeater (one email per row + add / remove).
     * Each row is validated with EnkValidation.test('IsEmail', v).
     *
     * @param {object} email
     * @param {function} onChange
     * @returns {HTMLElement}
     */
    function recipientsRepeater(email, onChange) {
        if (!Array.isArray(email.recipients)) {
            email.recipients = [];
        }
        var wrap = el('div', { class: 'enk-recipients' });
        var list = el('div', { class: 'enk-recipients-list' });
        wrap.appendChild(list);

        function validate(input) {
            var v = input.value.trim();
            var ok = v === '' || !EnkValidation || EnkValidation.test('IsEmail', v);
            if (ok) {
                input.classList.remove('enk-invalid');
            } else {
                input.classList.add('enk-invalid');
            }
        }

        function renderRows() {
            list.innerHTML = '';
            if (email.recipients.length === 0) {
                email.recipients.push('');
            }
            email.recipients.forEach(function (addr, idx) {
                var row = el('div', { class: 'enk-recipient-row' });

                var input = el('input', {
                    class: 'enk-prop-input',
                    type: 'email',
                    placeholder: 'name@example.com'
                });
                input.value = addr || '';
                validate(input);
                input.addEventListener('input', function () {
                    email.recipients[idx] = input.value.trim();
                    validate(input);
                    onChange();
                });

                var remove = el('button', { type: 'button', class: 'enk-btn-icon', title: 'Remove' }, '×');
                remove.addEventListener('click', function () {
                    email.recipients.splice(idx, 1);
                    renderRows();
                    onChange();
                });

                row.appendChild(input);
                row.appendChild(remove);
                list.appendChild(row);
            });
        }

        renderRows();

        var add = el('button', { type: 'button', class: 'enk-btn-small' }, '+ Add address');
        add.addEventListener('click', function () {
            email.recipients.push('');
            renderRows();
            onChange();
        });
        wrap.appendChild(add);

        return wrap;
    }

    /**
     * Build the FORM settings editor into containerEl.
     *
     * @param {object} definition
     * @param {HTMLElement} containerEl
     * @param {function} onChange
     */
    function renderFormParams(definition, containerEl, onChange) {
        containerEl.innerHTML = '';
        onChange = onChange || function () {};
        ensureDestinations(definition);

        var head = el('div', { class: 'enk-props-head' }, 'Form settings');
        containerEl.appendChild(head);

        var form = el('div', { class: 'enk-props-form' });
        containerEl.appendChild(form);

        // Title.
        if (definition.title === undefined || definition.title === null) {
            definition.title = '';
        }
        fieldRow(form, 'Title', textInput(definition, 'title', 'text', onChange));

        // CSS class.
        if (!definition.css_class) {
            definition.css_class = 'form-card';
        }
        fieldRow(form, 'CSS class', textInput(definition, 'css_class', 'text', onChange));

        // Destinations.
        var destHead = el('div', { class: 'enk-prop-sub' }, 'Destinations');
        form.appendChild(destHead);

        var d = definition.destinations;

        var emailRow = el('div', { class: 'enk-prop-row' });
        emailRow.appendChild(checkboxRow(d.email, 'enabled', 'Email', function () {
            onChange();
            toggleRecipients();
        }));
        form.appendChild(emailRow);

        var recipientsHolder = el('div', { class: 'enk-recipients-holder' });
        form.appendChild(recipientsHolder);

        function toggleRecipients() {
            recipientsHolder.innerHTML = '';
            if (d.email.enabled) {
                var lbl = el('div', { class: 'enk-prop-sub enk-prop-sub--minor' }, 'Email Address to Send To');
                recipientsHolder.appendChild(lbl);
                recipientsHolder.appendChild(recipientsRepeater(d.email, onChange));
            }
        }
        toggleRecipients();

        var dbRow = el('div', { class: 'enk-prop-row' });
        dbRow.appendChild(checkboxRow(d.database, 'enabled', 'Database', onChange));
        form.appendChild(dbRow);

        var txtRow = el('div', { class: 'enk-prop-row' });
        txtRow.appendChild(checkboxRow(d.textfile, 'enabled', 'Text File', onChange));
        form.appendChild(txtRow);
    }

    /* ------------------------------------------------------------------ */
    /* Export                                                              */
    /* ------------------------------------------------------------------ */

    root.EnkFieldTypes = {
        esc: esc,
        sanitizeName: sanitizeName,
        makeId: makeId,
        typeMeta: typeMeta,
        makeDefault: makeDefault,
        previewHtml: previewHtml,
        renderProps: renderProps,
        renderFormParams: renderFormParams
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = root.EnkFieldTypes;
    }
}(typeof window !== 'undefined' ? window : globalThis));
