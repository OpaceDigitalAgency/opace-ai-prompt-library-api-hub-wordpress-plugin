<?php
/** Lightweight release checks for dynamic model ranking. */

define( 'ABSPATH', '/tmp/' );

require dirname( __DIR__ ) . '/lib/autoload.php';
require dirname( __DIR__ ) . '/includes/class-ai-core-model-defaults.php';

use AICore\Registry\ModelRegistry;

$failures = 0;

function ai_core_release_check( $label, $condition ) {
	global $failures;
	echo ( $condition ? 'PASS' : 'FAIL' ) . "  {$label}\n";
	if ( ! $condition ) {
		++$failures;
	}
}

$gemini = array(
	'gemini-2.5-flash-lite',
	'gemini-3.6-deep-research-pro',
	'gemini-2.5-pro',
	'gemini-3.6-flash',
	'gemini-3.1-pro',
	'gemini-2.5-flash-image',
);
$sorted = ModelRegistry::sortModelsForDisplay( $gemini );
$positions = array_flip( $sorted );

ai_core_release_check( 'Gemini 3.6 Flash ranks first', 'gemini-3.6-flash' === $sorted[0] );
ai_core_release_check( 'Mainline text ranks above image', $positions['gemini-2.5-pro'] < $positions['gemini-2.5-flash-image'] );
ai_core_release_check( 'Research model is not the preferred writing model', 'gemini-3.6-deep-research-pro' !== ModelRegistry::getPreferredModel( 'gemini', $gemini ) );
ai_core_release_check( 'Hub chooses a current Gemini mainline model', 0 === strpos( AI_Core_Model_Defaults::best_text_model( $gemini ), 'gemini-3.' ) );

$openai = array( 'dall-e-3', 'o3', 'gpt-4o', 'gpt-5', 'gpt-image-1' );
$openai_sorted = ModelRegistry::sortModelsForDisplay( $openai );
ai_core_release_check( 'OpenAI GPT-5 ranks first', 'gpt-5' === $openai_sorted[0] );

$supported_text = array(
	'openai'    => array( 'gpt-5.6-sol', 'gpt-5.6-terra', 'gpt-5.6-luna', 'gpt-4o-mini', 'o3' ),
	'anthropic' => array( 'claude-opus-5', 'claude-sonnet-4-6' ),
	'gemini'    => array( 'gemini-3.7-flash', 'gemini-2.5-pro', 'gemma-4-31b-it' ),
);
foreach ( $supported_text as $provider => $models ) {
	foreach ( $models as $model ) {
		ai_core_release_check(
			"{$provider} prose model {$model} is selectable",
			ModelRegistry::isTextGenerationModel( $model, $provider )
		);
	}
}

$unsupported_text = array(
	'openai' => array(
		'gpt-image-2', 'gpt-realtime-2.1', 'gpt-4o-transcribe',
		'gpt-5.3-codex', 'gpt-5-search-api', 'o3-deep-research',
		'sora-2', 'omni-moderation-latest', 'computer-use-preview',
		'babbage-002', 'gpt-3.5-turbo-instruct',
	),
	'gemini' => array(
		'gemini-3.1-flash-tts-preview', 'gemini-2.5-flash-native-audio-latest',
		'gemini-embedding-2', 'gemini-robotics-er-2-preview',
		'gemini-3.1-flash-image', 'veo-3.1-generate-preview',
		'lyria-3-pro-preview', 'deep-research-max-preview-04-2026',
		'aqa', 'nano-banana-pro-preview', 'antigravity-preview-05-2026',
	),
);
foreach ( $unsupported_text as $provider => $models ) {
	foreach ( $models as $model ) {
		ai_core_release_check(
			"{$provider} specialist model {$model} is excluded from prose",
			! ModelRegistry::isTextGenerationModel( $model, $provider )
		);
	}
}

ai_core_release_check(
	'Non-text candidates cannot become an OpenAI default',
	null === ModelRegistry::getPreferredTextModel( 'openai', array( 'gpt-image-2', 'gpt-realtime-2.1', 'sora-2' ) )
);
ai_core_release_check( 'OpenAI still-image model remains available to image generation', ModelRegistry::isImageModel( 'gpt-image-2' ) );
ai_core_release_check( 'Gemini still-image model remains available to image generation', ModelRegistry::isImageModel( 'gemini-3.1-flash-image' ) );
ai_core_release_check( 'Future GPT-5 snapshots use Responses', 'responses' === ModelRegistry::inferEndpoint( 'openai', 'gpt-5.5-2026-04-23' ) );
ai_core_release_check( 'Dated o-series snapshots use Responses', 'responses' === ModelRegistry::inferEndpoint( 'openai', 'o4-mini-2025-04-16' ) );
$gpt5ProSchema = ModelRegistry::inferParameterSchema( 'openai', 'gpt-5-pro', 'responses' );
ai_core_release_check( 'GPT-5 Pro uses its only supported high reasoning effort', 'high' === $gpt5ProSchema['reasoning_effort']['default'] );
ai_core_release_check( 'Deprecated versioned chat alias is excluded', ! ModelRegistry::isTextGenerationModel( 'gpt-5.3-chat-latest', 'openai' ) );
ai_core_release_check( 'Gemini Interactions-only model is excluded', ! ModelRegistry::isTextGenerationModel( 'gemini-omni-flash-preview', 'gemini' ) );

exit( $failures > 0 ? 1 : 0 );
