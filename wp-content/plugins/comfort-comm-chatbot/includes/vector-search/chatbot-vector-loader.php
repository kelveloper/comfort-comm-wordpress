<?php
/**
 * Chatbot Vector Search - Main Loader
 *
 * Include this file in your main plugin file to enable vector search.
 *
 * Add to chatbot-chatgpt.php:
 *   require_once plugin_dir_path(__FILE__) . 'includes/vector-search/chatbot-vector-loader.php';
 *
 * @package comfort-comm-chatbot
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die();
}

// Load all vector search components
require_once plugin_dir_path(__FILE__) . 'chatbot-vector-schema.php';
require_once plugin_dir_path(__FILE__) . 'chatbot-vector-migration.php';
require_once plugin_dir_path(__FILE__) . 'chatbot-vector-search.php';
require_once plugin_dir_path(__FILE__) . 'chatbot-vector-integration.php';
require_once plugin_dir_path(__FILE__) . 'chatbot-vector-api.php';
require_once plugin_dir_path(__FILE__) . 'chatbot-vector-faq-crud.php'; // Supabase FAQ CRUD

/**
 * Initialize vector search on plugin load
 */
function chatbot_vector_init() {
    // Check if we should auto-initialize
    if (!chatbot_vector_is_available()) {
        return;
    }

    // Log that vector search is available
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[Chatbot] Vector search is available and enabled');
    }
}
add_action('plugins_loaded', 'chatbot_vector_init', 20);

/**
 * Activation hook - initialize schema
 */
function chatbot_vector_activate() {
    if (chatbot_vector_is_available()) {
        chatbot_vector_init_schema();
    }
}
register_activation_hook(
    dirname(dirname(dirname(__FILE__))) . '/chatbot-chatgpt.php',
    'chatbot_vector_activate'
);
