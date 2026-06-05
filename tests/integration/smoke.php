<?php
/**
 * End-to-end integration smoke test of the plugin's WordPress-integration layer
 * against a fake $wpdb, captured wp_mail and a real CSV file on disk.
 *
 * Run: php tests/integration/smoke.php
 *
 * @package Enkompass\Contact
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Enkompass\Contact\Admin\Rest_Admin_Controller;
use Enkompass\Contact\Csv_Writer;
use Enkompass\Contact\Form_Renderer;
use Enkompass\Contact\Form_Repository;
use Enkompass\Contact\Submission_Repository;
use Enkompass\Contact\Frontend\Submission_Controller;

$failures = 0;
function check(string $name, bool $cond): void
{
    global $failures;
    if ($cond) {
        echo "  ok  $name\n";
    } else {
        echo "  XX  $name\n";
        $GLOBALS['failures']++;
        $failures++;
    }
}

echo "== Enkompass Contact integration smoke ==\n";

// 1) Create a form (auto-numbered FORM1).
$id = Form_Repository::create();
check('create returns id', $id === 1);
$form = Form_Repository::get($id);
check('new form is named FORM1', $form['name'] === 'FORM1');
check('new form has no fields', $form['fields'] === []);

// 2) Save a definition with two fields + all three destinations enabled.
$definition = [
    'name'         => 'FORM1',
    'title'        => 'Contact Us',
    'css_class'    => 'form-card',
    'destinations' => [
        'email'    => ['enabled' => true, 'recipients' => ['sales@enkompass.net', 'bad-email', 'ops@enkompass.net']],
        'database' => ['enabled' => true],
        'textfile' => ['enabled' => true],
    ],
    'fields' => [
        [
            'id' => 'f_email', 'type' => 'email', 'name' => 'email', 'label' => 'Email',
            'required' => true, 'taborder' => 1, 'css_class' => 'input',
            'grid' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 1],
            'validation' => ['IsEmail'], 'fail_message' => 'Please enter a valid email.',
            'options' => [], 'props' => [],
        ],
        [
            'id' => 'f_first', 'type' => 'first_name', 'name' => 'first_name', 'label' => 'First Name',
            'required' => true, 'taborder' => 2, 'css_class' => 'input',
            'grid' => ['x' => 6, 'y' => 0, 'w' => 6, 'h' => 1],
            'validation' => [], 'fail_message' => 'First name is required.',
            'options' => [], 'props' => [],
        ],
        [
            'id' => 'f_submit', 'type' => 'submit', 'name' => '', 'label' => 'Send',
            'required' => false, 'taborder' => 3, 'css_class' => 'btn btn--primary',
            'grid' => ['x' => 0, 'y' => 1, 'w' => 12, 'h' => 1],
            'validation' => [], 'fail_message' => '', 'options' => [], 'props' => [],
        ],
    ],
];

$saveResp = Rest_Admin_Controller::save_form(new WP_REST_Request(['id' => $id, 'definition' => $definition]));
check('save returns 200', $saveResp->get_status() === 200);

$saved = Form_Repository::get($id);
check('email destination enabled', !empty($saved['destinations']['email']['enabled']));
check('invalid recipient was dropped', $saved['destinations']['email']['recipients'] === ['sales@enkompass.net', 'ops@enkompass.net']);
check('csv_filename was assigned', !empty($saved['csv_filename']));
check('database destination enabled', !empty($saved['destinations']['database']['enabled']));

$csvPath = enk_uploads_dir() . $saved['csv_filename'];
check('CSV file created on save', is_file($csvPath));
$headers = (new Csv_Writer($csvPath))->headers();
check('CSV header matches data field names', $headers === ['email', 'first_name']);
check('uploads dir protected by .htaccess', is_file(enk_uploads_dir() . '.htaccess'));

// 3) Submit valid data → all destinations fire.
$GLOBALS['enk_test_mail'] = [];
$ok = Submission_Controller::submit(new WP_REST_Request([
    'form_id' => $id, '_enk_nonce' => 'x', 'enk_hp' => '',
    'email' => 'jane@example.com', 'first_name' => 'Jane',
]));
check('valid submit returns 200', $ok->get_status() === 200);
check('valid submit success flag', ($ok->get_data()['success'] ?? false) === true);
check('submission stored in DB', Submission_Repository::count_for_form($id) === 1);
check('email sent to both valid recipients', count($GLOBALS['enk_test_mail']) === 2);
check('email subject names the form', str_contains($GLOBALS['enk_test_mail'][0]['subject'], 'FORM1'));
check('reply-to set from submitted email', in_array('Reply-To: jane@example.com', $GLOBALS['enk_test_mail'][0]['headers'], true));
$csv = new Csv_Writer($csvPath);
check('CSV now has a data row', $csv->hasData());
$rows = array_map(
    static fn (string $line): array => str_getcsv($line, ',', '"', ''),
    array_filter(explode("\n", trim((string) file_get_contents($csvPath))))
);
check('CSV data row holds submitted values', $rows[1] === ['jane@example.com', 'Jane']);

// 4) Invalid submit → 400 with the field's fail message.
$bad = Submission_Controller::submit(new WP_REST_Request([
    'form_id' => $id, '_enk_nonce' => 'x', 'enk_hp' => '',
    'email' => 'not-an-email', 'first_name' => '',
]));
check('invalid submit returns 400', $bad->get_status() === 400);
$errors = $bad->get_data()['errors'] ?? [];
check('email error uses fail message', ($errors['email'] ?? '') === 'Please enter a valid email.');
check('first_name required error present', ($errors['first_name'] ?? '') === 'First name is required.');
check('no new submission stored after invalid', Submission_Repository::count_for_form($id) === 1);

// 5) Honeypot filled → silently accepted, nothing stored.
$hp = Submission_Controller::submit(new WP_REST_Request([
    'form_id' => $id, '_enk_nonce' => 'x', 'enk_hp' => 'I am a bot',
    'email' => 'spam@example.com', 'first_name' => 'Spam',
]));
check('honeypot submit returns 200', $hp->get_status() === 200);
check('honeypot submit stored nothing', Submission_Repository::count_for_form($id) === 1);

// 6) Front-end render contains the expected Enkompass markup.
$html = Form_Renderer::render($saved);
check('render outputs form-card', str_contains($html, 'class="form-card"'));
check('render outputs email field', str_contains($html, 'name="email"') && str_contains($html, 'type="email"'));
check('render outputs submit button', str_contains($html, 'class="btn btn--primary"'));
check('render includes nonce + honeypot', str_contains($html, '_enk_nonce') && str_contains($html, 'enk-hp'));

// 7) Data durability: deleting the form keeps submissions + CSV.
Form_Repository::delete($id);
check('form row deleted', Form_Repository::get($id) === null);
check('submissions preserved after delete', Submission_Repository::count_for_form($id) === 1);
check('CSV file preserved after delete', is_file($csvPath));

// cleanup
@array_map('unlink', glob($GLOBALS['enk_test_upload'] . '/*'));
@rmdir($GLOBALS['enk_test_upload']);

echo "\n";
if ($failures === 0) {
    echo "INTEGRATION SMOKE: ALL CHECKS PASSED\n";
    exit(0);
}
echo "INTEGRATION SMOKE: {$failures} CHECK(S) FAILED\n";
exit(1);
