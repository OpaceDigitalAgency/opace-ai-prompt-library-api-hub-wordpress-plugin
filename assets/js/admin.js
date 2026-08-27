/**
 * AI-Core Admin JavaScript
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

    const initialSelectedModels = (aiCoreAdmin.providers && aiCoreAdmin.providers.selectedModels) || {};
    const providerModelsMap = {};
    Object.keys(initialSelectedModels).forEach((provider) => {
        providerModelsMap[provider] = { selected: initialSelectedModels[provider] };
    });

    const state = {
        debounceTimers: {},
        models: (aiCoreAdmin.providers && aiCoreAdmin.providers.models) || {},
        configured: new Set(aiCoreAdmin.providers && aiCoreAdmin.providers.configured ? aiCoreAdmin.providers.configured : []),
        sources: $.extend({}, (aiCoreAdmin.providers && aiCoreAdmin.providers.sources) || {}),
        defaultProvider: aiCoreAdmin.providers && aiCoreAdmin.providers.default ? aiCoreAdmin.providers.default : '',
        saving: {},
        // Keys typed in this page session only. The server never sends a key
        // to the browser, so this is empty after a reload - by design.
        sessionKeys: {},
        providerModels: providerModelsMap,
        providerOptions: $.extend(true, {}, (aiCoreAdmin.providers && aiCoreAdmin.providers.options) || {}),
        modelMeta: $.extend(true, {}, (aiCoreAdmin.providers && aiCoreAdmin.providers.meta) || {}),
        credentialValidation: $.extend({}, (aiCoreAdmin.providers && aiCoreAdmin.providers.validation) || {}),
        providerCapabilities: {}
    };

    const Admin = {
        init: function() {
            if (typeof aiCoreAdmin === 'undefined') {
                return;
            }

            this.bindEvents();
            this.bootstrapProviders();
            this.bootstrapTestPrompt();
            this.initThemeToggle();
        },

        /**
         * Dark mode toggle, mirroring AI-Scribe's wizard control.
         *
         * One source of truth: the "ai-scribe-theme" localStorage key the
         * wizard already reads and writes. An early admin_head script has
         * applied any stored choice before paint; this only adds the control
         * and persists changes. No stored choice means the stylesheet follows
         * prefers-color-scheme, so the starting state is read from the
         * document, falling back to the system preference.
         */
        initThemeToggle: function() {
            const $heading = $('.wrap > h1').first();
            if (!$heading.length || $('.ai-core-theme-toggle').length) {
                return;
            }

            const label = (aiCoreAdmin.strings && aiCoreAdmin.strings.toggleTheme) || 'Toggle dark mode';
            const current = () => {
                const set = document.documentElement.getAttribute('data-theme');
                if (set === 'dark' || set === 'light') {
                    return set;
                }
                return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
            };

            const $toggle = $('<button>', {
                type: 'button',
                'class': 'button ai-core-theme-toggle',
                'aria-label': label,
                title: label
            }).append($('<span>', { 'class': 'ai-core-theme-toggle-icon', 'aria-hidden': 'true' }));

            const render = () => {
                const theme = current();
                $toggle.attr('aria-pressed', theme === 'dark' ? 'true' : 'false');
                // Sun offers the way back to light; moon offers dark.
                $toggle.find('.ai-core-theme-toggle-icon').text(theme === 'dark' ? '☀' : '☾');
            };

            $toggle.on('click', () => {
                const next = current() === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', next);
                try {
                    window.localStorage.setItem('ai-scribe-theme', next);
                } catch (e) {
                    // Storage unavailable: the attribute still applies for this page.
                }
                render();
            });

            render();
            // Core's own pattern for a control beside the page title.
            $heading.addClass('wp-heading-inline').after($toggle);
        },

        bindEvents: function() {
            $(document).on('input', '.ai-core-api-key-input', this.onKeyInput.bind(this));
            $(document).on('blur', '.ai-core-api-key-input', this.onKeyBlur.bind(this));
            $(document).on('click', '.ai-core-test-key', this.testApiKey.bind(this));
            $(document).on('click', '.ai-core-refresh-models', this.onRefreshModels.bind(this));
            $(document).on('click', '.ai-core-provider-refresh', this.onRefreshModels.bind(this));
            $(document).on('click', '.ai-core-clear-key', this.onClearKey.bind(this));
           $(document).on('change', '.ai-core-provider-model', this.onProviderModelChange.bind(this));
           $(document).on('change', '#default_provider', this.onDefaultProviderChange.bind(this));
            $(document).on('change', '#ai-core-test-provider', (event) => {
                this.onTestProviderChange($(event.currentTarget).val(), { initialise: true });
                this.updateTypeDropdown();
            });
            $(document).on('change', '#ai-core-test-model', () => {
                this.updateTypeDropdown();
            });
            $(document).on('input change', '.ai-core-param-input', this.onParameterChange.bind(this));
            $(document).on('click', '#ai-core-refresh-prompts', this.loadPromptsList.bind(this));
            $(document).on('click', '#ai-core-refresh-pricing', this.refreshPricing.bind(this));
            $(document).on('change', '#persist_on_uninstall', this.updateRetentionSummary.bind(this));
            $(document).on('change', '#ai-core-load-prompt', this.loadPromptContent.bind(this));
            $(document).on('click', '#ai-core-run-test-prompt', this.runTestPrompt.bind(this));
            $(document).on('click', '#ai-core-reset-stats', this.resetStats.bind(this));
        },

        /**
         * Whether a key is stored server-side for this provider.
         *
         * The markup carries a boolean, never the key itself.
         */
        hasSavedKey: function(provider) {
            return $('#' + provider + '_api_key').attr('data-has-saved') === '1';
        },

        hasConfiguredProvider: function(provider) {
            return state.configured.has(provider);
        },

        setSavedKeyFlag: function(provider, hasKey) {
            const flag = hasKey ? '1' : '0';
            $('#' + provider + '_api_key').attr('data-has-saved', flag);
            $('.ai-core-api-key-field[data-provider="' + provider + '"]').attr('data-has-saved', flag);
        },

        renderCredentialState: function(provider, status) {
            const $field = $('.ai-core-api-key-field[data-provider="' + provider + '"]');
            if (!$field.length || !status) {
                return;
            }

            let $description = $field.siblings('.ai-core-credential-state').first();
            if (!$description.length) {
                $description = $('<p>', { 'class': 'description ai-core-credential-state' });
                $field.after($description);
            }

            let label = aiCoreAdmin.strings.credentialUntested;
            let detail = aiCoreAdmin.strings.credentialUntestedDetail;
            let icon = 'dashicons-info-outline';
            if (status === 'validated') {
                label = aiCoreAdmin.strings.credentialValid;
                detail = aiCoreAdmin.strings.credentialValidDetail;
                icon = 'dashicons-yes-alt';
            } else if (status === 'invalid') {
                label = aiCoreAdmin.strings.credentialInvalid;
                detail = aiCoreAdmin.strings.credentialInvalidDetail;
                icon = 'dashicons-warning';
            }

            $description
                .attr('class', 'description ai-core-credential-state ai-core-credential-state--' + status)
                .empty()
                .append($('<span>', { 'class': 'dashicons ' + icon, 'aria-hidden': 'true' }))
                .append(document.createTextNode(' '))
                .append($('<strong>').text(label + '.'))
                .append(document.createTextNode(' ' + detail));
        },

        bootstrapProviders: function() {
            const $cards = $('.ai-core-provider-card');
            $cards.each((_, card) => {
                const provider = $(card).data('provider');
                if (!provider) {
                    return;
                }

                if (state.configured.has(provider)) {
                    this.markProviderConnected(provider);
                } else {
                    this.markProviderDisconnected(provider);
                }

                const storedModels = state.models[provider];
                if (storedModels && storedModels.length) {
                    this.populateProviderModels(provider, storedModels);
                } else if (state.configured.has(provider)) {
                    this.fetchModels(provider, { showStatus: false });
                }
            });

            if (!state.defaultProvider && state.configured.size) {
                state.defaultProvider = Array.from(state.configured)[0];
            }

            if (state.defaultProvider) {
                $('#default_provider').val(state.defaultProvider);
                $('#ai-core-test-provider').val(state.defaultProvider);
            }
        },

        bootstrapTestPrompt: function() {
            if ($('#ai-core-load-prompt').length) {
                this.loadPromptsList();
            }

            const provider = $('#ai-core-test-provider').val();
            if (provider) {
                this.onTestProviderChange(provider, { initialise: true });
            }

            // Fetch provider capabilities and initialize type dropdown
            this.fetchProviderCapabilities().then(() => {
                this.updateTypeDropdown();
            });
        },

        onKeyInput: function(event) {
            const $input = $(event.currentTarget);
            const provider = $input.data('provider');
            if (!provider) {
                return;
            }

            const value = $.trim($input.val());
            const $status = $('#' + provider + '-status');

            if (!value) {
                this.showStatus($status, 'notice', aiCoreAdmin.strings.awaitingKey);
                return;
            }

            if (value.length < 12) {
                this.showStatus($status, 'notice', aiCoreAdmin.strings.keyTooShort);
                return;
            }

            if (state.debounceTimers[provider]) {
                clearTimeout(state.debounceTimers[provider]);
            }

            this.showStatus($status, 'notice', aiCoreAdmin.strings.saving);

            state.debounceTimers[provider] = setTimeout(() => {
                this.saveApiKey(provider, value, $input, $status);
            }, 600);
        },

        onKeyBlur: function(event) {
            const $input = $(event.currentTarget);
            const provider = $input.data('provider');
            if (!provider) {
                return;
            }

            if (state.debounceTimers[provider]) {
                clearTimeout(state.debounceTimers[provider]);
                delete state.debounceTimers[provider];
            }

            const value = $.trim($input.val());
            const $status = $('#' + provider + '-status');

            if (value && value.length >= 12) {
                this.saveApiKey(provider, value, $input, $status);
            }
        },

        onRefreshModels: function(event) {
            event.preventDefault();
            const provider = $(event.currentTarget).data('provider');
            if (!provider) {
                return;
            }
            this.fetchModels(provider, { force: true, showStatus: true });
        },

        onClearKey: function(event) {
            event.preventDefault();
            const $button = $(event.currentTarget);
            const fieldName = $button.data('field');
            const provider = fieldName.replace('_api_key', '');

            if (!provider || !confirm(aiCoreAdmin.strings.confirmClear)) {
                return;
            }

            this.clearApiKey(provider);
        },

        onProviderModelChange: function(event) {
            const $select = $(event.currentTarget);
            const provider = $select.data('provider');
            const model = $select.val();

            if (!provider) {
                return;
            }

            if (!state.providerModels[provider]) {
                state.providerModels[provider] = {};
            }

            state.providerModels[provider].selected = model;

            this.renderProviderParameters(provider, model);

            const currentTestProvider = $('#ai-core-test-provider').val();
            if (currentTestProvider === provider) {
                $('#ai-core-test-model').val(model);
            }
        },

        onDefaultProviderChange: function(event) {
            const provider = $(event.currentTarget).val();
            if (!provider) {
                return;
            }

            state.defaultProvider = provider;
            this.onTestProviderChange(provider, { initialise: true });
        },

        saveApiKey: function(provider, apiKey, $input, $status) {
            if (state.saving[provider]) {
                return;
            }

            if (state.sessionKeys[provider] === apiKey) {
                this.showStatus($status, 'success', aiCoreAdmin.strings.alreadySaved);
                $input.val('');
                return;
            }

            state.saving[provider] = true;

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_save_api_key',
                    nonce: aiCoreAdmin.nonce,
                    provider: provider,
                    api_key: apiKey
                }
            }).done((response) => {
                if (!response || !response.success) {
                    const message = response && response.data && response.data.message ? response.data.message : aiCoreAdmin.strings.error;
                    this.showStatus($status, 'error', message);
                    return;
                }

                this.onKeySaved(provider, apiKey, response.data, $input, $status);
            }).fail((xhr, status, error) => {
                this.showStatus($status, 'error', error || status || aiCoreAdmin.strings.error);
            }).always(() => {
                delete state.saving[provider];
            });
        },

        onKeySaved: function(provider, apiKey, data, $input, $status) {
            state.sessionKeys[provider] = apiKey;

            $input.val('').attr('placeholder', data.masked_key || aiCoreAdmin.strings.savedPlaceholder);
            this.setSavedKeyFlag(provider, true);

            this.showStatus($status, 'success', aiCoreAdmin.strings.saved);

            const $refreshButton = $('.ai-core-refresh-models[data-provider="' + provider + '"]');
            $refreshButton.prop('disabled', false);

            let $clearButton = $('.ai-core-clear-key[data-field="' + provider + '_api_key"]').first();
            if (!$clearButton.length) {
                $clearButton = $('<button></button>', {
                    type: 'button',
                    class: 'button ai-core-clear-key',
                    'data-field': provider + '_api_key'
                }).text(aiCoreAdmin.strings.clearKey);
                $refreshButton.after($clearButton);
            }

            $clearButton.prop('disabled', false);

            state.configured.add(provider);
            state.sources[provider] = data.source || 'ai_core';
            state.credentialValidation[provider] = data.credential_status || 'validated';
            this.renderCredentialState(provider, state.credentialValidation[provider]);
            this.markProviderConnected(provider);

            if (data.model_meta) {
                this.updateModelMeta(provider, data.model_meta);
            }

            if (data.parameters) {
                state.providerOptions[provider] = state.providerOptions[provider] || {};
                Object.keys(data.parameters).forEach((paramKey) => {
                    if (state.providerOptions[provider][paramKey] === undefined && data.parameters[paramKey].default !== undefined) {
                        state.providerOptions[provider][paramKey] = data.parameters[paramKey].default;
                    }
                });
            }

            if (Array.isArray(data.models)) {
                // The stored choice wins; the computed preference only fills a gap.
                const preferred = data.selected_model || data.preferred_model || (data.models.length ? data.models[0] : '');
                state.models[provider] = data.models;
                this.populateProviderModels(provider, data.models, { selected: preferred });
            } else {
                this.fetchModels(provider, { force: true, showStatus: false });
            }

            if (data.default_provider) {
                state.defaultProvider = data.default_provider;
                $('#default_provider').val(state.defaultProvider);
                $('#ai-core-test-provider').val(state.defaultProvider);
                this.onTestProviderChange(state.defaultProvider, { initialise: true });
            }

            this.ensureProviderOptionExists(provider);
        },

        fetchModels: function(provider, options = {}) {
            if (!this.hasConfiguredProvider(provider)) {
                this.markProviderDisconnected(provider);
                return;
            }

            const $status = $('#' + provider + '-status');
            const $refreshButtons = $('.ai-core-refresh-models[data-provider="' + provider + '"], .ai-core-provider-refresh[data-provider="' + provider + '"]');
            if (options.showStatus) {
                this.showStatus($status, 'notice', aiCoreAdmin.strings.refreshing);
                this.setRefreshBusy($refreshButtons, true);
            }

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    // No api_key: the handler falls back to the stored key,
                    // which keeps the secret on the server.
                    action: 'ai_core_get_models',
                    nonce: aiCoreAdmin.nonce,
                    provider: provider,
                    force_refresh: options.force ? 1 : 0
                }
            }).done((response) => {
                if (response && response.success) {
                    state.models[provider] = response.data.models;
                    if (response.data.model_meta) {
                        this.updateModelMeta(provider, response.data.model_meta);
                    }

                    if (response.data.parameters) {
                        state.providerOptions[provider] = state.providerOptions[provider] || {};
                        Object.keys(response.data.parameters).forEach((paramKey) => {
                            if (state.providerOptions[provider][paramKey] === undefined && response.data.parameters[paramKey].default !== undefined) {
                                state.providerOptions[provider][paramKey] = response.data.parameters[paramKey].default;
                            }
                        });
                    }

                    this.populateProviderModels(provider, response.data.models, { selected: response.data.preferred_model });

                    if (options.showStatus) {
                        this.showStatus($status, 'success', aiCoreAdmin.strings.modelsLoaded);
                    }
                } else if (options.showStatus) {
                    const message = response && response.data && response.data.message ? response.data.message : aiCoreAdmin.strings.errorLoadingModels;
                    this.showStatus($status, 'error', message);
                }
            }).fail((xhr, status, error) => {
                if (options.showStatus) {
                    this.showStatus($status, 'error', error || status || aiCoreAdmin.strings.errorLoadingModels);
                }
            }).always(() => {
                if (options.showStatus) {
                    this.setRefreshBusy($refreshButtons, false);
                }
            });
        },

        /**
         * Show or clear in-progress state on a Refresh Models control.
         *
         * The request already reported progress in the status area, but the
         * button itself sat inert, which read as "did nothing" (L-04). While
         * a refresh runs the button is disabled and carries a spinner; its
         * original label is restored afterwards.
         */
        setRefreshBusy: function($buttons, busy) {
            $buttons.each(function() {
                const $btn = $(this);
                if (busy) {
                    if (!$btn.data('idleHtml')) {
                        $btn.data('idleHtml', $btn.html());
                    }
                    $btn.prop('disabled', true)
                        .attr('aria-busy', 'true')
                        .html('<span class="dashicons dashicons-update spin"></span> ' + aiCoreAdmin.strings.refreshing);
                } else {
                    $btn.prop('disabled', false).removeAttr('aria-busy');
                    if ($btn.data('idleHtml')) {
                        $btn.html($btn.data('idleHtml'));
                        $btn.removeData('idleHtml');
                    }
                }
            });
        },

        clearApiKey: function(provider) {
            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_clear_api_key',
                    nonce: aiCoreAdmin.nonce,
                    provider: provider
                }
            }).done((response) => {
                if (!response || !response.success) {
                    alert(aiCoreAdmin.strings.error);
                    return;
                }

                const $input = $('#' + provider + '_api_key');
                const $status = $('#' + provider + '-status');

                $input.val('').attr('placeholder', aiCoreAdmin.strings.enterKeyPlaceholder);
                this.setSavedKeyFlag(provider, false);
                $('.ai-core-clear-key[data-field="' + provider + '_api_key"]').remove();

                this.showStatus($status, 'notice', aiCoreAdmin.strings.cleared);

                delete state.sessionKeys[provider];
                delete state.credentialValidation[provider];
                $('.ai-core-api-key-field[data-provider="' + provider + '"]').siblings('.ai-core-credential-state').remove();
                if (response.data && response.data.still_configured) {
                    state.configured.add(provider);
                    state.sources[provider] = response.data.source || 'wordpress';
                    $input.attr('placeholder', aiCoreAdmin.strings.wordpressPlaceholder);
                    $('.ai-core-refresh-models[data-provider="' + provider + '"]').prop('disabled', false);
                    this.markProviderConnected(provider);
                    this.fetchModels(provider, { force: false, showStatus: false });
                } else {
                    state.configured.delete(provider);
                    state.sources[provider] = 'none';
                    $('.ai-core-refresh-models[data-provider="' + provider + '"]').prop('disabled', true);
                    delete state.models[provider];
                    delete state.providerModels[provider];
                    this.markProviderDisconnected(provider);
                    this.removeProviderOption(provider);
                }

                if (response.data && response.data.default_provider) {
                    state.defaultProvider = response.data.default_provider;
                    $('#default_provider').val(state.defaultProvider);
                    $('#ai-core-test-provider').val(state.defaultProvider);
                    this.onTestProviderChange(state.defaultProvider, { initialise: true });
                }
            });
        },

        populateProviderModels: function(provider, models, options = {}) {
            const $select = $('.ai-core-provider-model[data-provider="' + provider + '"]');
            if (!$select.length) {
                return;
            }

            if (!Array.isArray(models) || !models.length) {
                $select.html('<option value="">' + aiCoreAdmin.strings.noModels + '</option>').prop('disabled', true);
                return;
            }

            $select.prop('disabled', false).empty();
            $select.append($('<option></option>').val('').text(aiCoreAdmin.strings.placeholderSelectModel));

            models.forEach((model) => {
                const meta = this.getModelMeta(provider, model);
                const text = meta && meta.display_name ? meta.display_name + ' (' + model + ')' : model;
                $select.append($('<option></option>').val(model).text(text));
            });

            const desired = options.selected || (state.providerModels[provider] && state.providerModels[provider].selected) || '';
            const stored = state.providerModels[provider] && state.providerModels[provider].selected;

            // A saved model the provider no longer lists - or that the registry
            // never knew - still belongs to the user. Keep it selectable rather
            // than silently swapping it for the first model in the list.
            if (stored && models.indexOf(stored) === -1) {
                $select.append($('<option></option>').val(stored).text(stored));
            }

            if (desired && $select.find('option[value="' + desired + '"]').length) {
                $select.val(desired);
            } else {
                const fallback = models[0];
                $select.val(fallback);
                state.providerModels[provider] = state.providerModels[provider] || {};
                state.providerModels[provider].selected = fallback;
            }

            const activeModel = $select.val();
            this.renderProviderParameters(provider, activeModel);

            const currentTestProvider = $('#ai-core-test-provider').val();
            if (currentTestProvider === provider) {
                this.onTestProviderChange(provider, { initialise: true });
            }
        },

        markProviderConnected: function(provider) {
            const $card = $('.ai-core-provider-card[data-provider="' + provider + '"]');
            const source = state.sources[provider] || '';
            const throughWordPress = source === 'wordpress' || source === 'wordpress_and_ai_core';
            const credentialStatus = state.credentialValidation[provider] || 'untested';
            let statusText = aiCoreAdmin.strings.credentialUntested;
            let statusClass = 'is-credential-untested';

            if (throughWordPress) {
                statusText = aiCoreAdmin.strings.configuredViaWordPress;
                statusClass = 'is-active';
            } else if (credentialStatus === 'validated') {
                statusText = aiCoreAdmin.strings.credentialValid;
                statusClass = 'is-credential-valid';
            } else if (credentialStatus === 'invalid') {
                statusText = aiCoreAdmin.strings.credentialInvalid;
                statusClass = 'is-credential-invalid';
            }

            $card.attr('data-has-key', '1').addClass('is-active');
            $card.find('.ai-core-provider-status')
                .text(statusText)
                .removeClass('is-inactive is-active is-credential-valid is-credential-invalid is-credential-untested')
                .addClass(statusClass);
            $card.find('.ai-core-provider-model').prop('disabled', false);
            $card.find('.ai-core-provider-refresh').prop('disabled', false);
            this.ensureProviderOptionExists(provider);
            const activeModel = state.providerModels[provider] && state.providerModels[provider].selected ? state.providerModels[provider].selected : null;
            this.renderProviderParameters(provider, activeModel);
        },

        markProviderDisconnected: function(provider) {
            const $card = $('.ai-core-provider-card[data-provider="' + provider + '"]');
            $card.attr('data-has-key', '0').removeClass('is-active');
            $card.find('.ai-core-provider-status').text(aiCoreAdmin.strings.awaiting).removeClass('is-active is-credential-valid is-credential-invalid is-credential-untested').addClass('is-inactive');
            $card.find('.ai-core-provider-model').prop('disabled', true).html('<option value="">' + aiCoreAdmin.strings.addKeyFirst + '</option>');
            $card.find('.ai-core-provider-refresh').prop('disabled', true);
            $card.find('.ai-core-provider-params').html('<p class="description">' + aiCoreAdmin.strings.addKeyFirst + '</p>');
            delete state.providerOptions[provider];
            delete state.providerModels[provider];
        },

        ensureProviderOptionExists: function(provider) {
            const label = aiCoreAdmin.providers && aiCoreAdmin.providers.labels && aiCoreAdmin.providers.labels[provider] ? aiCoreAdmin.providers.labels[provider] : provider;
            const $defaultSelect = $('#default_provider');
            const $testSelect = $('#ai-core-test-provider');

            if (!$defaultSelect.find('option[value="' + provider + '"]').length) {
                $defaultSelect.append($('<option></option>').val(provider).text(label));
            }

            if (!$testSelect.find('option[value="' + provider + '"]').length) {
                $testSelect.append($('<option></option>').val(provider).text(label));
            }
        },

        removeProviderOption: function(provider) {
            $('#default_provider').find('option[value="' + provider + '"]').remove();
            $('#ai-core-test-provider').find('option[value="' + provider + '"]').remove();
        },

        updateModelMeta: function(provider, meta) {
            if (!meta) {
                return;
            }
            state.modelMeta[provider] = state.modelMeta[provider] || {};
            Object.keys(meta).forEach((model) => {
                state.modelMeta[provider][model] = meta[model];
            });
        },

        getModelMeta: function(provider, model) {
            if (!provider || !model) {
                return null;
            }
            const providerMeta = state.modelMeta[provider] || {};
            return providerMeta[model] || null;
        },

        renderProviderParameters: function(provider, model) {
            const $container = $('.ai-core-provider-params[data-provider="' + provider + '"]');
            if (!$container.length) {
                return;
            }

            $container.empty();

            if (!model) {
                $container.html('<p class="description">' + aiCoreAdmin.strings.selectModelFirst + '</p>');
                return;
            }

            const meta = this.getModelMeta(provider, model);
            const parameters = meta && meta.parameters ? meta.parameters : {};

            state.providerOptions[provider] = state.providerOptions[provider] || {};

            const keys = Object.keys(parameters);
            if (!keys.length) {
                $container.html('<p class="description">' + aiCoreAdmin.strings.noTuningParameters + '</p>');
                state.providerOptions[provider] = {};
                return;
            }

            Object.keys(state.providerOptions[provider]).forEach((existingKey) => {
                if (keys.indexOf(existingKey) === -1) {
                    delete state.providerOptions[provider][existingKey];
                }
            });

            keys.forEach((paramKey) => {
                const definition = parameters[paramKey];
                if (typeof definition !== 'object') {
                    return;
                }

                if (state.providerOptions[provider][paramKey] === undefined && definition.default !== undefined) {
                    state.providerOptions[provider][paramKey] = definition.default;
                }

                const $control = this.createParameterControl(provider, paramKey, definition, state.providerOptions[provider][paramKey]);
                $container.append($control);
            });
        },

        createParameterControl: function(provider, key, definition, value) {
            const $wrapper = $('<div/>', { 'class': 'ai-core-param-control' });
            const labelText = definition.label || key;
            const inputName = 'ai_core_settings[provider_options][' + provider + '][' + key + ']';

            $wrapper.append($('<label/>').attr('for', provider + '-' + key).text(labelText));

            let $input;
            if (definition.type === 'select') {
                $input = $('<select/>', {
                    id: provider + '-' + key,
                    name: inputName,
                    class: 'ai-core-param-input',
                    'data-provider': provider,
                    'data-param': key,
                });

                const options = definition.options || [];
                options.forEach((opt) => {
                    const optionValue = opt.value !== undefined ? opt.value : opt;
                    const optionLabel = opt.label !== undefined ? opt.label : opt;
                    const $option = $('<option></option>').val(optionValue).text(optionLabel);
                    $input.append($option);
                });

                if (value !== undefined) {
                    $input.val(value);
                }
            } else {
                $input = $('<input/>', {
                    id: provider + '-' + key,
                    name: inputName,
                    type: 'number',
                    class: 'small-text ai-core-param-input',
                    'data-provider': provider,
                    'data-param': key,
                });

                if (definition.min !== undefined) {
                    $input.attr('min', definition.min);
                }
                if (definition.max !== undefined) {
                    $input.attr('max', definition.max);
                }
                if (definition.step !== undefined) {
                    $input.attr('step', definition.step);
                }

                if (value !== undefined) {
                    $input.val(value);
                } else if (definition.default !== undefined) {
                    $input.val(definition.default);
                }
            }

            $wrapper.append($input);

            if (definition.help) {
                $wrapper.append($('<p/>', {
                    'class': 'description'
                }).text(definition.help));
            }

            return $wrapper;
        },

        onParameterChange: function(event) {
            const $input = $(event.currentTarget);
            const provider = $input.data('provider');
            const param = $input.data('param');
            if (!provider || !param) {
                return;
            }

            state.providerOptions[provider] = state.providerOptions[provider] || {};
            state.providerOptions[provider][param] = $input.val();
        },

        testApiKey: function(event) {
            event.preventDefault();

            const $button = $(event.currentTarget);
            const provider = $button.data('provider');
            const $input = $('#' + provider + '_api_key');
            const $status = $('#' + provider + '-status');

            // An empty field is safe: the server tests the encrypted saved key
            // without ever sending it to the browser.
            const apiKey = $input.val() || state.sessionKeys[provider] || '';

            const source = state.sources[provider] || '';
            const wordpressManaged = source === 'wordpress' || source === 'wordpress_and_ai_core';
            if (!apiKey && !wordpressManaged && !this.hasSavedKey(provider)) {
                const message = aiCoreAdmin.strings.missingKey;
                this.showStatus($status, 'notice', message);
                return;
            }

            $button.prop('disabled', true).text(aiCoreAdmin.strings.testing);
            this.showStatus($status, 'notice', aiCoreAdmin.strings.testing);

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_test_api_key',
                    nonce: aiCoreAdmin.nonce,
                    provider: provider,
                    api_key: apiKey
                }
            }).done((response) => {
                if (response && response.success) {
                    if (response.data.credential_status) {
                        state.credentialValidation[provider] = response.data.credential_status;
                        this.renderCredentialState(provider, response.data.credential_status);
                        this.markProviderConnected(provider);
                    }
                    this.showStatus($status, 'success', aiCoreAdmin.strings.success + ': ' + response.data.message);
                } else {
                    const message = response && response.data && response.data.message ? response.data.message : aiCoreAdmin.strings.error;
                    if (response && response.data && response.data.credential_status) {
                        state.credentialValidation[provider] = response.data.credential_status;
                        this.renderCredentialState(provider, response.data.credential_status);
                        this.markProviderConnected(provider);
                    }
                    this.showStatus($status, 'error', aiCoreAdmin.strings.error + ': ' + message);
                }
            }).fail((xhr, status, error) => {
                this.showStatus($status, 'error', aiCoreAdmin.strings.error + ': ' + (error || status));
            }).always(() => {
                $button.prop('disabled', false).text(aiCoreAdmin.strings.testKey);
            });
        },

        onTestProviderChange: function(provider, options = {}) {
            if (!provider) {
                $('#ai-core-test-model').html('<option value="">' + aiCoreAdmin.strings.testSelectProvider + '</option>').prop('disabled', true);
                return;
            }

            $('#ai-core-test-provider').val(provider);

            const models = state.models[provider];
            const $modelSelect = $('#ai-core-test-model');

            if (!Array.isArray(models) || !models.length) {
                $modelSelect.html('<option value="">' + aiCoreAdmin.strings.loadingModels + '</option>').prop('disabled', true);
                this.fetchModels(provider, { force: false, showStatus: false });
                return;
            }

            $modelSelect.empty().prop('disabled', false);
            $modelSelect.append($('<option></option>').val('').text(aiCoreAdmin.strings.placeholderSelectModel));
            models.forEach((model) => {
                const meta = this.getModelMeta(provider, model);
                const text = meta && meta.display_name ? meta.display_name + ' (' + model + ')' : model;
                $modelSelect.append($('<option></option>').val(model).text(text));
            });

            const desired = (state.providerModels[provider] && state.providerModels[provider].selected) || models[0];
            if (desired) {
                $modelSelect.val(desired);
            }

            if (options.initialise) {
                const $providerCardSelect = $('.ai-core-provider-model[data-provider="' + provider + '"]');
                if ($providerCardSelect.length && $providerCardSelect.val()) {
                    $modelSelect.val($providerCardSelect.val());
                }
            }
        },

        /**
         * Reset the usage statistics.
         *
         * The endpoint has always existed (ai_core_reset_stats); nothing was
         * bound to the button.
         */
        resetStats: function(event) {
            event.preventDefault();

            const $button = $(event.currentTarget);
            const confirmMessage = aiCoreAdmin.strings.confirmResetStats
                || 'Are you sure you want to reset all usage statistics? This cannot be undone.';

            if (!confirm(confirmMessage)) {
                return;
            }

            const originalText = $button.text();
            $button.prop('disabled', true).text(translated('resetting', 'Resetting...'));

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_reset_stats',
                    nonce: aiCoreAdmin.nonce
                }
            }).done((response) => {
                if (response && response.success) {
                    // The table is rendered server side, so reload to show the
                    // zeroed counters rather than guessing at the markup.
                    location.reload();
                    return;
                }

                const message = response && response.data && response.data.message ? response.data.message : aiCoreAdmin.strings.error;
                alert(message);
                $button.prop('disabled', false).text(originalText);
            }).fail((xhr, status, error) => {
                alert(aiCoreAdmin.strings.error + ': ' + (error || status));
                $button.prop('disabled', false).text(originalText);
            });
        },

        refreshPricing: function(event) {
            event.preventDefault();
            const $button = $(event.currentTarget);
            const $status = $('#ai-core-pricing-status');
            const original = $button.text();
            $button.prop('disabled', true).text(aiCoreAdmin.strings.refreshingPricing || 'Refreshing pricing...');
            $.post(aiCoreAdmin.ajaxUrl, {
                action: 'ai_core_refresh_pricing',
                nonce: aiCoreAdmin.nonce
            }).done((response) => {
                if (response && response.success) {
                    $status.text(response.data.message);
                    window.setTimeout(() => location.reload(), 600);
                    return;
                }
                $status.text(response && response.data ? response.data.message : aiCoreAdmin.strings.error);
                $button.prop('disabled', false).text(original);
            }).fail(() => {
                $status.text(aiCoreAdmin.strings.error);
                $button.prop('disabled', false).text(original);
            });
        },

        updateRetentionSummary: function(event) {
            const keep = $(event.currentTarget).is(':checked');
            $('[data-retention-summary]').text(keep
                ? (aiCoreAdmin.strings.retentionKeep || 'Current choice: keep all AI-Core data after deletion.')
                : (aiCoreAdmin.strings.retentionDelete || 'Current choice: permanently remove all AI-Core data when deleted.'));
        },

        showStatus: function($element, type, message) {
            const classes = {
                success: 'success',
                error: 'error',
                notice: 'notice'
            };

            const iconMap = {
                success: 'yes-alt',
                error: 'dismiss',
                notice: 'info'
            };

            $element.removeClass('success error notice');
            $element.addClass(classes[type] || 'notice');
            $element.html('<span class="dashicons dashicons-' + (iconMap[type] || 'info') + '"></span> ' + message);

            clearTimeout($element.data('hideTimeout'));
            const timeout = setTimeout(() => {
                $element.fadeOut(200, function() {
                    $(this).empty().show().removeClass('success error notice');
                });
            }, 4000);
            $element.data('hideTimeout', timeout);
        },

        /* Existing prompt library + test prompt logic preserved with minor tweaks */
        loadPromptsList: function(e) {
            if (e) {
                e.preventDefault();
            }

            const $select = $('#ai-core-load-prompt');

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_get_prompts',
                    nonce: aiCoreAdmin.nonce
                }
            }).done((response) => {
                if (response && response.success) {
                    $select.empty().append('<option value="">-- Select a prompt --</option>');
                    response.data.prompts.forEach((prompt) => {
                        $select.append('<option value="' + prompt.id + '">' + this.escapeHtml(prompt.title) + '</option>');
                    });
                }
            });
        },

        loadPromptContent: function(event) {
            const promptId = $(event.currentTarget).val();
            if (!promptId) {
                return;
            }

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_get_prompts',
                    nonce: aiCoreAdmin.nonce
                }
            }).done((response) => {
                if (response && response.success) {
                    const prompt = response.data.prompts.find((p) => p.id == promptId);
                    if (prompt) {
                        $('#ai-core-test-prompt-content').val(prompt.content);
                        if (prompt.provider) {
                            $('#ai-core-test-provider').val(prompt.provider);
                            this.onTestProviderChange(prompt.provider, { initialise: true });
                        }
                        if (prompt.type) {
                            $('#ai-core-test-type').val(prompt.type);
                        }
                    }
                }
            });
        },

        runTestPrompt: function(event) {
            event.preventDefault();

            const content = $('#ai-core-test-prompt-content').val();
            const provider = $('#ai-core-test-provider').val();
            const model = $('#ai-core-test-model').val();
            const type = $('#ai-core-test-type').val();
            const $result = $('#ai-core-test-prompt-result');

            if (!content) {
                alert(aiCoreAdmin.strings.promptRequired);
                return;
            }

            if (!provider) {
                alert(aiCoreAdmin.strings.providerRequired);
                return;
            }

            if (!model && type === 'text') {
                alert(aiCoreAdmin.strings.modelRequired);
                return;
            }

            $result.show().html('<div class="loading"><span class="ai-core-spinner"></span> ' + aiCoreAdmin.strings.runningPrompt + '</div>');

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_test_prompt',
                    nonce: aiCoreAdmin.nonce,
                    prompt: content,
                    provider: provider,
                    model: model,
                    type: type
                }
            }).done((response) => {
                if (response && response.success) {
                    if (response.data.type === 'image') {
                        $result.html('<img src="' + response.data.result + '" alt="' + this.escapeHtml(translated('generatedImage', 'Generated image')) + '" style="max-width:100%;height:auto;" />');
                    } else {
                        $result.html('<pre style="white-space:pre-wrap;word-break:break-word;">' + this.escapeHtml(response.data.result) + '</pre>');
                    }
                } else {
                    const message = response && response.data && response.data.message ? response.data.message : aiCoreAdmin.strings.error;
                    $result.html('<div class="error" style="color:#d63638;padding:10px;background:#fcf0f1;border:1px solid #d63638;border-radius:4px;">' + this.escapeHtml(translatedFormat('errorDetail', 'Error: %s', message)) + '</div>');
                }
            }).fail((xhr, status, error) => {
                $result.html('<div class="error" style="color:#d63638;padding:10px;background:#fcf0f1;border:1px solid #d63638;border-radius:4px;">' + this.escapeHtml(translatedFormat('errorDetail', 'Error: %s', error || status)) + '</div>');
            });
        },

        fetchProviderCapabilities: function() {
            return $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_core_get_provider_capabilities',
                    nonce: aiCoreAdmin.nonce
                }
            }).done((response) => {
                if (response && response.success && response.data && response.data.capabilities) {
                    state.providerCapabilities = response.data.capabilities;
                }
            }).fail(() => {
                // Fallback to default capabilities
                state.providerCapabilities = {
                    openai: { text: true, image: true },
                    gemini: { text: true, image: true },
                    anthropic: { text: true, image: false }
                };
            });
        },

        updateTypeDropdown: function() {
            const provider = $('#ai-core-test-provider').val();
            const model = $('#ai-core-test-model').val();
            const $typeSelect = $('#ai-core-test-type');
            const $imageOption = $typeSelect.find('option[value="image"]');

            if (!provider) {
                // No provider selected, disable image option
                $imageOption.prop('disabled', true);
                if ($typeSelect.val() === 'image') {
                    $typeSelect.val('text');
                }
                return;
            }

            // Check if the selected model supports image generation
            let supportsImageGeneration = false;
            let disabledReason = '';

            if (model) {
                // Check model metadata for image capability
                const modelMeta = this.getModelMeta(provider, model);
                if (modelMeta && modelMeta.capabilities) {
                    supportsImageGeneration = modelMeta.capabilities.includes('image');
                }

                if (!supportsImageGeneration) {
                    disabledReason = 'Not supported by ' + model;
                }
            } else {
                // No model selected, check provider capabilities
                const capabilities = state.providerCapabilities[provider];
                supportsImageGeneration = capabilities && capabilities.image === true;

                if (!supportsImageGeneration) {
                    disabledReason = 'Not supported by ' + provider;
                }
            }

            $imageOption.prop('disabled', !supportsImageGeneration);

            // If image is selected but not supported, switch to text
            if ($typeSelect.val() === 'image' && !supportsImageGeneration) {
                $typeSelect.val('text');
            }

            // Add visual indicator for disabled option
            if (!supportsImageGeneration && disabledReason) {
                $imageOption.text(translatedFormat('imageGenerationUnavailable', 'Image Generation (%s)', disabledReason));
            } else {
                $imageOption.text(translated('imageGeneration', 'Image Generation'));
            }
        },

        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text || '').replace(/[&<>"']/g, (m) => map[m]);
        }
    };

    $(document).ready(() => {
        Admin.init();
        Addons.init();
    });

    /**
     * Add-ons Management
     */
    const Addons = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '.ai-core-addon-action', this.runAddonAction.bind(this));
        },

        runAddonAction: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const slug = $btn.data('slug');
            const requestedAction = $btn.data('action');
            const $status = $btn.siblings('.addon-action-status');

            if (!slug || (requestedAction !== 'install' && requestedAction !== 'activate')) {
                $status.text(translated('invalidAddonAction', 'Invalid add-on action.'));
                return;
            }

            $btn.prop('disabled', true);
            const originalHtml = $btn.html();
            const progressText = requestedAction === 'install'
                ? translated('installingAddon', 'Installing...')
                : translated('activating', 'Activating...');
            $btn.html('<span class="dashicons dashicons-update spin"></span> ' + progressText);
            $status.removeClass('is-error is-success').text(progressText);

            $.ajax({
                url: aiCoreAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: requestedAction === 'install' ? 'ai_core_install_addon' : 'ai_core_activate_addon',
                    nonce: aiCoreAdmin.nonce,
                    slug: slug
                },
                success: function(response) {
                    if (response.success) {
                        $status.addClass('is-success').text(response.data.message || translated('addonReady', 'Add-on ready.'));
                        if (response.data.next_action) {
                            $btn.attr('data-action', response.data.next_action).data('action', response.data.next_action);
                            $btn.empty()
                                .append($('<span></span>').addClass('dashicons dashicons-update'))
                                .append(document.createTextNode(' ' + response.data.button_label));
                            $btn.prop('disabled', false).trigger('focus');
                            return;
                        }
                        window.location.assign(response.data.redirect || window.location.href);
                    } else {
                        $btn.html(originalHtml).prop('disabled', false);
                        $status.addClass('is-error').text(response.data.message || translated('addonActionFailed', 'The add-on action failed.'));
                    }
                },
                error: function(xhr, status, error) {
                    $btn.html(originalHtml).prop('disabled', false);
                    const responseMessage = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
                    $status.addClass('is-error').text(responseMessage || translatedFormat('addonActionFailedDetail', 'The add-on action failed: %s', error));
                }
            });
        }
    };

})(jQuery);
