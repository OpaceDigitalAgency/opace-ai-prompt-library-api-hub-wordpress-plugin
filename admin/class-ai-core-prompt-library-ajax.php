<?php
/**
 * Opace AI Hub Prompt Library AJAX Handlers
 * 
 * Additional AJAX methods for the Prompt Library
 * 
 * @package AI_Core
 * @version 0.6.5
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Trait for Prompt Library AJAX handlers
 */
trait AI_Core_Prompt_Library_AJAX {

    // The prompt library owns custom tables, for which WordPress has no CRUD or cache API.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    
    /**
     * AJAX: Save prompt
     *
     * Security boundary: the nonce and capability check live here, and only
     * here. Persistence and sanitisation are delegated to
     * AI_Core_Prompt_Library::save_prompt() so the admin screen and
     * server-side callers share one code path.
     *
     * @return void
     */
    public function ajax_save_prompt() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }

        $result = $this->save_prompt(array(
            'id' => isset($_POST['prompt_id']) ? intval($_POST['prompt_id']) : 0,
            'title' => isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '',
            'content' => isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '',
            'group_id' => isset($_POST['group_id']) ? intval($_POST['group_id']) : null,
            'provider' => isset($_POST['provider']) ? sanitize_key(wp_unslash($_POST['provider'])) : '',
            'type' => isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : 'text',
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Prompt saved successfully', 'opace-ai-prompt-library-api-hub'),
            'prompt_id' => $result,
        ));
    }
    
    /**
     * AJAX: Delete prompt
     *
     * @return void
     */
    public function ajax_delete_prompt() {
        check_ajax_referer('ai_core_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }
        
        $prompt_id = isset($_POST['prompt_id']) ? intval($_POST['prompt_id']) : 0;
        
        if ($prompt_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid prompt ID', 'opace-ai-prompt-library-api-hub')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_core_prompts';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $prompt_id),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to delete prompt', 'opace-ai-prompt-library-api-hub')));
        }
        
        wp_send_json_success(array('message' => __('Prompt deleted successfully', 'opace-ai-prompt-library-api-hub')));
    }
    
    /**
     * AJAX: Get groups
     *
     * @return void
     */
    public function ajax_get_groups() {
        check_ajax_referer('ai_core_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }
        
        $groups = $this->get_groups();
        
        wp_send_json_success(array('groups' => $groups));
    }
    
    /**
     * AJAX: Save group
     *
     * Security boundary: the nonce and capability check live here, and only
     * here. Persistence and sanitisation are delegated to
     * AI_Core_Prompt_Library::save_group().
     *
     * @return void
     */
    public function ajax_save_group() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }

        $result = $this->save_group(array(
            'id' => isset($_POST['group_id']) ? intval($_POST['group_id']) : 0,
            'name' => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
            'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array(
            'message' => __('Group saved successfully', 'opace-ai-prompt-library-api-hub'),
            'group_id' => $result,
        ));
    }
    
    /**
     * AJAX: Delete group
     *
     * @return void
     */
    public function ajax_delete_group() {
        check_ajax_referer('ai_core_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }
        
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
        
        if ($group_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid group ID', 'opace-ai-prompt-library-api-hub')));
        }
        
        global $wpdb;
        
        // First, unassign all prompts from this group
        $prompts_table = $wpdb->prefix . 'ai_core_prompts';
        $wpdb->update(
            $prompts_table,
            array('group_id' => null),
            array('group_id' => $group_id),
            array('%d'),
            array('%d')
        );
        
        // Then delete the group
        $groups_table = $wpdb->prefix . 'ai_core_prompt_groups';
        $result = $wpdb->delete(
            $groups_table,
            array('id' => $group_id),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to delete group', 'opace-ai-prompt-library-api-hub')));
        }
        
        wp_send_json_success(array('message' => __('Group deleted successfully', 'opace-ai-prompt-library-api-hub')));
    }
    
    /**
     * AJAX: Move prompt to different group
     *
     * @return void
     */
    public function ajax_move_prompt() {
        check_ajax_referer('ai_core_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }
        
        $prompt_id = isset($_POST['prompt_id']) ? intval($_POST['prompt_id']) : 0;
        $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : null;
        
        if ($prompt_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid prompt ID', 'opace-ai-prompt-library-api-hub')));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_core_prompts';
        
        $result = $wpdb->update(
            $table_name,
            array('group_id' => $group_id),
            array('id' => $prompt_id),
            array('%d'),
            array('%d')
        );
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to move prompt', 'opace-ai-prompt-library-api-hub')));
        }
        
        wp_send_json_success(array('message' => __('Prompt moved successfully', 'opace-ai-prompt-library-api-hub')));
    }
    
    /**
     * AJAX: Run prompt
     *
     * @return void
     */
    public function ajax_run_prompt() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }

        $prompt_content = isset($_POST['prompt']) ? wp_kses_post(wp_unslash( $_POST['prompt'] )) : '';
        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash( $_POST['provider'] )) : '';
        $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash( $_POST['model'] )) : '';
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash( $_POST['type'] )) : 'text';

        if (empty($prompt_content)) {
            wp_send_json_error(array('message' => __('Prompt content is required', 'opace-ai-prompt-library-api-hub')));
        }

        // Get settings to check if API keys are configured
        $settings = get_option('ai_core_settings', array());

        $api = AI_Core_API::get_instance();
        $configured_providers = $api->get_configured_providers();
        if (empty($configured_providers)) {
            wp_send_json_error(array('message' => __('No provider is configured in Opace AI Hub or WordPress Connectors.', 'opace-ai-prompt-library-api-hub')));
        }

        // If no provider was specified, use the saved default when available.
        if (empty($provider) || $provider === 'default') {
            $saved_default = $settings['default_provider'] ?? '';
            $provider = in_array($saved_default, $configured_providers, true)
                ? $saved_default
                : $configured_providers[0];
        }

        if (!in_array($provider, $configured_providers, true)) {
            /* translators: %s: provider name, e.g. OpenAI. */
            wp_send_json_error(array('message' => sprintf(__('%s is not configured in Opace AI Hub or WordPress Connectors.', 'opace-ai-prompt-library-api-hub'), ucfirst($provider))));
        }

        try {
            // Use AI_Core_API to ensure statistics tracking
            $usage_context = array('tool' => 'prompt_library');

            if ($type === 'image') {
                // For image generation
                $result = $api->generate_image($prompt_content, array(), $provider, $usage_context);

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
                ));
            } else {
                // For text generation
                $messages = array(
                    array('role' => 'user', 'content' => $prompt_content)
                );

                // Determine model based on provider if not specified
                if (empty($model)) {
                    // First, try to get the saved model from settings
                    if (!empty($settings['provider_models'][$provider])) {
                        $model = $settings['provider_models'][$provider];
                    } else {
                        // Fallback to preferred model from ModelRegistry
                        if (class_exists('AICore\\Registry\\ModelRegistry')) {
                            $model = \AICore\Registry\ModelRegistry::getPreferredModel($provider);
                        }

                        // Final fallback to hardcoded defaults
                        if (empty($model)) {
                            $model_map = array(
                                'openai' => 'gpt-5',
                                'anthropic' => 'claude-sonnet-4-5-20250929',
                                'gemini' => 'gemini-2.5-flash',
                            );
                            $model = $model_map[$provider] ?? 'gpt-5';
                        }
                    }
                }

                $options = array();

                $result = $api->send_text_request($model, $messages, $options, $usage_context);

                // Check for WP_Error
                if (is_wp_error($result)) {
                    wp_send_json_error(array('message' => $result->get_error_message()));
                }

                // Use the library's extractContent method to properly extract text
                $text = \AICore\AICore::extractContent($result);

                wp_send_json_success(array(
                    'result' => $text,
                    'type' => 'text',
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * AJAX: Get provider capabilities
     *
     * @return void
     */
    public function ajax_get_provider_capabilities() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }

        $configured = AI_Core_API::get_instance()->get_configured_providers();
        $capabilities = array();

        // Check OpenAI
        if (in_array('openai', $configured, true)) {
            $capabilities['openai'] = array(
                'text' => true,
                'image' => true,
                'models' => array('gpt-image-1', 'dall-e-3', 'dall-e-2')
            );
        }

        // Check Gemini
        if (in_array('gemini', $configured, true)) {
            $capabilities['gemini'] = array(
                'text' => true,
                'image' => true,
                'models' => array('imagen-3.0-generate-001', 'imagen-3.0-fast-generate-001', 'gemini-2.5-flash-image')
            );
        }

        // Check Anthropic (text only)
        if (in_array('anthropic', $configured, true)) {
            $capabilities['anthropic'] = array(
                'text' => true,
                'image' => false,
                'models' => array()
            );
        }

        wp_send_json_success(array('capabilities' => $capabilities));
    }

    /**
     * AJAX: Export prompts
     *
     * @return void
     */
    public function ajax_export_prompts() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }

        global $wpdb;

        $format  = isset($_POST['format']) ? strtolower(sanitize_text_field(wp_unslash( $_POST['format'] ))) : 'json';
        $version = isset($_POST['version']) ? sanitize_text_field(wp_unslash( $_POST['version'] )) : '1.0';

        $groups = $this->get_groups();
        $prompts = $this->get_prompts();

        if ($format === 'csv') {
            // Build CSV for groups and prompts (two files)
            $groups_csv  = $this->build_groups_csv($groups);
            $prompts_csv = $this->build_prompts_csv($prompts, $groups);

            $timestamp = gmdate('Y-m-d-His');

            wp_send_json_success(array(
                'groups_csv'       => $groups_csv,
                'prompts_csv'      => $prompts_csv,
                'groups_filename'  => 'ai-core-prompt-groups-' . $timestamp . '.csv',
                'prompts_filename' => 'ai-core-prompts-' . $timestamp . '.csv',
                'version'          => $version,
                'exported_at'      => current_time('mysql'),
                'format'           => 'csv',
            ));
        } else {
            // JSON export (default)
            $export_data = array(
                'version'     => $version,
                'exported_at' => current_time('mysql'),
                'groups'      => $groups,
                'prompts'     => $prompts,
            );

            wp_send_json_success(array(
                'data'     => $export_data,
                'filename' => 'ai-core-prompts-' . gmdate('Y-m-d-His') . '.json',
                'format'   => 'json',
            ));
        }
    }

    /**
     * Build CSV for groups
     *
     * @param array $groups
     * @return string
     */
    private function build_groups_csv($groups) {
        $headers = array('id', 'name', 'description', 'created_at', 'updated_at');

        $lines = array();
        $lines[] = implode(',', array_map(array($this, 'csv_escape'), $headers));

        foreach ($groups as $g) {
            $row = array(
                $g['id'] ?? '',
                $g['name'] ?? '',
                $g['description'] ?? '',
                $g['created_at'] ?? '',
                $g['updated_at'] ?? '',
            );
            $lines[] = implode(',', array_map(array($this, 'csv_escape'), $row));
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * Build CSV for prompts
     *
     * @param array $prompts
     * @param array $groups
     * @return string
     */
    private function build_prompts_csv($prompts, $groups) {
        $headers = array('id', 'title', 'content', 'group_id', 'group_name', 'provider', 'type', 'created_at', 'updated_at');

        // Build group id => name map
        $group_map = array();
        foreach ($groups as $g) {
            if (isset($g['id'])) {
                $group_map[(string)$g['id']] = $g['name'] ?? '';
            }
        }

        $lines = array();
        $lines[] = implode(',', array_map(array($this, 'csv_escape'), $headers));

        foreach ($prompts as $p) {
            $gid = isset($p['group_id']) ? (string)$p['group_id'] : '';
            $row = array(
                $p['id'] ?? '',
                $p['title'] ?? '',
                $p['content'] ?? '',
                $gid,
                $group_map[$gid] ?? '',
                $p['provider'] ?? '',
                $p['type'] ?? 'text',
                $p['created_at'] ?? '',
                $p['updated_at'] ?? '',
            );
            $lines[] = implode(',', array_map(array($this, 'csv_escape'), $row));
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    /**
     * CSV escape helper
     *
     * @param mixed $value
     * @return string
     */
    private function csv_escape($value) {
        $v = (string) (is_null($value) ? '' : $value);
        // Normalise line breaks
        $v = str_replace(array("\r\n", "\r"), "\n", $v);
        // Quote if contains comma, quote or newline
        if ($v === '' || strpbrk($v, ",\"\n") !== false) {
            $v = '"' . str_replace('"', '""', $v) . '"';
        }
        return $v;
    }

    /**
     * AJAX: Import prompts
     *
     * @return void
     */
    public function ajax_import_prompts() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }

        if (!isset($_POST['import_data']) || empty($_POST['import_data'])) {
            wp_send_json_error(array('message' => __('No import data provided', 'opace-ai-prompt-library-api-hub')));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON is decoded here; each imported field is sanitised by the save methods.
        $import_data = json_decode(wp_unslash($_POST['import_data']), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => __('Invalid JSON data', 'opace-ai-prompt-library-api-hub')));
        }

        if (!isset($import_data['groups']) || !isset($import_data['prompts'])) {
            wp_send_json_error(array('message' => __('Invalid import format', 'opace-ai-prompt-library-api-hub')));
        }

        $group_id_map = array();
        $imported_groups = 0;
        $imported_prompts = 0;

        // Import groups
        foreach ($import_data['groups'] as $group) {
            if (!is_array($group)) {
                continue;
            }

            $old_id = isset($group['id']) ? (string) absint($group['id']) : '';
            $name = isset($group['name']) ? sanitize_text_field($group['name']) : '';

            if ('' === $old_id || '' === $name) {
                continue;
            }

            // Check if group with same name exists
            $existing_group = $this->get_group_by_name($name);

            if ($existing_group) {
                $group_id_map[$old_id] = (int) $existing_group['id'];
            } else {
                $result = $this->save_group(array(
                    'name' => $name,
                    'description' => isset($group['description']) ? sanitize_textarea_field($group['description']) : '',
                ));

                if (!is_wp_error($result)) {
                    $group_id_map[$old_id] = (int) $result;
                    $imported_groups++;
                }
            }
        }

        // Import prompts
        foreach ($import_data['prompts'] as $prompt) {
            if (!is_array($prompt)) {
                continue;
            }

            $group_id = null;
            if (!empty($prompt['group_id']) && isset($group_id_map[$prompt['group_id']])) {
                $group_id = $group_id_map[$prompt['group_id']];
            }

            $result = $this->save_prompt(array(
                'title' => isset($prompt['title']) ? sanitize_text_field($prompt['title']) : '',
                'content' => isset($prompt['content']) ? wp_kses_post($prompt['content']) : '',
                'group_id' => $group_id,
                'provider' => isset($prompt['provider']) ? sanitize_key($prompt['provider']) : '',
                'type' => isset($prompt['type']) ? sanitize_key($prompt['type']) : 'text',
            ));

            if (!is_wp_error($result)) {
                $imported_prompts++;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf(
                /* translators: 1: number of groups imported, 2: number of prompts imported. */
                __('Successfully imported %1$d groups and %2$d prompts', 'opace-ai-prompt-library-api-hub'),
                $imported_groups,
                $imported_prompts
            ),
            'groups' => $imported_groups,
            'prompts' => $imported_prompts,
        ));
    }

    /**
     * AJAX: Delete all prompts
     *
     * @return void
     */
    public function ajax_delete_all_prompts() {
        check_ajax_referer('ai_core_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'opace-ai-prompt-library-api-hub')));
        }

        global $wpdb;
        $prompts_table = $wpdb->prefix . 'ai_core_prompts';
        $groups_table = $wpdb->prefix . 'ai_core_prompt_groups';

        // Count prompts before deletion
        $prompt_count = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $prompts_table));
        $group_count = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i', $groups_table));

        // Delete all prompts
        $prompts_deleted = $wpdb->query($wpdb->prepare('DELETE FROM %i', $prompts_table));

        // Delete all groups
        $groups_deleted = $wpdb->query($wpdb->prepare('DELETE FROM %i', $groups_table));

        if ($prompts_deleted === false || $groups_deleted === false) {
            wp_send_json_error(array('message' => __('Failed to delete all prompts', 'opace-ai-prompt-library-api-hub')));
        }

        wp_send_json_success(array(
            'message' => sprintf(
                /* translators: 1: number of prompts deleted, 2: number of groups deleted. */
                __('Successfully deleted %1$d prompts and %2$d groups', 'opace-ai-prompt-library-api-hub'),
                $prompt_count,
                $group_count
            )
        ));
    }

    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}
