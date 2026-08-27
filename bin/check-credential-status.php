<?php
/**
 * Focused credential-status contract check for release preparation.
 *
 * Run with: php bin/check-credential-status.php
 */

define('ABSPATH', __DIR__ . '/');

$GLOBALS['ai_core_test_options'] = array();

function sanitize_key($value) {
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
}
function sanitize_text_field($value) {
    return trim(strip_tags((string) $value));
}
function absint($value) {
    return abs((int) $value);
}
function get_option($name, $default = false) {
    return array_key_exists($name, $GLOBALS['ai_core_test_options'])
        ? $GLOBALS['ai_core_test_options'][$name]
        : $default;
}
function update_option($name, $value) {
    $GLOBALS['ai_core_test_options'][$name] = $value;
    return true;
}
function add_filter() {}
function add_action() {}
function __($text) {
    return $text;
}
function wp_unslash($value) {
    return $value;
}
function check_ajax_referer() {}
function current_user_can() {
    return true;
}

class AI_Core_Status_JSON_Response extends RuntimeException {
    public $success;
    public $data;

    public function __construct($success, $data) {
        parent::__construct('JSON response');
        $this->success = $success;
        $this->data = $data;
    }
}

function wp_send_json_success($data = null) {
    throw new AI_Core_Status_JSON_Response(true, $data);
}
function wp_send_json_error($data = null) {
    throw new AI_Core_Status_JSON_Response(false, $data);
}

class AI_Core_Validator {
    public static $valid = true;

    public static function get_instance() {
        return new self();
    }

    public function validate_api_key($provider, $api_key) {
        return self::$valid
            ? array('valid' => true, 'provider' => $provider)
            : array('valid' => false, 'error' => 'Rejected test key');
    }
}

require_once dirname(__DIR__) . '/includes/class-ai-core-settings.php';

function ai_core_status_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$legacy = array('openai_api_key' => 'legacy-saved-key');
ai_core_status_assert(
    AI_Core_Settings::CREDENTIAL_UNTESTED === AI_Core_Settings::get_credential_validation_status('openai', $legacy),
    'A pre-1.0.16 stored key must be not yet tested.'
);
ai_core_status_assert(
    '' === AI_Core_Settings::get_credential_validation_status('anthropic', $legacy),
    'A provider without a stored Hub key must not have a credential state.'
);

$states = array(
    AI_Core_Settings::CREDENTIAL_VALID,
    AI_Core_Settings::CREDENTIAL_INVALID,
    AI_Core_Settings::CREDENTIAL_UNTESTED,
);
foreach ($states as $state) {
    $settings = array(
        'openai_api_key' => 'saved-key',
        'credential_validation' => array('openai' => $state),
    );
    ai_core_status_assert(
        $state === AI_Core_Settings::get_credential_validation_status('openai', $settings),
        "The {$state} state must round-trip."
    );
}

$unknown = array(
    'openai_api_key' => 'saved-key',
    'credential_validation' => array('openai' => 'invented-state'),
);
ai_core_status_assert(
    AI_Core_Settings::CREDENTIAL_UNTESTED === AI_Core_Settings::get_credential_validation_status('openai', $unknown),
    'Unknown state metadata must fail safely to not yet tested.'
);

$GLOBALS['ai_core_test_options']['ai_core_settings'] = array('openai_api_key' => 'saved-key');
ai_core_status_assert(
    AI_Core_Settings::record_credential_validation_status('openai', AI_Core_Settings::CREDENTIAL_VALID),
    'An explicit result must be recordable for a stored key.'
);
ai_core_status_assert(
    AI_Core_Settings::CREDENTIAL_VALID === $GLOBALS['ai_core_test_options']['ai_core_settings']['credential_validation']['openai'],
    'The public option contract must store only the validation state.'
);
ai_core_status_assert(
    !AI_Core_Settings::record_credential_validation_status('gemini', AI_Core_Settings::CREDENTIAL_INVALID),
    'A result must not be recorded where no Hub key is stored.'
);
ai_core_status_assert(
    !AI_Core_Settings::record_credential_validation_status('openai', 'active'),
    'Ambiguous or invented states must be rejected.'
);

$GLOBALS['ai_core_test_options']['ai_core_settings'] = array(
    'openai_api_key' => 'old-key',
    'credential_validation' => array('openai' => AI_Core_Settings::CREDENTIAL_VALID),
);
$normalised = AI_Core_Settings::normalise_credential_validation(array(
    'openai_api_key' => 'replacement-key',
    'credential_validation' => array('openai' => AI_Core_Settings::CREDENTIAL_VALID),
));
ai_core_status_assert(
    AI_Core_Settings::CREDENTIAL_UNTESTED === $normalised['credential_validation']['openai'],
    'A key changed outside the Settings screen must also reset validation.'
);

$settings_class = new ReflectionClass('AI_Core_Settings');
$settings_instance = $settings_class->newInstanceWithoutConstructor();
$_POST = array();
$GLOBALS['ai_core_test_options']['ai_core_settings'] = array(
    'openai_api_key' => 'old-key',
    'anthropic_api_key' => '',
    'gemini_api_key' => '',
    'credential_validation' => array('openai' => AI_Core_Settings::CREDENTIAL_VALID),
    'default_provider' => 'openai',
    'enable_stats' => true,
    'enable_caching' => true,
    'persist_on_uninstall' => true,
    'provider_models' => array(),
    'provider_options' => array(),
    'cache_duration' => 3600,
);
$changed = $settings_instance->sanitize_settings(array(
    'openai_api_key' => 'replacement-key',
    'anthropic_api_key' => '',
    'gemini_api_key' => '',
    'credential_validation' => array('openai' => AI_Core_Settings::CREDENTIAL_VALID),
    'default_provider' => 'openai',
    'enable_stats' => '1',
    'enable_caching' => '1',
    'persist_on_uninstall' => '1',
));
ai_core_status_assert(
    AI_Core_Settings::CREDENTIAL_UNTESTED === $changed['credential_validation']['openai'],
    'Replacing a key must reset any earlier validation result.'
);

$ajax_source = file_get_contents(dirname(__DIR__) . '/admin/class-ai-core-ajax.php');
$get_models_start = strpos($ajax_source, 'public function get_models()');
$get_models_end = strpos($ajax_source, 'public function get_model_capabilities()', $get_models_start);
$get_models_source = substr($ajax_source, $get_models_start, $get_models_end - $get_models_start);
ai_core_status_assert(
    false === strpos($get_models_source, 'record_credential_validation_status'),
    'Refreshing the model catalogue must not mutate credential validation.'
);
ai_core_status_assert(
    false !== strpos($get_models_source, 'get_credential_validation_status'),
    'A model refresh response must return the unchanged credential state.'
);

$settings_source = file_get_contents(dirname(__DIR__) . '/includes/class-ai-core-settings.php');
foreach (array('Saved and validated', 'Saved but invalid', 'Saved, not yet tested') as $label) {
    ai_core_status_assert(false !== strpos($settings_source, $label), "Missing status label: {$label}");
}
ai_core_status_assert(
    false !== strpos($settings_source, 'Use Test Key when you want to confirm it.'),
    'An untested key must receive optional rather than blocking guidance.'
);
ai_core_status_assert(
    false === strpos($settings_source, 'Use Test Key to validate it before generating.'),
    'An untested key must not be described as blocked from generation.'
);
ai_core_status_assert(
    false === strpos($settings_source, 'encrypted Hub key is active'),
    'A merely stored encrypted key must never be called active.'
);

require_once dirname(__DIR__) . '/admin/class-ai-core-ajax.php';
$ajax = AI_Core_AJAX::get_instance();
$GLOBALS['ai_core_test_options']['ai_core_settings'] = array(
    'openai_api_key' => 'saved-key',
    'credential_validation' => array('openai' => AI_Core_Settings::CREDENTIAL_UNTESTED),
);
$_POST = array('provider' => 'openai', 'api_key' => '');
AI_Core_Validator::$valid = true;
try {
    $ajax->test_api_key();
    ai_core_status_assert(false, 'The valid credential test did not return JSON.');
} catch (AI_Core_Status_JSON_Response $response) {
    ai_core_status_assert($response->success, 'The saved credential should validate successfully.');
    ai_core_status_assert(
        AI_Core_Settings::CREDENTIAL_VALID === $response->data['credential_status'],
        'A successful explicit test must return validated.'
    );
}
ai_core_status_assert(
    AI_Core_Settings::CREDENTIAL_VALID === AI_Core_Settings::get_credential_validation_status('openai'),
    'A successful explicit test must persist validated.'
);

AI_Core_Validator::$valid = false;
try {
    $ajax->test_api_key();
    ai_core_status_assert(false, 'The invalid credential test did not return JSON.');
} catch (AI_Core_Status_JSON_Response $response) {
    ai_core_status_assert(!$response->success, 'The rejected saved credential must return an error.');
    ai_core_status_assert(
        AI_Core_Settings::CREDENTIAL_INVALID === $response->data['credential_status'],
        'A failed explicit test of the saved key must return invalid.'
    );
}
ai_core_status_assert(
    AI_Core_Settings::CREDENTIAL_INVALID === AI_Core_Settings::get_credential_validation_status('openai'),
    'A failed explicit test of the saved key must persist invalid.'
);

$GLOBALS['ai_core_test_options']['ai_core_settings']['credential_validation']['openai'] = AI_Core_Settings::CREDENTIAL_VALID;
$_POST['api_key'] = 'different-unsaved-key';
try {
    $ajax->test_api_key();
    ai_core_status_assert(false, 'The different invalid key test did not return JSON.');
} catch (AI_Core_Status_JSON_Response $response) {
    ai_core_status_assert(!$response->success, 'The different invalid key must return an error.');
    ai_core_status_assert(
        '' === $response->data['credential_status'],
        'Testing a different unsaved key must not label the stored key invalid.'
    );
}
ai_core_status_assert(
    AI_Core_Settings::CREDENTIAL_VALID === AI_Core_Settings::get_credential_validation_status('openai'),
    'Testing a different unsaved key must not overwrite the saved key result.'
);

echo "PASS: credential status is explicit, persistent and independent of catalogue refresh.\n";
