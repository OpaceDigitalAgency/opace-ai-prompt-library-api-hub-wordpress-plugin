/**
 * AI-Core Prompt Library JavaScript
 *
 * @package AI_Core
 * @version 0.6.5
 */

(function($) {
    'use strict';

    const translated = (key, fallback) => (
        window.aiCoreAdmin && aiCoreAdmin.strings && aiCoreAdmin.strings[key]
            ? aiCoreAdmin.strings[key]
            : fallback
    );
    const translatedFormat = (key, fallback, value) => translated(key, fallback).replace('%s', value);

    /**
     * Prompt Library Object
     */
    const PromptLibrary = {

        currentGroupId: null,
        currentPromptId: null,

        /**
         * Initialize
         */
        init: function() {
            // Check if aiCoreAdmin is available
            if (typeof aiCoreAdmin === 'undefined') {
                console.error('AI-Core: aiCoreAdmin object not found. Cannot initialize Prompt Library.');
                this.showError(translated('configurationError', 'Configuration error. Please refresh the page.'));
                return;
            }

            console.log('AI-Core Prompt Library initialized');
            this.bindEvents();
            this.initDragDrop();
            // No need to load groups/prompts - they're server-side rendered
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Group actions
            $(document).on('click', '#ai-core-new-group', this.showGroupModal.bind(this));
            $(document).on('click', '#ai-core-new-group-empty', this.showGroupModal.bind(this));
            $(document).on('click', '.edit-group', this.editGroup.bind(this));
            $(document).on('click', '.delete-group', this.deleteGroup.bind(this));
            $(document).on('click', '.add-prompt-to-group', this.addPromptToGroup.bind(this));
            $(document).on('click', '#ai-core-save-group', this.saveGroup.bind(this));
            $(document).on('click', '#ai-core-cancel-group', this.hideGroupModal.bind(this));

            // Prompt actions
            $(document).on('click', '#ai-core-new-prompt', this.showPromptModal.bind(this));
            $(document).on('click', '#ai-core-new-prompt-empty', this.showPromptModal.bind(this));
            $(document).on('click', '.edit-prompt', this.editPrompt.bind(this));
            $(document).on('click', '.delete-prompt', this.deletePrompt.bind(this));
            $(document).on('click', '#ai-core-save-prompt', this.savePrompt.bind(this));
            $(document).on('click', '#ai-core-cancel-prompt', this.hidePromptModal.bind(this));
            $(document).on('click', '.run-prompt', this.runPromptFromCard.bind(this));
            $(document).on('click', '#ai-core-test-prompt-modal', this.runPromptFromModal.bind(this));

            // Import/Export
            $(document).on('click', '#ai-core-export-prompts', this.showExportModal.bind(this));
            $(document).on('click', '#ai-core-import-prompts', this.showImportModal.bind(this));
            $(document).on('click', '#ai-core-do-import', this.importPrompts.bind(this));
            $(document).on('click', '#ai-core-cancel-import', this.hideImportModal.bind(this));
            $(document).on('click', '#ai-core-do-export', this.exportPrompts.bind(this));
            $(document).on('click', '#ai-core-cancel-export', this.hideExportModal.bind(this));

            // Delete All
            $(document).on('click', '#ai-core-delete-all-prompts', this.deleteAllPrompts.bind(this));

            // Search and filters
            $(document).on('input', '#ai-core-search-prompts', this.filterPrompts.bind(this));
            $(document).on('change', '#ai-core-filter-group', this.filterPrompts.bind(this));

            // Modal close
            $(document).on('click', '.ai-core-modal-close', this.closeModal.bind(this));
            $(document).on('click', '.ai-core-modal', function(e) {
                if ($(e.target).hasClass('ai-core-modal')) {
                    $(e.target).hide().removeClass('active');
                }
            });

            // Escape key to close modals
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.ai-core-modal').hide().removeClass('active');
                }
            });
        },

        /**
         * Initialize drag and drop
         */
        initDragDrop: function() {
            if (typeof $.fn.sortable === 'undefined') {
                console.warn('jQuery UI Sortable not available. Drag and drop disabled.');
                return;
            }

            console.log('Initializing drag and drop...');

            // Make group cards sortable (reorder groups)
            const $groupsContainer = $('.ai-core-groups-container');
            if ($groupsContainer.length) {
                $groupsContainer.sortable({
                    items: '> .ai-core-group-card',
                    handle: '.group-card-header',
                    placeholder: 'group-card-placeholder',
                    cursor: 'move',
                    opacity: 0.8,
                    tolerance: 'pointer',
                    helper: 'clone',
                    appendTo: 'body',
                    zIndex: 10000,
                    cancel: '.group-card-body, .group-card-body *, .button, .button-link, a, input, textarea, select',
                    distance: 8,
                    delay: 120,
                    containment: 'document',
                    start: function(event, ui) {
                        ui.item.addClass('dragging');
                        ui.helper.addClass('dragging-helper');
                    },
                    stop: function(event, ui) {
                        ui.item.removeClass('dragging');
                    },
                    update: function(event, ui) {
                        console.log('Group reordered');
                    }
                });
                $groupsContainer.disableSelection();
                setTimeout(function() {
                    try {
                        $groupsContainer.sortable('refresh');
                        $groupsContainer.sortable('refreshPositions');
                    } catch (e) {}
                }, 0);
                console.log('Groups container sortable initialized');
            }

            // Make prompts sortable within each group card body
            const $groupBodies = $('.group-card-body');
            if ($groupBodies.length) {
                $groupBodies.sortable({
                    items: '> .ai-core-prompt-card',
                    placeholder: 'prompt-card-placeholder',
                    cursor: 'move',
                    opacity: 0.85,
                    tolerance: 'pointer',
                    helper: 'clone',
                    connectWith: '.group-card-body',
                    appendTo: 'body',
                    zIndex: 10000,
                    cancel: '.prompt-card-actions button, .button, .run-prompt, a, input, textarea, select',
                    distance: 3,
                    containment: 'document',
                    forcePlaceholderSize: true,
                    forceHelperSize: true,
                    dropOnEmpty: true,
                    refreshPositions: true,
                    scroll: true,
                    scrollSensitivity: 60,
                    revert: 'invalid',
                    start: function(event, ui) {
                        ui.item.addClass('dragging');
                        ui.helper.addClass('dragging-helper');
                        // Improve drop hitboxes: switch grid to block and hide empty-state while sorting
                        $('.group-card-body')
                            .addClass('drop-target-active sorting')
                            .find('.group-empty-state').addClass('hidden');
                        const $origin = ui.item.closest('.group-card-body');
                        ui.item.data('origin-group-id', $origin.data('group-id'));
                        if ($groupsContainer && $groupsContainer.length) {
                            try { $groupsContainer.sortable('disable'); } catch (e) {}
                        }
                    },
                    stop: function(event, ui) {
                        ui.item.removeClass('dragging');
                        $('.group-card-body')
                            .removeClass('drop-target-active drop-hover sorting')
                            .find('.group-empty-state').removeClass('hidden');
                        if ($groupsContainer && $groupsContainer.length) {
                            try { $groupsContainer.sortable('enable'); } catch (e) {}
                        }
                    },
                    over: function(event, ui) {
                        $(event.target).closest('.group-card-body').addClass('drop-hover');
                    },
                    out: function(event, ui) {
                        $(event.target).closest('.group-card-body').removeClass('drop-hover');
                    },
                    receive: function(event, ui) {
                        const $target = $(event.target);
                        const $sender = $(ui.sender);
                        const newGroupId = $target.data('group-id');
                        const $dragged = ui.item;
                        const promptId = $dragged.data('prompt-id');

                        if (promptId !== undefined && newGroupId !== undefined) {
                            PromptLibrary.movePromptToGroup(promptId, newGroupId, { $sender: $sender, $target: $target, $dragged: $dragged });
                        }
                    },
                    update: function(event, ui) {
                        if (!ui.sender) {
                            // Reordered within same group; no server call yet
                        }
                    }
                });
                $groupBodies.disableSelection();
                // Ensure correct hitboxes after layout
                setTimeout(function() {
                    try {
                        $groupBodies.sortable('refresh');
                        $groupBodies.sortable('refreshPositions');
                    } catch (e) {}
                }, 0);
                console.log('Group bodies sortable initialized on', $groupBodies.length, 'elements');
                // Debounced refresh on resize to keep hitboxes accurate
                (function attachResizeRefresh() {
                    let _aiCoreResizeTimer = null;
                    $(window).off('resize.aiCorePromptLib').on('resize.aiCorePromptLib', function() {
                        if (_aiCoreResizeTimer) clearTimeout(_aiCoreResizeTimer);
                        _aiCoreResizeTimer = setTimeout(function() {
                            try {
                                $groupBodies.sortable('refresh');
                                $groupBodies.sortable('refreshPositions');
                                if ($groupsContainer && $groupsContainer.length) {
                                    $groupsContainer.sortable('refresh');
                                    $groupsContainer.sortable('refreshPositions');
                                }
                            } catch (e) {}
                        }, 150);
                    });
                })();
            }
        },

        /**
         * Reinitialize drag and drop after content update
         */
        reinitDragDrop: function() {
            // Destroy existing sortables
            if ($('.ai-core-groups-container').hasClass('ui-sortable')) {
                $('.ai-core-groups-container').sortable('destroy');
            }

            $('.group-card-body').each(function() {
                if ($(this).hasClass('ui-sortable')) {
                    $(this).sortable('destroy');
                }
            });

            // Reinitialize
            this.initDragDrop();
        },

        /**
         * Show error message
         */
        showError: function(message) {
            const $notice = $('<div class="notice notice-error is-dismissible"><p>' + this.escapeHtml(message) + '</p></div>');
            $('.ai-core-prompt-library h1').after($notice);
            setTimeout(() => $notice.fadeOut(() => $notice.remove()), 5000);
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            const $notice = $('<div class="notice notice-success is-dismissible"><p>' + this.escapeHtml(message) + '</p></div>');
            $('.ai-core-prompt-library h1').after($notice);
            setTimeout(() => $notice.fadeOut(() => $notice.remove()), 3000);
        },

        /**
         * Load groups - No longer needed with server-side rendering
         * Just reload the page to show updated content
         */
        loadGroups: function() {
            // Page is server-side rendered, just reload
            window.location.reload();
        },

        /**
         * Load prompts - No longer needed with server-side rendering
         * Just reload the page to show updated content
         */
        loadPrompts: function() {
            // Page is server-side rendered, just reload
            window.location.reload();
        },



        /**
         * Add prompt to specific group
         */
        addPromptToGroup: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $button = $(e.currentTarget);
            const $groupCard = $button.closest('.ai-core-group-card');
            const groupId = $groupCard.data('group-id');

            // Store the target group ID and show prompt modal
            this.currentGroupId = groupId;
            this.showPromptModal(e);
        },

        /**
         * Filter prompts
         */
        filterPrompts: function() {
            const searchTerm = $('#ai-core-search-prompts').val().toLowerCase();
            const filterGroup = $('#ai-core-filter-group').val();

            $('.ai-core-group-card').each(function() {
                const $groupCard = $(this);
                const groupId = $groupCard.data('group-id');

                // Filter by group
                if (filterGroup && filterGroup != groupId) {
                    $groupCard.hide();
                    return;
                }

                // Filter prompts within group by search term
                let visibleCount = 0;
                $groupCard.find('.ai-core-prompt-card').each(function() {
                    const $card = $(this);
                    const title = ($card.find('.prompt-card-header h4').text() || '').toLowerCase();
                    const content = ($card.find('.prompt-card-body').text() || '').toLowerCase();

                    if (!searchTerm || title.includes(searchTerm) || content.includes(searchTerm)) {
                        $card.show();
                        visibleCount++;
                    } else {
                        $card.hide();
                    }
                });

                // Show/hide group based on visible prompts
                if (visibleCount > 0 || !searchTerm) {
                    $groupCard.show();
                    $groupCard.find('.group-count').text(visibleCount);
                } else {
                    $groupCard.hide();
                }
            });
        },

        /**
         * Show group modal
         */
        showGroupModal: function(e) {
            if (e) e.preventDefault();

            $('#group-id').val('');
            $('#group-name').val('');
            $('#group-description').val('');
            $('#ai-core-group-modal-title').text(translated('newGroup', 'New Group'));
            $('#ai-core-group-modal').show().addClass('active');
        },

        /**
         * Hide group modal
         */
        hideGroupModal: function(e) {
            if (e) e.preventDefault();
            $('#ai-core-group-modal').hide().removeClass('active');
        },

        /**
         * Edit group
         */
        editGroup: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $button = $(e.currentTarget);
            const $groupCard = $button.closest('.ai-core-group-card');
            const groupId = $groupCard.data('group-id');

            // Get group data via AJAX
            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_get_groups',
                    nonce: aiCoreAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const group = response.data.groups.find(g => g.id == groupId);
                        if (group) {
                            $('#group-id').val(group.id);
                            $('#group-name').val(group.name);
                            $('#group-description').val(group.description || '');
                            $('#ai-core-group-modal-title').text(translated('editGroup', 'Edit Group'));
                            $('#ai-core-group-modal').show().addClass('active');
                        }
                    } else {
                        this.showError(translated('failedLoadGroup', 'Failed to load group data.'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Error loading group:', error);
                    this.showError(translated('networkLoadGroup', 'Network error while loading the group.'));
                }
            });
        },

        /**
         * Save group
         */
        saveGroup: function(e) {
            e.preventDefault();

            const groupId = $('#group-id').val();
            const name = $('#group-name').val();
            const description = $('#group-description').val();

            if (!name) {
                alert(translated('enterGroupName', 'Please enter a group name.'));
                return;
            }

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_save_group',
                    nonce: aiCoreAdmin.nonce,
                    group_id: groupId,
                    name: name,
                    description: description
                },
                success: (response) => {
                    if (response.success) {
                        this.hideGroupModal();
                        this.showSuccess(translated('groupSaved', 'Group saved successfully.'));
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        alert(translatedFormat('errorDetail', 'Error: %s', response.data.message));
                    }
                },
                error: (xhr, status, error) => {
                    alert(translatedFormat('saveGroupError', 'Error saving group: %s', error));
                }
            });
        },

        /**
         * Delete group
         */
        deleteGroup: function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (!confirm(translated('confirmDeleteGroup', 'Are you sure you want to delete this group? Prompts in this group will not be deleted.'))) {
                return;
            }

            const $button = $(e.currentTarget);
            const $groupCard = $button.closest('.ai-core-group-card');
            const groupId = $groupCard.data('group-id');

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_delete_group',
                    nonce: aiCoreAdmin.nonce,
                    group_id: groupId
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(translated('groupDeleted', 'Group deleted successfully.'));
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        alert(translatedFormat('errorDetail', 'Error: %s', response.data.message));
                    }
                },
                error: (xhr, status, error) => {
                    alert(translatedFormat('deleteGroupError', 'Error deleting group: %s', error));
                }
            });
        },

        /**
         * Show prompt modal
         */
        showPromptModal: function(e) {
            console.log('showPromptModal called');
            if (e) e.preventDefault();

            $('#prompt-id').val('');
            $('#prompt-title').val('');
            $('#prompt-content').val('');
            $('#prompt-group').val(this.currentGroupId || '');
            $('#prompt-provider').val('');
            $('#prompt-type').val('text');
            $('#ai-core-modal-title').text(translated('newPrompt', 'New Prompt'));
            $('#ai-core-prompt-result').hide().html('');

            const $modal = $('#ai-core-prompt-modal');
            console.log('Modal found:', $modal.length);
            $modal.show().addClass('active');
            $('#prompt-title').trigger('focus');
            console.log('Modal shown and active class added');
        },

        /**
         * Hide prompt modal
         */
        hidePromptModal: function(e) {
            if (e) e.preventDefault();
            $('#ai-core-prompt-modal').hide().removeClass('active');
        },

        /**
         * Edit prompt
         */
        editPrompt: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const promptId = $(e.currentTarget).data('prompt-id');

            // Get prompt data via AJAX
            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_get_prompts',
                    nonce: aiCoreAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const prompt = response.data.prompts.find(p => p.id == promptId);
                        if (prompt) {
                            $('#prompt-id').val(prompt.id);
                            $('#prompt-title').val(prompt.title);
                            $('#prompt-content').val(prompt.content);
                            $('#prompt-group').val(prompt.group_id || '');
                            $('#prompt-provider').val(prompt.provider || '');
                            $('#prompt-type').val(prompt.type || 'text');
                            $('#ai-core-modal-title').text(translated('editPrompt', 'Edit Prompt'));
                            $('#ai-core-prompt-result').hide().html('');
                            $('#ai-core-prompt-modal').addClass('active');
                        }
                    }
                }
            });
        },

        /**
         * Save prompt
         */
        savePrompt: function(e) {
            e.preventDefault();

            const promptId = $('#prompt-id').val();
            const title = $('#prompt-title').val();
            const content = $('#prompt-content').val();
            const groupId = $('#prompt-group').val();
            const provider = $('#prompt-provider').val();
            const type = $('#prompt-type').val();

            if (!title || !content) {
                alert(translated('enterTitleContent', 'Please enter a title and content.'));
                return;
            }

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_save_prompt',
                    nonce: aiCoreAdmin.nonce,
                    prompt_id: promptId,
                    title: title,
                    content: content,
                    group_id: groupId,
                    provider: provider,
                    type: type
                },
                success: (response) => {
                    if (response.success) {
                        this.hidePromptModal();
                        this.showSuccess(translated('promptSaved', 'Prompt saved successfully.'));
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        alert(translatedFormat('errorDetail', 'Error: %s', response.data.message));
                    }
                },
                error: (xhr, status, error) => {
                    alert(translatedFormat('savePromptError', 'Error saving prompt: %s', error));
                }
            });
        },

        /**
         * Move prompt to a different group (via drag and drop)
         * Optimistic UI update without full page reload
         */
        movePromptToGroup: function(promptId, newGroupId, ctx) {
            if (promptId === undefined || newGroupId === undefined) {
                return;
            }

            ctx = ctx || {};
            const $sender  = ctx.$sender || null;   // origin .group-card-body
            const $target  = ctx.$target || null;   // destination .group-card-body
            const $dragged = ctx.$dragged || null;  // dragged .ai-core-prompt-card

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_move_prompt',
                    nonce: aiCoreAdmin.nonce,
                    prompt_id: promptId,
                    group_id: newGroupId
                }
            }).done((response) => {
                if (response && response.success) {
                    // Update data attributes on the moved card
                    if ($dragged && $dragged.length) {
                        $dragged.attr('data-group-id', newGroupId);
                        $dragged.data('group-id', newGroupId);
                    }

                    // Helper to adjust visible badge count
                    const adjustCount = ($container, delta) => {
                        if (!$container || !$container.length) return;
                        const $card  = $container.closest('.ai-core-group-card');
                        const $badge = $card.find('.group-card-title .group-count').first();
                        const current = parseInt(($badge.text() || '0').trim(), 10) || 0;
                        $badge.text(Math.max(0, current + delta));
                    };

                    // Destination: ensure empty-state removed and count incremented
                    if ($target && $target.length) {
                        $target.find('.group-empty-state').remove();
                        adjustCount($target, +1);
                    }

                    // Origin: if provided and different, decrement and add empty-state if none left
                    if ($sender && $sender.length && (!$target || $sender[0] !== $target[0])) {
                        adjustCount($sender, -1);
                        if ($sender.find('.ai-core-prompt-card').length === 0) {
                            if (!$sender.find('.group-empty-state').length) {
                                $sender.append(
                                    '<div class="group-empty-state">' +
                                        '<span class="dashicons dashicons-admin-post"></span>' +
                                        '<p>' + this.escapeHtml(translated('noPromptsGroup', 'No prompts in this group')) + '</p>' +
                                        '<p class="description">' + this.escapeHtml(translated('dragPromptsHere', 'Drag prompts here or click + to add')) + '</p>' +
                                    '</div>'
                                );
                            }
                        }
                    }

                    this.showSuccess(translated('promptMoved', 'Prompt moved successfully.'));
                } else {
                    this.showError(response?.data?.message || translated('failedMovePrompt', 'Failed to move prompt.'));
                    // Revert DOM move on failure
                    if ($sender && $sender.length && $dragged && $dragged.length) {
                        $sender.append($dragged);
                    }
                }
            }).fail((_xhr, _status, error) => {
                console.error('Error moving prompt:', error);
                this.showError(translated('networkMovePrompt', 'Network error while moving the prompt.'));
                if ($sender && $sender.length && $dragged && $dragged.length) {
                    $sender.append($dragged);
                }
            });
        },

        /**
         * Move prompt (reorder within same group)
         */
        movePrompt: function(promptId, groupId) {
            // This is for reordering within the same group
            // For now, we'll just reload to show the new order
            console.log('Reordering prompt', promptId, 'in group', groupId);
        },

        /**
         * Delete prompt
         */
        deletePrompt: function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (!confirm(translated('confirmDeletePrompt', 'Are you sure you want to delete this prompt?'))) {
                return;
            }

            const promptId = $(e.currentTarget).data('prompt-id');

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_delete_prompt',
                    nonce: aiCoreAdmin.nonce,
                    prompt_id: promptId
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(translated('promptDeleted', 'Prompt deleted successfully.'));
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        alert(translatedFormat('errorDetail', 'Error: %s', response.data.message));
                    }
                },
                error: (xhr, status, error) => {
                    alert(translatedFormat('deletePromptError', 'Error deleting prompt: %s', error));
                }
            });
        },

        /**
         * Run prompt from card
         */
        runPromptFromCard: function(e) {
            e.preventDefault();
            e.stopPropagation();

            const $button = $(e.currentTarget);
            const $card = $button.closest('.ai-core-prompt-card');
            const promptId = $button.data('prompt-id');

            // Show immediate loading feedback
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + translated('running', 'Running...'));

            // Remove any existing result
            $card.find('.prompt-card-result').remove();

            // Get prompt data and run it
            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_get_prompts',
                    nonce: aiCoreAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const prompt = response.data.prompts.find(p => p.id == promptId);
                        if (prompt) {
                            this.runPromptInCard($card, prompt.content, prompt.provider, prompt.type, $button);
                        } else {
                            this.showCardError($card, translated('promptNotFound', 'Prompt not found.'), $button);
                        }
                    } else {
                        this.showCardError($card, response.data?.message || translated('failedLoadPrompt', 'Failed to load prompt.'), $button);
                    }
                },
                error: (xhr, status, error) => {
                    this.showCardError($card, translatedFormat('networkErrorDetail', 'Network error: %s', error), $button);
                }
            });
        },

        /**
         * Run prompt in card
         */
        runPromptInCard: function($card, content, provider, type, $button) {
            // Create result container
            const $result = $('<div class="prompt-card-result"><div class="loading"><span class="ai-core-spinner"></span> ' + this.escapeHtml(translated('generatingResponse', 'Generating response...')) + '</div></div>');
            $card.find('.prompt-card-footer').after($result);

            // If no provider specified, try to get default from settings
            if (!provider || provider === 'default' || provider === '') {
                provider = aiCoreAdmin.providers?.default || 'openai'; // Use default provider from settings
            }

            // Get the selected model for this provider from settings
            let model = '';
            if (aiCoreAdmin.providers?.selectedModels && aiCoreAdmin.providers.selectedModels[provider]) {
                model = aiCoreAdmin.providers.selectedModels[provider];
            }

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_run_prompt',
                    nonce: aiCoreAdmin.nonce,
                    prompt: content,
                    provider: provider,
                    model: model,
                    type: type
                },
                success: (response) => {
                    if (response.success) {
                        if (response.data.type === 'image') {
                            $result.html(`
                                <div class="result-success">
                                    <img src="${response.data.result}" alt="${this.escapeHtml(translated('generatedImage', 'Generated image'))}" style="max-width: 100%; height: auto; border-radius: 4px;" />
                                </div>
                            `);
                        } else {
                            $result.html(`
                                <div class="result-success">
                                    <pre>${this.escapeHtml(response.data.result)}</pre>
                                </div>
                            `);
                        }
                    } else {
                        $result.html(`
                            <div class="result-error">
                                <span class="dashicons dashicons-warning"></span>
                                <strong>${this.escapeHtml(translated('error', 'Error'))}:</strong> ${this.escapeHtml(response.data?.message || translated('unknownError', 'Unknown error'))}
                            </div>
                        `);
                    }

                    // Reset button
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> ' + translated('run', 'Run'));
                },
                error: (xhr, status, error) => {
                    let errorMsg = translated('networkError', 'Network error');
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    } else if (error) {
                        errorMsg = error;
                    }

                    $result.html(`
                        <div class="result-error">
                            <span class="dashicons dashicons-warning"></span>
                            <strong>${this.escapeHtml(translated('error', 'Error'))}:</strong> ${this.escapeHtml(errorMsg)}
                        </div>
                    `);

                    // Reset button
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> ' + translated('run', 'Run'));
                }
            });
        },

        /**
         * Show error in card
         */
        showCardError: function($card, message, $button) {
            const $result = $('<div class="prompt-card-result"><div class="result-error"><span class="dashicons dashicons-warning"></span> <strong>' + this.escapeHtml(translated('error', 'Error')) + ':</strong> ' + this.escapeHtml(message) + '</div></div>');
            $card.find('.prompt-card-footer').after($result);

            // Reset button
            $button.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> ' + translated('run', 'Run'));
        },

        /**
         * Run prompt from modal
         */
        runPromptFromModal: function(e) {
            e.preventDefault();

            const content = $('#prompt-content').val();
            let provider = $('#prompt-provider').val();
            const type = $('#prompt-type').val();

            if (!content) {
                alert(translated('enterPromptContent', 'Please enter prompt content.'));
                return;
            }

            // If no provider specified, use default from settings
            if (!provider || provider === 'default' || provider === '') {
                provider = aiCoreAdmin.providers?.default || 'openai';
            }

            // Get the selected model for this provider from settings
            let model = '';
            if (aiCoreAdmin.providers?.selectedModels && aiCoreAdmin.providers.selectedModels[provider]) {
                model = aiCoreAdmin.providers.selectedModels[provider];
            }

            this.runPrompt(content, provider, type, model);
        },

        /**
         * Run prompt in modal
         */
        runPrompt: function(content, provider, type, model) {
            const $result = $('#ai-core-prompt-result');
            $result.show().html('<div class="loading"><span class="ai-core-spinner"></span> ' + this.escapeHtml(translated('runningPrompt', 'Running prompt...')) + '</div>');

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_run_prompt',
                    nonce: aiCoreAdmin.nonce,
                    prompt: content,
                    provider: provider,
                    model: model || '',
                    type: type
                },
                success: (response) => {
                    if (response.success) {
                        if (response.data.type === 'image') {
                            $result.html(`<img src="${response.data.result}" alt="${this.escapeHtml(translated('generatedImage', 'Generated image'))}" style="max-width: 100%; height: auto;" />`);
                        } else {
                            $result.html(`<pre>${this.escapeHtml(response.data.result)}</pre>`);
                        }
                    } else {
                        $result.html(`<div class="error"><span class="dashicons dashicons-warning"></span> ${this.escapeHtml(translated('error', 'Error'))}: ${this.escapeHtml(response.data?.message || translated('unknownError', 'Unknown error'))}</div>`);
                    }
                },
                error: (xhr, status, error) => {
                    let errorMsg = translated('networkError', 'Network error');
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    } else if (error) {
                        errorMsg = error;
                    }
                    $result.html(`<div class="error"><span class="dashicons dashicons-warning"></span> ${this.escapeHtml(translated('error', 'Error'))}: ${this.escapeHtml(errorMsg)}</div>`);
                }
            });
        },

        /**
         * Export prompts
         */
        exportPrompts: function(e) {
            console.log('exportPrompts called');
            e.preventDefault();

            const format = ($('#ai-core-export-format').val() || 'json').toLowerCase();
            const version = $('#ai-core-export-version').val() || '1.0';

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_export_prompts',
                    nonce: aiCoreAdmin.nonce,
                    format: format,
                    version: version
                },
                success: (response) => {
                    console.log('Export response:', response);
                    if (response.success) {
                        if (format === 'csv') {
                            const groupsCsv = response.data.groups_csv;
                            const promptsCsv = response.data.prompts_csv;
                            const groupsFilename = response.data.groups_filename || 'ai-core-groups.csv';
                            const promptsFilename = response.data.prompts_filename || 'ai-core-prompts.csv';

                            if (groupsCsv) {
                                const gBlob = new Blob([groupsCsv], {type: 'text/csv;charset=utf-8'});
                                const gUrl = URL.createObjectURL(gBlob);
                                const gLink = document.createElement('a');
                                gLink.href = gUrl;
                                gLink.download = groupsFilename;
                                gLink.click();
                                URL.revokeObjectURL(gUrl);
                            }

                            if (promptsCsv) {
                                const pBlob = new Blob([promptsCsv], {type: 'text/csv;charset=utf-8'});
                                const pUrl = URL.createObjectURL(pBlob);
                                const pLink = document.createElement('a');
                                pLink.href = pUrl;
                                pLink.download = promptsFilename;
                                pLink.click();
                                URL.revokeObjectURL(pUrl);
                            }

                            this.showSuccess(translated('csvExportComplete', 'CSV export completed.'));
                        } else {
                            const dataStr = JSON.stringify(response.data.data, null, 2);
                            const dataBlob = new Blob([dataStr], {type: 'application/json'});
                            const url = URL.createObjectURL(dataBlob);
                            const link = document.createElement('a');
                            link.href = url;
                            link.download = response.data.filename || 'ai-core-prompts-export.json';
                            link.click();
                            URL.revokeObjectURL(url);
                            console.log('Export download triggered');
                            this.showSuccess(translated('promptsExported', 'Prompts exported successfully!'));
                        }

                        this.hideExportModal();
                    } else {
                        this.showError(response.data.message || translated('exportError', 'Error exporting prompts.'));
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Export failed:', status, error);
                    this.showError(translatedFormat('networkExportError', 'Network error exporting prompts: %s', error));
                }
            });
        },

        /**
         * Show import modal
         */
        showImportModal: function(e) {
            console.log('showImportModal called');
            e.preventDefault();
            $('#ai-core-import-file').val('');
            const $modal = $('#ai-core-import-modal');
            console.log('Import modal found:', $modal.length);
            $modal.show().addClass('active');
            console.log('Import modal shown and active class added');
        },

        /**
         * Hide import modal
         */
        hideImportModal: function(e) {
            if (e) e.preventDefault();
            $('#ai-core-import-modal').hide().removeClass('active');
        },

        /**
         * Show export modal
         */
        showExportModal: function(e) {
            console.log('showExportModal called');
            e.preventDefault();
            const $modal = $('#ai-core-export-modal');
            $modal.show().addClass('active');
            console.log('Export modal shown and active class added');
        },

        /**
         * Hide export modal
         */
        hideExportModal: function(e) {
            if (e) e.preventDefault();
            $('#ai-core-export-modal').hide().removeClass('active');
        },

        /**
         * Import prompts
         */
        importPrompts: function(e) {
            e.preventDefault();

            const fileInput = document.getElementById('ai-core-import-file');
            const file = fileInput.files[0];

            if (!file) {
                this.showError(translated('selectFile', 'Please select a file.'));
                return;
            }

            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const data = JSON.parse(e.target.result);

                    $.ajax({
                        url: aiCoreAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'ai_core_import_prompts',
                            nonce: aiCoreAdmin.nonce,
                            import_data: JSON.stringify(data)
                        },
                        success: (response) => {
                            if (response.success) {
                                this.hideImportModal();
                                this.loadGroups();
                                this.loadPrompts();
                                this.showSuccess(response.data.message || translated('importSuccessful', 'Import successful!'));
                            } else {
                                this.showError(response.data.message || translated('importError', 'Error importing prompts.'));
                            }
                        },
                        error: (xhr, status, error) => {
                            this.showError(translatedFormat('networkImportError', 'Network error importing prompts: %s', error));
                        }
                    });
                } catch (error) {
                    this.showError(translatedFormat('invalidJson', 'Invalid JSON file: %s', error.message));
                }
            };
            reader.readAsText(file);
        },

        /**
         * Delete all prompts
         */
        deleteAllPrompts: function(e) {
            e.preventDefault();

            if (!confirm(translated('confirmDeleteAll', 'Are you sure you want to delete ALL prompts and groups? This action cannot be undone!'))) {
                return;
            }

            // Double confirmation for safety
            if (!confirm(translated('confirmDeleteAllAgain', 'This will permanently delete everything in your Prompt Library. Are you absolutely sure?'))) {
                return;
            }

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_delete_all_prompts',
                    nonce: aiCoreAdmin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccess(response.data.message || translated('allPromptsDeleted', 'All prompts deleted successfully.'));
                        // Reload page to show empty state
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        this.showError(response.data.message || translated('deleteAllError', 'Error deleting prompts.'));
                    }
                },
                error: (xhr, status, error) => {
                    this.showError(translatedFormat('networkDeleteAllError', 'Network error deleting prompts: %s', error));
                }
            });
        },

        /**
         * Close modal
         */
        closeModal: function(e) {
            const $m = $(e.currentTarget).closest('.ai-core-modal');
            $m.hide().removeClass('active');
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        },

        /**
         * Truncate text
         */
        truncateText: function(text, length) {
            if (text.length <= length) return text;
            return text.substr(0, length) + '...';
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        console.log('Prompt Library script loaded');
        console.log('jQuery version:', $.fn.jquery);
        console.log('aiCoreAdmin available:', typeof aiCoreAdmin !== 'undefined');
        console.log('.ai-core-prompt-library found:', $('.ai-core-prompt-library').length);

        if ($('.ai-core-prompt-library').length) {
            console.log('Initializing Prompt Library...');
            PromptLibrary.init();
        } else {
            console.warn('Prompt Library container not found. Skipping initialization.');
        }
    });

})(jQuery);
