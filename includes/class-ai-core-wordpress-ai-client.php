<?php
/**
 * WordPress 7.0+ AI Client bridge.
 *
 * Shares Opace AI Hub credentials with registered WordPress AI providers at
 * runtime and lets the Hub consume Connector-managed providers without ever
 * copying a secret between option stores.
 *
 * @package AI_Core
 * @since 1.0.11
 */

if (!defined('ABSPATH')) {
    exit;
}

class AI_Core_WordPress_AI_Client {

    /** @var array<string,bool> Providers authenticated from the Hub in this request. */
    private static $bridged_providers = array();

    /** @var array<string,bool> Providers already authenticated outside the Hub. */
    private static $wordpress_providers = array();

    /** @var bool Whether the normal per-request bridge pass has run. */
    private static $bridge_complete = false;

    /**
     * Whether the supported WordPress AI Client API is available and enabled.
     *
     * @return bool
     */
    public static function is_available() {
        return function_exists('wp_ai_client_prompt')
            && function_exists('wp_supports_ai')
            && class_exists('WordPress\\AiClient\\AiClient')
            && class_exists('WordPress\\AiClient\\Providers\\Http\\DTO\\ApiKeyRequestAuthentication')
            && (bool) call_user_func('wp_supports_ai');
    }

    /**
     * Supply encrypted Hub credentials to matching registered providers.
     *
     * Connector/env/constant credentials always win. No connector option is
     * written and no Connector secret is read into Hub storage.
     *
     * @return void
     */
    public static function bridge_hub_credentials() {
        self::$bridge_complete = true;

        if (!self::is_available()) {
            return;
        }

        $settings = get_option('ai_core_settings', array());
        $settings = is_array($settings) ? $settings : array();

        foreach (self::supported_providers() as $provider) {
            $key = isset($settings[$provider . '_api_key']) && is_string($settings[$provider . '_api_key'])
                ? $settings[$provider . '_api_key']
                : '';
            self::bridge_provider($provider, $key);
        }
    }

    /**
     * Refresh one provider after a Hub key is saved in the current request.
     *
     * @param string $provider Provider id.
     * @param string $api_key  Decrypted Hub key, held in memory only.
     * @return bool True when the provider is configured afterwards.
     */
    public static function bridge_provider($provider, $api_key) {
        $provider = sanitize_key($provider);

        if (!in_array($provider, self::supported_providers(), true) || !self::is_available()) {
            return false;
        }

        try {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            $core_provider = self::core_provider_id($provider);
            if (!$registry->hasProvider($core_provider)) {
                return false;
            }

            // A Connector, environment variable or constant is authoritative.
            $connector_key = self::get_connector_credential($provider);
            if ('' !== $connector_key) {
                $registry->setProviderRequestAuthentication(
                    $core_provider,
                    new \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication($connector_key)
                );
                self::$wordpress_providers[$provider] = true;
                unset(self::$bridged_providers[$provider]);
                return self::provider_has_authentication($registry, $core_provider);
            }

            // A provider already configured before the Hub bridge belongs to
            // WordPress/provider infrastructure and must not be overwritten.
            if (empty(self::$bridged_providers[$provider]) && self::provider_has_authentication($registry, $core_provider)) {
                self::$wordpress_providers[$provider] = true;
                return true;
            }

            if ('' === $api_key) {
                return self::provider_has_authentication($registry, $core_provider);
            }

            $authentication = new \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication($api_key);
            $registry->setProviderRequestAuthentication($core_provider, $authentication);

            if (self::provider_has_authentication($registry, $core_provider)) {
                self::$bridged_providers[$provider] = true;
                unset(self::$wordpress_providers[$provider]);
                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /**
     * Whether a provider implementation is registered with the core client.
     *
     * @param string $provider Provider id.
     * @return bool
     */
    public static function is_provider_registered($provider) {
        if (!self::is_available()) {
            return false;
        }

        try {
            return \WordPress\AiClient\AiClient::defaultRegistry()->hasProvider(self::core_provider_id($provider));
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Whether WordPress can currently execute requests for this provider.
     *
     * @param string $provider Provider id.
     * @return bool
     */
    public static function is_provider_configured($provider) {
        self::ensure_bridge();

        if (!self::is_available()) {
            return false;
        }

        try {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            $core_provider = self::core_provider_id($provider);
            return $registry->hasProvider($core_provider) && self::provider_has_authentication($registry, $core_provider);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Identify where the active provider credential comes from.
     *
     * @param string $provider Provider id.
     * @return string wordpress|wordpress_and_ai_core|ai_core|ai_core_direct|none
     */
    public static function get_provider_source($provider) {
        self::ensure_bridge();

        $settings = get_option('ai_core_settings', array());
        $has_hub_key = is_array($settings) && !empty($settings[$provider . '_api_key']);

        if (!empty(self::$wordpress_providers[$provider]) || '' !== self::get_connector_credential($provider)) {
            return $has_hub_key ? 'wordpress_and_ai_core' : 'wordpress';
        }

        if (!empty(self::$bridged_providers[$provider])) {
            return 'ai_core';
        }

        return $has_hub_key ? 'ai_core_direct' : 'none';
    }

    /**
     * Status for the Hub settings UI and public API.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function get_provider_status() {
        self::ensure_bridge();
        $status = array();

        foreach (self::supported_providers() as $provider) {
            $status[$provider] = array(
                'registered' => self::is_provider_registered($provider),
                'configured' => self::is_provider_configured($provider),
                'source' => self::get_provider_source($provider),
            );
        }

        return $status;
    }

    /**
     * Models exposed by a registered provider implementation.
     *
     * @param string $provider Provider id.
     * @return array<int,string>
     */
    public static function get_available_models($provider) {
        self::ensure_bridge();
        $models = array();

        if (!self::is_provider_configured($provider)) {
            return $models;
        }

        try {
            $registry = \WordPress\AiClient\AiClient::defaultRegistry();
            $class_name = $registry->getProviderClassName(self::core_provider_id($provider));
            $directory = $class_name::modelMetadataDirectory();

            foreach ($directory->listModelMetadata() as $metadata) {
                if (!is_object($metadata) || !method_exists($metadata, 'getId')) {
                    continue;
                }
                $model = (string) $metadata->getId();
                if ('' === $model) {
                    continue;
                }
                $models[] = $model;

                // Make WordPress-discovered models understandable to existing
                // Hub selectors, capability checks and usage attribution.
                if (class_exists('AICore\\Registry\\ModelRegistry')) {
                    \AICore\Registry\ModelRegistry::registerModel($model, array('provider' => $provider));
                }
            }
        } catch (\Throwable $e) {
            return array();
        }

        return array_values(array_unique($models));
    }

    /**
     * Whether the registered provider advertises a model.
     *
     * @param string $provider Provider id.
     * @param string $model    Model id.
     * @return bool
     */
    public static function supports_model($provider, $model) {
        return in_array($model, self::get_available_models($provider), true);
    }

    /**
     * Send a Hub message array through wp_ai_client_prompt().
     *
     * @param string $model    Preferred model id.
     * @param array  $messages Hub/OpenAI-shaped messages.
     * @param array  $options  Request options.
     * @param string $provider Optional provider id.
     * @return array|WP_Error OpenAI-compatible normalised response.
     */
    public static function send_text_request($model, array $messages, array $options = array(), $provider = '') {
        if (!self::is_available()) {
            return new WP_Error('wp_ai_client_unavailable', __('The WordPress AI Client is not available.', 'opace-ai-prompt-library-api-hub'));
        }

        try {
            $builder = call_user_func('wp_ai_client_prompt', null);
            $system = self::collect_system_text($messages);
            if ('' !== $system) {
                $builder = $builder->using_system_instruction($system);
            }

            $thread = self::normalise_thread($messages);
            if (empty($thread)) {
                return new WP_Error('invalid_params', __('No user prompt was supplied.', 'opace-ai-prompt-library-api-hub'));
            }

            $final = array_pop($thread);
            if (!empty($thread)) {
                $history = array();
                foreach ($thread as $entry) {
                    $history[] = \WordPress\AiClient\Messages\DTO\Message::fromArray(
                        array(
                            'role' => $entry['role'],
                            'parts' => array(array('type' => 'text', 'text' => $entry['text'])),
                        )
                    );
                }
                $builder = $builder->with_history(...$history);
            }

            $builder = $builder->with_text($final['text']);
            $builder = self::apply_parameters($builder, $model, $options, $provider);
            $result = $builder->generate_text_result();

            if (is_wp_error($result)) {
                return $result;
            }

            $content = $result->toText();
            $usage = self::extract_usage($result);
            $resolved_model = self::result_model($result, $model);

            return array(
                'id' => method_exists($result, 'getId') ? (string) $result->getId() : '',
                'object' => 'chat.completion',
                'created' => time(),
                'model' => $resolved_model,
                'choices' => array(
                    array(
                        'index' => 0,
                        'message' => array('role' => 'assistant', 'content' => $content),
                        'finish_reason' => 'stop',
                    ),
                ),
                'usage' => $usage,
                '_wordpress_ai_client' => true,
            );
        } catch (\Throwable $e) {
            return new WP_Error('wp_ai_client_error', $e->getMessage());
        }
    }

    /**
     * Generate an image through the WordPress AI Client.
     *
     * @param string $prompt   Image prompt.
     * @param array  $options  Image options.
     * @param string $provider Provider id.
     * @return array|WP_Error Existing Hub image response shape.
     */
    public static function generate_image($prompt, array $options = array(), $provider = '') {
        if (!self::is_available()) {
            return new WP_Error('wp_ai_client_unavailable', __('The WordPress AI Client is not available.', 'opace-ai-prompt-library-api-hub'));
        }

        try {
            $builder = call_user_func('wp_ai_client_prompt', (string) $prompt);
            $model = isset($options['model']) ? (string) $options['model'] : '';
            $builder = self::apply_parameters($builder, $model, $options, $provider);

            if (!empty($options['aspect_ratio'])) {
                $builder = $builder->as_output_media_aspect_ratio((string) $options['aspect_ratio']);
            }

            $result = $builder->generate_image_result();
            if (is_wp_error($result)) {
                return $result;
            }

            $file = $result->toImageFile();
            $item = array();
            if (method_exists($file, 'getUrl') && $file->getUrl()) {
                $item['url'] = (string) $file->getUrl();
            } elseif (method_exists($file, 'getBase64Data') && $file->getBase64Data()) {
                $item['b64_json'] = (string) $file->getBase64Data();
                if (method_exists($file, 'getMimeType')) {
                    $item['mime_type'] = (string) $file->getMimeType();
                }
            }

            if (empty($item)) {
                return new WP_Error('wp_ai_client_image_empty', __('The WordPress AI Client returned no image data.', 'opace-ai-prompt-library-api-hub'));
            }

            return array(
                'created' => time(),
                'data' => array($item),
                'model' => self::result_model($result, $model),
                'usage' => self::extract_usage($result),
                '_wordpress_ai_client' => true,
            );
        } catch (\Throwable $e) {
            return new WP_Error('wp_ai_client_error', $e->getMessage());
        }
    }

    /** @return array<int,string> */
    private static function supported_providers() {
        return array('openai', 'anthropic', 'gemini');
    }

    /**
     * Map Hub provider ids to WordPress AI Client provider ids.
     *
     * WordPress registers the Gemini provider and Connector as "google".
     *
     * @param string $provider Hub provider id.
     * @return string Core provider id.
     */
    private static function core_provider_id($provider) {
        return 'gemini' === $provider ? 'google' : $provider;
    }

    /** @return void */
    private static function ensure_bridge() {
        if (!self::$bridge_complete && (!function_exists('did_action') || did_action('init'))) {
            self::bridge_hub_credentials();
        }
    }

    /**
     * Read the active Connector credential for this request only.
     *
     * The value is never written to Hub storage. Reading it is necessary for
     * environment/constant sources that core identifies but does not itself
     * pass to the provider registry.
     *
     * @param string $provider Provider id.
     * @return bool
     */
    private static function get_connector_credential($provider) {
        if (!function_exists('wp_get_connector')) {
            return '';
        }

        $connector = wp_get_connector(self::core_provider_id($provider));
        if (!is_array($connector) || empty($connector['authentication']) || !is_array($connector['authentication'])) {
            return '';
        }

        $auth = $connector['authentication'];
        $env_name = isset($auth['env_var_name']) ? (string) $auth['env_var_name'] : '';
        if ('' !== $env_name) {
            $env_value = getenv($env_name);
            if (false !== $env_value && '' !== $env_value) {
                return (string) $env_value;
            }
        }

        $constant_name = isset($auth['constant_name']) ? (string) $auth['constant_name'] : '';
        if ('' !== $constant_name && defined($constant_name)) {
            $constant_value = constant($constant_name);
            if (is_string($constant_value) && '' !== $constant_value) {
                return $constant_value;
            }
        }

        $setting_name = isset($auth['setting_name']) ? (string) $auth['setting_name'] : '';
        $stored = '' !== $setting_name ? get_option($setting_name, '') : '';
        return is_string($stored) ? $stored : '';
    }

    /**
     * Whether authentication has been supplied without performing a provider
     * availability request. ProviderRegistry::isProviderConfigured() may make
     * a billable/network model-list request, so it is not a status primitive.
     *
     * @param object $registry      WordPress provider registry.
     * @param string $core_provider WordPress provider id.
     * @return bool
     */
    private static function provider_has_authentication($registry, $core_provider) {
        return method_exists($registry, 'getProviderRequestAuthentication')
            && null !== $registry->getProviderRequestAuthentication($core_provider);
    }

    /** @param array $messages @return string */
    private static function collect_system_text(array $messages) {
        $parts = array();
        foreach ($messages as $message) {
            if (!is_array($message) || 'system' !== ($message['role'] ?? '')) {
                continue;
            }
            $text = self::flatten_content($message['content'] ?? '');
            if ('' !== $text) {
                $parts[] = $text;
            }
        }
        return implode("\n\n", $parts);
    }

    /** @param array $messages @return array<int,array<string,string>> */
    private static function normalise_thread(array $messages) {
        $thread = array();
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }
            $role = isset($message['role']) ? (string) $message['role'] : 'user';
            if ('system' === $role) {
                continue;
            }
            $text = self::flatten_content($message['content'] ?? '');
            if ('' === $text) {
                continue;
            }
            $thread[] = array(
                'role' => 'assistant' === $role ? 'model' : 'user',
                'text' => $text,
            );
        }
        return $thread;
    }

    /** @param mixed $content @return string */
    private static function flatten_content($content) {
        if (is_string($content)) {
            return trim($content);
        }
        if (!is_array($content)) {
            return '';
        }

        $parts = array();
        foreach ($content as $block) {
            if (is_string($block)) {
                $parts[] = $block;
            } elseif (is_array($block) && isset($block['text'])) {
                $parts[] = (string) $block['text'];
            }
        }
        return trim(implode("\n", $parts));
    }

    /**
     * Map existing Hub options to the provider-neutral core builder.
     *
     * @param object $builder  WP prompt builder.
     * @param string $model    Preferred model.
     * @param array  $options  Hub options.
     * @param string $provider Provider id.
     * @return object
     */
    private static function apply_parameters($builder, $model, array $options, $provider) {
        if (!empty($options['max_tokens'])) {
            $builder = $builder->using_max_tokens((int) $options['max_tokens']);
        }
        if (isset($options['temperature']) && '' !== $options['temperature']) {
            $builder = $builder->using_temperature((float) $options['temperature']);
        }
        if (isset($options['top_p']) && '' !== $options['top_p']) {
            $builder = $builder->using_top_p((float) $options['top_p']);
        }
        if (isset($options['top_k']) && '' !== $options['top_k']) {
            $builder = $builder->using_top_k((int) $options['top_k']);
        }
        if (!empty($options['stop_sequences']) && is_array($options['stop_sequences'])) {
            $builder = $builder->using_stop_sequences(...array_map('strval', $options['stop_sequences']));
        }
        if (isset($options['presence_penalty']) && '' !== $options['presence_penalty']) {
            $builder = $builder->using_presence_penalty((float) $options['presence_penalty']);
        }
        if (isset($options['frequency_penalty']) && '' !== $options['frequency_penalty']) {
            $builder = $builder->using_frequency_penalty((float) $options['frequency_penalty']);
        }

        $schema = self::extract_json_schema($options);
        if (is_array($schema)) {
            $builder = $builder->as_json_response($schema);
        }

        if ('' !== $provider) {
            $builder = $builder->using_provider(self::core_provider_id($provider));
        }
        if ('' !== $model && 'wordpress-ai' !== $model) {
            $builder = $builder->using_model_preference($model);
        }

        return $builder;
    }

    /** @param array $options @return array|null */
    private static function extract_json_schema(array $options) {
        if (isset($options['response_format']['json_schema']['schema']) && is_array($options['response_format']['json_schema']['schema'])) {
            return $options['response_format']['json_schema']['schema'];
        }
        if (isset($options['generationConfig']['responseSchema']) && is_array($options['generationConfig']['responseSchema'])) {
            return $options['generationConfig']['responseSchema'];
        }
        if (isset($options['tools'][0]['input_schema']) && is_array($options['tools'][0]['input_schema'])) {
            return $options['tools'][0]['input_schema'];
        }
        return null;
    }

    /** @param object $result @return array<string,int> */
    private static function extract_usage($result) {
        $usage = array('prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0);
        try {
            $tokens = $result->getTokenUsage();
            $usage['prompt_tokens'] = (int) $tokens->getPromptTokens();
            $usage['completion_tokens'] = (int) $tokens->getCompletionTokens();
            $usage['total_tokens'] = (int) $tokens->getTotalTokens();
        } catch (\Throwable $e) {
            // Token data is optional; retain zeroes.
        }
        return $usage;
    }

    /** @param object $result @param string $fallback @return string */
    private static function result_model($result, $fallback) {
        try {
            return (string) $result->getModelMetadata()->getId();
        } catch (\Throwable $e) {
            return (string) $fallback;
        }
    }
}
