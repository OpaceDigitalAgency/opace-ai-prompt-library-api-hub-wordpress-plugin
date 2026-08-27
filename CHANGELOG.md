# Opace AI Hub changelog

## 1.0.16 — 27 August 2026

- show every stored Hub credential as saved and validated, saved but invalid, or saved and not yet tested
- keep the last explicit validation result separate from model catalogue refreshes
- let administrators re-test an encrypted saved key without exposing it to the browser

## 1.0.15 — 26 August 2026

- add a consistent More from Opace card to every Hub admin screen
- label WordPress plugin, GitHub, ChatGPT and Opace service destinations explicitly
- keep the card responsive and separate from provider, prompt and integration behaviour

## 1.0.14 — 26 August 2026

- document the paired AI-Scribe 3.2.36 install and upgrade compatibility boundary
- make release ZIP output reproducible from the same reviewed source
- retain the corrected WordPress.org paragraph formatting and prominent companion/source links

## 1.0.13 — 25 August 2026

- install AI-Scribe from WordPress.org only after explicit administrator consent
- stop after installation and expose a separate Activate and continue action in the same card
- retain in-card progress, errors and successful navigation without unrelated admin notices
- improve WordPress.org paragraph rendering, companion links, five focused tags and human-first non-brand search copy
- document user support, developer issues, best-effort open-source support and outcome limits

## 1.0.12 — 24 August 2026

- install and activate AI-Scribe from its WordPress.org package after one explicit administrator action
- activate an installed copy or open an active copy from the same state-aware Add-ons card
- report progress and failures in context instead of browser alerts or unrelated admin notices
- align Dashicons consistently in Add-ons, Settings and Prompt Library buttons

## 1.0.11 — 23 August 2026

- integrate WordPress 7.0 AI Client providers and Connector-managed credentials without copying secrets between stores
- share encrypted Hub credentials at runtime with matching registered WordPress AI provider plugins
- use WordPress provider model metadata and request routing across Settings, Prompt Library and the public Hub API
- give Connector, environment-variable and constant credentials precedence and report duplicate-key status clearly

## 1.0.10 — 23 August 2026

- replace the incomplete built-in fallback list with a timestamped snapshot of all 192 models returned by live provider discovery
- document `gpt-5.6-sol`, `gpt-5.6-terra` and `gpt-5.6-luna` as live, multimodal and selectable
- distinguish selectable text/image models from live specialist models that the Hub catalogues but does not yet invoke

## 1.0.9 — 23 August 2026

- make every user-facing URL in the WordPress.org description a labelled, clickable Markdown link
- document all 40 built-in model identifiers, grouped by text, multimodal, image and embedding roles
- distinguish models the Hub can invoke from specialist models retained through live provider discovery

## 1.0.8 — 23 August 2026

- remove the unsupported `Tested up to` field from the main PHP plugin header following final WordPress.org review guidance
- retain WordPress 7.1 compatibility in the supported WordPress.org `readme.txt` field
- prepare the approved `opace-ai-prompt-library-api-hub` slug and listing assets for the first SVN publication
- add a Leave a Review link to the plugin row on the main Plugins page, matching AI-Scribe

## 1.0.7 — 20 August 2026

- use the complete approved Opace AI Hub logo on plugin admin pages
- use a centred white 20px symbol on transparency in the WordPress sidebar
- keep relocated WordPress notices on their own row so the page logo remains visible
- state explicitly that Opace AI Hub is not affiliated with, endorsed by or sponsored by OpenAI, Anthropic or Google
- document the intentional provider-integration architecture at each static-analysis exception; Plugin Check 2.1.0 reports no errors or warnings

## 1.0.6 — 20 August 2026

- add the approved Opace AI Hub logo to plugin admin headings and the WordPress menu
- package central, text-free 16px, 32px and 48px favicon variants plus a 32px ICO
- keep the library metadata version aligned with the release build

## 1.0.5 — 20 August 2026

- classify the complete provider inventory for current and future add-ons
- keep non-prose and specialist models out of text defaults and selectors without removing them from the Hub API
- retain Gemini generation-method metadata for capability-aware consumers

## 1.0.4 — 20 August 2026

- retry a provider request once after an explicit pre-connection timeout
- do not retry generic response timeouts, avoiding possible duplicate charges

## 1.0.3 — 20 August 2026

- synchronised the plugin, library and autoload fallback version markers
- added release-build checks for every runtime version marker
- retained WordPress 7.1 and PHP 8.3 compatibility

## 1.0.2 — 20 August 2026

- corrected the Prompt Library admin-page hook after the public menu rename
- restored the dedicated Prompt Library stylesheet and drag-and-drop scripts
- tested the corrected release package on WordPress 7.1 with PHP 8.3

## 1.0.1 — 20 August 2026

Plugin review response release.

- renamed the plugin to Opace AI Prompt Library & API Integration Hub for OpenAI, Claude & Gemini (slug `opace-ai-prompt-library-api-hub`)
- moved the admin theme-boot script into the WordPress enqueue system (`wp_add_inline_script`) and removed the duplicate inline printer
- corrected the LiteLLM privacy link in readme.txt
- documented an accurate rationale for direct provider integration (vs the WordPress core AI Client) in the readme FAQ and provider library
- tested the release package on WordPress 7.1 with PHP 8.3
- renamed the public GitHub repository and links to `opace-ai-prompt-library-api-hub-wordpress-plugin`
- replaced the retired public name in WordPress.org artwork

## 1.0.0 — 14 August 2026

First public release. It consolidates all completed provider, model, request, prompt-library,
statistics, pricing, security and data-retention work described below.

The release package was tested on WordPress 7.1 with PHP 8.3.

### Provider connections and models

- centralised encrypted credentials for OpenAI, Anthropic Claude and Google Gemini
- added connection tests and live model discovery for the models available to each provider account
- ranked model families by capability and recency while avoiding niche-model defaults
- added provider-aware text and image defaults when a key is saved
- preserved valid saved text and image choices
- selected missing or retired defaults from each provider's current live model list
- preferred current Terra, Claude Opus and non-Lite Gemini Flash families for writing
- defaulted supported writing models to medium reasoning, effort or thinking
- preferred current GPT Image and Gemini Flash Image / Nano Banana families for images
- updated Gemini discovery for current text and image model families
- returned a clear provider-specific error when an API key is rejected

### Requests and structured output

- supported OpenAI Chat Completions, Responses and Images request shapes
- translated OpenAI structured-output settings to the Responses API `text.format` shape
- supported Anthropic tools and forced tool choice, including extraction of tool-use output
- supported Gemini response schemas and nested generation configuration
- normalised response extraction and error handling across providers
- limited image-only parameters to compatible model families

### WordPress administration

- added dashboard, settings, prompt library, statistics and add-ons screens
- added grouped prompt import and export
- added current provider and model status, explicit refresh actions and visible success or error results
- added shared light and dark presentation with accessible contrast improvements
- prevented an add-on Install action when no local or public installation source exists
- removed the dormant local add-on file copier; roadmap add-ons are presented as separate projects only
- replaced placeholder add-on claims with current roadmap information

### Usage, pricing and data ownership

- recorded requests, tokens, tools, errors and published outcomes
- added published-rate cost estimates using current LiteLLM catalogue data with a bundled fallback
- displayed `Cost unavailable` instead of treating an unknown price as zero
- cached successful pricing lookups for 12 hours and exposed a manual refresh action
- added a retain-or-remove uninstall setting, keeping shared data by default
- limited clean uninstall to Opace AI Hub-owned options, tables, transients, cron events and capabilities

### Security, quality and packaging

- encrypted provider credentials at rest and made encryption fail closed
- gated diagnostic logs behind `WP_DEBUG`
- escaped exception messages and hardened database queries
- unslashed and sanitised prompt-library AJAX input at the authenticated request boundary
- added direct-access protection to uninstall handling
- aligned the plugin header, runtime constant and WordPress.org stable tag
- added a deterministic build script for an `opace-ai-prompt-library-api-hub`-rooted WordPress ZIP
- removed the dormant, untested xAI/Grok implementation so shipped code matches the three documented providers
- reached zero Plugin Check errors in the packaged pre-release baseline

## Pre-public version mapping

- `0.8.0` — dynamic model pricing, fallback behaviour and complete uninstall retention
- `0.7.9` — pricing and retention preparation
- `0.7.8` — WordPress compatibility, current model families and administration refinements
- `0.7.7` — source fixes and user-interface improvements
- `0.7.6` — Gemini preview-model endpoint support
- `0.7.5` — dynamic provider model discovery
- `0.7.3` — OpenAI Responses API format correction and response extraction fixes
- `0.6.5` — statistics accuracy and presentation improvements
- `0.6.0` — provider abstraction refactor
- `0.5.0` — grouped prompt library with import and export
- `0.2.9` — per-provider usage statistics and prompt model selection
- `0.1.0` — initial OpenAI, Anthropic and Gemini key management

These numbers were development candidates. Version 1.0.0 is the first release intended for public
GitHub distribution and WordPress.org review.
