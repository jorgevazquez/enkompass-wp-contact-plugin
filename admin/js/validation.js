/**
 * Shared client-side validation. Mirrors the server-side PHP Validation_Rules /
 * Validator so the browser and server agree. Enqueued in both the admin builder
 * and the front-end form.
 *
 * Exposes window.EnkValidation (browser) and module.exports (Node, for tests).
 *
 * Empty-value policy: every FORMAT rule passes on '' so optional blank fields
 * never fail a format check; only NotBlank enforces presence.
 *
 * @package Enkompass\Contact
 */
(function (root) {
    'use strict';

    var ACTION_TYPES = { submit: true, submit_cancel: true };

    function isBlank(value) {
        if (value === null || value === undefined) {
            return true;
        }
        if (Array.isArray(value)) {
            return value.length === 0;
        }
        return String(value).trim() === '';
    }

    function len(value) {
        return Array.from(String(value)).length;
    }

    var RULES = {
        NotBlank: function (value) {
            return !isBlank(value);
        },
        Required: function (value) {
            return !isBlank(value);
        },
        IsEmail: function (value) {
            if (isBlank(value)) { return true; }
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value));
        },
        IsDate: function (value) {
            if (isBlank(value)) { return true; }
            var s = String(value);
            if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) { return false; }
            var parts = s.split('-');
            var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
            return d.getFullYear() === Number(parts[0])
                && d.getMonth() === Number(parts[1]) - 1
                && d.getDate() === Number(parts[2]);
        },
        IsZIPCode: function (value) {
            if (isBlank(value)) { return true; }
            return /^\d{5}(-\d{4})?$/.test(String(value));
        },
        IsNumber: function (value) {
            if (isBlank(value)) { return true; }
            return !isNaN(Number(value)) && isFinite(Number(value));
        },
        IsInteger: function (value) {
            if (isBlank(value)) { return true; }
            return /^-?\d+$/.test(String(value));
        },
        IsDecimal: function (value) {
            if (isBlank(value)) { return true; }
            return /^-?\d+(\.\d+)?$/.test(String(value));
        },
        IsPhone: function (value) {
            if (isBlank(value)) { return true; }
            return /^[\d\s()+\-.]{7,}$/.test(String(value));
        },
        IsURL: function (value) {
            if (isBlank(value)) { return true; }
            try {
                var u = new URL(String(value));
                return u.protocol === 'http:' || u.protocol === 'https:';
            } catch (e) {
                return false;
            }
        },
        IsAlpha: function (value) {
            if (isBlank(value)) { return true; }
            return /^[A-Za-z]+$/.test(String(value));
        },
        IsAlphaNumeric: function (value) {
            if (isBlank(value)) { return true; }
            return /^[A-Za-z0-9]+$/.test(String(value));
        },
        MinLength: function (value, params) {
            if (isBlank(value)) { return true; }
            return len(value) >= parseInt((params && params.length) || 0, 10);
        },
        MaxLength: function (value, params) {
            if (isBlank(value)) { return true; }
            var max = (params && params.length !== undefined) ? parseInt(params.length, 10) : Infinity;
            return len(value) <= max;
        },
        IsInRange: function (value, params) {
            if (isBlank(value)) { return true; }
            if (isNaN(Number(value))) { return false; }
            var n = Number(value);
            return n >= Number(params.min) && n <= Number(params.max);
        },
        RegEx: function (value, params) {
            if (isBlank(value)) { return true; }
            if (!params || !params.pattern) { return false; }
            try {
                var pattern = String(params.pattern);
                var m = pattern.match(/^([\/#~])(.*)\1([a-z]*)$/);
                var re = m ? new RegExp(m[2], m[3]) : new RegExp(pattern);
                return re.test(String(value));
            } catch (e) {
                return false;
            }
        },
        MatchesField: function (value, params) {
            return String(value) === String((params && params.value) !== undefined ? params.value : null);
        }
    };

    function test(rule, value, params) {
        if (!Object.prototype.hasOwnProperty.call(RULES, rule)) {
            return false;
        }
        return RULES[rule](value, params || {});
    }

    function validateField(field, value) {
        var required = !!field.required;

        if (required && !test('NotBlank', value)) {
            return field.fail_message || 'This field is required.';
        }

        if (test('NotBlank', value)) {
            var rules = field.validation || [];
            for (var i = 0; i < rules.length; i++) {
                var rule = rules[i];
                var params = (field.validation_params && field.validation_params[rule]) || {};
                if (!test(rule, value, params)) {
                    return field.fail_message || ((field.label || field.name) + ' is invalid.');
                }
            }
        }

        return null;
    }

    function validateForm(values, definition) {
        var errors = {};
        var fields = (definition && definition.fields) || [];
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if (ACTION_TYPES[field.type] || !field.name) {
                continue;
            }
            var value = Object.prototype.hasOwnProperty.call(values, field.name) ? values[field.name] : '';
            var msg = validateField(field, value);
            if (msg) {
                errors[field.name] = msg;
            }
        }
        return errors;
    }

    var api = {
        test: test,
        validateField: validateField,
        validateForm: validateForm,
        ruleKeys: function () { return Object.keys(RULES); }
    };

    root.EnkValidation = api;

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }
}(typeof window !== 'undefined' ? window : globalThis));
