/**
 * Block editor script for the "enkompass/form" block.
 *
 * No JSX / no build step: elements are created with wp.element.createElement.
 * The block is server-rendered by PHP (register_block_type render_callback),
 * so save() returns null and the editor uses ServerSideRender for a live
 * preview. The editor UI is a Form picker populated from the admin REST route
 * /enkompass/v1/forms (the editing user has manage_options).
 *
 * @package Enkompass\Contact
 */
(function () {
    'use strict';

    var el = wp.element.createElement;
    var registerBlockType = wp.blocks.registerBlockType;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var SelectControl = wp.components.SelectControl;
    var Placeholder = wp.components.Placeholder;
    var Spinner = wp.components.Spinner;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var apiFetch = wp.apiFetch;
    var ServerSideRender = wp.serverSideRender;

    /**
     * Block edit component.
     *
     * @param {Object} props
     * @param {Object} props.attributes
     * @param {Function} props.setAttributes
     */
    function edit(props) {
        var attributes = props.attributes;
        var setAttributes = props.setAttributes;

        var blockProps = useBlockProps();

        var formsState = useState(null);
        var forms = formsState[0];
        var setForms = formsState[1];

        var loadingState = useState(true);
        var loading = loadingState[0];
        var setLoading = loadingState[1];

        // Fetch the form list once.
        useEffect(function () {
            var active = true;
            apiFetch({ path: '/enkompass/v1/forms' }).then(function (result) {
                if (!active) {
                    return;
                }
                setForms(Array.isArray(result) ? result : []);
                setLoading(false);
            }).catch(function () {
                if (!active) {
                    return;
                }
                setForms([]);
                setLoading(false);
            });
            return function () {
                active = false;
            };
        }, []);

        // Loading state.
        if (loading) {
            return el(
                'div',
                blockProps,
                el(Placeholder, { icon: 'feedback', label: 'Enkompass Form' }, el(Spinner, null))
            );
        }

        var options = [{ label: '— Select a form —', value: 0 }].concat(
            (forms || []).map(function (f) {
                return { label: f.name, value: String(f.id) };
            })
        );

        var selector = el(SelectControl, {
            label: 'Form',
            value: String(attributes.formId),
            options: options,
            onChange: function (value) {
                setAttributes({ formId: parseInt(value, 10) || 0 });
            }
        });

        var body;
        if (attributes.formId > 0) {
            body = el(ServerSideRender, {
                block: 'enkompass/form',
                attributes: attributes
            });
        } else {
            body = el(
                Placeholder,
                {
                    icon: 'feedback',
                    label: 'Enkompass Form',
                    instructions: 'Choose which saved form to display.'
                }
            );
        }

        return el('div', blockProps, selector, body);
    }

    registerBlockType('enkompass/form', {
        edit: edit,
        save: function () {
            return null;
        }
    });
}());
