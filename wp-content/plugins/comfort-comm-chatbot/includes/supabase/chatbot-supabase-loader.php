<?php
/**
 * Supabase Module Loader
 *
 * All database operations use Supabase REST API.
 * No WordPress fallback - Supabase is required.
 *
 * @package chatbot-chatgpt
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die();
}

// Load Supabase database wrapper
require_once plugin_dir_path(__FILE__) . 'chatbot-supabase-db.php';

/**
 * Check if Supabase is properly configured
 * Returns true if all required credentials are set
 */
function chatbot_should_use_supabase_db() {
    return chatbot_supabase_is_configured();
}

/**
 * Log conversation message to Supabase
 */
function chatbot_db_log_conversation($session_id, $user_id, $page_id, $user_type, $thread_id, $assistant_id, $assistant_name, $message, $sentiment_score = null) {
    return chatbot_supabase_log_conversation($session_id, $user_id, $page_id, $user_type, $thread_id, $assistant_id, $assistant_name, $message, $sentiment_score);
}

/**
 * Update interaction count in Supabase
 */
function chatbot_db_update_interaction() {
    $result = chatbot_supabase_update_interaction_count();
    return $result['success'] ?? false;
}

/**
 * Log gap question to Supabase
 */
function chatbot_db_log_gap_question($question_text, $session_id, $user_id, $page_id, $faq_confidence, $faq_match_id = null) {
    return chatbot_supabase_log_gap_question($question_text, $session_id, $user_id, $page_id, $faq_confidence, $faq_match_id);
}

/**
 * Get gap questions from Supabase
 */
function chatbot_db_get_gap_questions($limit = 100, $include_resolved = false) {
    return chatbot_supabase_get_gap_questions($limit, $include_resolved);
}

/**
 * Get conversations from Supabase
 */
function chatbot_db_get_conversations($session_id, $limit = 100) {
    return chatbot_supabase_get_conversations($session_id, $limit);
}

/**
 * Get interaction counts from Supabase
 */
function chatbot_db_get_interaction_counts($start_date, $end_date) {
    return chatbot_supabase_get_interaction_counts($start_date, $end_date);
}

/**
 * Get database status for admin display
 */
function chatbot_db_get_status() {
    $status = [
        'using_supabase' => true,
        'supabase_configured' => chatbot_supabase_is_configured(),
        'supabase_url' => chatbot_supabase_get_url()
    ];

    if ($status['supabase_configured']) {
        $status['supabase_connected'] = chatbot_supabase_test_connection();

        if ($status['supabase_connected']) {
            $status['table_counts'] = chatbot_supabase_get_diagnostics();
        }
    }

    return $status;
}

/**
 * AJAX handler: Get database diagnostics
 */
function chatbot_ajax_get_db_diagnostics() {
    check_ajax_referer('chatbot_db_diagnostics', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }

    $status = chatbot_db_get_status();
    wp_send_json_success($status);
}
add_action('wp_ajax_chatbot_get_db_diagnostics', 'chatbot_ajax_get_db_diagnostics');
