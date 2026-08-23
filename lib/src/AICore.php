<?php
/**
 * Opace AI Hub Library - Main Factory Class
 *
 * Central factory for creating and managing AI providers
 * Provides a simple interface for AI-Scribe integration
 *
 * Why direct provider integration rather than the WordPress core AI Client
 * (WordPress 7.0+): the core client can use multiple configured providers,
 * discover suitable models, and generate text or images. This library also
 * supports WordPress 6.5 and supplies a shared prompt library, explicit
 * provider and model controls, and consolidated usage and published-rate cost
 * records for companion plugins such as AI-Scribe. Supporting the core AI
 * Client as an additional backend is on the roadmap.
 *
 * @package AI_Core
 * @version 1.0.10
 */

namespace AICore;

use AICore\Providers\OpenAIProvider;
use AICore\Providers\AnthropicProvider;
use AICore\Providers\GeminiProvider;
use AICore\Providers\OpenAIImageProvider;
use AICore\Providers\GeminiImageProvider;
use AICore\Registry\ModelRegistry;
use AICore\Response\ResponseNormalizer;

class AICore {
    
    /**
     * Library version
     */
    const VERSION = '1.0.10';
    
    /**
     * Provider instances cache
     * 
     * @var array
     */
    private static $providers = [];
    
    /**
     * Configuration settings
     * 
     * @var array
     */
    private static $config = [];
    
    /**
     * Initialize Opace AI Hub with configuration
     * 
     * @param array $config Configuration array with API keys
     * @return void
     */
    public static function init(array $config): void {
        self::$config = $config;
        self::$providers = []; // Reset providers cache
    }
    
    /**
     * Get text provider for a specific model
     *
     * @param string $model Model identifier
     * @return \AICore\Interfaces\ProviderInterface
     * @throws \Exception If model is not supported or provider not configured
     */
    public static function getTextProvider(string $model): \AICore\Interfaces\ProviderInterface {

        // First check if model exists in registry
        if (ModelRegistry::modelExists($model)) {
            if (ModelRegistry::isImageModel($model)) {
                throw new \Exception(\esc_html("Model {$model} is for image generation, use getImageProvider() instead"));
            }

            $provider_name = ModelRegistry::getProvider($model);
        } else {
            // If not in registry, try to infer provider from model name
            $provider_name = self::inferProviderFromModel($model);

            if (!$provider_name) {
                throw new \Exception(\esc_html("Unknown model: {$model}. Unable to determine provider."));
            }

            // Register the model dynamically. The endpoint and parameter
            // contract are inferred from the family: the previous keys here
            // ('type', 'max_tokens') were not registry keys at all, so the
            // model landed on the generic provider fallback and every
            // request for it was built with the wrong token parameter.
            $endpoint = ModelRegistry::inferEndpoint($provider_name, $model);
            ModelRegistry::registerModel($model, array(
                'provider' => $provider_name,
                'endpoint' => $endpoint,
                'parameters' => ModelRegistry::inferParameterSchema($provider_name, $model, $endpoint),
            ));
        }

        if (!isset(self::$providers[$provider_name])) {
            self::$providers[$provider_name] = self::createTextProvider($provider_name);
        }

        return self::$providers[$provider_name];
    }

    /**
     * Infer provider from model name
     *
     * @param string $model Model identifier
     * @return string|null Provider name or null if cannot be determined
     */
    private static function inferProviderFromModel(string $model): ?string {
        // OpenAI models. The o-series is matched by shape rather than by
        // listing the versions that exist today: o1/o3/o4 were hardcoded,
        // so an o5 or later was unroutable and failed as an unknown model.
        if (preg_match('/^(gpt-|o[0-9]|chatgpt-|codex-|text-embedding-|tts-|whisper-)/i', $model)) {
            return 'openai';
        }

        // Anthropic models
        if (preg_match('/^claude-/i', $model)) {
            return 'anthropic';
        }

        // Gemini models
        if (preg_match('/^(gemini-|models\/gemini-|imagen-|nano-banana)/i', $model)) {
            return 'gemini';
        }

        return null;
    }

    /**
     * Image providers Opace AI Hub can actually drive.
     *
     * Keyed by provider id, valued by the config key holding its API key.
     * Membership here means "an ImageProviderInterface implementation exists",
     * which is a different question from "the site has a key for it" and from
     * "the registry lists image models for it" — all three have to hold before
     * an image can be generated.
     *
     * @return array<string,string>
     */
    private static function imageProviderKeys(): array {
        return [
            'openai' => 'openai_api_key',
            'gemini' => 'gemini_api_key',
        ];
    }

    /**
     * Image providers this site is configured for, best first.
     *
     * Anthropic never appears: it publishes no image generation API, so a
     * site holding only an Anthropic key gets an empty list here and callers
     * can say so plainly instead of failing at the moment of use.
     *
     * @return array<int,string>
     */
    public static function getConfiguredImageProviders(): array {
        $keys = self::imageProviderKeys();
        $configured = [];

        foreach (ModelRegistry::getImageProviders() as $provider) {
            if (!isset($keys[$provider])) {
                continue;
            }
            if (!empty(self::$config[$keys[$provider]] ?? '')) {
                $configured[] = $provider;
            }
        }

        return $configured;
    }

    /**
     * Can this site generate an image at all?
     *
     * @return bool
     */
    public static function canGenerateImages(): bool {
        return !empty(self::getConfiguredImageProviders());
    }

    /**
     * Which provider should serve a given image model?
     *
     * The model is the concrete thing being asked for, so it decides. Only
     * when no usable model is named does this fall back to whichever
     * configured provider offers the best image model.
     *
     * @param string $model Image model id, may be empty.
     * @return string|null Provider id, or null when the site cannot make images.
     */
    public static function resolveImageProvider(string $model = ''): ?string {
        $model = trim($model);

        if ($model !== '') {
            $provider = ModelRegistry::getProvider($model);

            if ($provider !== null && isset(self::imageProviderKeys()[$provider])) {
                return $provider;
            }

            // An id the registry has never seen: place it by family so a newly
            // released image model still routes rather than falling back to
            // whichever provider happens to be configured.
            $inferred = self::inferProviderFromModel($model);
            if ($inferred !== null && isset(self::imageProviderKeys()[$inferred])) {
                return $inferred;
            }

            if (strpos($model, 'dall-e') === 0 || strpos($model, 'gpt-image') === 0) {
                return 'openai';
            }

            if (strpos($model, 'imagen') === 0 || strpos($model, 'nano-banana') === 0) {
                return 'gemini';
            }
        }

        return self::getConfiguredImageProviders()[0] ?? null;
    }

    /**
     * Best image model for the site, given a preferred provider.
     *
     * @param string|null $provider Provider id, or null to pick the best configured one.
     * @return string|null
     */
    public static function getDefaultImageModel(?string $provider = null): ?string {
        $provider = $provider ?: (self::getConfiguredImageProviders()[0] ?? null);

        return $provider ? ModelRegistry::getPreferredImageModel($provider) : null;
    }

    /**
     * Get image provider
     *
     * @param string $provider Provider name; empty resolves to the best configured one
     * @return \AICore\Interfaces\ImageProviderInterface
     * @throws \Exception If provider not supported or configured
     */
    public static function getImageProvider(string $provider = ''): \AICore\Interfaces\ImageProviderInterface {

        if ($provider === '') {
            $provider = self::resolveImageProvider();
        }

        if ($provider === null || $provider === '') {
            throw new \Exception('No image provider is configured. Add an OpenAI or Google Gemini API key to generate images.');
        }

        $cache_key = "image_{$provider}";

        if (!isset(self::$providers[$cache_key])) {
            self::$providers[$cache_key] = self::createImageProvider($provider);
        }

        return self::$providers[$cache_key];
    }

    /**
     * Create text provider instance
     *
     * @param string $provider_name Provider name
     * @return \AICore\Interfaces\ProviderInterface
     * @throws \Exception If provider not supported or API key missing
     */
    private static function createTextProvider(string $provider_name): \AICore\Interfaces\ProviderInterface {

        switch ($provider_name) {
            case 'openai':
                $api_key = self::$config['openai_api_key'] ?? '';
                if (empty($api_key)) {
                    throw new \Exception('OpenAI API key not configured');
                }
                return new OpenAIProvider($api_key);

            case 'anthropic':
                $api_key = self::$config['anthropic_api_key'] ?? '';
                if (empty($api_key)) {
                    throw new \Exception('Anthropic API key not configured');
                }
                return new AnthropicProvider($api_key);

            case 'gemini':
                $api_key = self::$config['gemini_api_key'] ?? '';
                if (empty($api_key)) {
                    throw new \Exception('Gemini API key not configured');
                }
                return new GeminiProvider($api_key);

            default:
                throw new \Exception(\esc_html("Unsupported text provider: {$provider_name}"));
        }
    }
    
    /**
     * Create image provider instance
     * 
     * @param string $provider_name Provider name
     * @return \AICore\Interfaces\ImageProviderInterface
     * @throws \Exception If provider not supported or API key missing
     */
    private static function createImageProvider(string $provider_name): \AICore\Interfaces\ImageProviderInterface {

        switch ($provider_name) {
            case 'openai':
                $api_key = self::$config['openai_api_key'] ?? '';
                if (empty($api_key)) {
                    throw new \Exception('OpenAI API key not configured for image generation');
                }
                return new OpenAIImageProvider($api_key);

            case 'gemini':
                $api_key = self::$config['gemini_api_key'] ?? '';
                if (empty($api_key)) {
                    throw new \Exception('Gemini API key not configured for image generation');
                }
                return new GeminiImageProvider($api_key);

            default:
                throw new \Exception(\esc_html("Unsupported image provider: {$provider_name}"));
        }
    }
    
    /**
     * Send text generation request
     * Convenience method that automatically selects the right provider
     * 
     * @param string $model Model identifier
     * @param array $messages Messages array
     * @param array $options Request options
     * @return array Normalized response
     * @throws \Exception On errors
     */
    public static function sendTextRequest(string $model, array $messages, array $options = []): array {
        $provider = self::getTextProvider($model);
        $options['model'] = $model;
        return $provider->sendRequest($messages, $options);
    }
    
    /**
     * Generate image
     * Convenience method for image generation
     * 
     * The provider follows the model unless the caller names one that can
     * actually serve that model. Callers historically passed a bare 'openai'
     * default alongside whatever model the site had configured, which sent
     * Gemini image models to OpenAI's endpoint and made an OpenAI key look
     * mandatory for image generation. It is not.
     *
     * @param string $prompt Image prompt
     * @param array $options Image options
     * @param string $provider Provider name; empty follows the model
     * @return array Image response
     * @throws \Exception On errors
     */
    public static function generateImage(string $prompt, array $options = [], string $provider = ''): array {
        $model = isset($options['model']) ? (string) $options['model'] : '';
        $resolved = self::resolveImageProvider($model);

        if ($provider !== '' && ($model === '' || $resolved === null || $provider === $resolved)) {
            // An explicit provider is honoured only when it does not contradict
            // the model actually being requested.
            $resolved = $provider;
        }

        if ($resolved === null || $resolved === '') {
            throw new \Exception('No image provider is configured. Add an OpenAI or Google Gemini API key to generate images.');
        }

        if ($model === '') {
            $default = self::getDefaultImageModel($resolved);
            if ($default !== null) {
                $options['model'] = $default;
            }
        }

        $image_provider = self::getImageProvider($resolved);
        return $image_provider->generateImage($prompt, $options);
    }
    
    /**
     * Check if model is supported
     * 
     * @param string $model Model identifier
     * @return bool True if model is supported
     */
    public static function isModelSupported(string $model): bool {
        return ModelRegistry::modelExists($model);
    }
    
    /**
     * Get all available models
     * 
     * @return array Array of model identifiers
     */
    public static function getAvailableModels(): array {
        return ModelRegistry::getAllModels();
    }
    
    /**
     * Get models by provider
     * 
     * @param string $provider Provider name
     * @return array Array of model identifiers
     */
    public static function getModelsByProvider(string $provider): array {
        return ModelRegistry::getModelsByProvider($provider);
    }
    
    /**
     * Check provider configuration status
     * 
     * @return array Status of all providers
     */
    public static function getProviderStatus(): array {
        $status = [];

        foreach (ModelRegistry::getSupportedProviders() as $provider) {
            $key = self::$config[$provider . '_api_key'] ?? '';
            $status[$provider] = [
                'configured' => !empty($key),
                'api_key' => !empty($key) ? substr($key, 0, 7) . '...' . substr($key, -4) : 'Not set',
                'images' => ModelRegistry::providerSupportsImages($provider),
            ];
        }

        return $status;
    }
    
    /**
     * Get library version
     * 
     * @return string Version string
     */
    public static function getVersion(): string {
        return self::VERSION;
    }
    
    /**
     * Reset all providers (useful for testing)
     * 
     * @return void
     */
    public static function reset(): void {
        self::$providers = [];
        self::$config = [];
    }
    
    /**
     * Extract content from response
     * Convenience method for getting text content
     * 
     * @param array $response Normalized response
     * @return string Content text
     */
    public static function extractContent(array $response): string {
        return ResponseNormalizer::extractContent($response);
    }
    
    /**
     * Extract usage information from response
     * 
     * @param array $response Normalized response
     * @return array Usage statistics
     */
    public static function extractUsage(array $response): array {
        return ResponseNormalizer::extractUsage($response);
    }
    
    /**
     * Check if response has error
     * 
     * @param array $response Response to check
     * @return bool True if response contains error
     */
    public static function hasError(array $response): bool {
        return ResponseNormalizer::hasError($response);
    }
    
    /**
     * Extract error message from response
     * 
     * @param array $response Response with error
     * @return string Error message
     */
    public static function extractError(array $response): string {
        return ResponseNormalizer::extractError($response);
    }
}
