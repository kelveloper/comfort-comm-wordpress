<?php
/**
 * Steve-Bot - Knowledge Base - FAQ Management
 *
 * This file handles FAQ management using Supabase vector database.
 * All FAQ CRUD operations go directly to Supabase - no local JSON storage.
 *
 * @package chatbot-chatgpt
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die();
}

// ============================================
// FAQ CRUD functions are in chatbot-vector-faq-crud.php
// These wrapper functions provide backwards compatibility
// ============================================

// Note: The following functions are defined in chatbot-vector-faq-crud.php:
// - chatbot_faq_load()
// - chatbot_faq_get_count()
// - chatbot_faq_get_all()
// - chatbot_faq_get_by_id($id)
// - chatbot_faq_add($question, $answer, $category)
// - chatbot_faq_update($id, $question, $answer, $category)
// - chatbot_faq_delete($id)
// - chatbot_faq_get_top_categories($limit)
// - chatbot_faq_get_category_questions($category, $limit)
// - chatbot_faq_get_buttons_data()
// - chatbot_faq_generate_keywords($text)
// - AJAX handlers: chatbot_faq_ajax_add, chatbot_faq_ajax_update, chatbot_faq_ajax_delete, chatbot_faq_ajax_get

// ============================================
// Gap Question Logging (WordPress MySQL)
// ============================================

/**
 * Log gap question - Ver 2.4.2
 * Updated Ver 2.4.8: Uses Supabase only with question validation
 * Updated Ver 2.5.0: Added conversation_context for follow-up questions
 * Questions that weren't matched with high confidence
 *
 * @param string $question The question asked
 * @param string|null $faq_match_id Matched FAQ ID (if any)
 * @param float $confidence_score Confidence score (0-1)
 * @param string $confidence_level Confidence level (very_high, high, medium, low, none)
 * @param string|null $session_id Session ID
 * @param int|null $user_id User ID
 * @param int|null $page_id Page ID
 * @param string|null $conversation_context Previous Q&A context for follow-up questions
 */
function chatbot_log_gap_question($question, $faq_match_id, $confidence_score, $confidence_level, $session_id = null, $user_id = null, $page_id = null, $conversation_context = null) {
    // Skip if question is empty
    if (empty($question)) {
        return false;
    }

    // Ver 2.5.0: Initialize quality_data to capture validation results
    $quality_data = null;

    // Validate question quality (spam, gibberish, off-topic detection)
    if (function_exists('chatbot_should_log_gap_question')) {
        $validation = chatbot_should_log_gap_question($question, floatval($confidence_score), [
            'session_id' => $session_id,
            'user_id' => $user_id,
            'page_id' => $page_id
        ]);

        if (!$validation['should_log']) {
            // Log rejection for debugging (optional)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Chatbot Gap] Question rejected: ' . $validation['reason'] . ' - "' . substr($question, 0, 50) . '..."');
            }
            return false;
        }

        // Ver 2.5.0: Capture validation data for storage
        if (isset($validation['validation'])) {
            $quality_data = [
                'quality_score' => $validation['validation']['quality_score'] ?? null,
                'validation_flags' => $validation['validation']['flags'] ?? [],
                'relevance_score' => $validation['relevance_score'] ?? null,
                'relevance_method' => $validation['relevance_method'] ?? null
            ];
        }
    } else {
        // Fallback: basic length check if validator not loaded
        if (strlen(trim($question)) < 10) {
            return false;
        }
    }

    // Use Supabase for all gap question logging
    if (function_exists('chatbot_supabase_log_gap_question')) {
        return chatbot_supabase_log_gap_question(
            sanitize_text_field($question),
            $session_id,
            $user_id ? intval($user_id) : 0,
            $page_id ? intval($page_id) : 0,
            floatval($confidence_score),
            $faq_match_id,
            $quality_data, // Ver 2.5.0: Pass quality data from validation
            $conversation_context
        );
    }

    return false;
}

/**
 * Track FAQ usage - Ver 2.4.2
 * Updated Ver 2.4.8: Uses Supabase only
 */
function chatbot_track_faq_usage($faq_id, $confidence_score) {
    if (empty($faq_id)) {
        return false;
    }

    // Use Supabase for all FAQ usage tracking
    if (function_exists('chatbot_supabase_track_faq_usage')) {
        return chatbot_supabase_track_faq_usage($faq_id, $confidence_score);
    }

    return false;
}

// ============================================
// CSV Import (imports to Supabase)
// ============================================

/**
 * Handle CSV file upload
 */
function chatbot_faq_handle_csv_upload() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    // Verify nonce
    if (!isset($_POST['chatbot_faq_import_nonce']) ||
        !wp_verify_nonce($_POST['chatbot_faq_import_nonce'], 'chatbot_faq_import')) {
        wp_die('Security check failed');
    }

    $redirect_url = admin_url('admin.php?page=chatbot-chatgpt&tab=kn_acquire');

    // Check if file was uploaded
    if (!isset($_FILES['faq_csv_file']) || $_FILES['faq_csv_file']['error'] !== UPLOAD_ERR_OK) {
        set_transient('chatbot_faq_import_message', [
            'type' => 'error',
            'message' => 'Error uploading file. Please try again.'
        ], 60);
        wp_redirect($redirect_url);
        exit;
    }

    $file = $_FILES['faq_csv_file'];

    // Validate file type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_ext !== 'csv') {
        set_transient('chatbot_faq_import_message', [
            'type' => 'error',
            'message' => 'Please upload a CSV file.'
        ], 60);
        wp_redirect($redirect_url);
        exit;
    }

    // Parse and import CSV
    $result = chatbot_faq_import_csv($file['tmp_name']);

    if ($result['success']) {
        set_transient('chatbot_faq_import_message', [
            'type' => 'success',
            'message' => sprintf('Successfully imported %d FAQ entries to vector database!', $result['count'])
        ], 60);
    } else {
        set_transient('chatbot_faq_import_message', [
            'type' => 'error',
            'message' => 'Error importing CSV: ' . $result['message']
        ], 60);
    }

    wp_redirect($redirect_url);
    exit;
}
add_action('admin_post_chatbot_faq_import_csv', 'chatbot_faq_handle_csv_upload');

/**
 * Parse and import CSV file to Supabase
 */
function chatbot_faq_import_csv($file_path) {
    // Open file
    $handle = fopen($file_path, 'r');
    if ($handle === false) {
        return ['success' => false, 'message' => 'Could not open file'];
    }

    // Read header row
    $header = fgetcsv($handle);
    if ($header === false) {
        fclose($handle);
        return ['success' => false, 'message' => 'Could not read CSV header'];
    }

    // Normalize header names
    $header = array_map('strtolower', array_map('trim', $header));

    // Find column indexes
    $question_idx = array_search('question', $header);
    $answer_idx = array_search('answer', $header);
    $category_idx = array_search('category', $header);

    if ($question_idx === false || $answer_idx === false) {
        fclose($handle);
        return ['success' => false, 'message' => 'CSV must have "question" and "answer" columns'];
    }

    // Import rows
    $count = 0;
    $errors = 0;

    while (($row = fgetcsv($handle)) !== false) {
        // Skip empty rows
        if (empty($row) || (count($row) === 1 && empty($row[0]))) {
            continue;
        }

        $question = isset($row[$question_idx]) ? trim($row[$question_idx]) : '';
        $answer = isset($row[$answer_idx]) ? trim($row[$answer_idx]) : '';
        $category = ($category_idx !== false && isset($row[$category_idx])) ? trim($row[$category_idx]) : '';

        // Skip if question or answer is empty
        if (empty($question) || empty($answer)) {
            continue;
        }

        // Add to Supabase vector database
        $result = chatbot_faq_add($question, $answer, $category);

        if ($result['success']) {
            $count++;
        } else {
            $errors++;
            error_log('[Chatbot FAQ Import] Failed to import: ' . $question . ' - ' . $result['message']);
        }

        // Small delay to avoid rate limiting
        usleep(200000); // 200ms
    }

    fclose($handle);

    if ($count === 0 && $errors > 0) {
        return ['success' => false, 'message' => "All $errors imports failed. Check API configuration."];
    }

    return [
        'success' => true,
        'count' => $count,
        'errors' => $errors
    ];
}

/**
 * Download sample CSV template
 */
function chatbot_faq_download_template() {
    // Security check
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="faq-template.csv"');

    $output = fopen('php://output', 'w');

    // Header row
    fputcsv($output, ['question', 'answer', 'category']);

    // Sample rows
    fputcsv($output, ['What are your store hours?', 'We are open Monday-Friday 9am-6pm, Saturday 10am-4pm.', 'Store Info']);
    fputcsv($output, ['What are the Spectrum internet options?', 'Spectrum offers plans starting at $49.99/month with speeds up to 300 Mbps.', 'Internet Plans']);
    fputcsv($output, ['How do I check my bill?', 'You can check your bill by logging into your provider account or calling their customer service.', 'Billing']);
    fputcsv($output, ['How do I reboot my modem?', 'Unplug your modem from power, wait 30 seconds, then plug it back in. Wait 2-3 minutes for it to fully restart.', 'Troubleshooting']);

    fclose($output);
    exit;
}
add_action('admin_post_chatbot_faq_download_template', 'chatbot_faq_download_template');
