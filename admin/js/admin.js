/**
 * Forms-tab page controller.
 *
 * Handles the cards grid: creating forms (+ card), inline renaming, opening the
 * builder, and deleting forms. Uses event delegation on #enk-cards so cards
 * added at runtime keep working.
 *
 * Depends on window.EnkBuilder (REST helper + open()) and window.ENK.
 *
 * @package Enkompass\Contact
 */
(function (root) {
    'use strict';

    var ENK = root.ENK || {};

    /** Reuse the builder's REST helper so the nonce handling is centralised. */
    function api(method, path, body) {
        return root.EnkBuilder.api(method, path, body);
    }

    function $(sel, ctx) { return (ctx || document).querySelector(sel); }

    /* ------------------------------------------------------------------ */
    /* Card creation                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Build a card-wrap element matching the PHP markup in tab-forms.php.
     *
     * @param {object} form  REST form object ({id, name, ...}).
     * @returns {HTMLElement}
     */
    function buildCard(form) {
        var id = form.id;
        var name = form.name || '';

        var wrap = document.createElement('div');
        wrap.className = 'enk-card-wrap';
        wrap.setAttribute('data-form-id', String(id));

        var title = document.createElement('div');
        title.className = 'enk-card-title';
        title.setAttribute('role', 'textbox');
        title.setAttribute('tabindex', '0');
        title.setAttribute('data-form-id', String(id));
        title.textContent = name;
        wrap.appendChild(title);

        var open = document.createElement('button');
        open.type = 'button';
        open.className = 'enk-card enk-open-form';
        open.setAttribute('data-form-id', String(id));

        var stat1 = document.createElement('span');
        stat1.className = 'enk-card-stat';
        var strong1 = document.createElement('strong');
        strong1.textContent = '0';
        stat1.appendChild(strong1);
        stat1.appendChild(document.createTextNode(' fields'));

        var stat2 = document.createElement('span');
        stat2.className = 'enk-card-stat';
        var strong2 = document.createElement('strong');
        strong2.textContent = '0';
        stat2.appendChild(strong2);
        stat2.appendChild(document.createTextNode(' submissions'));

        open.appendChild(stat1);
        open.appendChild(stat2);
        wrap.appendChild(open);

        var actions = document.createElement('div');
        actions.className = 'enk-card-actions';
        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'button button-small button-link-delete enk-delete-form';
        del.setAttribute('data-form-id', String(id));
        del.textContent = 'Delete';
        actions.appendChild(del);
        wrap.appendChild(actions);

        return wrap;
    }

    /**
     * Create a new form, insert its card before the add-card, and start an
     * inline rename so the user can name it immediately.
     */
    function createForm() {
        api('POST', '/forms').then(function (form) {
            var cards = $('#enk-cards');
            var addWrap = cards.querySelector('.enk-add-card-wrap');
            var card = buildCard(form);
            cards.insertBefore(card, addWrap);
            startRename(card.querySelector('.enk-card-title'));
        }).catch(function (err) {
            root.alert ? root.alert('Could not create form: ' + err.message) : null;
        });
    }

    /* ------------------------------------------------------------------ */
    /* Inline rename                                                       */
    /* ------------------------------------------------------------------ */

    /** Tracks the title currently being edited (so we don't double-bind). */
    var editingTitle = null;

    /**
     * Enter inline-edit mode on a card title: swap it to a focused input with
     * its text selected. Enter / blur commit, Escape reverts.
     *
     * @param {HTMLElement} title
     */
    function startRename(title) {
        if (!title || editingTitle === title) { return; }
        editingTitle = title;

        var formId = title.getAttribute('data-form-id');
        var previous = title.textContent.trim();

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'enk-card-title-input';
        input.value = previous;
        input.setAttribute('aria-label', 'Form name');

        title.textContent = '';
        title.classList.add('enk-editing');
        title.appendChild(input);
        input.focus();
        input.select();

        var done = false;

        function finish(commit) {
            if (done) { return; }
            done = true;
            editingTitle = null;
            title.classList.remove('enk-editing');

            var value = input.value.trim();

            if (!commit || value === '') {
                title.textContent = previous;
                return;
            }

            title.textContent = value;

            if (value !== previous) {
                api('POST', '/forms/' + formId + '/rename', { name: value })
                    .then(function (res) {
                        if (res && res.name) {
                            title.textContent = res.name;
                        }
                    })
                    .catch(function (err) {
                        title.textContent = previous;
                        root.alert ? root.alert('Could not rename form: ' + err.message) : null;
                    });
            }
        }

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                finish(true);
            } else if (e.key === 'Escape') {
                e.preventDefault();
                finish(false);
            }
        });
        input.addEventListener('blur', function () {
            finish(true);
        });
    }

    /* ------------------------------------------------------------------ */
    /* Delete                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * Confirm + delete a form, then remove its card from the DOM.
     *
     * @param {string} formId
     * @param {HTMLElement} wrap
     */
    function deleteForm(formId, wrap) {
        var msg = (ENK.i18n && ENK.i18n.confirmDelete) || 'Delete this form?';
        if (!root.confirm(msg)) { return; }

        api('DELETE', '/forms/' + formId).then(function () {
            if (wrap && wrap.parentNode) {
                wrap.parentNode.removeChild(wrap);
            }
        }).catch(function (err) {
            root.alert ? root.alert('Could not delete form: ' + err.message) : null;
        });
    }

    /* ------------------------------------------------------------------ */
    /* Wiring                                                              */
    /* ------------------------------------------------------------------ */

    function init() {
        var cards = $('#enk-cards');
        if (!cards) { return; }

        // Add-form button.
        var add = $('#enk-add-form');
        if (add) {
            add.addEventListener('click', function (e) {
                e.preventDefault();
                createForm();
            });
        }

        // Delegated click handling for the (possibly dynamic) cards.
        cards.addEventListener('click', function (e) {
            var target = e.target;

            // Delete.
            var del = target.closest('.enk-delete-form');
            if (del) {
                e.preventDefault();
                e.stopPropagation();
                deleteForm(del.getAttribute('data-form-id'), del.closest('.enk-card-wrap'));
                return;
            }

            // CSV download link: let it through.
            if (target.closest('.enk-csv-dl')) {
                return;
            }

            // Inline rename: clicking the title.
            var title = target.closest('.enk-card-title');
            if (title && !title.closest('.enk-add-card-wrap')) {
                e.preventDefault();
                startRename(title);
                return;
            }

            // Open the builder: the open button or the card body.
            var openBtn = target.closest('.enk-open-form');
            if (openBtn) {
                e.preventDefault();
                root.EnkBuilder.open(openBtn.getAttribute('data-form-id'));
            }
        });

        // Keyboard access: Enter / Space on a focused title starts a rename.
        cards.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') { return; }
            var title = e.target.closest ? e.target.closest('.enk-card-title') : null;
            if (title && !title.closest('.enk-add-card-wrap') && !title.classList.contains('enk-editing')) {
                e.preventDefault();
                startRename(title);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}(typeof window !== 'undefined' ? window : globalThis));
