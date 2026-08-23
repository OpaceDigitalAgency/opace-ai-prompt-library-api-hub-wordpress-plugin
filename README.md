# Opace AI Prompt Library & API Integration Hub for OpenAI, Claude & Gemini (WordPress Plugin)

![Opace AI Hub connects WordPress to OpenAI, Anthropic Claude, Google Gemini and compatible AI providers](.wordpress-org/banner-1544x500.png)

> [Browse Opace Digital Agency's open-source WordPress plugins, AI agent skills and web platforms](https://github.com/OpaceDigitalAgency/OpaceDigitalAgency)

**Configure AI providers once, then share their models, prompts and usage data across compatible WordPress plugins.**

<p align="center">
  <img alt="Opace AI Hub version 1.0.9" src="https://img.shields.io/badge/version-1.0.9-0A7DBB">
  <img alt="Requires WordPress 6.5 or newer" src="https://img.shields.io/badge/WordPress-6.5%2B-21759B">
  <img alt="Tested up to WordPress 7.1" src="https://img.shields.io/badge/tested%20up%20to-WordPress%207.1-21759B">
  <img alt="Requires PHP 7.4 or newer" src="https://img.shields.io/badge/PHP-7.4%2B-777BB4">
  <img alt="GPL 2.0 or later" src="https://img.shields.io/badge/licence-GPL--2.0--or--later-4C1">
</p>

<p align="center">
  <a href="https://github.com/OpaceDigitalAgency/opace-ai-prompt-library-api-hub-wordpress-plugin/releases/latest">Latest release</a>
  · <a href="https://github.com/OpaceDigitalAgency/opace-ai-prompt-library-api-hub-wordpress-plugin/releases/latest">Download ZIP</a>
  · <a href="https://github.com/OpaceDigitalAgency/opace-ai-prompt-library-api-hub-wordpress-plugin/issues">Issues</a>
  · <a href="SECURITY.md">Security</a>
  · <a href="https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator">AI Scribe</a>
  · <a href="https://github.com/OpaceDigitalAgency/OpaceDigitalAgency">More from Opace</a>
</p>

> **Opace AI Hub + [AI Scribe](https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator) are one companion WordPress AI stack, installed as two separate plugins.**
> Opace AI Hub supplies encrypted provider connections, live models, prompts, usage and pricing;
> AI Scribe supplies the guided article, SEO, image, review and publishing workflow. Install Opace AI Hub
> first, then [AI Scribe](https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator).

WordPress.org plugin slug: `opace-ai-prompt-library-api-hub`

Opace AI Hub is an integration hub, not a content generator. It keeps provider credentials in one place,
discovers the models available to your account, normalises provider responses and records usage. A
compatible plugin, such as [AI Scribe](https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator),
uses that shared connection to generate content.

Opace AI Hub is independently developed by Opace Digital Agency and is not affiliated with, endorsed by or sponsored by OpenAI, Anthropic or Google.

## Why Opace AI Hub was built

AI features were becoming harder to maintain as separate WordPress plugins each acquired their own
provider settings, model lists, request formats, prompt storage and cost calculations. That duplicated
security-sensitive code and made provider changes easy to fix in one plugin but miss in another.

Opace AI Hub separates that shared infrastructure from the product workflow. Provider integration can be
maintained and tested once, while each compatible plugin remains focused on its own job. It also gives
site administrators one place to rotate keys, choose models and understand usage.

## How AI Scribe uses Opace AI Hub

[AI Scribe](https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator) is the first
content workflow built around Opace AI Hub. AI Scribe owns article planning, writing, SEO metadata,
editorial review and WordPress publishing. Opace AI Hub supplies the lower-level services it needs:

- encrypted provider credentials and connection tests
- provider and model selection
- normalised text, image and structured-output requests
- reusable prompts and model capability information
- token, request and estimated-cost records

The split keeps content-generation decisions in AI Scribe and provider plumbing in Opace AI Hub. The two
projects are linked in both directions so users can see which plugin supplies each part of the stack.

## What it provides

- One settings screen for OpenAI, Anthropic Claude and Google Gemini credentials
- Live model discovery based on the models each provider makes available to your account
- Provider-aware request handling for text, images and structured output
- A grouped prompt library with import and export
- Request, token and published-rate cost estimates by provider and model
- A PHP API for WordPress plugins that need the shared configuration
- Encrypted credential storage and an explicit retain-or-remove choice on uninstall

## Supported providers

| Provider | Text | Images | Structured output |
|---|---:|---:|---|
| OpenAI | Yes | Yes | JSON schema through Chat Completions or Responses |
| Anthropic Claude | Yes | No | Forced tool calls |
| Google Gemini | Yes | Yes | Response schemas through the Gemini API |

Provider model lists are discovered at runtime. Opace AI Hub does not promise that a particular model will
be available to every account.

### Current providers and model catalogue

**Catalogue snapshot checked: 23 August 2026 at 12:57 BST (UTC+1).**

These are the 40 built-in model identifiers recognised by version 1.0.9. Live discovery can add newer
or account-specific identifiers without a plugin update. Availability depends on the provider, account,
region and rollout.

#### Text and reasoning

- **OpenAI:** `gpt-5-nano`, `o1-preview`, `o1-mini`, `o4-mini`, `gpt-4`, `gpt-3.5-turbo`
- **Anthropic Claude:** `claude-opus-4-1-20250805`, `claude-opus-4-20250514`, `claude-3-haiku-20240307`

#### Multimodal text

These models generate text and can accept visual input. Image input capability does not mean that a
model is a dedicated image generator.

- **OpenAI:** `gpt-5.6-terra`, `gpt-5`, `gpt-5-mini`, `o3`, `o3-mini`, `gpt-4.1`, `gpt-4.1-mini`, `gpt-4o`, `gpt-4o-mini`
- **Anthropic Claude:** `claude-opus-5`, `claude-sonnet-4-5-20250929`, `claude-sonnet-4-20250514`, `claude-3-7-sonnet-20250219`, `claude-3-5-haiku-20241022`
- **Google Gemini:** `gemini-3.6-flash`, `gemini-3.1-pro-preview`, `gemini-2.5-pro`, `gemini-2.5-flash`, `gemini-2.5-flash-preview-09-2025`, `gemini-2.5-flash-lite`

#### Image generation

- **OpenAI:** `gpt-image-2`, `gpt-image-1`, `dall-e-3`, `dall-e-2`
- **Google Gemini:** `gemini-3-pro-image-preview`, `gemini-3.1-flash-image`, `gemini-2.5-flash-image`, `gemini-2.5-flash-image-preview`, `imagen-3.0-generate-001`, `imagen-3.0-fast-generate-001`
- **Anthropic Claude:** no image-generation models

#### Embeddings

- **OpenAI catalogue:** `text-embedding-3-large`

The embedding identifier is catalogued for capability-aware integrations; the current public generation
helpers do not execute embedding requests.

#### Audio, speech, realtime, video and specialist models

The Hub retains and classifies additional identifiers returned by live provider discovery, including
audio, speech, realtime, video, moderation or safety, search, research, code, agent, robotics and
question-answering categories. These account-dependent models have no fixed built-in identifiers in
version 1.0.9. They are excluded from prose selectors and are not directly invoked by the current public
generation helpers.

### Dynamic defaults

A valid saved choice is always preserved. If a choice is missing or retired, Opace AI Hub ranks the
configured account's live list inside the intended family rather than falling back to an unrelated
old model:

| Provider | Writing default | Image default |
|---|---|---|
| OpenAI | newest mainstream Terra model, medium reasoning; current registry fallback `gpt-5.6-terra` | newest GPT Image model; current fallback `gpt-image-2` |
| Anthropic | newest Claude Opus model, medium effort; current fallback `claude-opus-5` | none |
| Gemini | newest non-Lite Gemini Flash model, medium thinking; current stable fallback `gemini-3.6-flash` | newest Gemini Flash Image / Nano Banana model; current fallback `gemini-3.1-flash-image` (Nano Banana 2) |

Live discovery takes precedence over those maintained fallback identifiers. A later model in the
same preferred family can therefore become the default without a plugin update, while an explicit
valid choice made by an administrator is never silently replaced.

## How it fits together

```text
AI Scribe       another compatible plugin       your plugin
    \                    |                           /
     \                   |                          /
      +---------------- Opace AI Hub -------------------+
      | credentials · models · prompts · usage     |
      +---------+-------------+-------------+-------+
                |             |             |
             OpenAI       Anthropic       Gemini
```

## Installation

1. [Download the latest release ZIP](https://github.com/OpaceDigitalAgency/opace-ai-prompt-library-api-hub-wordpress-plugin/releases/latest), or build it from this repository.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP and activate **Opace AI Hub**.
4. Open **Opace AI Hub → Settings** and enter a key for at least one provider.
5. Test the key, select a default provider and save.
6. Install [AI Scribe](https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator), following its current repository instructions, to add the guided content, SEO and publishing workflow.

Plugins that depend on Opace AI Hub can then use the saved configuration.

## PHP API

Use the public `ai_core()` helper from server-side WordPress code:

```php
if ( ! function_exists( 'ai_core' ) ) {
    return;
}

$provider = 'openai';
$model    = ai_core()->get_default_model_for_provider( $provider );

if ( ! $model ) {
    return;
}

$response = ai_core()->send_text_request(
    $model,
    array(
        array(
            'role'    => 'user',
            'content' => 'Write a concise headline about tea.',
        ),
    ),
    ai_core()->get_provider_options( $provider, $model ),
    array( 'tool' => 'my-plugin' )
);

if ( is_wp_error( $response ) ) {
    $message = $response->get_error_message();
} else {
    $text = AICore\AICore::extractContent( $response );
}
```

The helper also exposes configured providers, live models, image generation and usage statistics.
Treat provider credentials as secrets and never expose them in HTML, JavaScript, logs or public API
responses.

## Security and privacy

- Provider keys are encrypted at rest with AES-256-CBC, a random initialisation vector per value and
  a key derived from the site's WordPress salts.
- Encryption fails closed: if Opace AI Hub cannot encrypt a key, it does not save the key as plain text.
- Rotating the salts in `wp-config.php` makes existing encrypted keys unreadable. Re-enter them after
  a salt rotation.
- Opace AI Hub does not include analytics or user tracking.
- Generation data goes only to the provider selected for that request. Pricing lookups send only a
  provider name and model identifier to the public LiteLLM catalogue.

See [SECURITY.md](SECURITY.md) for private vulnerability reporting.

## External services

Opace AI Hub connects only when an administrator configures a provider or a compatible plugin requests a
generation. It may connect to:

- [OpenAI](https://openai.com/) for OpenAI model discovery and generation
- [Anthropic](https://www.anthropic.com/) for Claude model discovery and generation
- [Google Gemini](https://ai.google.dev/) for Gemini model discovery and generation
- [LiteLLM's public model catalogue](https://github.com/BerriAI/litellm) for published model-pricing data

The WordPress.org `readme.txt` in this repository states what each service receives and links to its
terms and privacy information.

## Data removal

Opace AI Hub keeps its data by default because other plugins may rely on it. To remove all Opace AI Hub-owned
credentials, settings, prompt tables, statistics and caches, turn off **Persist Settings on
Uninstall** before deleting the plugin. Opace AI Hub does not delete another plugin's data.

## Related projects and roadmap

- [AI Scribe](https://github.com/OpaceDigitalAgency/ai-scribe-chat-gpt-content-creator) — guided AI
  content and SEO workflow; the first plugin to use Opace AI Hub's shared infrastructure
- **AI-Imagen** — a planned image-generation workflow intended to use Opace AI Hub; it is not included in
  this release and has no announced release date
- [Opace Agent Skills](https://github.com/OpaceDigitalAgency/skills) — reusable skills for AI coding
  agents

Future compatible plugins can use the same public PHP API. Roadmap names describe direction, not a
promise of availability.

## Screenshots

| Provider settings | Dashboard |
|---|---|
| [![Opace AI Hub settings for provider credentials, model discovery and data retention](.wordpress-org/screenshot-1.png)](.wordpress-org/screenshot-1.png) | [![Opace AI Hub dashboard showing configured providers, usage totals and quick links](.wordpress-org/screenshot-2.png)](.wordpress-org/screenshot-2.png) |
| **Prompt Library** | **Statistics** |
| [![Opace AI Hub Prompt Library with grouped reusable prompts](.wordpress-org/screenshot-3.png)](.wordpress-org/screenshot-3.png) | [![Opace AI Hub usage statistics with requests, tokens and published-rate cost estimates](.wordpress-org/screenshot-4.png)](.wordpress-org/screenshot-4.png) |
| **Compatible add-ons** | |
| [![Opace AI Hub add-ons screen linking AI Scribe and showing planned Opace AI plugins](.wordpress-org/screenshot-5.png)](.wordpress-org/screenshot-5.png) | |

## Release history

Version 1.0.9 makes the WordPress.org links clickable and documents every built-in model by capability. Version 1.0.8 is the WordPress.org approval release, keeps `Tested up to` in the supported listing readme rather than the main PHP header, and adds a Plugins-page review link. Version 1.0.7 uses the complete approved page logo, a white WordPress sidebar symbol, a tidy notice layout, an explicit provider non-affiliation statement and documented Plugin Check exceptions for the Hub's intended provider-integration role. Version 1.0.6 adds the approved installed-plugin branding and small icon variants. Version 1.0.5
classifies the complete provider catalogue while keeping non-prose products out of
text-model selectors. Version 1.0.4 retries
transient provider connection failures once. Version 1.0.3 synchronises every
runtime version marker and makes the build reject future mismatches.
Version 1.0.2 restores the Prompt Library assets after the public menu rename. Version 1.0.1
addressed the WordPress.org review feedback and updated the public identity to Opace AI Hub. Both
were tested with WordPress 7.1. See [CHANGELOG.md](CHANGELOG.md) for the full release history.

## Build a WordPress.org-ready ZIP

```bash
bin/build-zip.sh
```

The build script checks that the plugin header, `AI_CORE_VERSION` and the WordPress.org stable tag
match. It creates `dist/opace-ai-prompt-library-api-hub-1.0.9.zip`, makes the installed directory
slug `opace-ai-prompt-library-api-hub`, and excludes development files and unshipped add-ons.

## Licence

Opace AI Hub is licensed under [GPL-2.0-or-later](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

## Opace services

Opace AI Hub is maintained by [Opace Digital Agency](https://opace.agency/), a Birmingham digital agency:

- [Web design and development](https://opace.agency/services/web-design/)
- [WordPress development](https://opace.agency/services/web-design/wordpress-development/)
- [AI SEO services](https://opace.agency/services/ai-seo/)

For GitHub repository metadata, the recommended sidebar website is the
[Opace web design service](https://opace.agency/services/web-design/).
