=== Opace AI Prompt Library & API Integration Hub for OpenAI, Claude & Gemini ===
Contributors: opacewebdesign
Tags: artificial intelligence, openai, claude, gemini, automation
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage OpenAI, Anthropic and Gemini credentials once, then share models, prompts and usage data across compatible WordPress plugins.

== Description ==

**Compatibility:** Version 1.0.8 was tested on WordPress 7.1 and PHP 8.3. Minimums: WordPress 6.5 and PHP 7.4.

Opace AI Hub is independently developed by Opace Digital Agency and is not affiliated with, endorsed by or sponsored by OpenAI, Anthropic or Google.

Opace AI Hub gives compatible WordPress plugins one shared connection to AI providers. Add an OpenAI,
Anthropic Claude or Google Gemini key once, test it, and let compatible plugins use the same saved
configuration.

Opace AI Hub is an integration hub, not a content generator. A plugin such as AI-Scribe sends generation
requests through it.

= Why Opace AI Hub was built =

As separate WordPress plugins added AI features, each one risked duplicating provider settings, model
lists, request formats, prompt storage and cost calculations. Opace AI Hub moves that security-sensitive
infrastructure into one maintained plugin. Site administrators get one place to rotate keys, choose
models and review usage, while compatible plugins stay focused on their own workflows.

= How AI-Scribe uses Opace AI Hub =

AI-Scribe owns article planning, writing, SEO metadata, editorial review and WordPress publishing.
Opace AI Hub supplies its encrypted credentials, provider and model selection, normalised generation
requests, reusable prompts, model capabilities and usage records.

AI-Scribe project: https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator

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

Model lists are fetched using your key. Providers may make different models available to different
accounts, so two sites can show different choices.

= Dynamic model defaults =

A valid saved choice is always preserved. If it is missing or retired, Opace AI Hub ranks the configured account's live list within the intended family: newest Terra with medium reasoning for OpenAI writing; newest Claude Opus with medium effort for Anthropic writing; newest non-Lite Gemini Flash with medium thinking for Gemini writing; newest GPT Image for OpenAI images; and newest Gemini Flash Image / Nano Banana for Gemini images.

The maintained offline fallbacks are currently `gpt-5.6-terra`, `claude-opus-5`, `gemini-3.6-flash`, `gpt-image-2` and `gemini-3.1-flash-image` (Nano Banana 2). Live discovery takes precedence, so a later model in the same preferred family can become the default without a plugin update. Opace AI Hub never silently replaces an explicit valid choice made by an administrator.

= Compatible plugins =

* **AI-Scribe 3.0 or later** - AI content creation and SEO optimisation

Developers can also use Opace AI Hub's PHP API in their own plugins.

= What comes next =

AI-Imagen is a planned image-generation workflow intended to use Opace AI Hub. It is not included in this
release and has no announced release date. Other compatible plugins can use the same PHP API as the
project grows.

= Security and privacy =

Provider keys are stored in the `ai_core_settings` option on your WordPress site. They are encrypted
at rest with AES-256-CBC, using a random initialisation vector per value and a key derived from the
site's WordPress salts. If encryption fails, Opace AI Hub does not save the key as plain text.

Rotating the salts in `wp-config.php` makes saved keys unreadable. Re-enter them after a salt
rotation.

Opace AI Hub does not include analytics or user tracking. Generation data is sent only when an
administrator tests a provider or a compatible plugin requests a generation.

== External services ==

Opace AI Hub connects to the following third-party services. Each provider bills you directly under the
terms of your account.

**OpenAI**

Used when an administrator tests an OpenAI key or refreshes its model list, and when OpenAI is chosen
for a generation. Opace AI Hub sends the OpenAI API key, requested model, request settings and prompt or
image instructions supplied by the calling plugin. Requests go to `api.openai.com`.

Terms: https://openai.com/policies/terms-of-use
Privacy: https://openai.com/policies/privacy-policy

**Anthropic Claude**

Used when an administrator tests an Anthropic key or refreshes its model list, and when Anthropic is
chosen for a generation. Opace AI Hub sends the Anthropic API key, requested model, request settings and
prompt supplied by the calling plugin. Requests go to `api.anthropic.com`.

Terms: https://www.anthropic.com/legal/consumer-terms
Privacy: https://www.anthropic.com/legal/privacy

**Google Gemini**

Used when an administrator tests a Gemini key or refreshes its model list, and when Gemini is chosen
for a generation. Opace AI Hub sends the Gemini API key, requested model, request settings and prompt or
image instructions supplied by the calling plugin. Requests go to
`generativelanguage.googleapis.com`.

Terms: https://ai.google.dev/gemini-api/terms
Privacy: https://policies.google.com/privacy

**LiteLLM public model catalogue**

Used when Opace AI Hub needs current published pricing for a discovered model and when an administrator
selects Refresh Model Pricing. Opace AI Hub sends only the provider name and model identifier to
`api.litellm.ai`. API keys, prompts, generated content, site details and usage totals are not sent.
Successful results are cached for 12 hours. Opace AI Hub uses its bundled catalogue when no current result
is available and shows Cost unavailable rather than treating an unknown price as zero.

Catalogue and licence: https://github.com/BerriAI/litellm
Privacy and data security: https://docs.litellm.ai/docs/data_security

Cost figures shown by Opace AI Hub are published-rate estimates. Free tiers, cached-token rates, batch
pricing, negotiated rates and provider billing changes can make an invoice differ from the estimate.

== Installation ==

1. Upload the `opace-ai-prompt-library-api-hub` ZIP through Plugins > Add New Plugin > Upload Plugin, or install it from the WordPress.org Plugin Directory after approval.
2. Activate Opace AI Hub.
3. Open Opace AI Hub > Settings.
4. Enter a key for at least one provider and select Test Key.
5. Select a default provider and save.

Compatible plugins can then use the shared configuration.

== Frequently Asked Questions ==

= Does Opace AI Hub include an API key or free AI usage? =

No. Obtain a key directly from OpenAI, Anthropic or Google. The provider charges your account for
usage under its own pricing and terms.

= Does Opace AI Hub generate content by itself? =

No. It manages provider connections, models, prompts and usage data. Install a compatible plugin such
as AI-Scribe to generate content.

= Why not use the WordPress core AI Client instead of direct provider integration? =

The core AI Client (WordPress 7.0+) can use multiple configured providers, discover suitable models and
generate text or images. Opace AI Hub remains useful for sites running WordPress 6.5 or later and for
companion plugins that need its shared prompt library, explicit provider and model controls, and
consolidated usage and published-rate cost records. Supporting the core AI Client as an additional
backend is on the roadmap.

= Can I use more than one provider? =

Yes. You can save keys for multiple supported providers. A compatible plugin can use one provider for
text and another for images.

= Why is a model missing? =

Opace AI Hub asks each provider which models your key can access. Availability can differ by account,
region and provider rollout.

= Are API keys visible to browser visitors? =

No. Keys are stored server-side, encrypted at rest, and are not printed into public pages or normal
Opace AI Hub responses. Other trusted server-side WordPress plugins can use Opace AI Hub's PHP API.

= What happens when I uninstall Opace AI Hub? =

Opace AI Hub keeps its data by default because other plugins may depend on it. To remove all Opace AI Hub-owned
credentials, settings, prompt tables, statistics and caches, turn off Persist Settings on Uninstall
before deleting the plugin. Opace AI Hub does not delete another plugin's data.

== Screenshots ==

1. Settings - test provider credentials, refresh models, choose defaults and control retained data.
2. Dashboard - review provider status, usage totals and the main Opace AI Hub tools.
3. Prompt Library - group, import, export and run reusable text or image prompts.
4. Statistics - inspect requests, tokens, errors and estimated costs by provider, tool and model.
5. Add-ons - see AI-Scribe status and the labelled roadmap for AI-Imagen, AI-Stats and AI-Pulse.

== Opace and related links ==

* AI-Scribe: https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator
* Opace AI Hub source and full changelog: https://github.com/OpaceDigitalAgency/opace-ai-prompt-library-api-hub-wordpress-plugin
* Opace Digital Agency: https://opace.agency/
* Web design and development: https://opace.agency/services/web-design/
* WordPress development: https://opace.agency/services/web-design/wordpress-development/
* AI SEO services: https://opace.agency/services/ai-seo/

== Changelog ==

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
