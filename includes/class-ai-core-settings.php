<?php
/**
 * Opace AI Hub Settings Class
 * 
 * Handles plugin settings management using WordPress Settings API
 * 
 * @package AI_Core
 * @version 0.7.3
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Opace AI Hub Settings Class
 * 
 * Manages plugin settings and configuration
 */
class AI_Core_Settings {

    /**
     * Option name holding every Opace AI Hub setting
     *
     * Declared as a constant as well as a property so the encryption hooks,
     * which run outside any instance, can reference it.
     *
     * @var string
     */
    const OPTION_NAME = 'ai_core_settings';

    /**
     * Versioned marker prefixed to every encrypted value at rest
     *
     * @var string
     */
    const ENCRYPTION_PREFIX = 'aicenc1:';

    /**
     * Option recording which storage format the keys are in
     *
     * @var string
     */
    const ENCRYPTION_OPTION = 'ai_core_key_storage_version';

    /**
     * Current storage format version
     *
     * @var string
     */
    const ENCRYPTION_VERSION = '1';

    /**
     * Sentinel that asks sanitize_settings() to erase a stored key
     *
     * A blank field and a deliberate clear are two different intentions, and
     * only one of them may delete a key. See sanitize_settings().
     *
     * @var string
     */
    const CLEAR_SENTINEL = '__ai_core_clear_key__';

    /** Credential validation states stored alongside provider settings. */
    const CREDENTIAL_VALID = 'validated';
    const CREDENTIAL_INVALID = 'invalid';
    const CREDENTIAL_UNTESTED = 'untested';

    /**
     * Class instance
     *
     * @var AI_Core_Settings
     */
    private static $instance = null;

    /**
     * Settings group name
     * 
     * @var string
     */
    private $settings_group = 'ai_core_settings_group';
    
    /**
     * Settings page slug
     * 
     * @var string
     */
    private $settings_page = 'ai-core-settings';
    
    /**
     * Option name
     * 
     * @var string
     */
    private $option_name = self::OPTION_NAME;

    /**
     * Setting keys that hold a secret and are encrypted at rest
     *
     * @return array
     */
    private static function get_secret_fields() {
        return array(
            'openai_api_key',
            'anthropic_api_key',
            'gemini_api_key',
        );
    }

    /**
     * Display labels for the providers currently offered, in display order.
     *
     * Derived from ModelRegistry::getSupportedProviders() so a provider that is
     * withheld disappears from every dropdown and card on this screen at once,
     * rather than from the three lists someone remembered to edit.
     *
     * @return array Provider key => label
     */
    private static function get_provider_labels() {
        $labels = array(
            'openai'    => 'OpenAI',
            'anthropic' => 'Anthropic Claude',
            'gemini'    => 'Google Gemini',
        );

        if (!class_exists('\\AICore\\Registry\\ModelRegistry')) {
            return $labels;
        }

        $offered = array();

        foreach (\AICore\Registry\ModelRegistry::getSupportedProviders() as $provider) {
            $offered[$provider] = $labels[$provider] ?? $provider;
        }

        return $offered;
    }

    /**
     * Read the last explicit validation result for a stored Hub key.
     *
     * Existing keys from releases before 1.0.16 have no result recorded, so
     * they truthfully begin as "not yet tested". Model discovery and catalogue
     * refreshes never call this a validation result.
     *
     * @param string     $provider Provider key.
     * @param array|null $settings Optional settings snapshot.
     * @return string Empty when no Hub key is stored, otherwise a state constant.
     */
    public static function get_credential_validation_status($provider, $settings = null) {
        $provider = sanitize_key($provider);
        $settings = is_array($settings) ? $settings : get_option(self::OPTION_NAME, array());
        $field = $provider . '_api_key';

        if (empty($settings[$field])) {
            return '';
        }

        $status = isset($settings['credential_validation'][$provider])
            ? sanitize_key($settings['credential_validation'][$provider])
            : self::CREDENTIAL_UNTESTED;

        return in_array($status, self::get_credential_validation_states(), true)
            ? $status
            : self::CREDENTIAL_UNTESTED;
    }

    /**
     * Persist the result of an explicit credential test.
     *
     * @param string $provider Provider key.
     * @param string $status   One of the credential state constants.
     * @return bool Whether a stored Hub key was updated.
     */
    public static function record_credential_validation_status($provider, $status) {
        $provider = sanitize_key($provider);
        $status = sanitize_key($status);

        if (!in_array($status, self::get_credential_validation_states(), true)) {
            return false;
        }

        $settings = get_option(self::OPTION_NAME, array());
        if (!is_array($settings) || empty($settings[$provider . '_api_key'])) {
            return false;
        }

        if (!isset($settings['credential_validation']) || !is_array($settings['credential_validation'])) {
            $settings['credential_validation'] = array();
        }

        $settings['credential_validation'][$provider] = $status;
        update_option(self::OPTION_NAME, $settings);
        return true;
    }

    /** @return array Allowed stored credential states. */
    private static function get_credential_validation_states() {
        return array(self::CREDENTIAL_VALID, self::CREDENTIAL_INVALID, self::CREDENTIAL_UNTESTED);
    }

    /**
     * Keep credential metadata truthful on every option write, including
     * migrations and integrations that run outside the Settings screen.
     *
     * @param mixed $settings Settings about to be stored.
     * @return mixed Normalised settings.
     */
    public static function normalise_credential_validation($settings) {
        if (!is_array($settings)) {
            return $settings;
        }

        $existing = get_option(self::OPTION_NAME, array());
        $existing = is_array($existing) ? $existing : array();
        $validation = isset($settings['credential_validation']) && is_array($settings['credential_validation'])
            ? $settings['credential_validation']
            : array();
        $clean = array();

        foreach ($validation as $provider => $status) {
            $provider = sanitize_key($provider);
            $status = sanitize_key($status);
            if (in_array($status, self::get_credential_validation_states(), true)) {
                $clean[$provider] = $status;
            }
        }

        foreach (self::get_secret_fields() as $field) {
            $provider = substr($field, 0, -strlen('_api_key'));
            $old_key = isset($existing[$field]) ? (string) $existing[$field] : '';
            $new_key = isset($settings[$field]) ? (string) $settings[$field] : '';

            if ('' === $new_key) {
                unset($clean[$provider]);
            } elseif ('' === $old_key || !hash_equals($old_key, $new_key)) {
                $clean[$provider] = self::CREDENTIAL_UNTESTED;
            } elseif (!isset($clean[$provider])) {
                $clean[$provider] = self::CREDENTIAL_UNTESTED;
            }
        }

        $settings['credential_validation'] = $clean;
        return $settings;
    }

    /**
     * Translate the exact user-facing state for a stored Hub key.
     *
     * @param string $status Credential state.
     * @return string
     */
    private static function get_credential_validation_label($status) {
        if (self::CREDENTIAL_VALID === $status) {
            return __('Saved and validated', 'opace-ai-prompt-library-api-hub');
        }
        if (self::CREDENTIAL_INVALID === $status) {
            return __('Saved but invalid', 'opace-ai-prompt-library-api-hub');
        }
        return __('Saved, not yet tested', 'opace-ai-prompt-library-api-hub');
    }

    /**
     * Get class instance
     *
     * @return AI_Core_Settings
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize settings
     *
     * @return void
     */
    private function init() {
        // Register settings immediately since this is already called in admin_init
        $this->register_settings();
    }
    
    /**
     * Register plugin settings
     *
     * @return void
     */
    public function register_settings() {
        // Register settings
        register_setting(
            $this->settings_group,
            $this->option_name,
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
                'default' => $this->get_default_settings(),
                'show_in_rest' => false
            )
        );

        // Add settings sections
        $this->add_settings_sections();

        // Add settings fields
        $this->add_settings_fields();
    }
    
    /**
     * Add settings sections
     * 
     * @return void
     */
    private function add_settings_sections() {
        // API Keys Section
        add_settings_section(
            'ai_core_api_keys_section',
            __('API Keys Configuration', 'opace-ai-prompt-library-api-hub'),
            array($this, 'api_keys_section_callback'),
            $this->settings_page
        );

        // Provider Configuration Section
        add_settings_section(
            'ai_core_provider_section',
            __('Provider Configuration', 'opace-ai-prompt-library-api-hub'),
            array($this, 'provider_section_callback'),
            $this->settings_page
        );
        
        // General Settings Section
        add_settings_section(
            'ai_core_general_section',
            __('General Settings', 'opace-ai-prompt-library-api-hub'),
            array($this, 'general_section_callback'),
            $this->settings_page
        );

        // Test Prompt Section
        add_settings_section(
            'ai_core_test_prompt_section',
            __('Test Prompt', 'opace-ai-prompt-library-api-hub'),
            array($this, 'test_prompt_section_callback'),
            $this->settings_page
        );
    }
    
    /**
     * Add settings fields
     *
     * @return void
     */
    private function add_settings_fields() {
        // OpenAI API Key
        add_settings_field(
            'openai_api_key',
            __('OpenAI API Key', 'opace-ai-prompt-library-api-hub'),
            array($this, 'api_key_field_callback'),
            $this->settings_page,
            'ai_core_api_keys_section',
            array('provider' => 'openai', 'label' => 'OpenAI')
        );
        
        // Anthropic API Key
        add_settings_field(
            'anthropic_api_key',
            __('Anthropic API Key', 'opace-ai-prompt-library-api-hub'),
            array($this, 'api_key_field_callback'),
            $this->settings_page,
            'ai_core_api_keys_section',
            array('provider' => 'anthropic', 'label' => 'Anthropic Claude')
        );
        
        // Gemini API Key
        add_settings_field(
            'gemini_api_key',
            __('Google Gemini API Key', 'opace-ai-prompt-library-api-hub'),
            array($this, 'api_key_field_callback'),
            $this->settings_page,
            'ai_core_api_keys_section',
            array('provider' => 'gemini', 'label' => 'Google Gemini')
        );
        

        // Provider defaults configuration
        add_settings_field(
            'provider_defaults',
            __('Provider Defaults', 'opace-ai-prompt-library-api-hub'),
            array($this, 'provider_settings_field_callback'),
            $this->settings_page,
            'ai_core_provider_section'
        );
        
        // Default Provider
        add_settings_field(
            'default_provider',
            __('Default Provider', 'opace-ai-prompt-library-api-hub'),
            array($this, 'default_provider_field_callback'),
            $this->settings_page,
            'ai_core_general_section'
        );
        
        // Enable Stats
        add_settings_field(
            'enable_stats',
            __('Enable Usage Statistics', 'opace-ai-prompt-library-api-hub'),
            array($this, 'checkbox_field_callback'),
            $this->settings_page,
            'ai_core_general_section',
            array('field' => 'enable_stats', 'label' => 'Track API usage statistics')
        );
        
        // Enable Caching
        add_settings_field(
            'enable_caching',
            __('Enable Model Caching', 'opace-ai-prompt-library-api-hub'),
            array($this, 'checkbox_field_callback'),
            $this->settings_page,
            'ai_core_general_section',
            array('field' => 'enable_caching', 'label' => 'Cache available models list')
        );

        // Persist Settings on Uninstall
        add_settings_field(
            'persist_on_uninstall',
            __('Data on Plugin Deletion', 'opace-ai-prompt-library-api-hub'),
            array($this, 'retention_field_callback'),
            $this->settings_page,
            'ai_core_general_section'
        );

        // Test Prompt Field
        add_settings_field(
            'test_prompt',
            '',
            array($this, 'test_prompt_field_callback'),
            $this->settings_page,
            'ai_core_test_prompt_section'
        );
    }
    
    /**
     * API keys section callback
     *
     * @return void
     */
    public function api_keys_section_callback() {
        echo '<p>' . esc_html__('Configure a provider once. On WordPress 7.0 or newer, Opace AI Hub automatically uses credentials from Settings > Connectors and shares Hub credentials with matching WordPress AI provider plugins at runtime.', 'opace-ai-prompt-library-api-hub') . '</p>';
        echo '<p style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px; margin: 16px 0;">';
        echo '<span class="dashicons dashicons-info" style="color: #2271b1;"></span> ';
        echo '<strong>' . esc_html__('One credential:', 'opace-ai-prompt-library-api-hub') . '</strong> ';
        echo esc_html__('Keys are never copied between the Hub and WordPress Connectors. Connector credentials take precedence; Hub keys remain encrypted in Hub storage.', 'opace-ai-prompt-library-api-hub');
        echo '</p>';
    }

    /**
     * Provider section callback
     *
     * @return void
     */
    public function provider_section_callback() {
        echo '<p>' . esc_html__('Choose default models and tuning options for each provider. These settings are applied across all Opace AI Hub integrations.', 'opace-ai-prompt-library-api-hub') . '</p>';
    }
    
    /**
     * General section callback
     *
     * @return void
     */
    public function general_section_callback() {
        echo '<p>' . esc_html__('Configure general plugin settings.', 'opace-ai-prompt-library-api-hub') . '</p>';
    }

    /**
     * Test prompt section callback
     *
     * @return void
     */
    public function test_prompt_section_callback() {
        echo '<p>' . esc_html__('Test your AI providers with a prompt. You can load saved prompts from the Prompt Library.', 'opace-ai-prompt-library-api-hub') . '</p>';
    }

    /**
     * Test prompt field callback
     *
     * @return void
     */
    public function test_prompt_field_callback() {
        ?>
        <div class="ai-core-test-prompt-wrapper">
            <div class="ai-core-prompt-loader">
                <label for="ai-core-load-prompt"><?php esc_html_e('Load from Library:', 'opace-ai-prompt-library-api-hub'); ?></label>
                <select id="ai-core-load-prompt" class="regular-text">
                    <option value=""><?php esc_html_e('-- Select a prompt --', 'opace-ai-prompt-library-api-hub'); ?></option>
                </select>
                <button type="button" class="button" id="ai-core-refresh-prompts">
                    <span class="dashicons dashicons-update"></span>
                    <?php esc_html_e('Refresh', 'opace-ai-prompt-library-api-hub'); ?>
                </button>
            </div>

            <div class="ai-core-test-prompt-form">
                <textarea id="ai-core-test-prompt-content" rows="6" class="large-text" placeholder="<?php esc_attr_e('Enter your test prompt here...', 'opace-ai-prompt-library-api-hub'); ?>"></textarea>

                <div class="ai-core-test-prompt-options">
                    <label for="ai-core-test-provider"><?php esc_html_e('Provider:', 'opace-ai-prompt-library-api-hub'); ?></label>
                    <select id="ai-core-test-provider">
                        <option value=""><?php esc_html_e('-- Select Provider --', 'opace-ai-prompt-library-api-hub'); ?></option>
                        <?php
                        // Only show configured providers
                        $api = AI_Core_API::get_instance();
                        $configured_providers = $api->get_configured_providers();
                        $provider_names = self::get_provider_labels();
                        foreach ($configured_providers as $provider) {
                            // A stored key for a withheld provider must not
                            // resurface as a choice here.
                            if (!isset($provider_names[$provider])) {
                                continue;
                            }
                            echo '<option value="' . esc_attr($provider) . '">' . esc_html($provider_names[$provider]) . '</option>';
                        }
                        ?>
                    </select>

                    <label for="ai-core-test-model"><?php esc_html_e('Model:', 'opace-ai-prompt-library-api-hub'); ?></label>
                    <select id="ai-core-test-model">
                        <option value=""><?php esc_html_e('-- Select Provider First --', 'opace-ai-prompt-library-api-hub'); ?></option>
                    </select>

                    <label for="ai-core-test-type"><?php esc_html_e('Type:', 'opace-ai-prompt-library-api-hub'); ?></label>
                    <select id="ai-core-test-type">
                        <option value="text"><?php esc_html_e('Text Generation', 'opace-ai-prompt-library-api-hub'); ?></option>
                        <option value="image"><?php esc_html_e('Image Generation', 'opace-ai-prompt-library-api-hub'); ?></option>
                    </select>

                    <button type="button" class="button button-primary" id="ai-core-run-test-prompt">
                        <span class="dashicons dashicons-controls-play"></span>
                        <?php esc_html_e('Run Prompt', 'opace-ai-prompt-library-api-hub'); ?>
                    </button>
                </div>

                <div id="ai-core-test-prompt-result" class="ai-core-test-prompt-result" style="display: none;"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * API key field callback
     *
     * @param array $args Field arguments
     * @return void
     */
    public function api_key_field_callback($args) {
        $settings = get_option($this->option_name, $this->get_default_settings());
        $provider = $args['provider'];
        $field_name = $provider . '_api_key';
        $value = $settings[$field_name] ?? '';
        $has_saved_key = !empty($value);
        $api = AI_Core_API::get_instance();
        $source = $api->get_provider_source($provider);
        $is_configured = in_array($provider, $api->get_configured_providers(), true);
        $wordpress_managed = in_array($source, array('wordpress', 'wordpress_and_ai_core'), true);
        $credential_status = self::get_credential_validation_status($provider, $settings);

        // Masked hint only. The key itself is never written into the markup,
        // so the browser has no copy of it and view-source shows nothing.
        $masked_hint = $has_saved_key
            ? '••••••••••••••••••••' . substr($value, -4)
            : ($wordpress_managed
                ? __('Managed by WordPress Connectors', 'opace-ai-prompt-library-api-hub')
                : __('Enter your API key', 'opace-ai-prompt-library-api-hub'));

        echo '<div class="ai-core-api-key-field" data-provider="' . esc_attr($provider) . '" data-has-saved="' . ($has_saved_key ? '1' : '0') . '">';

        // Visible input field. Always empty on render: it accepts a new key,
        // it never displays the stored one.
        echo '<input type="password" ';
        echo 'id="' . esc_attr($field_name) . '" ';
        echo 'name="' . esc_attr($this->option_name) . '[' . esc_attr($field_name) . ']" ';
        echo 'value="" ';
        echo 'class="regular-text ai-core-api-key-input" ';
        echo 'autocomplete="new-password" ';
        echo 'spellcheck="false" ';
        echo 'autocapitalize="off" ';
        echo 'autocorrect="off" ';
        /* translators: %s: provider name, e.g. OpenAI. */
        echo 'aria-label="' . esc_attr(sprintf(__('%s API key', 'opace-ai-prompt-library-api-hub'), $args['label'])) . '" ';
        echo 'data-has-saved="' . ($has_saved_key ? '1' : '0') . '" ';
        echo 'data-provider="' . esc_attr($provider) . '" ';
        echo 'placeholder="' . esc_attr($masked_hint) . '" />';

        echo '<button type="button" class="button ai-core-test-key" data-provider="' . esc_attr($provider) . '">';
        echo esc_html($wordpress_managed && !$has_saved_key ? __('Check Connection', 'opace-ai-prompt-library-api-hub') : __('Test Key', 'opace-ai-prompt-library-api-hub'));
        echo '</button>';

        echo '<button type="button" class="button ai-core-refresh-models" data-provider="' . esc_attr($provider) . '"' . ($is_configured ? '' : ' disabled') . '>';
        echo esc_html__('Refresh Models', 'opace-ai-prompt-library-api-hub');
        echo '</button>';

        if ($has_saved_key) {
            echo '<button type="button" class="button ai-core-clear-key" data-field="' . esc_attr($field_name) . '">';
            echo esc_html__('Clear', 'opace-ai-prompt-library-api-hub');
            echo '</button>';
        }

        echo '<span class="ai-core-key-status" id="' . esc_attr($provider) . '-status"></span>';
        echo '</div>';

        if ($has_saved_key) {
            $status_colour = self::CREDENTIAL_VALID === $credential_status ? '#008a20' : (self::CREDENTIAL_INVALID === $credential_status ? '#b32d2e' : '#646970');
            $status_icon = self::CREDENTIAL_VALID === $credential_status ? 'dashicons-yes-alt' : (self::CREDENTIAL_INVALID === $credential_status ? 'dashicons-warning' : 'dashicons-info-outline');
            echo '<p class="description ai-core-credential-state ai-core-credential-state--' . esc_attr($credential_status) . '" style="color: ' . esc_attr($status_colour) . ';">';
            echo '<span class="dashicons ' . esc_attr($status_icon) . '"></span> ';
            echo '<strong>' . esc_html(self::get_credential_validation_label($credential_status)) . '.</strong> ';
            if (self::CREDENTIAL_INVALID === $credential_status) {
                echo esc_html__('Enter a working key and test it again before generating.', 'opace-ai-prompt-library-api-hub');
            } elseif (self::CREDENTIAL_UNTESTED === $credential_status) {
                echo esc_html__('Use Test Key when you want to confirm it.', 'opace-ai-prompt-library-api-hub');
            } else {
                echo esc_html__('Use Test Key anytime to check it again.', 'opace-ai-prompt-library-api-hub');
            }
            echo '</p>';
        }

        if ('wordpress_and_ai_core' === $source) {
            echo '<p class="description" style="color: #b32d2e;">';
            echo '<span class="dashicons dashicons-warning"></span> ';
            echo esc_html__('A key exists in both places. WordPress Connectors takes precedence; clear the Hub key to remove the duplicate.', 'opace-ai-prompt-library-api-hub');
            echo '</p>';
        } elseif ('wordpress' === $source && $is_configured) {
            echo '<p class="description" style="color: #2271b1;">';
            echo '<span class="dashicons dashicons-yes-alt"></span> ';
            echo esc_html__('Connected through WordPress Settings > Connectors. No second key is needed here.', 'opace-ai-prompt-library-api-hub');
            echo '</p>';
        } elseif ('wordpress' === $source) {
            echo '<p class="description" style="color: #b32d2e;">';
            echo '<span class="dashicons dashicons-warning"></span> ';
            echo esc_html__('A WordPress Connector key exists, but its matching AI provider plugin is not active or configured.', 'opace-ai-prompt-library-api-hub');
            echo '</p>';
        } elseif ('ai_core' === $source) {
            echo '<p class="description" style="color: #2271b1;">';
            echo '<span class="dashicons dashicons-info-outline"></span> ';
            echo esc_html__('The encrypted Hub key can also be supplied at runtime to plugins using the WordPress AI Client.', 'opace-ai-prompt-library-api-hub');
            echo '</p>';
        } elseif ('ai_core_direct' === $source) {
            echo '<p class="description" style="color: #2271b1;">';
            echo '<span class="dashicons dashicons-info-outline"></span> ';
            echo esc_html__('Install and activate the matching WordPress AI provider plugin if you want to share this Hub key with external AI Client plugins.', 'opace-ai-prompt-library-api-hub');
            echo '</p>';
        } elseif (!$has_saved_key) {
            echo '<p class="description">';
            printf(
                /* translators: %s: provider name, e.g. OpenAI. */
            esc_html__('Paste your %s API key. Validation runs automatically and you can click Test Key to confirm manually.', 'opace-ai-prompt-library-api-hub'),
                esc_html($args['label'])
            );
            echo '</p>';
        }
    }

    /**
     * Provider settings field callback
     *
     * @return void
     */
    public function provider_settings_field_callback() {
        $settings = get_option($this->option_name, $this->get_default_settings());
        $provider_models = $settings['provider_models'] ?? array();
        $provider_options = $settings['provider_options'] ?? array();

        $providers = self::get_provider_labels();

        $api = AI_Core_API::get_instance();

        echo '<div class="ai-core-provider-grid">';

        foreach ($providers as $key => $label) {
            $is_configured = in_array($key, $api->get_configured_providers(), true);
            $source = $api->get_provider_source($key);
            $has_saved_key = !empty($settings[$key . '_api_key']);
            $credential_status = self::get_credential_validation_status($key, $settings);
            $models = $is_configured ? $api->get_available_models($key) : array();
            $models = array_values(array_filter($models, static function ($model) use ($key) {
                return \AICore\Registry\ModelRegistry::isTextGenerationModel((string) $model, $key);
            }));
            $selected_model = $provider_models[$key] ?? '';
            $options = $provider_options[$key] ?? array();

            echo '<div class="ai-core-provider-card" data-provider="' . esc_attr($key) . '" data-has-key="' . ($is_configured ? '1' : '0') . '">';
            echo '<div class="ai-core-provider-card__header">';
            echo '<h4>' . esc_html($label) . '</h4>';
            $status_class = $is_configured ? 'is-active' : 'is-inactive';
            if ($has_saved_key) {
                $status_class = self::CREDENTIAL_VALID === $credential_status
                    ? 'is-credential-valid'
                    : 'is-credential-' . $credential_status;
            }
            echo '<span class="ai-core-provider-status ' . esc_attr($status_class) . '">';
            if (!$is_configured) {
                $status_label = __('Awaiting Provider', 'opace-ai-prompt-library-api-hub');
            } elseif (in_array($source, array('wordpress', 'wordpress_and_ai_core'), true)) {
                $status_label = __('Configured via WordPress', 'opace-ai-prompt-library-api-hub');
            } else {
                $status_label = self::get_credential_validation_label($credential_status);
            }
            echo esc_html($status_label);
            echo '</span>';
            echo '</div>';

            echo '<div class="ai-core-provider-card__body">';

            $model_field_id = 'ai-core-provider-model-' . $key;

            echo '<label for="' . esc_attr($model_field_id) . '">' . esc_html__('Default Model', 'opace-ai-prompt-library-api-hub') . '</label>';
            // aria-label repeats the visible label text and adds the provider,
            // so the four cards do not present four identical names.
            /* translators: %s: provider name, e.g. OpenAI. */
            echo '<select id="' . esc_attr($model_field_id) . '" class="ai-core-provider-model" aria-label="' . esc_attr(sprintf(__('%s default model', 'opace-ai-prompt-library-api-hub'), $label)) . '" data-provider="' . esc_attr($key) . '" name="' . esc_attr($this->option_name) . '[provider_models][' . esc_attr($key) . ']" ' . ($is_configured ? '' : 'disabled') . '>';

            if (!$is_configured) {
                echo '<option value="">' . esc_html__('Configure a provider to load models', 'opace-ai-prompt-library-api-hub') . '</option>';
            } else {
                if (empty($models)) {
                    echo '<option value="">' . esc_html__('Loading models...', 'opace-ai-prompt-library-api-hub') . '</option>';
                } else {
                    echo '<option value="">' . esc_html__('Select a model', 'opace-ai-prompt-library-api-hub') . '</option>';
                    foreach ($models as $model) {
                        $meta = \AICore\Registry\ModelRegistry::getModelConfig($model);
                        $label_text = $meta && !empty($meta['display_name']) ? $meta['display_name'] . ' (' . $model . ')' : $model;
                        echo '<option value="' . esc_attr($model) . '" ' . selected($selected_model, $model, false) . '>' . esc_html($label_text) . '</option>';
                    }
                }
            }

            echo '</select>';

            echo '<div class="ai-core-provider-params" data-provider="' . esc_attr($key) . '">';
            if (!$is_configured) {
                echo '<p class="description">' . esc_html__('Configure a provider to set defaults.', 'opace-ai-prompt-library-api-hub') . '</p>';
            }
            echo '</div>'; // dynamic params container

            echo '</div>'; // body

            echo '<div class="ai-core-provider-card__footer">';
            echo '<button type="button" class="button-link ai-core-provider-refresh" data-provider="' . esc_attr($key) . '"' . ($is_configured ? '' : ' disabled') . '>' . esc_html__('Refresh models', 'opace-ai-prompt-library-api-hub') . '</button>';
            echo '</div>';

            echo '</div>'; // card
        }

        echo '</div>';
    }
    
    /**
     * Default provider field callback
     *
     * @return void
     */
    public function default_provider_field_callback() {
        $settings = get_option($this->option_name, $this->get_default_settings());
        $value = $settings['default_provider'] ?? 'openai';

        // Get configured providers
        $api = AI_Core_API::get_instance();
        $configured_providers = $api->get_configured_providers();

        $provider_names = self::get_provider_labels();
        $configured_providers = array_values(array_filter($configured_providers, static function ($provider) use ($provider_names) {
            return isset($provider_names[$provider]);
        }));

        // The Settings API renders the field title in a table header, which is
        // not an accessible name, so the control carries its own.
        echo '<select id="default_provider" aria-label="' . esc_attr__('Default provider', 'opace-ai-prompt-library-api-hub') . '" name="' . esc_attr($this->option_name) . '[default_provider]">';

        if (empty($configured_providers)) {
            echo '<option value="">' . esc_html__('-- No providers configured --', 'opace-ai-prompt-library-api-hub') . '</option>';
        } else {
            foreach ($configured_providers as $provider_key) {
                $provider_label = $provider_names[$provider_key] ?? $provider_key;
                echo '<option value="' . esc_attr($provider_key) . '" ' . selected($value, $provider_key, false) . '>';
                echo esc_html($provider_label);
                echo '</option>';
            }
        }

        echo '</select>';

        echo '<p class="description">' . esc_html__('Default AI provider for add-on plugins. Only configured providers are shown.', 'opace-ai-prompt-library-api-hub') . '</p>';
    }
    
    /**
     * Checkbox field callback
     *
     * @param array $args Field arguments
     * @return void
     */
    public function checkbox_field_callback($args) {
        $settings = get_option($this->option_name, $this->get_default_settings());
        $field = $args['field'];
        $value = $settings[$field] ?? false;
        
        echo '<label>';
        echo '<input type="checkbox" ';
        echo 'id="' . esc_attr($field) . '" ';
        echo 'name="' . esc_attr($this->option_name) . '[' . esc_attr($field) . ']" ';
        echo 'value="1" ';
        checked($value, true);
        echo '/> ';
        echo esc_html($args['label']);
        echo '</label>';
    }

    /** Render the complete, explicit uninstall-retention choice. */
    public function retention_field_callback() {
        $settings = get_option($this->option_name, $this->get_default_settings());
        $keep = !empty($settings['persist_on_uninstall']);
        echo '<fieldset class="ai-core-retention-control">';
        echo '<label class="ai-core-retention-choice">';
        echo '<input type="checkbox" id="persist_on_uninstall" name="' . esc_attr($this->option_name) . '[persist_on_uninstall]" value="1" ' . checked($keep, true, false) . '> ';
        echo '<strong>' . esc_html__('Keep all Opace AI Hub data when the plugin is deleted (recommended)', 'opace-ai-prompt-library-api-hub') . '</strong>';
        echo '</label>';
        echo '<p class="description">' . esc_html__('Keeps encrypted provider keys, provider and model settings, the prompt library and groups, usage/token/estimated-cost statistics, version and encryption metadata, and cached model/pricing data. Deactivation and normal updates always keep this data.', 'opace-ai-prompt-library-api-hub') . '</p>';
        echo '<p class="description ai-core-retention-delete"><strong>' . esc_html__('If unticked:', 'opace-ai-prompt-library-api-hub') . '</strong> ' . esc_html__('deleting Opace AI Hub permanently removes every Opace AI Hub item listed above. It does not delete content or settings owned by AI-Scribe or another plugin.', 'opace-ai-prompt-library-api-hub') . '</p>';
        echo '<p class="description" aria-live="polite" data-retention-summary>' . ($keep
            ? esc_html__('Current choice: keep all Opace AI Hub data after deletion.', 'opace-ai-prompt-library-api-hub')
            : esc_html__('Current choice: permanently remove all Opace AI Hub data when deleted.', 'opace-ai-prompt-library-api-hub')) . '</p>';
        echo '</fieldset>';
    }
    
    /**
     * Get default settings
     *
     * @return array Default settings
     */
    private function get_default_settings() {
        return array(
            'openai_api_key' => '',
            'anthropic_api_key' => '',
            'gemini_api_key' => '',
            'default_provider' => 'openai',
            'enable_stats' => true,
            'enable_caching' => true,
            'cache_duration' => 3600,
            'persist_on_uninstall' => true,
            'provider_models' => array(),
            'provider_options' => array(),
            'credential_validation' => array(),
        );
    }
    
    /**
     * Sanitize settings
     *
     * register_setting() installs this as the sanitize_option_ai_core_settings
     * filter, so it runs for the settings form AND for every programmatic
     * update_option() call, including the Clear key AJAX handler. The two
     * callers mean different things by an empty value:
     *
     * - Settings form: the key fields render empty by design, so a blank field
     *   means "leave the stored key alone".
     * - Programmatic write: the caller passes the complete settings array it
     *   wants stored, so an empty key means "remove this key".
     *
     * The form is identified by the option_page field WordPress posts to
     * options.php. That field is only read to tell the two callers apart -
     * options.php has already verified the nonce and the capability before this
     * filter runs, and nothing here grants access.
     *
     * A form can also clear a key deliberately by posting the CLEAR_SENTINEL
     * value, which always wins.
     *
     * @param array $input Raw input values
     * @return array Sanitized values
     */
    public function sanitize_settings($input) {
        $existing_settings = get_option($this->option_name, $this->get_default_settings());

        if (!is_array($input)) {
            return $existing_settings;
        }

        $sanitized = array();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Identifies the caller only; options.php verifies the nonce first.
        $posted_page = isset($_POST['option_page']) ? sanitize_text_field(wp_unslash($_POST['option_page'])) : '';
        $is_settings_form = ($posted_page === $this->settings_group);

        // Sanitize API keys
        foreach (self::get_secret_fields() as $key) {
            $existing = $existing_settings[$key] ?? '';

            if (!array_key_exists($key, $input) || !is_string($input[$key])) {
                $sanitized[$key] = $existing;
                continue;
            }

            $new_value = sanitize_text_field($input[$key]);

            if (self::CLEAR_SENTINEL === $new_value) {
                // Explicit request to erase the key, from any caller.
                $sanitized[$key] = '';
                continue;
            }

            if ('' === $new_value) {
                $sanitized[$key] = $is_settings_form ? $existing : '';
                continue;
            }

            $sanitized[$key] = $new_value;
        }

        // Validation is separate from storage and from model discovery. A
        // changed key has not been tested unless the explicit validation AJAX
        // handler records a result after the write succeeds.
        $validation = isset($existing_settings['credential_validation']) && is_array($existing_settings['credential_validation'])
            ? $existing_settings['credential_validation']
            : array();

        if (!$is_settings_form && isset($input['credential_validation']) && is_array($input['credential_validation'])) {
            $validation = array();
            foreach ($input['credential_validation'] as $provider => $status) {
                $provider = sanitize_key($provider);
                $status = sanitize_key($status);
                if (in_array($status, self::get_credential_validation_states(), true)) {
                    $validation[$provider] = $status;
                }
            }
        }

        foreach (self::get_secret_fields() as $key) {
            $provider = substr($key, 0, -strlen('_api_key'));
            $existing = isset($existing_settings[$key]) ? (string) $existing_settings[$key] : '';
            $current = isset($sanitized[$key]) ? (string) $sanitized[$key] : '';

            if ('' === $current) {
                unset($validation[$provider]);
            } elseif ('' === $existing || !hash_equals($existing, $current)) {
                $validation[$provider] = self::CREDENTIAL_UNTESTED;
            } elseif (!isset($validation[$provider])) {
                $validation[$provider] = self::CREDENTIAL_UNTESTED;
            }
        }

        $sanitized['credential_validation'] = $validation;

        // Sanitize default provider
        $sanitized['default_provider'] = isset($input['default_provider']) ? sanitize_text_field($input['default_provider']) : 'openai';

        // Sanitize checkboxes
        $sanitized['enable_stats'] = isset($input['enable_stats']) && $input['enable_stats'] == '1';
        $sanitized['enable_caching'] = isset($input['enable_caching']) && $input['enable_caching'] == '1';
        $sanitized['persist_on_uninstall'] = isset($input['persist_on_uninstall']) && $input['persist_on_uninstall'] == '1';
        if ($is_settings_form) {
            add_settings_error(
                'ai_core_settings',
                'ai_core_retention_saved',
                $sanitized['persist_on_uninstall']
                    ? __('Settings saved. Opace AI Hub will retain all of its data if the plugin is deleted.', 'opace-ai-prompt-library-api-hub')
                    : __('Settings saved. Deleting Opace AI Hub will permanently remove all Opace AI Hub data.', 'opace-ai-prompt-library-api-hub'),
                $sanitized['persist_on_uninstall'] ? 'success' : 'warning'
            );
        }

        // Sanitize cache duration - the form has no field for it, so keep what is stored
        $sanitized['cache_duration'] = isset($input['cache_duration'])
            ? absint($input['cache_duration'])
            : absint($existing_settings['cache_duration'] ?? 3600);

        // Sanitize provider models selections.
        // The form only carries the cards on screen, so it merges over what is
        // stored. A programmatic write passes the whole array and is
        // authoritative, which is what lets the Clear path drop a provider.
        $posted_models = (isset($input['provider_models']) && is_array($input['provider_models'])) ? $input['provider_models'] : null;

        if (null === $posted_models) {
            $sanitized['provider_models'] = $existing_settings['provider_models'] ?? array();
        } else {
            $sanitized['provider_models'] = $is_settings_form ? ($existing_settings['provider_models'] ?? array()) : array();
            foreach ($posted_models as $provider => $model) {
                $provider = sanitize_key($provider);
                if (!empty($model) && is_string($model)) {
                    $sanitized['provider_models'][$provider] = sanitize_text_field($model);
                } else {
                    unset($sanitized['provider_models'][$provider]);
                }
            }
        }

        // Sanitize provider-specific parameter values
        $posted_options = (isset($input['provider_options']) && is_array($input['provider_options'])) ? $input['provider_options'] : null;

        if (null === $posted_options) {
            $sanitized['provider_options'] = $existing_settings['provider_options'] ?? array();
        } else {
            $sanitized['provider_options'] = $is_settings_form ? ($existing_settings['provider_options'] ?? array()) : array();

            foreach ($posted_options as $provider => $options) {
                $provider = sanitize_key($provider);

                if (!is_array($options)) {
                    continue;
                }

                $model = $sanitized['provider_models'][$provider] ?? ($existing_settings['provider_models'][$provider] ?? '');
                $schema = ($model && class_exists('\\AICore\\Registry\\ModelRegistry'))
                    ? \AICore\Registry\ModelRegistry::getParameterSchema($model)
                    : array();

                $clean = array();

                foreach ($schema as $param_key => $meta) {
                    if (isset($options[$param_key])) {
                        $clean[$param_key] = $this->sanitize_parameter_value($options[$param_key], $meta);
                    } elseif (isset($meta['default'])) {
                        $clean[$param_key] = $meta['default'];
                    }
                }

                // A model the registry does not describe still has values the
                // user typed. Keep them rather than dropping them on the floor,
                // otherwise the fields vanish on the next page load.
                foreach ($options as $param_key => $param_value) {
                    $param_key = sanitize_key($param_key);
                    if (isset($clean[$param_key]) || is_array($param_value)) {
                        continue;
                    }
                    $clean[$param_key] = $this->sanitize_parameter_value($param_value, array());
                }

                $sanitized['provider_options'][$provider] = $clean;
            }
        }

        /*

         * Adding a key is enough: the hub records the newest text model and the

         * newest image model that account can actually serve, so nothing has to

         * guess a default later. A model the user picked is left alone; one the

         * account no longer serves is replaced, because it can only fail.

         */

        if (class_exists('AI_Core_Model_Defaults')) {

            $sanitized = AI_Core_Model_Defaults::apply($sanitized);

        }


        return $sanitized;
    }

    /**
     * Sanitize individual model parameter values based on metadata.
     *
     * @param mixed $value
     * @param array $meta
     * @return mixed
     */
    private function sanitize_parameter_value($value, array $meta) {
        switch ($meta['type'] ?? '') {
            case 'number':
                $value = is_numeric($value) ? $value : ($meta['default'] ?? 0);
                $min = $meta['min'] ?? null;
                $max = $meta['max'] ?? null;
                if ($min !== null && $value < $min) {
                    $value = $min;
                }
                if ($max !== null && $value > $max) {
                    $value = $max;
                }
                if (isset($meta['step']) && $meta['step'] < 1) {
                    return (float) $value;
                }
                return (int) $value;
            case 'select':
                $valid = array_column($meta['options'] ?? [], 'value');
                return in_array($value, $valid, true) ? $value : ($meta['default'] ?? reset($valid));
            default:
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Get setting value
     *
     * @param string $key Setting key
     * @param mixed $default Default value
     * @return mixed Setting value
     */
    public function get_setting($key, $default = null) {
        $settings = get_option($this->option_name, $this->get_default_settings());
        return $settings[$key] ?? $default;
    }

    /**
     * Register the option-level hooks that keep API keys encrypted at rest.
     *
     * These run on every request, admin or not, because add-ons read the keys
     * through get_option() from the front end and from cron as well.
     *
     * Compatibility contract: the keys are ciphertext in the database and
     * plaintext everywhere else. Anything reading get_option('ai_core_settings')
     * - Opace AI Hub itself, AI-Scribe's get_hub_api_key(), any other add-on - keeps
     * receiving a usable key and needs no change. Only a direct SQL read sees
     * the ciphertext.
     *
     * @return void
     */
    public static function bootstrap() {
        add_filter('option_' . self::OPTION_NAME, array(__CLASS__, 'decrypt_settings_option'));
        // Runs even when an integration writes outside wp-admin.
        add_filter('sanitize_option_' . self::OPTION_NAME, array(__CLASS__, 'normalise_credential_validation'), 15);
        // Priority 20 so this runs after the registered sanitize callback.
        add_filter('sanitize_option_' . self::OPTION_NAME, array(__CLASS__, 'encrypt_settings_option'), 20);
        add_action('plugins_loaded', array(__CLASS__, 'maybe_migrate_key_storage'), 5);
    }

    /**
     * Decrypt secret fields as the option is read.
     *
     * @param mixed $settings Stored settings
     * @return mixed Settings with plaintext keys
     */
    public static function decrypt_settings_option($settings) {
        if (!is_array($settings)) {
            return $settings;
        }

        foreach (self::get_secret_fields() as $field) {
            if (isset($settings[$field]) && is_string($settings[$field]) && '' !== $settings[$field]) {
                $settings[$field] = self::decrypt_value($settings[$field]);
            }
        }

        return $settings;
    }

    /**
     * Encrypt secret fields as the option is written.
     *
     * @param mixed $settings Settings about to be stored
     * @return mixed Settings with encrypted keys
     */
    public static function encrypt_settings_option($settings) {
        if (!is_array($settings)) {
            return $settings;
        }

        foreach (self::get_secret_fields() as $field) {
            if (isset($settings[$field]) && is_string($settings[$field]) && '' !== $settings[$field]) {
                $settings[$field] = self::encrypt_value($settings[$field]);
            }
        }

        return $settings;
    }

    /**
     * One-time migration of plaintext keys already in the database.
     *
     * Reads through the decrypt filter (which passes unprefixed plaintext
     * straight through) and writes back through the encrypt filter.
     *
     * @return void
     */
    public static function maybe_migrate_key_storage() {
        if (get_option(self::ENCRYPTION_OPTION) === self::ENCRYPTION_VERSION) {
            return;
        }

        $settings = get_option(self::OPTION_NAME, null);

        if (is_array($settings)) {
            update_option(self::OPTION_NAME, $settings);
        }

        update_option(self::ENCRYPTION_OPTION, self::ENCRYPTION_VERSION);
    }

    /**
     * Encrypt a single secret with AES-256-CBC and a random per-value IV.
     *
     * @param string $value Plaintext secret
     * @return string Versioned ciphertext, or the plaintext if OpenSSL is unavailable
     */
    private static function encrypt_value($value) {
        if ('' === $value || 0 === strpos($value, self::ENCRYPTION_PREFIX)) {
            return $value;
        }

        if (!function_exists('openssl_encrypt') || !function_exists('openssl_random_pseudo_bytes')) {
            return $value;
        }

        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($value, 'AES-256-CBC', self::get_encryption_key(), 0, $iv);

        if (false === $encrypted) {
            return $value;
        }

        return self::ENCRYPTION_PREFIX . base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a single secret.
     *
     * Fails closed: a marked ciphertext that will not decrypt (the salts have
     * changed, say) returns an empty string rather than leaking stored bytes.
     *
     * @param string $value Stored value
     * @return string Plaintext secret
     */
    private static function decrypt_value($value) {
        if (0 !== strpos($value, self::ENCRYPTION_PREFIX)) {
            // Predates the migration: still plaintext.
            return $value;
        }

        $decoded = base64_decode(substr($value, strlen(self::ENCRYPTION_PREFIX)), true);

        if (false === $decoded || strlen($decoded) <= 16 || !function_exists('openssl_decrypt')) {
            return '';
        }

        $decrypted = openssl_decrypt(
            substr($decoded, 16),
            'AES-256-CBC',
            self::get_encryption_key(),
            0,
            substr($decoded, 0, 16)
        );

        return false !== $decrypted ? $decrypted : '';
    }

    /**
     * Derive the 32-byte encryption key from the WordPress salts.
     *
     * @return string Binary key
     */
    private static function get_encryption_key() {
        $salt_data  = defined('AUTH_SALT') ? AUTH_SALT : 'ai-core-auth';
        $salt_data .= defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : 'ai-core-secure';
        $salt_data .= defined('LOGGED_IN_SALT') ? LOGGED_IN_SALT : 'ai-core-logged';
        $salt_data .= defined('NONCE_SALT') ? NONCE_SALT : 'ai-core-nonce';

        return hash('sha256', $salt_data . 'ai-core-encryption-key', true);
    }
}

// Keys are encrypted at rest and decrypted on read for every request.
AI_Core_Settings::bootstrap();
