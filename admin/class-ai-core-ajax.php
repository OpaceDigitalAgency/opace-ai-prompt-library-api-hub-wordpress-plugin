<?php
/**
 * Opace AI Hub AJAX Class
 *
 * Handles AJAX requests for admin interface
 *
 * @package AI_Core
 * @version 0.2.7
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Opace AI Hub AJAX Class
 * 
 * Manages AJAX handlers
 */
class AI_Core_AJAX {
    
    /**
     * Class instance
     * 
     * @var AI_Core_AJAX
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return AI_Core_AJAX
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
     * Initialize AJAX handlers
     *
     * @return void
     */
    private function init() {
        add_action('wp_ajax_ai_core_test_api_key', array($this, 'test_api_key'));
        add_action('wp_ajax_ai_core_get_models', array($this, 'get_models'));
        add_action('wp_ajax_ai_core_get_model_capabilities', array($this, 'get_model_capabilities'));
        add_action('wp_ajax_ai_core_reset_stats', array($this, 'reset_stats'));
        add_action('wp_ajax_ai_core_refresh_pricing', array($this, 'refresh_pricing'));
        add_action('wp_ajax_ai_core_test_prompt', array($this, 'test_prompt'));
        add_action('wp_ajax_ai_core_save_api_key', array($this, 'save_api_key'));
        add_action('wp_ajax_ai_core_clear_api_key', array($this, 'clear_api_key'));
        // NOTE: ai_core_get_prompts and ai_core_run_prompt are handled by AI_Core_Prompt_Library class
        // Removed duplicate handlers to prevent conflicts
    }

    /** Refresh prices for every model represented in the statistics option. */
    public function refresh_pricing() {
        check_ajax_referer('ai_core_admin', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }
        $stats = AI_Core_Stats::get_instance();
        $data = $stats->reconcile_pricing(true);
        $available = 0;
        $unavailable = 0;
        foreach (($data['models'] ?? array()) as $row) {
            if ('unavailable' === ($row['cost_status'] ?? 'unavailable')) {
                $unavailable++;
            } else {
                $available++;
            }
        }
        wp_send_json_success(array(
            'message' => sprintf(
                /* translators: 1: number priced, 2: number unavailable. */
                __('Pricing refreshed: %1$d model(s) priced; %2$d unavailable.', 'opace-ai-prompt-library-api-hub'),
                $available,
                $unavailable
            ),
        ));
    }

    /**
     * Persist API key immediately
     *
     * @return void
     */
    public function save_api_key() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash( $_POST['provider'] )) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';

        if (empty($provider) || empty($api_key)) {
            wp_send_json_error(array('message' => __('Provider and API key are required', 'opace-ai-prompt-library-api-hub')));
        }

        $validator = AI_Core_Validator::get_instance();
        $validation = $validator->validate_api_key($provider, $api_key);

        if (empty($validation['valid'])) {
            $message = $validation['error'] ?? __('API key validation failed', 'opace-ai-prompt-library-api-hub');
            $stored_settings = get_option('ai_core_settings', array());
            $stored_key = isset($stored_settings[$provider . '_api_key']) ? (string) $stored_settings[$provider . '_api_key'] : '';
            $testing_saved_key = '' !== $stored_key && hash_equals($stored_key, $api_key);
            if ($testing_saved_key) {
                AI_Core_Settings::record_credential_validation_status($provider, AI_Core_Settings::CREDENTIAL_INVALID);
            }
            wp_send_json_error(array(
                'message' => $message,
                'credential_status' => $testing_saved_key ? AI_Core_Settings::CREDENTIAL_INVALID : '',
            ));
        }

        $settings = get_option('ai_core_settings', array());
        $field = $provider . '_api_key';
        $settings[$field] = $api_key;

        if (empty($settings['default_provider'])) {
            $settings['default_provider'] = $provider;
        }

        if (!isset($settings['provider_models']) || !is_array($settings['provider_models'])) {
            $settings['provider_models'] = array();
        }

        update_option('ai_core_settings', $settings);
        AI_Core_Settings::record_credential_validation_status($provider, AI_Core_Settings::CREDENTIAL_VALID);

        if (class_exists('AI_Core_WordPress_AI_Client')) {
            AI_Core_WordPress_AI_Client::bridge_provider($provider, $api_key);
        }

        $models = $validator->get_available_models($provider, $api_key, true);

        // Saving above ran the sanitize callback, where AI_Core_Model_Defaults
        // records a default from the live list. Re-read rather than trusting
        // the stale local copy, and fill in only if that still left a gap.
        $settings = get_option('ai_core_settings', array());

        if (!empty($models)) {
            $preferredModel = \AICore\Registry\ModelRegistry::getPreferredModel($provider, $models);
            if (empty($settings['provider_models'][$provider]) && !empty($preferredModel)) {
                $settings['provider_models'][$provider] = $preferredModel;
                update_option('ai_core_settings', $settings);
            }
        } else {
            $preferredModel = \AICore\Registry\ModelRegistry::getPreferredModel($provider);
        }

        // The model the site will actually use. The computed preference is a
        // fallback only: reporting it over the stored choice made the dropdown
        // select a model that was never saved.
        $selectedModel = $settings['provider_models'][$provider] ?? '';
        $activeModel = $selectedModel ?: (string) $preferredModel;

        $parameterSchema = $activeModel ? \AICore\Registry\ModelRegistry::getParameterSchema($activeModel) : array();

        wp_send_json_success(array(
            'message' => __('API key saved successfully.', 'opace-ai-prompt-library-api-hub'),
            'provider' => $provider,
            'models' => $models,
            'count' => count($models),
            'default_provider' => $settings['default_provider'],
            'masked_key' => str_repeat('•', max(0, strlen($api_key) - 4)) . substr($api_key, -4),
            'selected_model' => $selectedModel,
            'preferred_model' => $activeModel,
            'parameters' => $parameterSchema,
            'model_meta' => \AICore\Registry\ModelRegistry::exportProviderMetadata()[$provider] ?? array(),
            'source' => AI_Core_API::get_instance()->get_provider_source($provider),
            'credential_status' => AI_Core_Settings::CREDENTIAL_VALID,
        ));
    }

    /**
     * Clear stored API key for a provider
     *
     * @return void
     */
    public function clear_api_key() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash( $_POST['provider'] )) : '';

        if (empty($provider)) {
            wp_send_json_error(array('message' => __('Provider is required', 'opace-ai-prompt-library-api-hub')));
        }

        $settings = get_option('ai_core_settings', array());
        $field = $provider . '_api_key';
        $api = AI_Core_API::get_instance();
        $source_before = $api->get_provider_source($provider);
        $still_configured = in_array($source_before, array('wordpress', 'wordpress_and_ai_core'), true);

        if (isset($settings[$field])) {
            $settings[$field] = '';
        }

        if (!$still_configured && isset($settings['provider_models'][$provider])) {
            unset($settings['provider_models'][$provider]);
        }

        if (!$still_configured && isset($settings['provider_options'][$provider])) {
            unset($settings['provider_options'][$provider]);
        }

        if (!empty($settings['default_provider']) && $settings['default_provider'] === $provider) {
            $settings['default_provider'] = $still_configured ? $provider : $this->get_next_configured_provider($settings, $provider);
        }

        update_option('ai_core_settings', $settings);

        $cache_prefix = 'ai_core_models_' . $provider;
        $this->purge_model_cache($cache_prefix);

        wp_send_json_success(array(
            'message' => __('API key removed.', 'opace-ai-prompt-library-api-hub'),
            'provider' => $provider,
            'default_provider' => $settings['default_provider'],
            'still_configured' => $still_configured,
            'source' => $still_configured ? 'wordpress' : 'none',
        ));
    }
    
    /**
     * Test API key
     *
     * @return void
     */
    public function test_api_key() {
        check_ajax_referer('ai_core_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }
        
        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash( $_POST['provider'] )) : '';
        $posted_api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash( $_POST['api_key'] )) : '';
        $api_key = $posted_api_key;
        $stored_key = '';

        // The stored key is never sent to the browser, so a test after a page
        // reload arrives with an empty field. Fall back to the saved value, as
        // AI_Core_Validator::get_available_models() already does.
        if ('' === $api_key && '' !== $provider) {
            $settings = get_option('ai_core_settings', array());
            $stored_key = isset($settings[$provider . '_api_key']) ? (string) $settings[$provider . '_api_key'] : '';
            $api_key  = $stored_key;
        } elseif ('' !== $provider) {
            $settings = get_option('ai_core_settings', array());
            $stored_key = isset($settings[$provider . '_api_key']) ? (string) $settings[$provider . '_api_key'] : '';
        }

        $testing_saved_key = '' !== $stored_key && hash_equals($stored_key, $api_key);

        if ('' !== $provider && '' === $api_key) {
            $api = AI_Core_API::get_instance();
            if (in_array($provider, $api->get_configured_providers(), true)) {
                wp_send_json_success(array(
                    'message' => __('Provider is configured through WordPress Connectors.', 'opace-ai-prompt-library-api-hub'),
                    'provider' => $provider,
                    'source' => $api->get_provider_source($provider),
                ));
            }
        }

        if (empty($provider) || empty($api_key)) {
            wp_send_json_error(array('message' => __('Provider and API key are required', 'opace-ai-prompt-library-api-hub')));
        }
        
        $validator = AI_Core_Validator::get_instance();
        $result = $validator->validate_api_key($provider, $api_key);
        
        if ($result['valid']) {
            if ($testing_saved_key) {
                AI_Core_Settings::record_credential_validation_status($provider, AI_Core_Settings::CREDENTIAL_VALID);
            }
            wp_send_json_success(array(
                'message' => __('API key is valid!', 'opace-ai-prompt-library-api-hub'),
                'provider' => $result['provider'] ?? $provider,
                'credential_status' => $testing_saved_key ? AI_Core_Settings::CREDENTIAL_VALID : '',
            ));
        } else {
            if ($testing_saved_key) {
                AI_Core_Settings::record_credential_validation_status($provider, AI_Core_Settings::CREDENTIAL_INVALID);
            }
            wp_send_json_error(array(
                'message' => $result['error'] ?? __('API key validation failed', 'opace-ai-prompt-library-api-hub'),
                'credential_status' => $testing_saved_key ? AI_Core_Settings::CREDENTIAL_INVALID : '',
            ));
        }
    }
    
    /**
     * Get available models for a provider
     *
     * @return void
     */
    public function get_models() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }

        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash( $_POST['provider'] )) : '';

        if (empty($provider)) {
            wp_send_json_error(array('message' => __('Provider is required', 'opace-ai-prompt-library-api-hub')));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        $force_refresh = !empty($_POST['force_refresh']);

        $api = AI_Core_API::get_instance();
        $source = $api->get_provider_source($provider);
        if ('' !== $api_key || in_array($source, array('ai_core_direct', 'none'), true)) {
            $validator = AI_Core_Validator::get_instance();
            $models = $validator->get_available_models($provider, $api_key ?: null, (bool) $force_refresh);
        } else {
            $models = $api->get_available_models($provider);
        }
        $models = array_values(array_filter($models, static function ($model) use ($provider) {
            return \AICore\Registry\ModelRegistry::isTextGenerationModel((string) $model, $provider);
        }));

        $settings = get_option('ai_core_settings', array());
        $has_saved_key = !empty($settings[$provider . '_api_key']);

        // A refresh must not flip the user's saved choice: only compute a
        // preference when nothing is stored, or the stored model has vanished
        // from the account's list.
        $selectedModel = $settings['provider_models'][$provider] ?? '';
        $preferredModel = ('' !== $selectedModel && in_array($selectedModel, $models, true))
            ? $selectedModel
            : \AICore\Registry\ModelRegistry::getPreferredModel($provider, $models);

        wp_send_json_success(array(
            'models' => $models,
            'count' => count($models),
            'provider' => $provider,
            'has_saved_key' => $has_saved_key,
            'configured' => in_array($provider, $api->get_configured_providers(), true),
            'source' => $source,
            'selected_model' => $selectedModel,
            'preferred_model' => $preferredModel,
            'parameters' => $preferredModel ? \AICore\Registry\ModelRegistry::getParameterSchema($preferredModel) : array(),
            'model_meta' => \AICore\Registry\ModelRegistry::exportProviderMetadata()[$provider] ?? array(),
            // Catalogue discovery is deliberately separate from credential
            // validation. This reports the stored state without changing it.
            'credential_status' => AI_Core_Settings::get_credential_validation_status($provider, $settings),
        ));
    }

    /**
     * Get model capabilities (supported parameters)
     *
     * @return void
     */
    public function get_model_capabilities() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }

        $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash( $_POST['model'] )) : '';
        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash( $_POST['provider'] )) : '';

        if (empty($model) || empty($provider)) {
            wp_send_json_error(array('message' => __('Model and provider are required', 'opace-ai-prompt-library-api-hub')));
        }

        $capabilities = \AICore\Registry\ModelRegistry::getParameterSchema($model);

        wp_send_json_success(array(
            'model' => $model,
            'provider' => $provider,
            'capabilities' => $capabilities
        ));
    }

    /**
     * Remove cached model entries when clearing keys
     *
     * @param string $cache_prefix Prefix used for model cache transient
     * @return void
     */
    private function purge_model_cache($cache_prefix) {
        global $wpdb;

        $like = $wpdb->esc_like('_transient_' . $cache_prefix);
        // Bulk transient cleanup has no equivalent core API for a dynamic prefix.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM %i WHERE option_name LIKE %s',
                $wpdb->options,
                $like . '%'
            )
        );

        $timeout_like = $wpdb->esc_like('_transient_timeout_' . $cache_prefix);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM %i WHERE option_name LIKE %s',
                $wpdb->options,
                $timeout_like . '%'
            )
        );
    }

    /**
     * Determine next configured provider for defaults
     *
     * @param array  $settings Current settings array
     * @param string $exclude  Provider being removed.
     * @return string Provider key or empty string
     */
    private function get_next_configured_provider($settings, $exclude = '') {
        $configured = AI_Core_API::get_instance()->get_configured_providers();
        foreach (array('openai', 'anthropic', 'gemini') as $provider) {
            if ($provider !== $exclude && (in_array($provider, $configured, true) || !empty($settings[$provider . '_api_key']))) {
                return $provider;
            }
        }

        return '';
    }
    
    /**
     * Reset statistics
     *
     * @return void
     */
    public function reset_stats() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }

        $stats = AI_Core_Stats::get_instance();
        $result = $stats->reset_stats();

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Statistics reset successfully', 'opace-ai-prompt-library-api-hub')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to reset statistics', 'opace-ai-prompt-library-api-hub')
            ));
        }
    }

    /**
     * Test prompt (for testing in Settings page)
     *
     * @return void
     */
    public function test_prompt() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }

        $prompt_content = isset($_POST['prompt']) ? wp_kses_post(wp_unslash( $_POST['prompt'] )) : '';
        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash( $_POST['provider'] )) : '';
        $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash( $_POST['model'] )) : '';
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash( $_POST['type'] )) : 'text';
        $settings = get_option('ai_core_settings', array());

        if (empty($prompt_content)) {
            wp_send_json_error(array('message' => __('Prompt content is required', 'opace-ai-prompt-library-api-hub')));
        }

        if (empty($provider)) {
            wp_send_json_error(array('message' => __('Provider is required', 'opace-ai-prompt-library-api-hub')));
        }

        if (empty($model) && $type === 'text') {
            $saved_model = $settings['provider_models'][$provider] ?? '';
            if (!empty($saved_model)) {
                $model = $saved_model;
            } else {
                wp_send_json_error(array('message' => __('Model is required for text generation', 'opace-ai-prompt-library-api-hub')));
            }
        }

        $api = AI_Core_API::get_instance();
        if (!in_array($provider, $api->get_configured_providers(), true)) {
            wp_send_json_error(array('message' => __('This provider is not configured in Opace AI Hub or WordPress Connectors.', 'opace-ai-prompt-library-api-hub')));
        }

        try {
            if ($type === 'image') {
                // For image generation - pass the model to the image provider
                $image_options = array();
                $original_model = $model;

                // Auto-switch Gemini models to -image variant if needed
                if ($provider === 'gemini' && !empty($model)) {
                    $model = $this->get_gemini_image_model($model);
                }

                // If model is specified, use it (important for Gemini image models)
                if (!empty($model)) {
                    $image_options['model'] = $model;
                }

                // Use AI_Core_API to ensure statistics tracking
                $api = AI_Core_API::get_instance();
                $usage_context = array('tool' => 'settings_page');
                $result = $api->generate_image($prompt_content, $image_options, $provider, $usage_context);

                // Check for WP_Error
                if (is_wp_error($result)) {
                    wp_send_json_error(array('message' => $result->get_error_message()));
                }

                $image_item = $result['data'][0] ?? array();
                $image_url = $result['url'] ?? $image_item['url'] ?? '';
                if ('' === $image_url && !empty($image_item['b64_json'])) {
                    $mime_type = !empty($image_item['mime_type']) ? (string) $image_item['mime_type'] : 'image/png';
                    $image_url = 'data:' . $mime_type . ';base64,' . $image_item['b64_json'];
                }

                wp_send_json_success(array(
                    'result' => $image_url,
                    'type' => 'image',
                    'model' => $model,
                    'original_model' => $original_model,
                    'provider' => $provider,
                ));
            } else {
                // For text generation - use the selected model directly
                // Model is now selected by user from dropdown, not hardcoded
                $messages = array(
                    array(
                        'role' => 'user',
                        'content' => $prompt_content
                    )
                );

                $options = array('model' => $model);

                // Only apply provider options that are supported by the specific model
                if (!empty($settings['provider_options'][$provider]) && is_array($settings['provider_options'][$provider])) {
                    // Get the model's parameter schema to check which parameters are supported
                    $modelRegistry = \AICore\Registry\ModelRegistry::class;
                    if (class_exists($modelRegistry)) {
                        $parameterSchema = $modelRegistry::getParameterSchema($model);
                        $supportedParams = array_keys($parameterSchema);

                        // Only merge parameters that the model actually supports
                        foreach ($settings['provider_options'][$provider] as $key => $value) {
                            if (in_array($key, $supportedParams, true)) {
                                $options[$key] = $value;
                            }
                        }
                    } else {
                        // Fallback: merge all options if ModelRegistry not available
                        $options = array_merge($options, $settings['provider_options'][$provider]);
                    }
                }

                // Use AI_Core_API to ensure statistics tracking
                $api = AI_Core_API::get_instance();
                $usage_context = array('tool' => 'settings_page');
                $result = $api->send_text_request($model, $messages, $options, $usage_context);

                // Check for WP_Error
                if (is_wp_error($result)) {
                    wp_send_json_error(array('message' => $result->get_error_message()));
                }

                // Use the library's extractContent method to properly extract text from normalized response
                $text_response = \AICore\AICore::extractContent($result);

                wp_send_json_success(array(
                    'result' => $text_response,
                    'type' => 'text',
                    'model' => $model,
                    'provider' => $provider,
                ));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Get the appropriate Gemini image model
     *
     * Automatically converts standard Gemini models to an image-capable model
     * for image generation, similar to how GPT-5 works for both text and images.
     *
     * @param string $model The selected model
     * @return string The image-capable model
     */
    private function get_gemini_image_model($model) {
        // If already an image model, return as-is
        if (strpos($model, '-image') !== false || strpos($model, 'imagen-') === 0) {
            return $model;
        }

        // A text model was selected: swap to the registry's best Gemini image
        // model rather than a hardcoded generation, so the mapping keeps up
        // with whatever the registry (and live discovery) currently knows.
        $best = \AICore\Registry\ModelRegistry::getPreferredImageModel('gemini');

        return $best ?: 'gemini-2.5-flash-image';
    }

    // NOTE: get_prompts() method removed - it's handled by AI_Core_Prompt_Library class
    // This prevents duplicate AJAX handler registration which causes the second handler to never run
}

// Initialize AJAX handlers
AI_Core_AJAX::get_instance();
