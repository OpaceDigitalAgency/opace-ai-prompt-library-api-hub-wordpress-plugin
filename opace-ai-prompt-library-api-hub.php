<?php
/**
 * Plugin Name: Opace AI Prompt Library & API Integration Hub for OpenAI, Claude & Gemini
 * Plugin URI: https://opace.agency/services/web-design/wordpress-development/
 * Description: Connect WordPress plugins to OpenAI, Anthropic Claude and Google Gemini with shared credentials, live models, prompts and usage records.
 * Version: 1.0.17
 * Author: Opace Digital Agency
 * Author URI: https://opace.agency
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: opace-ai-prompt-library-api-hub
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Tags: ai, openai, claude, gemini, api, integration, artificial intelligence
 *
 * @package AI_Core
 * @version 1.0.17
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants. Guarded because an add-on bundling this library can
// already be loaded when Opace AI Hub is activated, which otherwise emits a
// "Constant already defined" warning immediately before the redeclare fatal.
if (!defined('AI_CORE_VERSION')) {
    define('AI_CORE_VERSION', '1.0.17');
}
if (!defined('AI_CORE_PLUGIN_FILE')) {
    define('AI_CORE_PLUGIN_FILE', __FILE__);
    define('AI_CORE_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('AI_CORE_PLUGIN_URL', plugin_dir_url(__FILE__));
    define('AI_CORE_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

// Minimum PHP version check
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . esc_html__('Opace AI Hub:', 'opace-ai-prompt-library-api-hub') . '</strong> ';
        printf(
            /* translators: %s: current PHP version. */
            esc_html__('This plugin requires PHP 7.4 or higher. You are running PHP %s.', 'opace-ai-prompt-library-api-hub'),
            esc_html(PHP_VERSION)
        );
        echo '</p></div>';
    });
    return;
}

/**
 * Main Opace AI Hub Plugin Class
 * 
 * Handles plugin initialization, activation, and deactivation
 * Provides centralized AI provider management for WordPress
 */
class AI_Core_Plugin {
    
    /**
     * Plugin instance
     * 
     * @var AI_Core_Plugin
     */
    private static $instance = null;
    
    /**
     * Get plugin instance (Singleton pattern)
     * 
     * @return AI_Core_Plugin
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
     * Initialize the plugin
     * 
     * @return void
     */
    private function init() {
        // Load Opace AI Hub library
        $this->load_ai_core_library();
        
        // Load plugin files
        $this->load_includes();
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Load Opace AI Hub library
     *
     * @return void
     */
    private function load_ai_core_library() {
        $ai_core_autoload = AI_CORE_PLUGIN_DIR . 'lib/autoload.php';

        if (file_exists($ai_core_autoload)) {
            require_once $ai_core_autoload;
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>' . esc_html__('Opace AI Hub:', 'opace-ai-prompt-library-api-hub') . '</strong> ';
                esc_html_e('Core library not found. Please reinstall the plugin.', 'opace-ai-prompt-library-api-hub');
                echo '</p></div>';
            });
        }
    }
    
    /**
     * Load plugin includes
     *
     * @return void
     */
    private function load_includes() {
        // Core functionality
        require_once AI_CORE_PLUGIN_DIR . 'includes/class-ai-core-pricing.php';
        require_once AI_CORE_PLUGIN_DIR . 'includes/class-ai-core-model-defaults.php';
        require_once AI_CORE_PLUGIN_DIR . 'includes/class-ai-core-settings.php';
        require_once AI_CORE_PLUGIN_DIR . 'includes/class-ai-core-wordpress-ai-client.php';
        require_once AI_CORE_PLUGIN_DIR . 'includes/class-ai-core-api.php';
        require_once AI_CORE_PLUGIN_DIR . 'includes/class-ai-core-validator.php';
        require_once AI_CORE_PLUGIN_DIR . 'includes/class-ai-core-stats.php';

        // Admin functionality
        if (is_admin()) {
            require_once AI_CORE_PLUGIN_DIR . 'admin/class-ai-core-admin.php';
            require_once AI_CORE_PLUGIN_DIR . 'admin/class-ai-core-ajax.php';
            require_once AI_CORE_PLUGIN_DIR . 'admin/class-ai-core-addons.php';
            require_once AI_CORE_PLUGIN_DIR . 'admin/class-ai-core-prompt-library.php';
        }
    }
    
    /**
     * Initialize WordPress hooks
     * 
     * @return void
     */
    private function init_hooks() {
        // Activation, deactivation, and uninstall hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Plugin loaded hook
        add_action('plugins_loaded', array($this, 'plugins_loaded'));

        // Core passes Connector credentials to registered AI providers at
        // init priority 20. Run afterwards so a Connector always takes
        // precedence and a Hub key is supplied only when WordPress has none.
        add_action('init', array('AI_Core_WordPress_AI_Client', 'bridge_hub_credentials'), 30);
        
        // Admin init
        if (is_admin()) {
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

            // Initialize admin class (which will add its own admin_menu hook)
            AI_Core_Admin::get_instance();

            // Initialize add-ons class (registers AJAX handlers)
            AI_Core_Addons::get_instance();
        }
        
        // Add settings link on plugins page
        add_filter('plugin_action_links_' . AI_CORE_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }
    
    /**
     * Plugin activation
     *
     * @return void
     */
    public function activate() {
        // Set default options
        $default_settings = array(
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
        );

        add_option('ai_core_settings', $default_settings);
        add_option('ai_core_stats', array());
        add_option('ai_core_version', AI_CORE_VERSION);

        // Create database tables for Prompt Library
        $this->create_prompt_library_tables();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables for Prompt Library
     *
     * @return void
     */
    private function create_prompt_library_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Prompt groups table
        $groups_table = $wpdb->prefix . 'ai_core_prompt_groups';
        $groups_sql = "CREATE TABLE {$groups_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY name (name)
        ) {$charset_collate};";

        // Prompts table
        $prompts_table = $wpdb->prefix . 'ai_core_prompts';
        $prompts_sql = "CREATE TABLE {$prompts_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            content longtext NOT NULL,
            group_id bigint(20) unsigned DEFAULT NULL,
            provider varchar(50) DEFAULT '',
            type varchar(50) DEFAULT 'text',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY group_id (group_id),
            KEY type (type),
            KEY provider (provider)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($groups_sql);
        dbDelta($prompts_sql);

        // Add default group if none exists
        // Direct access is required for the plugin-owned custom table during activation.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $count = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $groups_table));
        if ($count == 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- plugin-owned custom table.
            $wpdb->insert(
                $groups_table,
                array(
                    'name' => __('General', 'opace-ai-prompt-library-api-hub'),
                    'description' => __('General purpose prompts', 'opace-ai-prompt-library-api-hub'),
                ),
                array('%s', '%s')
            );
        }
    }
    
    /**
     * Plugin deactivation
     * 
     * @return void
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugins loaded hook
     * 
     * @return void
     */
    public function plugins_loaded() {
        // No load_plugin_textdomain() call: WordPress has auto-loaded
        // translations for plugins hosted on wordpress.org since 4.6, and the
        // explicit call is flagged as discouraged by Plugin Check.

        // Initialize Opace AI Hub library with saved settings
        $this->initialize_ai_core();
    }
    
    /**
     * Initialize Opace AI Hub library with saved settings
     * 
     * @return void
     */
    private function initialize_ai_core() {
        $settings = get_option('ai_core_settings', array());
        
        // Initialize Opace AI Hub with all configured API keys
        if (class_exists('AICore\\AICore')) {
            $config = array();
            
            if (!empty($settings['openai_api_key'])) {
                $config['openai_api_key'] = $settings['openai_api_key'];
            }
            
            if (!empty($settings['anthropic_api_key'])) {
                $config['anthropic_api_key'] = $settings['anthropic_api_key'];
            }
            
            if (!empty($settings['gemini_api_key'])) {
                $config['gemini_api_key'] = $settings['gemini_api_key'];
            }
            
            // Initialize Opace AI Hub
            \AICore\AICore::init($config);
        }
    }
    
    /**
     * Admin init hook
     *
     * @return void
     */
    public function admin_init() {
        // Initialize settings
        AI_Core_Settings::get_instance();
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function admin_enqueue_scripts($hook) {
        // The menu is visible throughout wp-admin, so its alignment rule must be too.
        wp_enqueue_style(
            'ai-core-admin-menu',
            AI_CORE_PLUGIN_URL . 'assets/css/admin-menu.css',
            array(),
            AI_CORE_VERSION
        );

        // Only load on Opace AI Hub admin pages and AI-Imagen pages (bundled addon)
        if (strpos($hook, 'ai-core') === false && strpos($hook, 'ai-imagen') === false && strpos($hook, 'opace-ai-prompt-library-api-hub') === false) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'ai-core-admin',
            AI_CORE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AI_CORE_VERSION
        );

        // Apply the stored theme choice before first paint. The preference is
        // shared with AI-Scribe's wizard toggle (localStorage key
        // "ai-scribe-theme"), so both plugins follow one setting; without a
        // stored choice the OS preference decides. Registered as a src-less
        // handle printed in the head: it must run before the stylesheet paints,
        // so it cannot ride on the footer-loaded admin script.
        $theme_boot = "try{var t=window.localStorage.getItem('ai-scribe-theme');"
            . "if(!t&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){t='dark';}"
            . "if(t){document.documentElement.setAttribute('data-theme',t);"
            . "document.documentElement.setAttribute('data-ai-scribe-theme',t);}}catch(e){}";
        wp_register_script('ai-core-theme-boot', false, array(), AI_CORE_VERSION, false);
        wp_enqueue_script('ai-core-theme-boot');
        wp_add_inline_script('ai-core-theme-boot', $theme_boot);

        // Enqueue scripts
        wp_enqueue_script(
            'ai-core-admin',
            AI_CORE_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            AI_CORE_VERSION,
            true
        );

        $settings = get_option('ai_core_settings', array());
        $api = AI_Core_API::get_instance();
        $configured_providers = $api->get_configured_providers();
        $default_provider = $settings['default_provider'] ?? '';
        $provider_labels = array(
            'openai' => __('OpenAI', 'opace-ai-prompt-library-api-hub'),
            'anthropic' => __('Anthropic Claude', 'opace-ai-prompt-library-api-hub'),
            'gemini' => __('Google Gemini', 'opace-ai-prompt-library-api-hub'),
        );

        $provider_models_map = array();
        foreach ($provider_labels as $provider_key => $provider_label) {
            if (in_array($provider_key, $configured_providers, true)) {
                $provider_models_map[$provider_key] = array_values(array_filter(
                    $api->get_available_models($provider_key),
                    static function ($model) use ($provider_key) {
                        return \AICore\Registry\ModelRegistry::isTextGenerationModel((string) $model, $provider_key);
                    }
                ));
            } else {
                $provider_models_map[$provider_key] = array();
            }
        }

        $provider_selected_models = isset($settings['provider_models']) && is_array($settings['provider_models']) ? $settings['provider_models'] : array();
        $provider_options = isset($settings['provider_options']) && is_array($settings['provider_options']) ? $settings['provider_options'] : array();
        $provider_metadata = class_exists('AICore\\Registry\\ModelRegistry') ? \AICore\Registry\ModelRegistry::exportProviderMetadata() : array();
        $provider_validation = array();
        foreach (array_keys($provider_labels) as $provider_key) {
            $provider_validation[$provider_key] = AI_Core_Settings::get_credential_validation_status($provider_key, $settings);
        }

        // Prepare localization data
        $localize_data = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ai_core_admin'),
            'strings' => array(
                'testing' => __('Testing...', 'opace-ai-prompt-library-api-hub'),
                'success' => __('Success!', 'opace-ai-prompt-library-api-hub'),
                'error' => __('Error', 'opace-ai-prompt-library-api-hub'),
                'validating' => __('Validating...', 'opace-ai-prompt-library-api-hub'),
                'rememberToSave' => __('Remember to click Save to store this key.', 'opace-ai-prompt-library-api-hub'),
                'confirmResetStats' => __('Are you sure you want to reset all usage statistics? This cannot be undone.', 'opace-ai-prompt-library-api-hub'),
                'resetting' => __('Resetting...', 'opace-ai-prompt-library-api-hub'),
                'pasteKeyToTest' => __('A key is saved for this provider. Paste it again to re-test it.', 'opace-ai-prompt-library-api-hub'),
                'loadingModels' => __('Loading models...', 'opace-ai-prompt-library-api-hub'),
                'noModels' => __('No models available', 'opace-ai-prompt-library-api-hub'),
                'errorLoadingModels' => __('Failed to load models.', 'opace-ai-prompt-library-api-hub'),
                'placeholderSelectModel' => __('-- Select Model --', 'opace-ai-prompt-library-api-hub'),
                /* translators: %d: number of models available. */
                'availableModels' => __('Available Models (%d):', 'opace-ai-prompt-library-api-hub'),
                'missingKey' => __('Enter an API key to load models.', 'opace-ai-prompt-library-api-hub'),
                'awaitingKey' => __('Waiting for key...', 'opace-ai-prompt-library-api-hub'),
                'keyTooShort' => __('Continue pasting your key to validate.', 'opace-ai-prompt-library-api-hub'),
                'saving' => __('Saving key...', 'opace-ai-prompt-library-api-hub'),
                'saved' => __('Saved and validated.', 'opace-ai-prompt-library-api-hub'),
                'alreadySaved' => __('This key is already saved and validated.', 'opace-ai-prompt-library-api-hub'),
                'enterKeyPlaceholder' => __('Enter your API key', 'opace-ai-prompt-library-api-hub'),
                'refreshing' => __('Refreshing models...', 'opace-ai-prompt-library-api-hub'),
                'refreshingPricing' => __('Refreshing pricing...', 'opace-ai-prompt-library-api-hub'),
                'retentionKeep' => __('Current choice: keep all Opace AI Hub data after deletion.', 'opace-ai-prompt-library-api-hub'),
                'retentionDelete' => __('Current choice: permanently remove all Opace AI Hub data when deleted.', 'opace-ai-prompt-library-api-hub'),
                'modelsLoaded' => __('Models updated. Credential validation status unchanged.', 'opace-ai-prompt-library-api-hub'),
                'cleared' => __('API key cleared.', 'opace-ai-prompt-library-api-hub'),
                'connected' => __('Connected', 'opace-ai-prompt-library-api-hub'),
                'configuredViaWordPress' => __('Configured via WordPress', 'opace-ai-prompt-library-api-hub'),
                'credentialValid' => __('Saved and validated', 'opace-ai-prompt-library-api-hub'),
                'credentialInvalid' => __('Saved but invalid', 'opace-ai-prompt-library-api-hub'),
                'credentialUntested' => __('Saved, not yet tested', 'opace-ai-prompt-library-api-hub'),
                'credentialValidDetail' => __('Use Test Key anytime to check it again.', 'opace-ai-prompt-library-api-hub'),
                'credentialInvalidDetail' => __('Enter a working key and test it again before generating.', 'opace-ai-prompt-library-api-hub'),
                'credentialUntestedDetail' => __('Use Test Key when you want to confirm it.', 'opace-ai-prompt-library-api-hub'),
                'awaiting' => __('Awaiting Provider', 'opace-ai-prompt-library-api-hub'),
                'addKeyFirst' => __('Configure a provider to load models', 'opace-ai-prompt-library-api-hub'),
                'testSelectProvider' => __('Select a provider first', 'opace-ai-prompt-library-api-hub'),
                'promptRequired' => __('Please enter a prompt.', 'opace-ai-prompt-library-api-hub'),
                'providerRequired' => __('Please select a provider.', 'opace-ai-prompt-library-api-hub'),
                'modelRequired' => __('Please select a model.', 'opace-ai-prompt-library-api-hub'),
                'runningPrompt' => __('Running prompt...', 'opace-ai-prompt-library-api-hub'),
                'confirmClear' => __('Are you sure you want to clear this API key?', 'opace-ai-prompt-library-api-hub'),
                'savedPlaceholder' => __('Saved key (hidden)', 'opace-ai-prompt-library-api-hub'),
                'wordpressPlaceholder' => __('Managed by WordPress Connectors', 'opace-ai-prompt-library-api-hub'),
                'wordpressManaged' => __('WordPress Connector is configured; no second key is needed.', 'opace-ai-prompt-library-api-hub'),
                'clearKey' => __('Clear', 'opace-ai-prompt-library-api-hub'),
                'testKey' => __('Test Key', 'opace-ai-prompt-library-api-hub'),
                'noTuningParameters' => __('No adjustable parameters for this model.', 'opace-ai-prompt-library-api-hub'),
                'selectModelFirst' => __('Select a model to view available settings.', 'opace-ai-prompt-library-api-hub'),
                'toggleTheme' => __('Toggle dark mode', 'opace-ai-prompt-library-api-hub'),
                'generatedImage' => __('Generated image', 'opace-ai-prompt-library-api-hub'),
                'imageGeneration' => __('Image Generation', 'opace-ai-prompt-library-api-hub'),
                /* translators: %s: reason image generation is unavailable. */
                'imageGenerationUnavailable' => __('Image Generation (%s)', 'opace-ai-prompt-library-api-hub'),
                'invalidAddonAction' => __('Invalid add-on action.', 'opace-ai-prompt-library-api-hub'),
                'installingAddon' => __('Installing...', 'opace-ai-prompt-library-api-hub'),
                'activating' => __('Activating...', 'opace-ai-prompt-library-api-hub'),
                'active' => __('Active', 'opace-ai-prompt-library-api-hub'),
                'addonReady' => __('Add-on ready.', 'opace-ai-prompt-library-api-hub'),
                'addonActionFailed' => __('The add-on action failed.', 'opace-ai-prompt-library-api-hub'),
                /* translators: %s: add-on action error detail. */
                'addonActionFailedDetail' => __('The add-on action failed: %s', 'opace-ai-prompt-library-api-hub'),
                'activationFailed' => __('Activation failed.', 'opace-ai-prompt-library-api-hub'),
                /* translators: %s: activation error detail. */
                'activationFailedDetail' => __('Activation failed: %s', 'opace-ai-prompt-library-api-hub'),
                'configurationError' => __('Configuration error. Please refresh the page.', 'opace-ai-prompt-library-api-hub'),
                'newGroup' => __('New Group', 'opace-ai-prompt-library-api-hub'),
                'editGroup' => __('Edit Group', 'opace-ai-prompt-library-api-hub'),
                'failedLoadGroup' => __('Failed to load group data.', 'opace-ai-prompt-library-api-hub'),
                'networkLoadGroup' => __('Network error while loading the group.', 'opace-ai-prompt-library-api-hub'),
                'enterGroupName' => __('Please enter a group name.', 'opace-ai-prompt-library-api-hub'),
                'groupSaved' => __('Group saved successfully.', 'opace-ai-prompt-library-api-hub'),
                /* translators: %s: error detail. */
                'errorDetail' => __('Error: %s', 'opace-ai-prompt-library-api-hub'),
                /* translators: %s: error detail. */
                'saveGroupError' => __('Error saving group: %s', 'opace-ai-prompt-library-api-hub'),
                'confirmDeleteGroup' => __('Are you sure you want to delete this group? Prompts in this group will not be deleted.', 'opace-ai-prompt-library-api-hub'),
                'groupDeleted' => __('Group deleted successfully.', 'opace-ai-prompt-library-api-hub'),
                /* translators: %s: error detail. */
                'deleteGroupError' => __('Error deleting group: %s', 'opace-ai-prompt-library-api-hub'),
                'newPrompt' => __('New Prompt', 'opace-ai-prompt-library-api-hub'),
                'editPrompt' => __('Edit Prompt', 'opace-ai-prompt-library-api-hub'),
                'enterTitleContent' => __('Please enter a title and content.', 'opace-ai-prompt-library-api-hub'),
                'promptSaved' => __('Prompt saved successfully.', 'opace-ai-prompt-library-api-hub'),
                /* translators: %s: error detail. */
                'savePromptError' => __('Error saving prompt: %s', 'opace-ai-prompt-library-api-hub'),
                'noPromptsGroup' => __('No prompts in this group', 'opace-ai-prompt-library-api-hub'),
                'dragPromptsHere' => __('Drag prompts here or click + to add', 'opace-ai-prompt-library-api-hub'),
                'promptMoved' => __('Prompt moved successfully.', 'opace-ai-prompt-library-api-hub'),
                'failedMovePrompt' => __('Failed to move prompt.', 'opace-ai-prompt-library-api-hub'),
                'networkMovePrompt' => __('Network error while moving the prompt.', 'opace-ai-prompt-library-api-hub'),
                'confirmDeletePrompt' => __('Are you sure you want to delete this prompt?', 'opace-ai-prompt-library-api-hub'),
                'promptDeleted' => __('Prompt deleted successfully.', 'opace-ai-prompt-library-api-hub'),
                /* translators: %s: error detail. */
                'deletePromptError' => __('Error deleting prompt: %s', 'opace-ai-prompt-library-api-hub'),
                'running' => __('Running...', 'opace-ai-prompt-library-api-hub'),
                'run' => __('Run', 'opace-ai-prompt-library-api-hub'),
                'promptNotFound' => __('Prompt not found.', 'opace-ai-prompt-library-api-hub'),
                'failedLoadPrompt' => __('Failed to load prompt.', 'opace-ai-prompt-library-api-hub'),
                /* translators: %s: network error detail. */
                'networkErrorDetail' => __('Network error: %s', 'opace-ai-prompt-library-api-hub'),
                'generatingResponse' => __('Generating response...', 'opace-ai-prompt-library-api-hub'),
                'unknownError' => __('Unknown error', 'opace-ai-prompt-library-api-hub'),
                'networkError' => __('Network error', 'opace-ai-prompt-library-api-hub'),
                'enterPromptContent' => __('Please enter prompt content.', 'opace-ai-prompt-library-api-hub'),
                'csvExportComplete' => __('CSV export completed.', 'opace-ai-prompt-library-api-hub'),
                'promptsExported' => __('Prompts exported successfully!', 'opace-ai-prompt-library-api-hub'),
                'exportError' => __('Error exporting prompts.', 'opace-ai-prompt-library-api-hub'),
                /* translators: %s: network error detail. */
                'networkExportError' => __('Network error exporting prompts: %s', 'opace-ai-prompt-library-api-hub'),
                'selectFile' => __('Please select a file.', 'opace-ai-prompt-library-api-hub'),
                'importSuccessful' => __('Import successful!', 'opace-ai-prompt-library-api-hub'),
                'importError' => __('Error importing prompts.', 'opace-ai-prompt-library-api-hub'),
                /* translators: %s: network error detail. */
                'networkImportError' => __('Network error importing prompts: %s', 'opace-ai-prompt-library-api-hub'),
                /* translators: %s: JSON parsing error detail. */
                'invalidJson' => __('Invalid JSON file: %s', 'opace-ai-prompt-library-api-hub'),
                'confirmDeleteAll' => __('Are you sure you want to delete ALL prompts and groups? This action cannot be undone!', 'opace-ai-prompt-library-api-hub'),
                'confirmDeleteAllAgain' => __('This will permanently delete everything in your Prompt Library. Are you absolutely sure?', 'opace-ai-prompt-library-api-hub'),
                'allPromptsDeleted' => __('All prompts deleted successfully.', 'opace-ai-prompt-library-api-hub'),
                'deleteAllError' => __('Error deleting prompts.', 'opace-ai-prompt-library-api-hub'),
                /* translators: %s: network error detail. */
                'networkDeleteAllError' => __('Network error deleting prompts: %s', 'opace-ai-prompt-library-api-hub'),
            ),
            'providers' => array(
                'configured' => $configured_providers,
                'default' => $default_provider,
                'labels' => $provider_labels,
                'models' => $provider_models_map,
                'selectedModels' => $provider_selected_models,
                'options' => $provider_options,
                'meta' => $provider_metadata,
                'validation' => $provider_validation,
                'sources' => array_combine(
                    array_keys($provider_labels),
                    array_map(array($api, 'get_provider_source'), array_keys($provider_labels))
                ),
            ),
        );

        // Localize to ai-core-admin script (always loaded)
        wp_localize_script('ai-core-admin', 'aiCoreAdmin', $localize_data);

        // Enqueue Prompt Library assets on its page
        if ($hook === 'opace-ai-hub_page_ai-core-prompt-library') {
            // Enqueue jQuery UI for drag and drop
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('jquery-ui-droppable');

            wp_enqueue_style(
                'ai-core-prompt-library',
                AI_CORE_PLUGIN_URL . 'assets/css/prompt-library.css',
                array('ai-core-admin'),
                AI_CORE_VERSION
            );

            wp_enqueue_script(
                'ai-core-prompt-library',
                AI_CORE_PLUGIN_URL . 'assets/js/prompt-library.js',
                array('jquery', 'jquery-ui-sortable', 'jquery-ui-droppable', 'ai-core-admin'),
                AI_CORE_VERSION,
                true
            );
        }
    }
    
    /**
     * Add action links to plugins page
     * 
     * @param array $links Existing links
     * @return array Modified links
     */
    public function add_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=ai-core-settings') . '">' . __('Settings', 'opace-ai-prompt-library-api-hub') . '</a>';
        $addons_link = '<a href="' . admin_url('admin.php?page=ai-core-addons') . '">' . __('Add-ons', 'opace-ai-prompt-library-api-hub') . '</a>';
        $review_link = '<a href="' . esc_url('https://wordpress.org/support/plugin/opace-ai-prompt-library-api-hub/reviews/#new-post') . '" target="_blank" rel="noopener noreferrer">' . __('Leave a Review', 'opace-ai-prompt-library-api-hub') . '</a>';

        array_unshift($links, $settings_link, $addons_link, $review_link);
        
        return $links;
    }
}

// Initialize the plugin
AI_Core_Plugin::get_instance();
