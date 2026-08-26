=== Opace AI Prompt Library & API Integration Hub for OpenAI, Claude & Gemini ===
Contributors: opacewebdesign
Tags: AI, Prompt Engineering, Claude, Gemini, OpenAI
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.14
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI prompt engineering hub for WordPress: manage OpenAI, Claude and Gemini API keys, models, reusable prompts, pricing and usage once.

== Description ==

**Compatibility:** Version 1.0.14 was tested on WordPress 7.1 and PHP 8.4, plus WordPress 6.5 and PHP 7.4. Minimums: WordPress 6.5 and PHP 7.4.

Opace AI Hub is independently developed by Opace Digital Agency and is not affiliated with, endorsed by or sponsored by OpenAI, Anthropic or Google.

Opace AI Hub gives compatible WordPress plugins one shared connection to AI providers. Add an OpenAI, Anthropic Claude or Google Gemini key once, test it, and let compatible plugins use the same saved configuration.

Opace AI Hub is independently installable infrastructure, not a content generator. AI-Scribe is a separate companion plugin that sends content-generation requests through it.

**[AI-Scribe on WordPress.org](https://en-gb.wordpress.org/plugins/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/)**

**[AI-Scribe on GitHub](https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator)**

**[Opace AI Hub on GitHub](https://github.com/OpaceDigitalAgency/opace-ai-prompt-library-api-hub-wordpress-plugin)**

= Why Opace AI Hub was built =

As separate WordPress plugins added AI features, each one risked duplicating provider settings, model lists, request formats, prompt storage and cost calculations. Opace AI Hub moves that security-sensitive infrastructure into one maintained plugin. Site administrators get one place to rotate keys, choose models and review usage, while compatible plugins stay focused on their own workflows.

= How AI-Scribe uses Opace AI Hub =

AI-Scribe owns article planning, writing, SEO metadata, editorial review and WordPress publishing. Opace AI Hub supplies its encrypted credentials, provider and model selection, normalised generation requests, reusable prompts, model capabilities and usage records.

**[AI-Scribe on WordPress.org](https://en-gb.wordpress.org/plugins/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/)**

**[AI-Scribe on GitHub](https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator)**

**[Opace AI Hub on GitHub](https://github.com/OpaceDigitalAgency/opace-ai-prompt-library-api-hub-wordpress-plugin)**

= What Opace AI Hub provides =

* One settings screen for supported provider credentials
* Live model discovery based on the models available to your provider account
* Provider-aware handling for text, images and structured output
* A grouped prompt library with import and export
* Request, token and published-rate cost estimates by provider and model
* A PHP API for compatible WordPress plugins
* Encrypted credential storage and an explicit data-retention choice on uninstall

= Supported providers =

* **OpenAI** - text and image generation through the Chat Completions, Responses and Images APIs
* **Anthropic Claude** - text generation through the Messages API; Anthropic does not provide image generation
* **Google Gemini** - text and image generation through the Gemini API

Model lists are fetched using your key. Providers may make different models available to different accounts, so two sites can show different choices.

= Current providers and live model catalogue =

**Live catalogue snapshot: 23 August 2026 at 13:03 BST (UTC+1).** The Hub refreshed configured provider accounts at that time and received 132 OpenAI, 10 Anthropic Claude and 50 Google Gemini identifiers. Provider access varies by account, region and rollout; Settings always uses the current list returned for your own key. The built-in registry is only a fallback and metadata source when live discovery is unavailable.

**Multimodal text models selectable in the Hub**

These generate text and accept visual input. Image input does not make them dedicated image generators.

* **OpenAI (45):** `gpt-5.6-sol`, `gpt-5.6-terra`, `gpt-5.6-luna`, `gpt-5.5-pro`, `gpt-5.5-pro-2026-04-23`, `gpt-5.5`, `gpt-5.5-2026-04-23`, `gpt-5.4-pro`, `gpt-5.4-pro-2026-03-05`, `gpt-5.4`, `gpt-5.4-2026-03-05`, `gpt-5.4-mini`, `gpt-5.4-mini-2026-03-17`, `gpt-5.4-nano`, `gpt-5.4-nano-2026-03-17`, `gpt-5.2-pro`, `gpt-5.2-pro-2025-12-11`, `gpt-5.2`, `gpt-5.2-2025-12-11`, `gpt-5.1`, `gpt-5.1-2025-11-13`, `gpt-5-pro`, `gpt-5-pro-2025-10-06`, `gpt-5`, `gpt-5-2025-08-07`, `gpt-5-mini-2025-08-07`, `gpt-5-mini`, `gpt-5-nano-2025-08-07`, `gpt-4.1-2025-04-14`, `gpt-4.1`, `gpt-4.1-mini-2025-04-14`, `gpt-4.1-mini`, `gpt-4.1-nano`, `gpt-4.1-nano-2025-04-14`, `gpt-4o-2024-05-13`, `gpt-4o-2024-08-06`, `gpt-4o-2024-11-20`, `gpt-4-0613`, `gpt-4o`, `gpt-4-turbo`, `gpt-4-turbo-2024-04-09`, `gpt-4o-mini-2024-07-18`, `gpt-4o-mini`, `o3`, `o3-mini`
* **Anthropic Claude (7):** `claude-opus-5`, `claude-opus-4-8`, `claude-opus-4-7`, `claude-opus-4-6`, `claude-sonnet-4-6`, `claude-opus-4-5-20251101`, `claude-sonnet-4-5-20250929`
* **Google Gemini (6):** `gemini-3.6-flash`, `gemini-3.1-pro-preview`, `gemini-3.1-pro-preview-customtools`, `gemini-2.5-pro`, `gemini-2.5-flash`, `gemini-pro-latest`

**Text and reasoning models selectable in the Hub**

* **OpenAI (17):** `gpt-5-nano`, `gpt-4`, `gpt-3.5-turbo-0125`, `gpt-3.5-turbo-1106`, `gpt-3.5-turbo-16k`, `gpt-3.5-turbo`, `o4-mini-2025-04-16`, `o4-mini`, `o3-pro`, `o3-pro-2025-06-10`, `o3-2025-04-16`, `o3-mini-2025-01-31`, `o1-pro`, `o1-pro-2025-03-19`, `o1`, `o1-2024-12-17`, `chat-latest`
* **Anthropic Claude (3):** `claude-sonnet-5`, `claude-fable-5`, `claude-haiku-4-5-20251001`
* **Google Gemini (11):** `gemini-3.7-flash`, `gemini-3.5-flash`, `gemini-3.5-flash-lite`, `gemini-3.1-flash-lite-preview`, `gemini-3.1-flash-lite`, `gemini-3-flash-preview`, `gemini-2.5-flash-lite`, `gemini-flash-latest`, `gemini-flash-lite-latest`, `gemma-4-26b-a4b-it`, `gemma-4-31b-it`

**Image-generation models selectable in the Hub**

* **OpenAI (6):** `gpt-image-2`, `gpt-image-2-2026-04-21`, `gpt-image-1.5`, `gpt-image-1`, `gpt-image-1-mini`, `chatgpt-image-latest`
* **Google Gemini (7):** `gemini-3.1-flash-image`, `gemini-3.1-flash-image-preview`, `gemini-3.1-flash-lite-image`, `gemini-3-pro-image`, `gemini-3-pro-image-preview`, `gemini-2.5-flash-image`, `nano-banana-pro-preview`
* **Anthropic Claude:** no image-generation models were returned.

**Live specialist models catalogued, not currently invoked**

The Hub retains these live identifiers for capability-aware add-ons, but its present public helpers only execute text, structured-output and image requests. Listing a specialist model here does not claim a working Hub request path for that category.

* **OpenAI audio, speech and realtime (31):** `gpt-4o-transcribe`, `gpt-4o-transcribe-diarize`, `gpt-4o-mini-transcribe`, `gpt-4o-mini-transcribe-2025-03-20`, `gpt-4o-mini-transcribe-2025-12-15`, `gpt-4o-mini-tts`, `gpt-4o-mini-tts-2025-03-20`, `gpt-4o-mini-tts-2025-12-15`, `gpt-realtime-2.1`, `gpt-realtime-2.1-mini`, `gpt-realtime-2`, `gpt-audio-1.5`, `gpt-realtime-1.5`, `gpt-transcribe`, `gpt-audio`, `gpt-audio-2025-08-28`, `gpt-audio-mini`, `gpt-audio-mini-2025-10-06`, `gpt-audio-mini-2025-12-15`, `gpt-live-transcribe`, `gpt-realtime`, `gpt-realtime-2025-08-28`, `gpt-realtime-mini`, `gpt-realtime-mini-2025-12-15`, `gpt-realtime-translate`, `gpt-realtime-whisper`, `whisper-1`, `tts-1`, `tts-1-1106`, `tts-1-hd`, `tts-1-hd-1106`
* **Gemini audio, music and live (10):** `gemini-3.5-live-translate-preview`, `gemini-3.1-flash-live-preview`, `gemini-3.1-flash-tts-preview`, `gemini-2.5-pro-preview-tts`, `gemini-2.5-flash-native-audio-latest`, `gemini-2.5-flash-native-audio-preview-09-2025`, `gemini-2.5-flash-native-audio-preview-12-2025`, `gemini-2.5-flash-preview-tts`, `lyria-3-pro-preview`, `lyria-3-clip-preview`
* **Embeddings (6):** OpenAI `text-embedding-3-small`, `text-embedding-3-large`, `text-embedding-ada-002`; Gemini `gemini-embedding-2-preview`, `gemini-embedding-2`, `gemini-embedding-001`
* **Video (5):** OpenAI `sora-2-pro`, `sora-2`; Gemini `veo-3.1-fast-generate-preview`, `veo-3.1-generate-preview`, `veo-3.1-lite-generate-preview`
* **OpenAI code (6):** `gpt-5.3-codex`, `gpt-5.2-codex`, `gpt-5.1-codex-max`, `gpt-5.1-codex`, `gpt-5.1-codex-mini`, `gpt-5-codex`
* **OpenAI research and search (10):** `o4-mini-deep-research`, `o4-mini-deep-research-2025-06-26`, `o3-deep-research`, `o3-deep-research-2025-06-26`, `gpt-5-search-api`, `gpt-5-search-api-2025-10-14`, `gpt-4o-search-preview`, `gpt-4o-search-preview-2025-03-11`, `gpt-4o-mini-search-preview`, `gpt-4o-mini-search-preview-2025-03-11`
* **Gemini research (3):** `deep-research-max-preview-04-2026`, `deep-research-preview-04-2026`, `deep-research-pro-preview-12-2025`
* **Computer-use and agent models (4):** OpenAI `computer-use-preview`, `computer-use-preview-2025-03-11`; Gemini `gemini-2.5-computer-use-preview-10-2025`, `antigravity-preview-05-2026`
* **Gemini robotics and question answering (4):** `gemini-robotics-er-2-preview`, `gemini-robotics-er-2-streaming-preview`, `gemini-robotics-er-1.6-preview`, `aqa`
* **OpenAI moderation (2):** `omni-moderation-2024-09-26`, `omni-moderation-latest`
* **Legacy or provider-specific text IDs excluded from the prose picker (9):** OpenAI `gpt-5.3-chat-latest`, `gpt-5.2-chat-latest`, `gpt-5.1-chat-latest`, `gpt-5-chat-latest`, `gpt-3.5-turbo-instruct`, `gpt-3.5-turbo-instruct-0914`, `babbage-002`, `davinci-002`; Gemini `gemini-omni-flash-preview`

= Dynamic model defaults =

A valid saved choice is always preserved. If it is missing or retired, Opace AI Hub ranks the configured account's live list within the intended family: newest Terra with medium reasoning for OpenAI writing; newest Claude Opus with medium effort for Anthropic writing; newest non-Lite Gemini Flash with medium thinking for Gemini writing; newest GPT Image for OpenAI images; and newest Gemini Flash Image / Nano Banana for Gemini images.

The maintained offline fallbacks are currently `gpt-5.6-terra`, `claude-opus-5`, `gemini-3.6-flash`, `gpt-image-2` and `gemini-3.1-flash-image` (Nano Banana 2). Live discovery takes precedence, so a later model in the same preferred family can become the default without a plugin update. Opace AI Hub never silently replaces an explicit valid choice made by an administrator.

= Compatible plugins =

* **AI-Scribe 3.0 or later** - AI content creation and SEO optimisation. Hub 1.0.13 or newer supports AI-Scribe 3.2.36's corrected install and upgrade flow. The Add-ons screen installs the public AI-Scribe package after explicit consent, then waits for the administrator to activate it from the same card.

Developers can also use Opace AI Hub's PHP API in their own plugins.

= What comes next =

AI-Imagen is a planned image-generation workflow intended to use Opace AI Hub. It is not included in this release and has no announced release date. Other compatible plugins can use the same PHP API as the project grows.

= Security and privacy =

Provider keys are stored in the `ai_core_settings` option on your WordPress site. They are encrypted at rest with AES-256-CBC, using a random initialisation vector per value and a key derived from the site's WordPress salts. If encryption fails, Opace AI Hub does not save the key as plain text.

Rotating the salts in `wp-config.php` makes saved keys unreadable. Re-enter them after a salt rotation.

Opace AI Hub does not include analytics or user tracking. Generation data is sent only when an administrator tests a provider or a compatible plugin requests a generation.

== External services ==

Opace AI Hub connects to the following third-party services. Each provider bills you directly under the terms of your account.

**OpenAI**

Used when an administrator tests an OpenAI key or refreshes its model list, and when OpenAI is chosen for a generation. Opace AI Hub sends the OpenAI API key, requested model, request settings and prompt or image instructions supplied by the calling plugin. Requests go to [api.openai.com](https://api.openai.com/).

* [OpenAI Terms of Use](https://openai.com/policies/terms-of-use)
* [OpenAI Privacy Policy](https://openai.com/policies/privacy-policy)

**Anthropic Claude**

Used when an administrator tests an Anthropic key or refreshes its model list, and when Anthropic is chosen for a generation. Opace AI Hub sends the Anthropic API key, requested model, request settings and prompt supplied by the calling plugin. Requests go to [api.anthropic.com](https://api.anthropic.com/).

* [Anthropic Consumer Terms](https://www.anthropic.com/legal/consumer-terms)
* [Anthropic Privacy Policy](https://www.anthropic.com/legal/privacy)

**Google Gemini**

Used when an administrator tests a Gemini key or refreshes its model list, and when Gemini is chosen for a generation. Opace AI Hub sends the Gemini API key, requested model, request settings and prompt or image instructions supplied by the calling plugin. Requests go to [generativelanguage.googleapis.com](https://generativelanguage.googleapis.com/).

* [Gemini API Additional Terms of Service](https://ai.google.dev/gemini-api/terms)
* [Google Privacy Policy](https://policies.google.com/privacy)

**LiteLLM public model catalogue**

Used when Opace AI Hub needs current published pricing for a discovered model and when an administrator selects Refresh Model Pricing. Opace AI Hub sends only the provider name and model identifier to [api.litellm.ai](https://api.litellm.ai/). API keys, prompts, generated content, site details and usage totals are not sent. Successful results are cached for 12 hours. Opace AI Hub uses its bundled catalogue when no current result is available and shows Cost unavailable rather than treating an unknown price as zero.

* [LiteLLM catalogue and licence](https://github.com/BerriAI/litellm)
* [LiteLLM privacy and data security](https://docs.litellm.ai/docs/data_security)

**WordPress.org plugin directory**

Used only when an administrator chooses **Install AI-Scribe** on the Add-ons screen. Opace AI Hub asks the WordPress plugin API for AI-Scribe's public package and passes its download URL to WordPress's core installer. WordPress.org receives the normal plugin-information and package-download requests; Opace AI Hub does not send API keys, prompts, generated content, site settings or usage records. Installation stops for the administrator to choose **Activate AI-Scribe and continue**.

**[AI-Scribe on WordPress.org](https://en-gb.wordpress.org/plugins/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/)**

Cost figures shown by Opace AI Hub are published-rate estimates. Free tiers, cached-token rates, batch pricing, negotiated rates and provider billing changes can make an invoice differ from the estimate.

== Installation ==

1. Upload the `opace-ai-prompt-library-api-hub` ZIP through Plugins > Add New Plugin > Upload Plugin, or install it from the WordPress.org Plugin Directory after approval.
2. Activate Opace AI Hub.
3. Open Opace AI Hub > Settings.
4. Enter a key for at least one provider, or on WordPress 7.0+ configure it under Settings > Connectors.
5. Select a default provider and save.

Compatible plugins can then use the shared configuration.

== Frequently Asked Questions ==

= Does Opace AI Hub include an API key or free AI usage? =

No. Obtain a key directly from OpenAI, Anthropic or Google. The provider charges your account for usage under its own pricing and terms.

= Does Opace AI Hub generate content by itself? =

No. It manages provider connections, models, prompts and usage data. Install a compatible plugin such as AI-Scribe to generate content.

= How does the WordPress core AI Client integration work? =

The core AI Client (WordPress 7.0+) can use multiple configured providers, discover suitable models and generate text or images. Version 1.0.11 uses a Connector credential without copying it into Hub storage. If the key is stored in the Hub, the Hub supplies its decrypted value to the matching WordPress provider for that request only, so external plugins using the core client can use it too. The official OpenAI, Anthropic or Google provider plugin must be active. When both stores contain a key, WordPress Connectors takes precedence. WordPress 6.5 to 6.9 continues to use the Hub's direct provider integration.

= Can I use more than one provider? =

Yes. You can save keys for multiple supported providers. A compatible plugin can use one provider for text and another for images.

= Why is a model missing? =

Opace AI Hub asks each provider which models your key can access. Availability can differ by account, region and provider rollout.

= Are API keys visible to browser visitors? =

No. Keys stored by Opace AI Hub are server-side, encrypted at rest, and are not printed into public pages or normal Hub responses. WordPress Connector credentials remain under WordPress core's storage rules and are not copied into the Hub. Other trusted server-side plugins can use the Hub or WordPress AI Client APIs.

= What happens when I uninstall Opace AI Hub? =

Opace AI Hub keeps its data by default because other plugins may depend on it. To remove all Opace AI Hub-owned credentials, settings, prompt tables, statistics and caches, turn off Persist Settings on Uninstall before deleting the plugin. Opace AI Hub does not delete another plugin's data.

= Where can I get support or report a development issue? =

For user help, use the [WordPress.org support forum](https://wordpress.org/support/plugin/opace-ai-prompt-library-api-hub/). Developers can review the source or report a reproducible code issue through the [Opace AI Hub GitHub repository](https://github.com/OpaceDigitalAgency/opace-ai-prompt-library-api-hub-wordpress-plugin) and its [issue tracker](https://github.com/OpaceDigitalAgency/opace-ai-prompt-library-api-hub-wordpress-plugin/issues).

Opace AI Hub is free, open-source software supplied on a best-effort basis. Support response times and feature requests are not guaranteed. Reviews are welcome but always optional and are never required for support or functionality. The Hub does not guarantee search rankings, generated-content outcomes, provider availability or third-party pricing.

== Video demonstration ==

Watch the current Opace AI Hub walkthrough, covering provider setup, live model discovery, the Prompt Library, usage statistics and companion-plugin integration.

https://www.youtube.com/watch?v=nn3tV6UqJT4

== Screenshots ==

1. Settings - test provider credentials, refresh models, choose defaults and control retained data.
2. Dashboard - review provider status, usage totals and the main Opace AI Hub tools.
3. Prompt Library - group, import, export and run reusable text or image prompts.
4. Statistics - inspect requests, tokens, errors and estimated costs by provider, tool and model.
5. Add-ons - see AI-Scribe status and the labelled roadmap for AI-Imagen, AI-Stats and AI-Pulse.

== Opace and related links ==

* **[AI-Scribe on WordPress.org](https://en-gb.wordpress.org/plugins/ai-scribe-the-chatgpt-powered-seo-content-creation-wizard/)**
* **[AI-Scribe on GitHub](https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator)**
* **[Opace AI Hub on GitHub](https://github.com/OpaceDigitalAgency/opace-ai-prompt-library-api-hub-wordpress-plugin)**
* [Opace Digital Agency](https://opace.agency/)
* [Web design and development](https://opace.agency/services/web-design/)
* [WordPress development](https://opace.agency/services/web-design/wordpress-development/)
* [AI SEO services](https://opace.agency/services/ai-seo/)

== Changelog ==

= 1.0.14 =

* Documented the paired AI-Scribe 3.2.36 install and upgrade compatibility boundary.
* Made Hub release ZIPs reproducible so the reviewed source always rebuilds to the same hash.
* Rechecked the complete readme for unwanted forced line breaks and retained the prominent companion and source links.

= 1.0.13 =

* Split AI-Scribe installation and activation into two explicit actions in the same Add-ons card.
* Kept installation progress, success and errors inside the card with no unrelated admin notice.
* Improved WordPress.org paragraph rendering, companion links, focused tags and human-first search copy.

= 1.0.12 =

* Added one consent-led action that installs and activates AI-Scribe from WordPress.org.
* Added state-aware Activate and Open AI-Scribe actions for existing installations.
* Kept installation progress and errors inside the AI-Scribe add-on card.
* Corrected Dashicon alignment across Add-ons, Settings and Prompt Library buttons.

= 1.0.11 =

* Added two-way runtime credential sharing with the WordPress 7.0 AI Client and Connectors APIs.
* Used Connector, environment-variable or constant credentials in the Hub without copying them into Hub storage.
* Shared encrypted Hub credentials with matching registered WordPress AI provider plugins without writing a second Connector key.
* Added WordPress AI provider models to Hub selectors and made Settings and Prompt Library requests use the active core provider.
* Made credential precedence and duplicate-key status explicit: WordPress Connectors wins when both stores contain a key.
* Made the complete reachable admin and Prompt Library interface translation-ready, including JavaScript actions, confirmations and errors.

= 1.0.10 =

* Replaced the incomplete built-in fallback list with a timestamped snapshot of all 192 models returned by live provider discovery.
* Documented `gpt-5.6-sol`, `gpt-5.6-terra` and `gpt-5.6-luna` as live, multimodal and selectable.
* Distinguished selectable text and image models from live specialist models that the Hub catalogues but does not yet invoke.

= 1.0.9 =

* Made every user-facing URL in the WordPress.org description a labelled, clickable Markdown link.
* Documented all 40 built-in model identifiers, grouped by text, multimodal, image and embedding roles.
* Distinguished models the Hub can invoke from specialist models retained through live provider discovery.

= 1.0.8 =

* Removed the unsupported `Tested up to` field from the main PHP plugin header following final WordPress.org review guidance; compatibility remains declared in readme.txt.
* Prepared the approved permanent slug and listing assets for the first WordPress.org SVN release.
* Added a Leave a Review link to the plugin row on the main Plugins page, matching AI-Scribe.

= 1.0.7 =

* Changed plugin page headings to the complete approved Opace AI Hub logo and the WordPress sidebar to a centred white 20px symbol.
* Fixed the admin page header layout so WordPress notices occupy their own row and cannot displace or hide the logo.
* Added an explicit non-affiliation statement for OpenAI, Anthropic and Google.
* Documented the Hub's intentional provider integration at the exact code sites so Plugin Check completes without errors or warnings.

= 1.0.6 =

* Added the approved Opace AI Hub logo to every plugin admin screen and the WordPress admin menu.
* Included symbol-only 16px, 32px and 48px favicon variants plus a 32px ICO file.

= 1.0.5 =

* Classified the complete live provider inventory by capability for current and future add-ons.
* Kept text selectors focused on prose models without removing image, audio, video, embedding or specialist models from the Hub API.
* Recorded Gemini's supported generation methods and applied one prose-model rule to Hub defaults and AI-Scribe's text picker.

= 1.0.4 =

* Improved provider reliability by retrying once when WordPress fails before establishing the provider connection.
* Generic response timeouts are not retried, preventing duplicate generation requests.

= 1.0.3 =

* Fixed the library and fallback version constants so every runtime version marker agrees with the plugin header.
* Added build checks that reject a ZIP when any runtime version marker differs.

= 1.0.2 =

* Fixed the Prompt Library asset hook after the public menu rename so its stylesheet, drag-and-drop scripts and controls load correctly.

= 1.0.1 =

* Renamed the plugin to Opace AI Prompt Library & API Integration Hub for OpenAI, Claude & Gemini (slug opace-ai-prompt-library-api-hub) following plugin review feedback.
* Moved the admin theme-boot script to the WordPress enqueue system (wp_add_inline_script).
* Corrected the LiteLLM privacy link and documented why the plugin integrates with providers directly.

= 1.0.0 =

* First public release.
* Added shared credential management for OpenAI, Anthropic Claude and Google Gemini.
* Added live model discovery, provider-aware text and image requests, and structured-output handling.
* Added the prompt library and PHP integration API for compatible plugins.
* Added encrypted credential storage, usage statistics and published-rate cost estimates.
* Added explicit retain-or-remove behaviour for Opace AI Hub data on uninstall.
* Added provider-family-aware dynamic defaults while preserving valid saved model choices.
* Tested the release package on WordPress 7.1 with PHP 8.3.
* Consolidated all completed pre-release work as the first public 1.0 release.

== Upgrade Notice ==

= 1.0.14 =

Pairs with AI-Scribe 3.2.36's corrected legacy migration, historical-Hub updating and multisite setup flow.

= 1.0.13 =

Install AI-Scribe from the Hub Add-ons card, then activate it with a second explicit administrator action.

= 1.0.12 =

Install and activate AI-Scribe directly from the Hub Add-ons screen with one explicit action.

= 1.0.11 =

Enter each provider key once. WordPress 7.0 AI Client plugins and Opace AI Hub can now use the same runtime credential when the matching provider plugin is active, with all reachable Hub screens available to WordPress language packs.

= 1.0.10 =

Documents the complete live model inventory, including selectable GPT-5.6 Sol, Terra and Luna.

= 1.0.9 =

Adds clickable directory links and a complete, capability-grouped provider and model catalogue.

= 1.0.8 =

Final WordPress.org approval release with corrected compatibility metadata and a direct review link on the Plugins page.

= 1.0.7 =

Uses the complete approved page logo, a white WordPress sidebar symbol and a tidy notice layout.

= 1.0.6 =

Adds consistent installed-plugin branding and small favicon and admin-menu variants.

= 1.0.5 =

Classifies the full provider catalogue while keeping non-prose products out of text-model selectors.

= 1.0.4 =

Retries transient provider connection failures once without repeating ambiguous response timeouts.

= 1.0.3 =

Synchronises all runtime version markers and prevents mismatched release packages.

= 1.0.2 =

Restores the complete Prompt Library styling and interactions after the Opace AI Hub rename.

= 1.0.1 =

Plugin renamed for the WordPress.org directory; no functional changes to saved settings or data.

= 1.0.0 =

First public release with shared provider connections, dynamic model discovery, prompts, structured output and usage records.
