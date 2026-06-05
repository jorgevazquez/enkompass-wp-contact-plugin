/**
 * The form-builder modal controller.
 *
 * Opens a form into the GridStack canvas, lets the user drag field types from
 * the palette, edit their properties, then serialises everything back into a
 * form definition and POSTs it via the admin REST API.
 *
 * Exposes window.EnkBuilder. Depends on window.GridStack (vendored),
 * window.EnkFieldTypes and window.EnkValidation.
 *
 * @package Enkompass\Contact
 */
(function (root) {
    'use strict';

    var ENK = root.ENK || {};
    var FT = root.EnkFieldTypes;

    /* ------------------------------------------------------------------ */
    /* REST helper                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Centralised REST fetch with the WP nonce header.
     *
     * @param {string} method
     * @param {string} path  Path appended to ENK.restUrl (leading slash).
     * @param {object} [body]
     * @returns {Promise<object>}
     */
    function api(method, path, body) {
        var opts = {
            method: method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': ENK.nonce
            }
        };
        if (body !== undefined && body !== null) {
            opts.body = JSON.stringify(body);
        }
        return fetch(ENK.restUrl + path, opts).then(function (res) {
            if (!res.ok) {
                return res.text().then(function (text) {
                    throw new Error('Request failed (' + res.status + '): ' + text);
                });
            }
            if (res.status === 204) {
                return {};
            }
            return res.json();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Builder state                                                       */
    /* ------------------------------------------------------------------ */

    var grid = null;            // GridStack instance.
    var definition = null;      // Working (deep-cloned) form definition.
    var fieldsById = {};        // id -> field object.
    var selectedId = null;      // Currently selected field id.
    var dirty = false;          // Unsaved changes flag.

    function $(sel) { return document.querySelector(sel); }

    function deepClone(obj) {
        return JSON.parse(JSON.stringify(obj));
    }

    function markDirty() { dirty = true; }

    /* ------------------------------------------------------------------ */
    /* Definition normalisation                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Ensure a fetched definition has all the shape we rely on.
     *
     * @param {object} def
     * @returns {object}
     */
    function normalize(def) {
        def = def || {};
        if (!def.grid) { def.grid = { columns: 12 }; }
        if (!def.css_class) { def.css_class = 'form-card'; }
        if (def.title === undefined || def.title === null) { def.title = ''; }
        if (!def.destinations) { def.destinations = {}; }
        var d = def.destinations;
        if (!d.email) { d.email = { enabled: false, recipients: [] }; }
        if (!Array.isArray(d.email.recipients)) { d.email.recipients = []; }
        if (!d.database) { d.database = { enabled: false }; }
        if (!d.textfile) { d.textfile = { enabled: false, filename: null }; }
        if (!Array.isArray(def.fields)) { def.fields = []; }

        def.fields.forEach(function (f) {
            if (!f.id) { f.id = FT.makeId(); }
            if (!f.grid) { f.grid = { x: 0, y: 0, w: 6, h: 1 }; }
            if (!Array.isArray(f.validation)) { f.validation = []; }
            if (!f.validation_params) { f.validation_params = {}; }
            if (!Array.isArray(f.options)) { f.options = []; }
            if (!f.props) { f.props = {}; }
        });

        return def;
    }

    /* ------------------------------------------------------------------ */
    /* Widget rendering                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Build the inner content element for a widget showing the field preview.
     *
     * @param {object} field
     * @returns {HTMLElement}
     */
    function widgetContent(field) {
        var inner = document.createElement('div');
        inner.className = 'enk-widget';
        inner.dataset.fieldId = field.id;
        inner.innerHTML = FT.previewHtml(field);
        return inner;
    }

    /**
     * Refresh a single widget's preview after a property change.
     *
     * @param {object} field
     */
    function refreshWidget(field) {
        var inner = document.querySelector('.enk-widget[data-field-id="' + field.id + '"]');
        if (inner) {
            inner.innerHTML = FT.previewHtml(field);
        }
    }

    /**
     * Apply resize permissions to a widget element from its type flags.
     * NOTE: GridStack resizes BOTH axes from a single corner handle; it has no
     * native single-axis lock, so width-only types (e.g. inputs) still allow
     * the handle to drag both ways. We only fully lock resize for types that
     * are neither width- nor height-resizable (checkbox, radio).
     *
     * @param {HTMLElement} elem
     * @param {object} field
     */
    function applyResize(elem, field) {
        var meta = FT.typeMeta(field.type) || {};
        var resizable = !!meta.resizeWidth || !!meta.resizeHeight;
        grid.update(elem, { noResize: !resizable });
    }

    /**
     * Add a widget for a field to the grid and wire it up.
     *
     * @param {object} field
     * @returns {HTMLElement} the widget element
     */
    function addWidget(field) {
        var g = field.grid || {};
        var node = grid.addWidget({
            x: g.x || 0,
            y: g.y || 0,
            w: g.w || 6,
            h: g.h || 1,
            id: field.id
        });
        // GridStack v10/v11 returns the grid-item element.
        var elem = node;
        elem.classList.add('enk-grid-item');
        elem.dataset.fieldId = field.id;

        var contentHost = elem.querySelector('.grid-stack-item-content') || elem;
        contentHost.innerHTML = '';
        contentHost.appendChild(widgetContent(field));

        applyResize(elem, field);
        return elem;
    }

    /* ------------------------------------------------------------------ */
    /* Selection / properties                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Highlight the selected widget and clear others.
     *
     * @param {string|null} id
     */
    function highlight(id) {
        var items = document.querySelectorAll('#enk-canvas .enk-grid-item');
        items.forEach(function (item) {
            if (item.dataset.fieldId === id) {
                item.classList.add('enk-selected');
            } else {
                item.classList.remove('enk-selected');
            }
        });
    }

    /**
     * Select a field and render its properties into the left sidebar.
     *
     * @param {string} id
     */
    function selectField(id) {
        var field = fieldsById[id];
        if (!field) { return; }
        selectedId = id;
        highlight(id);
        FT.renderProps(field, $('#enk-props'), function () {
            markDirty();
            refreshWidget(field);
        });
    }

    /**
     * Deselect and show the form-settings editor.
     */
    function selectForm() {
        selectedId = null;
        highlight(null);
        FT.renderFormParams(definition, $('#enk-props'), function () {
            markDirty();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Field-name de-duplication                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Ensure a freshly-created field has a unique submission name.
     *
     * @param {object} field
     */
    function dedupeName(field) {
        var base = field.name || field.type;
        var name = base;
        var n = 2;
        var taken = {};
        definition.fields.forEach(function (f) {
            if (f.id !== field.id && f.name) {
                taken[f.name] = true;
            }
        });
        while (taken[name]) {
            name = base + '_' + n;
            n += 1;
        }
        field.name = name;
    }

    /* ------------------------------------------------------------------ */
    /* Palette                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Create a field at the next free row (click fallback for the palette).
     *
     * @param {string} typeKey
     */
    function addFieldAtEnd(typeKey) {
        var field = FT.makeDefault(typeKey);
        dedupeName(field);
        // Place below everything currently on the grid.
        var maxY = 0;
        definition.fields.forEach(function (f) {
            var bottom = (f.grid.y || 0) + (f.grid.h || 1);
            if (bottom > maxY) { maxY = bottom; }
        });
        field.grid.x = 0;
        field.grid.y = maxY;
        definition.fields.push(field);
        fieldsById[field.id] = field;
        addWidget(field);
        markDirty();
        selectField(field.id);
    }

    /**
     * Build the palette list from ENK.fieldTypes and wire drag-in + click.
     */
    function buildPalette() {
        var palette = $('#enk-palette');
        palette.innerHTML = '';

        (ENK.fieldTypes || []).forEach(function (t) {
            var li = document.createElement('li');
            li.className = 'enk-palette-item';
            li.dataset.type = t.key;
            li.setAttribute('role', 'button');
            li.setAttribute('tabindex', '0');
            li.textContent = t.label;
            palette.appendChild(li);

            // Click / keyboard fallback (environments without HTML5 DnD).
            li.addEventListener('click', function () {
                addFieldAtEnd(t.key);
            });
            li.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    addFieldAtEnd(t.key);
                }
            });
        });

        // Native GridStack drag-from-palette: a cloned helper is appended to
        // <body> and dropped onto the canvas, firing the 'dropped' event below.
        if (root.GridStack && typeof root.GridStack.setupDragIn === 'function') {
            root.GridStack.setupDragIn('.enk-palette-item', { appendTo: 'body', helper: 'clone' });
        }
    }

    /* ------------------------------------------------------------------ */
    /* Open                                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Open the builder for a form id.
     *
     * @param {number|string} formId
     * @returns {Promise<void>}
     */
    function open(formId) {
        return api('GET', '/forms/' + formId).then(function (form) {
            definition = normalize(deepClone(form));
            fieldsById = {};
            selectedId = null;
            dirty = false;

            // Init GridStack on a clean canvas.
            var canvas = $('#enk-canvas');
            canvas.innerHTML = '';
            grid = root.GridStack.init({
                column: 12,
                cellHeight: 60,
                margin: 6,
                float: true
            }, canvas);

            // Add a widget per field.
            definition.fields.forEach(function (field) {
                fieldsById[field.id] = field;
                addWidget(field);
            });

            buildPalette();

            // Drag-from-palette drop handler: create the field + select it.
            grid.on('dropped', function (event, previousNode, newNode) {
                if (!newNode || !newNode.el) { return; }
                var typeKey = newNode.el.dataset ? newNode.el.dataset.type : null;
                if (!typeKey) { return; }

                var field = FT.makeDefault(typeKey);
                // Adopt the dropped position / size from GridStack.
                field.grid.x = newNode.x || 0;
                field.grid.y = newNode.y || 0;
                if (newNode.w) { field.grid.w = newNode.w; }
                if (newNode.h) { field.grid.h = newNode.h; }
                dedupeName(field);

                definition.fields.push(field);
                fieldsById[field.id] = field;

                // Convert the dropped placeholder element into a real widget.
                var elem = newNode.el;
                elem.classList.add('enk-grid-item');
                elem.dataset.fieldId = field.id;
                elem.removeAttribute('data-type');
                grid.update(elem, { id: field.id });

                var host = elem.querySelector('.grid-stack-item-content') || elem;
                host.innerHTML = '';
                host.appendChild(widgetContent(field));
                applyResize(elem, field);

                markDirty();
                selectField(field.id);
            });

            // Keep field.grid in sync when widgets move / resize.
            grid.on('change', function (event, items) {
                if (!items) { return; }
                items.forEach(function (item) {
                    var id = item.el ? item.el.dataset.fieldId : item.id;
                    var field = fieldsById[id];
                    if (field) {
                        field.grid.x = item.x;
                        field.grid.y = item.y;
                        field.grid.w = item.w;
                        field.grid.h = item.h;
                    }
                });
                markDirty();
            });

            // Click selection: a widget selects its field; empty canvas selects form.
            canvas.addEventListener('click', function (e) {
                var item = e.target.closest ? e.target.closest('.enk-grid-item') : null;
                if (item && item.dataset.fieldId) {
                    selectField(item.dataset.fieldId);
                } else {
                    selectForm();
                }
            });

            // Start on form settings.
            selectForm();

            // Show the modal.
            var modal = $('#enk-modal');
            if (modal) { modal.removeAttribute('hidden'); }
        }).catch(function (err) {
            root.alert ? root.alert('Could not open form: ' + err.message) : null;
        });
    }

    /* ------------------------------------------------------------------ */
    /* Save                                                                */
    /* ------------------------------------------------------------------ */

    /**
     * Read widget geometry back into fields, assign taborder by visual order,
     * assemble the definition and POST it.
     *
     * @returns {Promise<void>}
     */
    function save() {
        // Pull the live grid node geometry into every field.
        if (grid && typeof grid.getGridItems === 'function') {
            grid.getGridItems().forEach(function (elem) {
                var node = elem.gridstackNode;
                var id = elem.dataset.fieldId || (node && node.id);
                var field = fieldsById[id];
                if (field && node) {
                    field.grid.x = node.x;
                    field.grid.y = node.y;
                    field.grid.w = node.w;
                    field.grid.h = node.h;
                }
            });
        }

        // Tab order follows visual reading order (top-to-bottom, left-to-right).
        var ordered = definition.fields.slice().sort(function (a, b) {
            var ay = a.grid.y || 0, by = b.grid.y || 0;
            if (ay !== by) { return ay - by; }
            return (a.grid.x || 0) - (b.grid.x || 0);
        });
        ordered.forEach(function (field, idx) {
            field.taborder = idx + 1;
        });

        var payload = {
            id: definition.id,
            name: definition.name,
            title: definition.title || '',
            css_class: definition.css_class || 'form-card',
            grid: definition.grid || { columns: 12 },
            destinations: definition.destinations,
            fields: definition.fields
        };

        return api('POST', '/forms/' + definition.id, { definition: payload })
            .then(function () {
                dirty = false;
                close({ force: true });
                root.location.reload();
            })
            .catch(function (err) {
                root.alert ? root.alert('Could not save form: ' + err.message) : null;
            });
    }

    /* ------------------------------------------------------------------ */
    /* Close                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Close the modal, optionally forcing past the unsaved-changes guard.
     *
     * @param {object} [opts]
     */
    function close(opts) {
        opts = opts || {};
        if (dirty && !opts.force) {
            var msg = (ENK.i18n && ENK.i18n.unsaved) || 'You have unsaved changes. Discard them?';
            if (!root.confirm(msg)) {
                return;
            }
        }

        if (grid && typeof grid.destroy === 'function') {
            // false = keep the DOM element, just tear down GridStack.
            grid.destroy(false);
        }
        grid = null;

        var canvas = $('#enk-canvas');
        if (canvas) { canvas.innerHTML = ''; }
        var props = $('#enk-props');
        if (props) { props.innerHTML = ''; }

        definition = null;
        fieldsById = {};
        selectedId = null;
        dirty = false;

        var modal = $('#enk-modal');
        if (modal) { modal.setAttribute('hidden', 'hidden'); }
    }

    /* ------------------------------------------------------------------ */
    /* Wiring                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Attach the modal's own buttons / backdrop handlers. Idempotent-safe to
     * call once on DOMContentLoaded.
     */
    function wire() {
        var save_ = $('#enk-save');
        if (save_) { save_.addEventListener('click', function () { save(); }); }

        var cancel = $('#enk-cancel');
        if (cancel) { cancel.addEventListener('click', function () { close({}); }); }

        var closeBtn = $('#enk-modal-close');
        if (closeBtn) { closeBtn.addEventListener('click', function () { close({}); }); }

        var modal = $('#enk-modal');
        if (modal) {
            // Backdrop click (the overlay itself, not its inner modal) closes.
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    close({});
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wire);
    } else {
        wire();
    }

    /* ------------------------------------------------------------------ */
    /* Export                                                              */
    /* ------------------------------------------------------------------ */

    root.EnkBuilder = {
        api: api,
        open: open,
        save: save,
        close: close
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = root.EnkBuilder;
    }
}(typeof window !== 'undefined' ? window : globalThis));
