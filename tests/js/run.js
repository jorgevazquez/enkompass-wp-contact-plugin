/**
 * Minimal dependency-free test runner for the plugin's browser JS modules.
 * Verifies the shared validation module agrees with the PHP rules it mirrors.
 *
 * Run with: npm test
 *
 * @package Enkompass\Contact
 */
'use strict';

const path = require('path');
const V = require(path.join(__dirname, '..', '..', 'admin', 'js', 'validation.js'));

let failures = 0;
function ok(name, cond) {
    if (!cond) {
        console.log('  ✘ ' + name);
        failures++;
    }
}

// --- rule parity (mirrors tests/Unit/ValidationRulesTest.php) ---
ok('IsEmail pass', V.test('IsEmail', 'a@b.com') === true);
ok('IsEmail fail', V.test('IsEmail', 'nope') === false);
ok('IsEmail empty passes', V.test('IsEmail', '') === true);
ok('NotBlank empty fails', V.test('NotBlank', '') === false);
ok('NotBlank whitespace fails', V.test('NotBlank', '   ') === false);
ok('NotBlank non-empty array passes', V.test('NotBlank', ['x']) === true);
ok('IsDate valid', V.test('IsDate', '2026-06-05') === true);
ok('IsDate overflow rejected', V.test('IsDate', '2026-13-40') === false);
ok('IsZIPCode valid', V.test('IsZIPCode', '12345-6789') === true);
ok('IsZIPCode invalid', V.test('IsZIPCode', '1234') === false);
ok('IsInteger', V.test('IsInteger', '-42') === true && V.test('IsInteger', '4.2') === false);
ok('IsDecimal', V.test('IsDecimal', '4.2') === true);
ok('IsURL', V.test('IsURL', 'https://x.com') === true && V.test('IsURL', 'x.com') === false);
ok('MinLength', V.test('MinLength', 'abc', { length: 3 }) === true && V.test('MinLength', 'ab', { length: 3 }) === false);
ok('MaxLength', V.test('MaxLength', 'abcd', { length: 3 }) === false);
ok('IsInRange', V.test('IsInRange', '5', { min: 1, max: 10 }) === true && V.test('IsInRange', '11', { min: 1, max: 10 }) === false);
ok('RegEx with delimiters', V.test('RegEx', 'abc', { pattern: '/^a/' }) === true);
ok('MatchesField', V.test('MatchesField', 'abc', { value: 'abc' }) === true);
ok('unknown rule is false', V.test('Nope', 'x') === false);

// --- validateForm parity (mirrors tests/Unit/ValidatorTest.php) ---
const def = {
    fields: [
        { type: 'email', name: 'email', label: 'Email', required: true, validation: ['IsEmail'], fail_message: 'Bad email' },
        { type: 'first_name', name: 'first_name', label: 'First', required: true, validation: [], fail_message: 'Need first' },
        { type: 'company', name: 'company', label: 'Company', required: false, validation: ['IsAlphaNumeric'], fail_message: '' },
        { type: 'submit', name: '', label: 'Send' }
    ]
};
let e = V.validateForm({ email: 'a@b.com', first_name: 'Jo', company: '' }, def);
ok('valid form -> no errors', Object.keys(e).length === 0);
e = V.validateForm({ email: '', first_name: '', company: '' }, def);
ok('required blank email uses fail_message', e.email === 'Bad email');
ok('required blank first uses fail_message', e.first_name === 'Need first');
e = V.validateForm({ email: 'bad', first_name: 'Jo', company: '' }, def);
ok('invalid email uses fail_message', e.email === 'Bad email');
e = V.validateForm({ email: 'a@b.com', first_name: 'Jo', company: '$$$' }, def);
ok('empty fail_message falls back to default', e.company === 'Company is invalid.');
ok('action field ignored', !('' in e));

if (failures === 0) {
    console.log('OK — all JS validation parity checks pass');
    process.exit(0);
} else {
    console.log(failures + ' JS check(s) failed');
    process.exit(1);
}
