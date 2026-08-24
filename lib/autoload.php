<?php
/**
 * Opace AI Hub Library - Autoloader
 * 
 * Simple PSR-4 compatible autoloader for Opace AI Hub library
 * Allows AI-Scribe to use Opace AI Hub without Composer
 * 
 * @package AI_Core
 * @version 1.0.12
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Opace AI Hub autoloader function
 * 
 * @param string $class_name Fully qualified class name
 * @return bool True if class was loaded
 */
if ( ! function_exists( 'ai_core_autoloader' ) ) {
    // Both the Opace AI Hub hub and any add-on bundling this library ship this
    // file. Whichever loads first wins; without this guard the second
    // declaration is a fatal, which is what happens when Opace AI Hub is
    // activated while an add-on carrying the bundled copy is already live.
function ai_core_autoloader($class_name) {
    
    // Check if this is an AICore class
    if (strpos($class_name, 'AICore\\') !== 0) {
        return false;
    }
    
    // Remove the AICore namespace prefix
    $class_name = substr($class_name, 7);
    
    // Convert namespace separators to directory separators
    $class_path = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
    
    // Build the full file path
    $file_path = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $class_path . '.php';
    
    // Check if file exists and include it
    if (file_exists($file_path)) {
        require_once $file_path;
        return true;
    }
    
    return false;
}

// Register the autoloader
spl_autoload_register('ai_core_autoloader');

// Define Opace AI Hub constants
if (!defined('AI_CORE_VERSION')) {
    define('AI_CORE_VERSION', '1.0.12');
}

if (!defined('AI_CORE_PATH')) {
    define('AI_CORE_PATH', __DIR__);
}

// Initialize Opace AI Hub if not already done
if (!class_exists('AICore\\AICore')) {
    // The autoloader will handle loading the class when it's first used
}
}
