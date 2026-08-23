<?php
/**
 * Opace AI Hub Admin Class
 * 
 * Handles admin interface and menu pages
 * 
 * @package AI_Core
 * @version 1.0.8
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Opace AI Hub Admin Class
 * 
 * Manages admin pages and interface
 */
class AI_Core_Admin {
    
    /**
     * Class instance
     * 
     * @var AI_Core_Admin
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return AI_Core_Admin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Render the shared branded heading used on Opace AI Hub admin screens.
     *
     * @param string $title Page title.
     * @return void
     */
    public static function render_page_brand($title) {
        ?>
        <div class="ai-core-page-brand">
            <img src="<?php echo esc_url(AI_CORE_PLUGIN_URL . 'assets/images/opace-ai-hub-logo.png'); ?>"
                 alt="" aria-hidden="true" width="72" height="72">
            <h1><?php echo esc_html($title); ?></h1>
        </div>
        <?php
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize admin
     *
     * @return void
     */
    private function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        // The dark/light theme boot script is registered through the enqueue
        // system in AI_Core::admin_enqueue_scripts() (handle
        // "ai-core-theme-boot"), which covers every plugin screen.
    }

    /**
     * Add admin menu
     *
     * @return void
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Opace AI Hub', 'opace-ai-prompt-library-api-hub'),
            __('Opace AI Hub', 'opace-ai-prompt-library-api-hub'),
            'manage_options',
            'opace-ai-prompt-library-api-hub',
            array($this, 'render_dashboard_page'),
            AI_CORE_PLUGIN_URL . 'assets/images/opace-ai-hub-menu-icon-20x20.png',
            30
        );
        
        // Dashboard submenu (same as main)
        add_submenu_page(
            'opace-ai-prompt-library-api-hub',
            __('Dashboard', 'opace-ai-prompt-library-api-hub'),
            __('Dashboard', 'opace-ai-prompt-library-api-hub'),
            'manage_options',
            'opace-ai-prompt-library-api-hub',
            array($this, 'render_dashboard_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'opace-ai-prompt-library-api-hub',
            __('Settings', 'opace-ai-prompt-library-api-hub'),
            __('Settings', 'opace-ai-prompt-library-api-hub'),
            'manage_options',
            'ai-core-settings',
            array($this, 'render_settings_page')
        );

        // Prompt Library submenu
        add_submenu_page(
            'opace-ai-prompt-library-api-hub',
            __('Prompt Library', 'opace-ai-prompt-library-api-hub'),
            __('Prompt Library', 'opace-ai-prompt-library-api-hub'),
            'manage_options',
            'ai-core-prompt-library',
            array($this, 'render_prompt_library_page')
        );

        // Statistics submenu
        add_submenu_page(
            'opace-ai-prompt-library-api-hub',
            __('Statistics', 'opace-ai-prompt-library-api-hub'),
            __('Statistics', 'opace-ai-prompt-library-api-hub'),
            'manage_options',
            'ai-core-stats',
            array($this, 'render_stats_page')
        );

        // Add-ons submenu
        add_submenu_page(
            'opace-ai-prompt-library-api-hub',
            __('Add-ons', 'opace-ai-prompt-library-api-hub'),
            __('Add-ons', 'opace-ai-prompt-library-api-hub'),
            'manage_options',
            'ai-core-addons',
            array($this, 'render_addons_page')
        );
    }
    
    /**
     * Render dashboard page
     *
     * @return void
     */
    public function render_dashboard_page() {
        $api = AI_Core_API::get_instance();
        $configured = $api->is_configured();
        $providers = $api->get_configured_providers();
        $stats = AI_Core_Stats::get_instance()->get_total_stats();

        // Quick Stats are read defensively: a site with no recorded usage has no
        // counters at all, and an older stats option predates the total_tokens key.
        $total_requests = isset($stats['requests']) ? (int) $stats['requests'] : 0;
        $total_tokens   = isset($stats['total_tokens']) ? (int) $stats['total_tokens'] : (isset($stats['tokens']) ? (int) $stats['tokens'] : 0);
        $models_used    = isset($stats['models_used']) ? (int) $stats['models_used'] : 0;
        $has_usage      = ($total_requests > 0 || $total_tokens > 0 || $models_used > 0);

        ?>
        <div class="wrap ai-core-dashboard">
            <?php self::render_page_brand(get_admin_page_title()); ?>
            
            <div class="ai-core-welcome-panel">
                <h2><?php esc_html_e('Welcome to Opace AI Hub', 'opace-ai-prompt-library-api-hub'); ?></h2>
                <p><?php esc_html_e('Universal AI Integration Hub for WordPress', 'opace-ai-prompt-library-api-hub'); ?></p>
                
                <?php if (!$configured): ?>
                    <div class="notice notice-warning inline">
                        <p>
                            <strong><?php esc_html_e('Getting Started:', 'opace-ai-prompt-library-api-hub'); ?></strong>
                            <?php esc_html_e('Please configure at least one API key in the Settings page to start using Opace AI Hub.', 'opace-ai-prompt-library-api-hub'); ?>
                        </p>
                        <p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=ai-core-settings')); ?>" class="button button-primary">
                                <?php esc_html_e('Configure API Keys', 'opace-ai-prompt-library-api-hub'); ?>
                            </a>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-success inline">
                        <p>
                            <strong><?php esc_html_e('Status:', 'opace-ai-prompt-library-api-hub'); ?></strong>
                            <?php
                            printf(
                                /* translators: %d: number of AI providers that have an API key configured. */
                                esc_html(_n('%d provider configured', '%d providers configured', count($providers), 'opace-ai-prompt-library-api-hub')),
                                (int) count($providers)
                            );
                            ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($configured): ?>
                <div class="ai-core-stats-overview">
                    <h2><?php esc_html_e('Quick Stats', 'opace-ai-prompt-library-api-hub'); ?></h2>
                    <div class="ai-core-stats-grid">
                        <div class="stat-box">
                            <span class="stat-label"><?php esc_html_e('Total Requests', 'opace-ai-prompt-library-api-hub'); ?></span>
                            <span class="stat-value"><?php echo esc_html(number_format_i18n($total_requests)); ?></span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-label"><?php esc_html_e('Total Tokens', 'opace-ai-prompt-library-api-hub'); ?></span>
                            <span class="stat-value"><?php echo esc_html(number_format_i18n($total_tokens)); ?></span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-label"><?php esc_html_e('Configured Providers', 'opace-ai-prompt-library-api-hub'); ?></span>
                            <span class="stat-value"><?php echo esc_html(number_format_i18n(count($providers))); ?></span>
                        </div>
                        <div class="stat-box">
                            <span class="stat-label"><?php esc_html_e('Models Used', 'opace-ai-prompt-library-api-hub'); ?></span>
                            <span class="stat-value"><?php echo esc_html(number_format_i18n($models_used)); ?></span>
                        </div>
                    </div>
                    <?php if (!$has_usage): ?>
                        <p class="ai-core-stats-hint">
                            <?php esc_html_e('No requests recorded yet. Counters start moving as soon as Opace AI Hub or one of its add-ons sends its first request.', 'opace-ai-prompt-library-api-hub'); ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="ai-core-providers-status">
                    <h2><?php esc_html_e('Configured Providers', 'opace-ai-prompt-library-api-hub'); ?></h2>
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Provider', 'opace-ai-prompt-library-api-hub'); ?></th>
                                <th><?php esc_html_e('Status', 'opace-ai-prompt-library-api-hub'); ?></th>
                                <th><?php esc_html_e('Available Models', 'opace-ai-prompt-library-api-hub'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($providers as $provider): 
                                $models = $api->get_available_models($provider);
                                $provider_names = array(
                                    'openai' => 'OpenAI',
                                    'anthropic' => 'Anthropic Claude',
                                    'gemini' => 'Google Gemini',
                                );
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($provider_names[$provider] ?? $provider); ?></strong></td>
                                    <td><span class="dashicons dashicons-yes-alt ai-core-status-ok"></span> <?php esc_html_e('Configured', 'opace-ai-prompt-library-api-hub'); ?></td>
                                    <td><?php echo count($models); ?> <?php esc_html_e('models', 'opace-ai-prompt-library-api-hub'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="ai-core-quick-links">
                <h2><?php esc_html_e('Quick Links', 'opace-ai-prompt-library-api-hub'); ?></h2>
                <div class="ai-core-links-grid">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ai-core-settings')); ?>" class="ai-core-link-box">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <h3><?php esc_html_e('Settings', 'opace-ai-prompt-library-api-hub'); ?></h3>
                        <p><?php esc_html_e('Configure API keys and preferences', 'opace-ai-prompt-library-api-hub'); ?></p>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ai-core-stats')); ?>" class="ai-core-link-box">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <h3><?php esc_html_e('Statistics', 'opace-ai-prompt-library-api-hub'); ?></h3>
                        <p><?php esc_html_e('View detailed usage statistics', 'opace-ai-prompt-library-api-hub'); ?></p>
                    </a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=ai-core-addons')); ?>" class="ai-core-link-box">
                        <span class="dashicons dashicons-admin-plugins"></span>
                        <h3><?php esc_html_e('Add-ons', 'opace-ai-prompt-library-api-hub'); ?></h3>
                        <p><?php esc_html_e('Discover plugins that extend Opace AI Hub', 'opace-ai-prompt-library-api-hub'); ?></p>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     *
     * @return void
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <?php self::render_page_brand(get_admin_page_title()); ?>
            <?php settings_errors('ai_core_settings'); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('ai_core_settings_group');
                do_settings_sections('ai-core-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render statistics page
     *
     * @return void
     */
    public function render_stats_page() {
        $stats = AI_Core_Stats::get_instance();
        $total = $stats->get_total_stats();

        // Nothing to reset until at least one counter has moved, so the control
        // is withheld rather than offered as a no-op.
        $has_usage = (
            (isset($total['requests']) ? (int) $total['requests'] : 0) > 0
            || (isset($total['total_tokens']) ? (int) $total['total_tokens'] : 0) > 0
            || (isset($total['models_used']) ? (int) $total['models_used'] : 0) > 0
            || (isset($total['tools_used']) ? (int) $total['tools_used'] : 0) > 0
            || (isset($total['errors']) ? (int) $total['errors'] : 0) > 0
        );

        ?>
        <div class="wrap">
            <?php self::render_page_brand(get_admin_page_title()); ?>

            <div class="ai-core-stats-page">
                <h2 class="screen-reader-text"><?php esc_html_e('Usage summary', 'opace-ai-prompt-library-api-hub'); ?></h2>

                <?php
                // format_stats_html() builds its own table markup; wp_kses_post()
                // keeps the tables and spans it needs while stripping anything else.
                echo wp_kses_post($stats->format_stats_html());
                ?>

                <?php if ($has_usage): ?>
                    <p>
                        <button type="button" class="button button-primary" id="ai-core-refresh-pricing">
                            <?php esc_html_e('Refresh Model Pricing', 'opace-ai-prompt-library-api-hub'); ?>
                        </button>
                        <button type="button" class="button" id="ai-core-reset-stats">
                            <?php esc_html_e('Reset Statistics', 'opace-ai-prompt-library-api-hub'); ?>
                        </button>
                    </p>
                    <div id="ai-core-pricing-status" class="ai-core-inline-status" role="status" aria-live="polite"></div>
                <?php else: ?>
                    <p class="ai-core-stats-hint">
                        <?php esc_html_e('There is nothing to reset yet. Once usage is recorded, a Reset Statistics button appears here.', 'opace-ai-prompt-library-api-hub'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render prompt library page
     *
     * @return void
     */
    public function render_prompt_library_page() {
        $library = AI_Core_Prompt_Library::get_instance();
        $library->render_page();
    }

    /**
     * Render add-ons page
     *
     * @return void
     */
    public function render_addons_page() {
        $addons = AI_Core_Addons::get_instance();
        $addons->render_addons_page();
    }
}
