<?php
/**
 * Opace AI Hub Prompt Library Class
 *
 * Manages prompt library with groups, search, filter, import/export
 *
 * @package AI_Core
 * @version 0.6.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// The prompt library owns two custom tables. Core has no CRUD or object-cache API for them.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

// Load AJAX trait
require_once AI_CORE_PLUGIN_DIR . 'admin/class-ai-core-prompt-library-ajax.php';

/**
 * Opace AI Hub Prompt Library Class
 *
 * Manages prompt catalogue with modern UX
 */
class AI_Core_Prompt_Library {

    use AI_Core_Prompt_Library_AJAX;
    
    /**
     * Class instance
     * 
     * @var AI_Core_Prompt_Library
     */
    private static $instance = null;
    
    /**
     * Get class instance
     * 
     * @return AI_Core_Prompt_Library
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
        try {
            $this->init();
        } catch (Exception $e) {
            return;
        }
    }

    /**
     * Initialize
     *
     * @return void
     */
    private function init() {
        // AJAX handlers
        add_action('wp_ajax_ai_core_get_prompts', array($this, 'ajax_get_prompts'));
        add_action('wp_ajax_ai_core_save_prompt', array($this, 'ajax_save_prompt'));
        add_action('wp_ajax_ai_core_delete_prompt', array($this, 'ajax_delete_prompt'));
        add_action('wp_ajax_ai_core_delete_all_prompts', array($this, 'ajax_delete_all_prompts'));
        add_action('wp_ajax_ai_core_get_groups', array($this, 'ajax_get_groups'));
        add_action('wp_ajax_ai_core_save_group', array($this, 'ajax_save_group'));
        add_action('wp_ajax_ai_core_delete_group', array($this, 'ajax_delete_group'));
        add_action('wp_ajax_ai_core_move_prompt', array($this, 'ajax_move_prompt'));
        add_action('wp_ajax_ai_core_run_prompt', array($this, 'ajax_run_prompt'));
        add_action('wp_ajax_ai_core_export_prompts', array($this, 'ajax_export_prompts'));
        add_action('wp_ajax_ai_core_import_prompts', array($this, 'ajax_import_prompts'));
        add_action('wp_ajax_ai_core_get_provider_capabilities', array($this, 'ajax_get_provider_capabilities'));
    }
    
    /**
     * Render prompt library page
     *
     * @return void
     */
    public function render_page() {
        // Add error handling and debugging
        try {
            $groups = $this->get_groups();
            $prompts = $this->get_prompts();

            // Debug logging
        } catch (Exception $e) {
            echo '<div class="wrap">';
            AI_Core_Admin::render_page_brand(__('Prompt Library', 'opace-ai-prompt-library-api-hub'));
            echo '<div class="notice notice-error"><p>';
            printf(
                /* translators: %s: error detail. */
                esc_html__('Error loading Prompt Library: %s', 'opace-ai-prompt-library-api-hub'),
                esc_html($e->getMessage())
            );
            echo '</p></div>';
            echo '</div>';
            return;
        }

        ?>
        <div class="wrap ai-core-prompt-library">
            <?php AI_Core_Admin::render_page_brand(__('Prompt Library', 'opace-ai-prompt-library-api-hub')); ?>
            
            <div class="ai-core-library-header">
                <div class="ai-core-library-actions">
                    <button type="button" class="button button-primary" id="ai-core-new-prompt">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php esc_html_e('New Prompt', 'opace-ai-prompt-library-api-hub'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="ai-core-new-group">
                        <span class="dashicons dashicons-category"></span>
                        <?php esc_html_e('New Group', 'opace-ai-prompt-library-api-hub'); ?>
                    </button>
                    <button type="button" class="button" id="ai-core-import-prompts">
                        <span class="dashicons dashicons-upload"></span>
                        <?php esc_html_e('Import', 'opace-ai-prompt-library-api-hub'); ?>
                    </button>
                    <button type="button" class="button" id="ai-core-export-prompts">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export', 'opace-ai-prompt-library-api-hub'); ?>
                    </button>
                    <button type="button" class="button button-link-delete" id="ai-core-delete-all-prompts" style="color: #b32d2e;">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Delete All', 'opace-ai-prompt-library-api-hub'); ?>
                    </button>
                    <a href="<?php echo esc_url( AI_CORE_PLUGIN_URL . 'prompts-template.json' ); ?>"
                       class="button"
                       download
                       title="<?php esc_attr_e('Download JSON template file', 'opace-ai-prompt-library-api-hub'); ?>">
                        <span class="dashicons dashicons-media-code"></span>
                        <?php esc_html_e('JSON Template', 'opace-ai-prompt-library-api-hub'); ?>
                    </a>
                    <a href="<?php echo esc_url( AI_CORE_PLUGIN_URL . 'prompts-template.csv' ); ?>"
                       class="button"
                       download
                       title="<?php esc_attr_e('Download CSV template file', 'opace-ai-prompt-library-api-hub'); ?>">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <?php esc_html_e('CSV Template', 'opace-ai-prompt-library-api-hub'); ?>
                    </a>
                </div>

                <div class="ai-core-library-search">
                    <input type="search"
                           id="ai-core-search-prompts"
                           class="regular-text"
                           aria-label="<?php esc_attr_e('Search prompts', 'opace-ai-prompt-library-api-hub'); ?>"
                           placeholder="<?php esc_attr_e('Search prompts...', 'opace-ai-prompt-library-api-hub'); ?>" />
                    <select id="ai-core-filter-group"
                            class="regular-text"
                            aria-label="<?php esc_attr_e('Filter prompts by group', 'opace-ai-prompt-library-api-hub'); ?>">
                        <option value=""><?php esc_html_e('All Groups', 'opace-ai-prompt-library-api-hub'); ?></option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo esc_attr($group['id']); ?>">
                                <?php echo esc_html($group['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="ai-core-library-content">
                <h2 class="screen-reader-text"><?php esc_html_e('Prompt Groups', 'opace-ai-prompt-library-api-hub'); ?></h2>
                <div id="ai-core-groups-container" class="ai-core-groups-container">
                    <?php if (empty($groups)): ?>
                        <div class="ai-core-empty-state">
                            <span class="dashicons dashicons-category"></span>
                            <h3><?php esc_html_e('No groups yet', 'opace-ai-prompt-library-api-hub'); ?></h3>
                            <p><?php esc_html_e('Create your first group to organise prompts.', 'opace-ai-prompt-library-api-hub'); ?></p>
                            <button type="button" class="button button-primary" id="ai-core-new-group-empty">
                                <?php esc_html_e('Create Group', 'opace-ai-prompt-library-api-hub'); ?>
                            </button>
                        </div>
                    <?php else: ?>
                        <?php
                        // Organise prompts by group
                        $prompts_by_group = array();
                        foreach ($prompts as $prompt) {
                            $group_id = $prompt['group_id'] ?? 0;
                            if (!isset($prompts_by_group[$group_id])) {
                                $prompts_by_group[$group_id] = array();
                            }
                            $prompts_by_group[$group_id][] = $prompt;
                        }

                        // Render each group as a card
                        foreach ($groups as $group):
                            $group_prompts = $prompts_by_group[$group['id']] ?? array();
                        ?>
                            <div class="ai-core-group-card" data-group-id="<?php echo esc_attr($group['id']); ?>">
                                <div class="group-card-header">
                                    <div class="group-card-title">
                                        <span class="dashicons dashicons-category"></span>
                                        <h3><?php echo esc_html($group['name']); ?></h3>
                                        <span class="group-count"><?php echo count($group_prompts); ?></span>
                                    </div>
                                    <div class="group-card-actions">
                                        <button type="button" class="button-link edit-group" title="<?php esc_attr_e('Edit Group', 'opace-ai-prompt-library-api-hub'); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </button>
                                        <button type="button" class="button-link delete-group" title="<?php esc_attr_e('Delete Group', 'opace-ai-prompt-library-api-hub'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                        <button type="button" class="button-link add-prompt-to-group" title="<?php esc_attr_e('Add Prompt', 'opace-ai-prompt-library-api-hub'); ?>">
                                            <span class="dashicons dashicons-plus-alt"></span>
                                        </button>
                                    </div>
                                </div>
                                <div class="group-card-body" data-group-id="<?php echo esc_attr($group['id']); ?>">
                                    <?php if (empty($group_prompts)): ?>
                                        <div class="group-empty-state">
                                            <span class="dashicons dashicons-admin-post"></span>
                                            <p><?php esc_html_e('No prompts in this group', 'opace-ai-prompt-library-api-hub'); ?></p>
                                            <p class="description"><?php esc_html_e('Drag prompts here or click + to add', 'opace-ai-prompt-library-api-hub'); ?></p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($group_prompts as $prompt): ?>
                                            <?php $this->render_prompt_card($prompt); ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php
                        $ungrouped_prompts = $prompts_by_group[0] ?? array();
                        if (!empty($ungrouped_prompts)):
                        ?>
                            <div class="ai-core-group-card ungrouped" data-group-id="0">
                                <div class="group-card-header">
                                    <div class="group-card-title">
                                        <span class="dashicons dashicons-admin-post"></span>
                                        <h3><?php esc_html_e('Ungrouped Prompts', 'opace-ai-prompt-library-api-hub'); ?></h3>
                                        <span class="group-count"><?php echo count($ungrouped_prompts); ?></span>
                                    </div>
                                </div>
                                <div class="group-card-body" data-group-id="0">
                                    <?php foreach ($ungrouped_prompts as $prompt): ?>
                                        <?php $this->render_prompt_card($prompt); ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div id="ai-core-prompt-modal" class="ai-core-modal" style="display: none;">
            <div class="ai-core-modal-content">
                <div class="ai-core-modal-header">
                    <h2 id="ai-core-modal-title"><?php esc_html_e('Edit Prompt', 'opace-ai-prompt-library-api-hub'); ?></h2>
                    <button type="button" class="ai-core-modal-close">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
                <div class="ai-core-modal-body">
                    <input type="hidden" id="prompt-id" value="" />
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="prompt-title"><?php esc_html_e('Title', 'opace-ai-prompt-library-api-hub'); ?></label></th>
                            <td><input type="text" id="prompt-title" class="large-text" /></td>
                        </tr>
                        <tr>
                            <th><label for="prompt-group"><?php esc_html_e('Group', 'opace-ai-prompt-library-api-hub'); ?></label></th>
                            <td>
                                <select id="prompt-group" class="regular-text">
                                    <option value=""><?php esc_html_e('Ungrouped', 'opace-ai-prompt-library-api-hub'); ?></option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo esc_attr($group['id']); ?>">
                                            <?php echo esc_html($group['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="prompt-content"><?php esc_html_e('Prompt', 'opace-ai-prompt-library-api-hub'); ?></label></th>
                            <td><textarea id="prompt-content" rows="8" class="large-text"></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="prompt-provider"><?php esc_html_e('Provider', 'opace-ai-prompt-library-api-hub'); ?></label></th>
                            <td>
                                <select id="prompt-provider" class="regular-text">
                                    <option value=""><?php esc_html_e('Default', 'opace-ai-prompt-library-api-hub'); ?></option>
                                    <option value="openai">OpenAI</option>
                                    <option value="anthropic">Anthropic Claude</option>
                                    <option value="gemini">Google Gemini</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="prompt-type"><?php esc_html_e('Type', 'opace-ai-prompt-library-api-hub'); ?></label></th>
                            <td>
                                <select id="prompt-type" class="regular-text">
                                    <option value="text"><?php esc_html_e('Text Generation', 'opace-ai-prompt-library-api-hub'); ?></option>
                                    <option value="image"><?php esc_html_e('Image Generation', 'opace-ai-prompt-library-api-hub'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="ai-core-prompt-test">
                        <h3><?php esc_html_e('Test Prompt', 'opace-ai-prompt-library-api-hub'); ?></h3>
                        <button type="button" class="button" id="ai-core-test-prompt-modal">
                            <span class="dashicons dashicons-controls-play"></span>
                            <?php esc_html_e('Run Prompt', 'opace-ai-prompt-library-api-hub'); ?>
                        </button>
                        <div id="ai-core-prompt-result" class="ai-core-prompt-result" style="display: none;"></div>
                    </div>
                </div>
                <div class="ai-core-modal-footer">
                    <button type="button" class="button button-primary" id="ai-core-save-prompt">
                        <?php esc_html_e('Save Prompt', 'opace-ai-prompt-library-api-hub'); ?>
                    </button>
                    <button type="button" class="button" id="ai-core-cancel-prompt">
                        <?php esc_html_e('Cancel', 'opace-ai-prompt-library-api-hub'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <div id="ai-core-group-modal" class="ai-core-modal" style="display: none;">
            <div class="ai-core-modal-content ai-core-modal-small">
                <div class="ai-core-modal-header">
                    <h2 id="ai-core-group-modal-title"><?php esc_html_e('Edit Group', 'opace-ai-prompt-library-api-hub'); ?></h2>
                    <button type="button" class="ai-core-modal-close">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
                <div class="ai-core-modal-body">
                    <input type="hidden" id="group-id" value="" />
                    <table class="form-table">
                        <tr>
                            <th><label for="group-name"><?php esc_html_e('Group Name', 'opace-ai-prompt-library-api-hub'); ?></label></th>
                            <td><input type="text" id="group-name" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th><label for="group-description"><?php esc_html_e('Description', 'opace-ai-prompt-library-api-hub'); ?></label></th>
                            <td><textarea id="group-description" rows="3" class="large-text"></textarea></td>
                        </tr>
                    </table>
                </div>
                <div class="ai-core-modal-footer">
                    <button type="button" class="button button-primary" id="ai-core-save-group">
                        <?php esc_html_e('Save Group', 'opace-ai-prompt-library-api-hub'); ?>
                    </button>
                    <button type="button" class="button" id="ai-core-cancel-group">
                        <?php esc_html_e('Cancel', 'opace-ai-prompt-library-api-hub'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <div id="ai-core-import-modal" class="ai-core-modal" style="display: none;">
            <div class="ai-core-modal-content ai-core-modal-small">
                <div class="ai-core-modal-header">
                    <h2><?php esc_html_e('Import Prompts', 'opace-ai-prompt-library-api-hub'); ?></h2>
                    <button type="button" class="ai-core-modal-close">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
                <div class="ai-core-modal-body">
                    <p><?php esc_html_e('Upload a JSON file containing prompts and groups.', 'opace-ai-prompt-library-api-hub'); ?></p>
                    <input type="file" id="ai-core-import-file" accept=".json" />

                    <div class="ai-core-import-templates">
                        <h4>
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Need a template?', 'opace-ai-prompt-library-api-hub'); ?>
                        </h4>
                        <p>
                            <?php esc_html_e('Download a template file to see the correct format for importing prompts:', 'opace-ai-prompt-library-api-hub'); ?>
                        </p>
                        <div class="ai-core-import-template-links">
                            <a href="<?php echo esc_url( AI_CORE_PLUGIN_URL . 'prompts-template.json' ); ?>"
                               class="button"
                               download>
                                <span class="dashicons dashicons-media-code"></span>
                                <?php esc_html_e('Download JSON Template', 'opace-ai-prompt-library-api-hub'); ?>
                            </a>
                            <a href="<?php echo esc_url( AI_CORE_PLUGIN_URL . 'prompts-template.csv' ); ?>"
                               class="button"
                               download>
                                <span class="dashicons dashicons-media-spreadsheet"></span>
                                <?php esc_html_e('Download CSV Template', 'opace-ai-prompt-library-api-hub'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="ai-core-modal-footer">
                    <button type="button" class="button button-primary" id="ai-core-do-import">
                        <?php esc_html_e('Import', 'opace-ai-prompt-library-api-hub'); ?>
                    </button>
                    <button type="button" class="button" id="ai-core-cancel-import">
                        <?php esc_html_e('Cancel', 'opace-ai-prompt-library-api-hub'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div id="ai-core-export-modal" class="ai-core-modal" style="display: none;">
            <div class="ai-core-modal-content ai-core-modal-small">
                <div class="ai-core-modal-header">
                    <h2><?php esc_html_e('Export Prompts', 'opace-ai-prompt-library-api-hub'); ?></h2>
                    <button type="button" class="ai-core-modal-close">
                        <span class="dashicons dashicons-no"></span>
                    </button>
                </div>
                <div class="ai-core-modal-body">
                    <p><?php esc_html_e('Choose export format and version.', 'opace-ai-prompt-library-api-hub'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="ai-core-export-format"><?php esc_html_e('Format', 'opace-ai-prompt-library-api-hub'); ?></label>
                            </th>
                            <td>
                                <select id="ai-core-export-format" class="regular-text">
                                    <option value="json"><?php esc_html_e('JSON', 'opace-ai-prompt-library-api-hub'); ?></option>
                                    <option value="csv"><?php esc_html_e('CSV', 'opace-ai-prompt-library-api-hub'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="ai-core-export-version"><?php esc_html_e('Version', 'opace-ai-prompt-library-api-hub'); ?></label>
                            </th>
                            <td>
                                <select id="ai-core-export-version" class="regular-text">
                                    <option value="1.0">1.0</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="ai-core-modal-footer">
                    <button type="button" class="button button-primary" id="ai-core-do-export">
                        <?php esc_html_e('Export', 'opace-ai-prompt-library-api-hub'); ?>
                    </button>
                    <button type="button" class="button" id="ai-core-cancel-export">
                        <?php esc_html_e('Cancel', 'opace-ai-prompt-library-api-hub'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render prompt card
     *
     * @param array $prompt Prompt data
     * @return void
     */
    private function render_prompt_card($prompt) {
        // Held raw and escaped at each point of output below, so the escaping
        // is visible to both a reader and a static analyser.
        $prompt_id = $prompt['id'];
        $title = $prompt['title'];
        $content = wp_trim_words($prompt['content'], 20);
        $group_id = $prompt['group_id'] ?? '';
        $type = $prompt['type'] ?? 'text';
        $provider = $prompt['provider'] ?? 'default';

        ?>
        <div class="ai-core-prompt-card" data-prompt-id="<?php echo esc_attr($prompt_id); ?>" data-group-id="<?php echo esc_attr($group_id); ?>">
            <div class="prompt-card-header">
                <h4><?php echo esc_html($title); ?></h4>
                <div class="prompt-card-actions">
                    <button type="button" class="button-link edit-prompt" data-prompt-id="<?php echo esc_attr($prompt_id); ?>" title="<?php esc_attr_e('Edit', 'opace-ai-prompt-library-api-hub'); ?>">
                        <span class="dashicons dashicons-edit"></span>
                    </button>
                    <button type="button" class="button-link delete-prompt" data-prompt-id="<?php echo esc_attr($prompt_id); ?>" title="<?php esc_attr_e('Delete', 'opace-ai-prompt-library-api-hub'); ?>">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </div>
            </div>
            <div class="prompt-card-body">
                <p><?php echo esc_html($content); ?></p>
            </div>
            <div class="prompt-card-footer">
                <span class="prompt-type">
                    <span class="dashicons dashicons-<?php echo $type === 'image' ? 'format-image' : 'text'; ?>"></span>
                    <?php echo esc_html(ucfirst($type)); ?>
                </span>
                <span class="prompt-provider"><?php echo esc_html(ucfirst($provider)); ?></span>
                <button type="button" class="button button-small run-prompt" data-prompt-id="<?php echo esc_attr($prompt_id); ?>">
                    <span class="dashicons dashicons-controls-play"></span>
                    <?php esc_html_e('Run', 'opace-ai-prompt-library-api-hub'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Get all groups
     *
     * @return array
     */
    public function get_groups() {
        global $wpdb;
        $groups_table = $wpdb->prefix . 'ai_core_prompt_groups';
        $prompts_table = $wpdb->prefix . 'ai_core_prompts';

        // Check if tables exist
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $groups_table));
        if (!$table_exists) {
            return array();
        }

        // Optimised query: Get groups with prompt counts in a single query
        $groups = $wpdb->get_results(
            $wpdb->prepare(
            "SELECT g.*, COUNT(p.id) as count
             FROM %i g
             LEFT JOIN %i p ON g.id = p.group_id
             GROUP BY g.id
             ORDER BY g.name ASC",
                $groups_table,
                $prompts_table
            ),
            ARRAY_A
        );

        if ($wpdb->last_error) {
            return array();
        }

        return $groups ?: array();
    }

    /**
     * Get prompt count for a group
     *
     * @param int $group_id Group ID
     * @return int
     */
    private function get_group_prompt_count($group_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_core_prompts';

        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM %i WHERE group_id = %d',
            $table_name,
            $group_id
        ));
    }

    /**
     * Get all prompts
     *
     * @param array $args Query arguments
     * @return array
     */
    public function get_prompts($args = array()) {
        global $wpdb;
        $prompts_table = $wpdb->prefix . 'ai_core_prompts';
        $groups_table = $wpdb->prefix . 'ai_core_prompt_groups';

        // Check if tables exist
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $prompts_table));
        if (!$table_exists) {
            return array();
        }

        $defaults = array(
            'group_id' => null,
            'group_name' => '',
            'search' => '',
            'type' => '',
            'provider' => '',
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $prepare_args = array();

        if (!is_null($args['group_id'])) {
            $where[] = 'p.group_id = %d';
            $prepare_args[] = $args['group_id'];
        }

        // Filter by group name (used by AI-Imagen workflow cards)
        if (!empty($args['group_name'])) {
            $where[] = 'g.name = %s';
            $prepare_args[] = $args['group_name'];
        }

        if (!empty($args['search'])) {
            $where[] = '(p.title LIKE %s OR p.content LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $prepare_args[] = $search_term;
            $prepare_args[] = $search_term;
        }

        if (!empty($args['type'])) {
            $where[] = 'p.type = %s';
            $prepare_args[] = $args['type'];
        }

        if (!empty($args['provider'])) {
            $where[] = 'p.provider = %s';
            $prepare_args[] = $args['provider'];
        }

        $where_clause = implode(' AND ', $where);

        // Table names go through the %i identifier placeholder (WP 6.2+) and the
        // whole statement is always prepared, so no branch reaches get_results()
        // with an unprepared query. $where_clause is built only from the literal
        // fragments above; every user value is a placeholder in $prepare_args.
        // prepare() is inlined into the call so the static analyser can see the
        // query is prepared; assigning it to a variable first reads as unprepared.
        //
        // $where_clause is safe by construction and the sniff cannot see it:
        // every element of $where is a string literal written above in this
        // method ('1=1', 'p.group_id = %d', 'g.name = %s', the LIKE pair,
        // 'p.type = %s', 'p.provider = %s'). No caller-supplied text reaches it
        // — every user value is bound through $prepare_args, and the two table
        // names go through %i identifier placeholders.
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- built from literals only, values are bound.
        $prompts = $wpdb->get_results(
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the WHERE fragments are fixed literals assembled above; all values remain placeholders.
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- the dynamic literal filters and their values are assembled together above.
            $wpdb->prepare(
                "SELECT p.*, g.name as group_name
                  FROM %i p
                  LEFT JOIN %i g ON p.group_id = g.id
                  WHERE {$where_clause}
                  ORDER BY p.created_at DESC",
                array_merge(array($prompts_table, $groups_table), $prepare_args)
            ),
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );

        if ($wpdb->last_error) {
            return array();
        }

        return $prompts ?: array();
    }

    /**
     * Save a prompt (create or update).
     *
     * PUBLIC WRITE API. This is the single persistence path for prompts —
     * ajax_save_prompt() is a thin wrapper around it, so the AJAX screen and
     * any server-side caller (for example AI-Scribe migrating its own
     * library into the hub) write identical rows through identical
     * sanitisation.
     *
     * SECURITY: this method performs NO nonce and NO capability check. It is
     * a trusted-caller API. Every check stays where it belongs — in the AJAX
     * layer, which verifies the `ai_core_admin` nonce and `manage_options`
     * before delegating here. A caller reaching this method from anything
     * derived from user input MUST perform its own authorisation first.
     * Input is still sanitised here (sanitize_text_field / wp_kses_post) so
     * no caller can write unsafe content, whatever it passes.
     *
     * @param array $data {
     *     @type int    $id       Prompt ID. 0 or absent creates a new prompt.
     *     @type string $title    Required.
     *     @type string $content  Required.
     *     @type int    $group_id Group ID, 0/null for ungrouped.
     *     @type string $provider Provider slug, '' for the default provider.
     *     @type string $type     'text' or 'image'. Defaults to 'text'.
     * }
     * @return int|WP_Error Prompt ID on success, WP_Error on failure.
     */
    public function save_prompt($data) {
        global $wpdb;

        if (!is_array($data)) {
            return new WP_Error('ai_core_invalid_prompt', __('Prompt data must be an array', 'opace-ai-prompt-library-api-hub'));
        }

        $table_name = $wpdb->prefix . 'ai_core_prompts';

        $prompt_id = isset($data['id']) ? intval($data['id']) : 0;
        $title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
        $content = isset($data['content']) ? wp_kses_post($data['content']) : '';
        $group_id = isset($data['group_id']) ? intval($data['group_id']) : null;
        $provider = isset($data['provider']) ? sanitize_text_field($data['provider']) : '';
        $type = isset($data['type']) && '' !== $data['type'] ? sanitize_text_field($data['type']) : 'text';

        if (empty($title) || empty($content)) {
            return new WP_Error('ai_core_missing_field', __('Title and content are required', 'opace-ai-prompt-library-api-hub'));
        }

        $row = array(
            'title' => $title,
            'content' => $content,
            'group_id' => $group_id,
            'provider' => $provider,
            'type' => $type,
            'updated_at' => current_time('mysql'),
        );

        if ($prompt_id > 0) {
            // Update existing prompt
            $result = $wpdb->update(
                $table_name,
                $row,
                array('id' => $prompt_id),
                array('%s', '%s', '%d', '%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Create new prompt
            $row['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $table_name,
                $row,
                array('%s', '%s', '%d', '%s', '%s', '%s', '%s')
            );
            $prompt_id = $wpdb->insert_id;
        }

        if (false === $result) {
            return new WP_Error(
                'ai_core_save_failed',
                __('Failed to save prompt', 'opace-ai-prompt-library-api-hub'),
                array('db_error' => $wpdb->last_error)
            );
        }

        return (int) $prompt_id;
    }

    /**
     * Save a prompt group (create or update).
     *
     * PUBLIC WRITE API, and the single persistence path for groups —
     * ajax_save_group() delegates here. The same security contract as
     * save_prompt() applies: no nonce and no capability check happen in this
     * method. The AJAX layer keeps its `ai_core_admin` nonce and
     * `manage_options` check, and any other caller is responsible for its
     * own authorisation. Input is sanitised here regardless of caller.
     *
     * @param array $data {
     *     @type int    $id          Group ID. 0 or absent creates a new group.
     *     @type string $name        Required.
     *     @type string $description Optional.
     * }
     * @return int|WP_Error Group ID on success, WP_Error on failure.
     */
    public function save_group($data) {
        global $wpdb;

        if (!is_array($data)) {
            return new WP_Error('ai_core_invalid_group', __('Group data must be an array', 'opace-ai-prompt-library-api-hub'));
        }

        $table_name = $wpdb->prefix . 'ai_core_prompt_groups';

        $group_id = isset($data['id']) ? intval($data['id']) : 0;
        $name = isset($data['name']) ? sanitize_text_field($data['name']) : '';
        $description = isset($data['description']) ? sanitize_textarea_field($data['description']) : '';

        if (empty($name)) {
            return new WP_Error('ai_core_missing_field', __('Group name is required', 'opace-ai-prompt-library-api-hub'));
        }

        $row = array(
            'name' => $name,
            'description' => $description,
            'updated_at' => current_time('mysql'),
        );

        if ($group_id > 0) {
            // Update existing group
            $result = $wpdb->update(
                $table_name,
                $row,
                array('id' => $group_id),
                array('%s', '%s', '%s'),
                array('%d')
            );
        } else {
            // Create new group
            $row['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $table_name,
                $row,
                array('%s', '%s', '%s', '%s')
            );
            $group_id = $wpdb->insert_id;
        }

        if (false === $result) {
            return new WP_Error(
                'ai_core_save_failed',
                __('Failed to save group', 'opace-ai-prompt-library-api-hub'),
                array('db_error' => $wpdb->last_error)
            );
        }

        return (int) $group_id;
    }

    /**
     * Find a group by its exact name.
     *
     * Read-only companion to save_group(), so a caller that wants "the group
     * called X, creating it only if it is missing" does not have to walk
     * get_groups() itself.
     *
     * @param string $name Group name.
     * @return array|null Group row, or null when no group carries that name.
     */
    public function get_group_by_name($name) {
        $name = is_string($name) ? trim($name) : '';

        if ('' === $name) {
            return null;
        }

        foreach ($this->get_groups() as $group) {
            if (isset($group['name']) && $group['name'] === $name) {
                return $group;
            }
        }

        return null;
    }

    /**
     * AJAX: Get prompts
     *
     * @return void
     */
    public function ajax_get_prompts() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }

        $args = array(
            'search' => isset($_POST['search']) ? sanitize_text_field(wp_unslash( $_POST['search'] )) : '',
            'type' => isset($_POST['type']) ? sanitize_text_field(wp_unslash( $_POST['type'] )) : '',
            'provider' => isset($_POST['provider']) ? sanitize_text_field(wp_unslash( $_POST['provider'] )) : '',
        );

        // Only add group_id filter if explicitly set (not "All Prompts")
        if (isset($_POST['group_id']) && $_POST['group_id'] !== '' && $_POST['group_id'] !== 'null') {
            $args['group_id'] = intval($_POST['group_id']);
        }

        // Support filtering by group name (used by AI-Imagen workflow cards)
        if (isset($_POST['group_name']) && !empty($_POST['group_name'])) {
            $args['group_name'] = sanitize_text_field(wp_unslash( $_POST['group_name'] ));
        }

        $prompts = $this->get_prompts($args);

        wp_send_json_success(array('prompts' => $prompts));
    }
}

// Initialize Prompt Library to register AJAX handlers
AI_Core_Prompt_Library::get_instance();

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
