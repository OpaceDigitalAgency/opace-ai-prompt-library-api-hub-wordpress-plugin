<?php
/**
 * Opace AI Hub Add-ons Class
 * 
 * Handles add-ons library and discovery
 * 
 * @package AI_Core
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Opace AI Hub Add-ons Class
 * 
 * Manages add-ons library
 */
class AI_Core_Addons {
    
    /**
     * Class instance
     * 
     * @var AI_Core_Addons
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return AI_Core_Addons
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
        // Private constructor for singleton
        add_action('wp_ajax_ai_core_activate_addon', array($this, 'ajax_activate_addon'));
        add_action('wp_ajax_ai_core_install_addon', array($this, 'ajax_install_addon'));
    }
    
    /**
     * Get available add-ons
     *
     * @return array List of add-ons
     */
    public function get_addons() {
        // Ensure plugin functions are available
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $addons = array(
            array(
                'slug' => 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard',
                'name' => 'AI-Scribe',
                'description' => 'SEO content creator and humaniser. Generate optimised articles and long-form content through an 11-step wizard or a single Express request. Uses whichever provider you have configured here: OpenAI (GPT-5 family), Anthropic Claude (including the Claude 5 family) or Google Gemini (3.x family), with per-section images, editable prompts, and meta for Yoast, Rank Math, AIOSEO and SEOPress.',
                'author' => 'Opace Digital Agency',
                'version' => '',
                /* translators: %s: the Opace AI Hub version number this add-on needs, e.g. 0.7.7. */
                'requires' => sprintf(__('Opace AI Hub %s or later', 'opace-ai-prompt-library-api-hub'), AI_CORE_VERSION),
                'icon' => 'dashicons-edit',
                'url' => 'https://wordpress.org/plugins/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/',
                'plugin_file' => 'ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/article_builder.php',
                'wordpress_org' => true,
                'available' => true,
                'open_url' => admin_url('admin.php?page=ai-scribe'),
            ),
            array(
                'slug' => 'ai-imagen',
                'name' => 'AI-Imagen',
                'description' => 'Image generation inside WordPress, with automatic media library integration. Draws on the image models your key grants: OpenAI GPT Image (the successor to DALL-E) and Google\'s Gemini image models, including Gemini 3 Pro Image, Gemini Flash Image and the Imagen family. Model lists come from your own provider account, so newly released image models appear without a plugin update.',
                'author' => 'Opace Digital Agency',
                'version' => '0.6.6',
                /* translators: %s: the Opace AI Hub version number this add-on needs, e.g. 0.7.7. */
                'requires' => sprintf(__('Opace AI Hub %s or later', 'opace-ai-prompt-library-api-hub'), AI_CORE_VERSION),
                'icon' => 'dashicons-format-image',
                'url' => 'https://opace.agency/services/web-design/wordpress-development/',
                'bundled' => true,
                // Not verified end to end yet, so it is shown but not installable.
                'available' => false,
                'unavailable_reason' => __('In testing and not yet available to install.', 'opace-ai-prompt-library-api-hub'),
                'plugin_file' => 'ai-imagen/ai-imagen.php',
            ),
            array(
                'slug' => 'ai-stats',
                'name' => 'AI-Stats',
                'description' => 'Dynamic SEO content modules with 6 switchable modes. Generates fresh, data-driven content from real-time web sources and any text model configured here, including the GPT-5, Claude and Gemini 3.x families. Built for authority and trust signals.',
                'author' => 'Opace Digital Agency',
                'version' => '0.8.2',
                /* translators: %s: the Opace AI Hub version number this add-on needs, e.g. 0.7.7. */
                'requires' => sprintf(__('Opace AI Hub %s or later', 'opace-ai-prompt-library-api-hub'), AI_CORE_VERSION),
                'icon' => 'dashicons-chart-bar',
                'url' => 'https://opace.agency/services/web-design/wordpress-development/',
                'bundled' => true,
                // Not verified end to end yet, so it is shown but not installable.
                'available' => false,
                'unavailable_reason' => __('In testing and not yet available to install.', 'opace-ai-prompt-library-api-hub'),
                'plugin_file' => 'ai-stats/ai-stats.php',
            ),
            array(
                'slug' => 'wp-ai-pulse',
                'name' => 'AI-Pulse',
                'description' => 'Trend analysis with Google Gemini search grounding, using the current Gemini 3.x models your key grants. Generates crawlable, static HTML content for service pages across 11 analysis modes including trends, FAQs, statistics, forecasts and local insights.',
                'author' => 'Opace Digital Agency',
                'version' => '1.0.8',
                /* translators: %s: the Opace AI Hub version number this add-on needs, e.g. 0.7.7. */
                'requires' => sprintf(__('Opace AI Hub %s or later', 'opace-ai-prompt-library-api-hub'), AI_CORE_VERSION),
                'icon' => 'dashicons-analytics',
                'url' => 'https://opace.agency/services/web-design/wordpress-development/',
                'bundled' => true,
                // Not verified end to end yet, so it is shown but not installable.
                'available' => false,
                'unavailable_reason' => __('In testing and not yet available to install.', 'opace-ai-prompt-library-api-hub'),
                'plugin_file' => 'wp-ai-pulse/ai-pulse.php',
            ),
        );

        // Plugin headers are the source of truth for a version number: what the
        // site actually has installed first, then the bundled copy. A
        // hand-maintained number here is only ever the last resort.
        foreach ($addons as $index => $addon) {
            $plugin_file = $this->get_installed_addon_file($addon);
            $version = $this->get_installed_addon_version($plugin_file);

            $addons[$index]['plugin_file'] = $plugin_file !== '' ? $plugin_file : ($addon['plugin_file'] ?? '');
            $addons[$index]['installed'] = $plugin_file !== '';
            $addons[$index]['active'] = $plugin_file !== '' && $this->is_plugin_file_active($plugin_file);

            if ($version === '' && !empty($addon['bundled'])) {
                $version = $this->get_bundled_addon_version($addon['slug'], $addon['plugin_file']);
            }

            if ($version === '' && !empty($addon['wordpress_org'])) {
                $version = $this->get_wordpress_org_version($addon['slug']);
            }

            if ($version !== '') {
                $addons[$index]['version'] = $version;
            }
        }

        return $addons;
    }

    /**
     * Read an installed add-on's version from the plugin list
     *
     * @param string $slug Plugin slug
     * @return string Version string, or an empty string when not installed
     */
    private function get_installed_addon_version($plugin_file) {
        if ($plugin_file === '') {
            return '';
        }

        $plugins = get_plugins();
        return !empty($plugins[$plugin_file]['Version']) ? trim($plugins[$plugin_file]['Version']) : '';
    }

    /**
     * Read a bundled add-on's version straight from its plugin header
     *
     * @param string $slug Plugin slug
     * @param string $plugin_file Plugin file path, relative to the plugins directory
     * @return string Version string, or an empty string when it cannot be read
     */
    private function get_bundled_addon_version($slug, $plugin_file) {
        $basename = basename($plugin_file);
        $path = AI_CORE_PLUGIN_DIR . 'bundled-addons/' . $slug . '/' . $basename;

        if (!is_readable($path)) {
            return '';
        }

        $data = get_file_data($path, array('Version' => 'Version'));

        return isset($data['Version']) ? trim($data['Version']) : '';
    }

    /**
     * Check if plugin is installed
     * 
     * @param string $slug Plugin slug
     * @return bool True if installed
     */
    private function get_installed_addon_file($addon) {
        $plugins = get_plugins();

        if (!empty($addon['plugin_file']) && isset($plugins[$addon['plugin_file']])) {
            return $addon['plugin_file'];
        }

        foreach ($plugins as $plugin_file => $plugin_data) {
            if (!empty($plugin_data['TextDomain']) && $addon['slug'] === $plugin_data['TextDomain']) {
                return $plugin_file;
            }
        }

        return '';
    }

    /**
     * Check whether an exact plugin file is active.
     *
     * @param string $plugin_file Plugin file relative to the plugins directory.
     * @return bool True if active
     */
    private function is_plugin_file_active($plugin_file) {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (is_plugin_active($plugin_file)) {
            return true;
        }

        return is_multisite() && function_exists('is_plugin_active_for_network') && is_plugin_active_for_network($plugin_file);
    }

    /**
     * Read the current public version from WordPress.org.
     *
     * @param string $slug WordPress.org plugin slug.
     * @return string Version string, or an empty string when unavailable.
     */
    private function get_wordpress_org_version($slug) {
        if (!function_exists('plugins_api')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        $data = plugins_api('plugin_information', array(
            'slug' => $slug,
            'fields' => array('sections' => false),
        ));

        return !is_wp_error($data) && !empty($data->version) ? (string) $data->version : '';
    }

    /**
     * Resolve one configured add-on by its exact slug.
     *
     * @param string $slug Add-on slug.
     * @return array|null Add-on data or null.
     */
    private function get_addon_by_slug($slug) {
        foreach ($this->get_addons() as $addon) {
            if ($addon['slug'] === $slug) {
                return $addon;
            }
        }

        return null;
    }
    
    /**
     * Render add-ons page
     *
     * @return void
     */
    public function render_addons_page() {
        $addons = $this->get_addons();
        
        ?>
        <div class="wrap">
            <?php AI_Core_Admin::render_page_brand(__('Opace AI Hub Add-ons', 'opace-ai-prompt-library-api-hub')); ?>
            
            <p class="description">
                <?php esc_html_e('Extend Opace AI Hub functionality with these powerful add-on plugins. All add-ons automatically use your configured API keys from Opace AI Hub.', 'opace-ai-prompt-library-api-hub'); ?>
            </p>
            
            <h2 class="screen-reader-text"><?php esc_html_e('Available add-ons', 'opace-ai-prompt-library-api-hub'); ?></h2>

            <div class="ai-core-addons-grid">
                <?php foreach ($addons as $addon): ?>
                    <div class="ai-core-addon-card <?php echo $addon['active'] ? 'active' : ''; ?>">
                        <div class="addon-icon">
                            <span class="dashicons <?php echo esc_attr($addon['icon']); ?>"></span>
                        </div>
                        <div class="addon-content">
                            <h3><?php echo esc_html($addon['name']); ?></h3>
                            <p class="addon-description"><?php echo esc_html($addon['description']); ?></p>
                            <div class="addon-meta">
                                <span class="addon-author"><?php echo esc_html__('By', 'opace-ai-prompt-library-api-hub') . ' ' . esc_html($addon['author']); ?></span>
                                <span class="addon-version"><?php echo esc_html__('Version', 'opace-ai-prompt-library-api-hub') . ' ' . esc_html($addon['version']); ?></span>
                            </div>
                            <div class="addon-requires">
                                <span class="dashicons dashicons-info"></span>
                                <?php echo esc_html__('Requires:', 'opace-ai-prompt-library-api-hub') . ' ' . esc_html($addon['requires']); ?>
                            </div>
                        </div>
                        <div class="addon-actions">
                            <?php if ($addon['active']): ?>
                                <?php if (!empty($addon['open_url'])): ?>
                                    <a class="button button-primary" href="<?php echo esc_url($addon['open_url']); ?>">
                                        <span class="dashicons dashicons-external"></span>
                                        <?php esc_html_e('Open AI-Scribe', 'opace-ai-prompt-library-api-hub'); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="button button-disabled">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php esc_html_e('Active', 'opace-ai-prompt-library-api-hub'); ?>
                                    </span>
                                <?php endif; ?>
                            <?php elseif ($addon['installed']): ?>
                                <button type="button" class="button button-primary ai-core-addon-action" data-action="activate" data-slug="<?php echo esc_attr($addon['slug']); ?>">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php esc_html_e('Activate AI-Scribe and continue', 'opace-ai-prompt-library-api-hub'); ?>
                                </button>
                            <?php elseif (isset($addon['available']) && !$addon['available']): ?>
                                <span class="button button-disabled" aria-disabled="true">
                                    <span class="dashicons dashicons-clock"></span>
                                    <?php esc_html_e('Coming soon', 'opace-ai-prompt-library-api-hub'); ?>
                                </span>
                                <p class="addon-unavailable-reason"><?php echo esc_html($addon['unavailable_reason']); ?></p>
                            <?php elseif (!empty($addon['wordpress_org'])): ?>
                                <button type="button" class="button button-primary ai-core-addon-action" data-action="install" data-slug="<?php echo esc_attr($addon['slug']); ?>">
                                    <span class="dashicons dashicons-download"></span>
                                    <?php esc_html_e('Install AI-Scribe', 'opace-ai-prompt-library-api-hub'); ?>
                                </button>
                                <p class="addon-unavailable-reason">
                                    <a href="<?php echo esc_url($addon['url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php esc_html_e('View details on WordPress.org', 'opace-ai-prompt-library-api-hub'); ?>
                                    </a>
                                </p>
                            <?php else: ?>
                                <?php if (!empty($addon['bundled'])): ?>
                                    <span class="button button-disabled" aria-disabled="true">
                                        <span class="dashicons dashicons-external"></span>
                                        <?php esc_html_e('Available separately', 'opace-ai-prompt-library-api-hub'); ?>
                                    </span>
                                    <p class="addon-unavailable-reason"><?php esc_html_e('Not included in this copy of Opace AI Hub.', 'opace-ai-prompt-library-api-hub'); ?></p>
                                <?php else: ?>
                                    <a href="<?php echo esc_url($addon['url']); ?>" class="button button-primary" target="_blank">
                                        <?php esc_html_e('Learn More', 'opace-ai-prompt-library-api-hub'); ?>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <p class="addon-action-status" aria-live="polite"></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="ai-core-addons-info">
                <h2><?php esc_html_e('Developing Add-ons', 'opace-ai-prompt-library-api-hub'); ?></h2>
                <p><?php esc_html_e('Opace AI Hub provides a simple API for developers to create add-on plugins. Your add-ons can access all configured AI providers without requiring users to enter API keys again.', 'opace-ai-prompt-library-api-hub'); ?></p>
                
                <h3><?php esc_html_e('Example Usage', 'opace-ai-prompt-library-api-hub'); ?></h3>
                <pre><code>&lt;?php
// Check if Opace AI Hub is available
if (function_exists('ai_core')) {
    $ai_core = ai_core();
    
    // Check if configured
    if ($ai_core->is_configured()) {
        // Send a text generation request
        $response = $ai_core->send_text_request(
            'gpt-5-mini',
            array(
                array('role' => 'user', 'content' => 'Hello, AI!')
            ),
            array('max_tokens' => 100)
        );
        
        if (!is_wp_error($response)) {
            echo $response['choices'][0]['message']['content'];
        }
    }
}
?&gt;</code></pre>

                <p class="description">
                    <?php esc_html_e('Model identifiers come from Opace AI Hub\'s own registry, so any provider and model the site has configured can be named here. Check the Settings screen for the identifiers currently available.', 'opace-ai-prompt-library-api-hub'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for activating add-on
     *
     * @return void
     */
    public function ajax_activate_addon() {
        // Check nonce
        check_ajax_referer('ai_core_admin', 'nonce');

        // Check permissions
        if (!current_user_can('activate_plugins')) {
            wp_send_json_error(array('message' => __('You do not have permission to activate plugins.', 'opace-ai-prompt-library-api-hub')));
        }

        $slug = isset($_POST['slug']) ? sanitize_key(wp_unslash($_POST['slug'])) : '';
        $addon = $this->get_addon_by_slug($slug);

        if (!$addon || empty($addon['installed']) || empty($addon['plugin_file'])) {
            wp_send_json_error(array('message' => __('The requested add-on is not installed.', 'opace-ai-prompt-library-api-hub')));
        }

        $result = activate_plugin($addon['plugin_file'], '', is_multisite() && is_network_admin());

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('AI-Scribe activated successfully.', 'opace-ai-prompt-library-api-hub'),
            'redirect' => !empty($addon['open_url']) ? $addon['open_url'] : admin_url('admin.php?page=ai-core-addons'),
        ));
    }

    /**
     * Install a public add-on from WordPress.org.
     *
     * @return void
     */
    public function ajax_install_addon() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('install_plugins')) {
            wp_send_json_error(array('message' => __('You do not have permission to install plugins.', 'opace-ai-prompt-library-api-hub')), 403);
        }

        $slug = isset($_POST['slug']) ? sanitize_key(wp_unslash($_POST['slug'])) : '';
        $addon = $this->get_addon_by_slug($slug);

        if (!$addon || empty($addon['wordpress_org']) || empty($addon['available'])) {
            wp_send_json_error(array('message' => __('This add-on is not available for installation.', 'opace-ai-prompt-library-api-hub')), 400);
        }

        if (!empty($addon['installed'])) {
            wp_send_json_error(array('message' => __('This add-on is already installed. Refresh the page and activate it.', 'opace-ai-prompt-library-api-hub')), 409);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $api = plugins_api('plugin_information', array(
            'slug' => $addon['slug'],
            'fields' => array('sections' => false),
        ));

        if (is_wp_error($api) || empty($api->download_link)) {
            $message = is_wp_error($api) ? $api->get_error_message() : __('WordPress.org did not return a download package.', 'opace-ai-prompt-library-api-hub');
            wp_send_json_error(array('message' => $message), 502);
        }

        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);
        $installed = $upgrader->install($api->download_link);

        if (is_wp_error($installed) || is_wp_error($skin->result) || $skin->get_errors()->has_errors() || true !== $installed) {
            if (is_wp_error($installed)) {
                $message = $installed->get_error_message();
            } elseif (is_wp_error($skin->result)) {
                $message = $skin->result->get_error_message();
            } elseif ($skin->get_errors()->has_errors()) {
                $message = $skin->get_error_messages();
            } else {
                $message = __('WordPress could not install AI-Scribe. Check filesystem access and try again.', 'opace-ai-prompt-library-api-hub');
            }
            wp_send_json_error(array('message' => $message), 500);
        }

        wp_clean_plugins_cache(true);
        $installed_addon = $this->get_addon_by_slug($slug);

        if (!$installed_addon || empty($installed_addon['plugin_file'])) {
            wp_send_json_error(array('message' => __('AI-Scribe installed, but its plugin file could not be found.', 'opace-ai-prompt-library-api-hub')), 500);
        }

        wp_send_json_success(array(
            'message' => __('AI-Scribe installed. Activate it to finish setup.', 'opace-ai-prompt-library-api-hub'),
            'next_action' => 'activate',
            'button_label' => __('Activate AI-Scribe and continue', 'opace-ai-prompt-library-api-hub'),
        ));
    }

}
